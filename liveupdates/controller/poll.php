<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class poll
{
	/** @var \ecyaz\liveupdates\settings */
	protected $settings;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\request\request */
	protected $request;
	/** @var \phpbb\notification\manager */
	protected $notification_manager;
	/** @var \phpbb\cache\service */
	protected $cache;
	/** @var \phpbb\config\config */
	protected $config;

	public function __construct(
		\ecyaz\liveupdates\settings $settings,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\auth\auth $auth,
		\phpbb\user $user,
		\phpbb\request\request $request,
		\phpbb\notification\manager $notification_manager,
		\phpbb\cache\service $cache,
		\phpbb\config\config $config
	)
	{
		$this->settings = $settings;
		$this->db = $db;
		$this->auth = $auth;
		$this->user = $user;
		$this->request = $request;
		$this->notification_manager = $notification_manager;
		$this->cache = $cache;
		$this->config = $config;
	}

	public function handle()
	{
		$is_guest = (int) $this->user->data['user_id'] === ANONYMOUS;

		if (!$this->settings->is_enabled() || ($is_guest && !$this->settings->is_guest_allowed()))
		{
			return new JsonResponse(['enabled' => false, 'deltas' => []]);
		}

		// Server-enforced minimum interval (per session id), backed by the data cache.
		$cache_key = '_ecyaz_lu_last_' . md5((string) $this->user->session_id);
		$now = time();
		$last = (int) $this->cache->get($cache_key);
		if ($last && ($now - $last) < $this->settings->min_interval())
		{
			return new JsonResponse(['enabled' => true, 'throttled' => true, 'deltas' => []], 429);
		}
		$this->cache->put($cache_key, $now, $this->settings->min_interval());

		$surfaces = $this->settings->surfaces();
		$deltas = [];

		if ($surfaces['topic'])
		{
			$deltas['topic'] = $this->topic_delta();
		}
		if ($surfaces['notify'])
		{
			$deltas['notify'] = $this->notify_delta();
		}
		if ($surfaces['index'] && $this->request->is_set('since'))
		{
			$deltas['index'] = $this->index_delta();
		}
		if ($surfaces['pm'])
		{
			$deltas['pm'] = $this->pm_delta();
		}
		if ($surfaces['stats'] && $this->request->is_set('stats'))
		{
			$deltas['stats'] = $this->stats_delta();
		}
		if ($surfaces['online'] && $this->request->is_set('online'))
		{
			$deltas['online'] = $this->online_delta();
		}

		return new JsonResponse([
			'enabled'  => true,
			'interval' => $this->settings->effective_interval($is_guest),
			'deltas'   => $deltas,
		]);
	}

	protected function topic_delta()
	{
		$topic_id     = (int) $this->request->variable('topic_id', 0);
		$last_post_id = (int) $this->request->variable('last_post_id', 0);

		if (!$topic_id)
		{
			return ['count' => 0, 'last_post_id' => $last_post_id];
		}

		$sql = 'SELECT forum_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . $topic_id;
		$result = $this->db->sql_query($sql);
		$forum_id = (int) $this->db->sql_fetchfield('forum_id');
		$this->db->sql_freeresult($result);

		if (!$forum_id || !$this->auth->acl_get('f_read', $forum_id))
		{
			return ['count' => 0, 'last_post_id' => $last_post_id];
		}

		$sql = 'SELECT COUNT(post_id) AS new_count, MAX(post_id) AS max_id
			FROM ' . POSTS_TABLE . '
			WHERE topic_id = ' . $topic_id . '
				AND post_id > ' . $last_post_id . '
				AND post_visibility = ' . ITEM_APPROVED;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$count  = (int) $row['new_count'];
		$max_id = (int) $row['max_id'];

		return [
			'count'        => $count,
			'last_post_id' => $count ? $max_id : $last_post_id,
		];
	}

	protected function notify_delta()
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return ['unread' => 0];
		}

		$notifications = $this->notification_manager->load_notifications('notification.method.board', [
			'all_unread' => true,
			'limit'      => 1,
		]);

		return ['unread' => (int) $notifications['unread_count']];
	}

	protected function pm_delta()
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return ['unread' => 0];
		}

		return ['unread' => (int) ($this->user->data['user_unread_privmsg'] ?? 0)];
	}

	protected function stats_delta()
	{
		return [
			'posts'   => (int) $this->config['num_posts'],
			'topics'  => (int) $this->config['num_topics'],
			'members' => (int) $this->config['num_users'],
			'newest'  => (string) $this->config['newest_username'],
		];
	}

	protected function online_delta()
	{
		$online = obtain_users_online();

		return ['online' => (int) $online['total_online']];
	}

	protected function index_delta()
	{
		$since    = (int) $this->request->variable('since', 0);
		$forum_id = (int) $this->request->variable('forum_id', 0);

		$readable = array_keys($this->auth->acl_getf('f_read', true));
		if ($forum_id)
		{
			$readable = in_array($forum_id, $readable, true) ? [$forum_id] : [];
		}

		if (empty($readable))
		{
			return ['count' => 0, 'since' => $since];
		}

		$sql = 'SELECT COUNT(topic_id) AS new_count, MAX(topic_last_post_time) AS max_time
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('forum_id', array_map('intval', $readable)) . '
				AND topic_visibility = ' . ITEM_APPROVED . '
				AND topic_last_post_time > ' . $since;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$count = (int) $row['new_count'];

		return [
			'count' => $count,
			'since' => $count ? (int) $row['max_time'] : $since,
		];
	}
}
