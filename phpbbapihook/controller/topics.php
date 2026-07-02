<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\controller;

use ecyaz\phpbbapihook\api\exception;

/**
 * Topic + post endpoints. Every write goes through phpBB's own submit_post(),
 * and every permission decision is made with $auth (already loaded as the
 * credential's user by the authenticator), so the API can never exceed what the
 * linked account is allowed to do in the board.
 */
class topics extends base
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\content_visibility */
	protected $content_visibility;

	/** @var \ecyaz\phpbbapihook\api\content_renderer */
	protected $content_renderer;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	public function __construct(
		\ecyaz\phpbbapihook\api\authenticator $authenticator,
		\ecyaz\phpbbapihook\api\responder $responder,
		\ecyaz\phpbbapihook\api\logger $logger,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\content_visibility $content_visibility,
		\ecyaz\phpbbapihook\api\content_renderer $content_renderer,
		$root_path,
		$php_ext
	)
	{
		parent::__construct($authenticator, $responder, $logger, $request, $user);

		$this->auth               = $auth;
		$this->config             = $config;
		$this->db                 = $db;
		$this->content_visibility = $content_visibility;
		$this->content_renderer   = $content_renderer;
		$this->root_path          = $root_path;
		$this->php_ext            = $php_ext;
	}

	/**
	 * POST /api/topics — create a new topic.
	 */
	public function create_topic()
	{
		return $this->run('topic.create', function (\ecyaz\phpbbapihook\api\auth_context $ctx) {
			if ($ctx->is_read_only())
			{
				throw new exception('read_only_credential', 403);
			}

			$forum_id = $this->input_int('forum_id');
			$title    = trim($this->input_string('title'));
			$content  = trim($this->input_string('content'));
			$type     = $this->input_string('type', 'normal');

			if ($forum_id <= 0 || $title === '' || $content === '')
			{
				throw new exception('missing_fields', 400);
			}

			if (!$ctx->can_access_forum($forum_id))
			{
				throw new exception('forum_not_allowed', 403);
			}

			if (!$this->auth->acl_get('f_read', $forum_id) || !$this->auth->acl_get('f_post', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}

			$topic_type = $this->resolve_topic_type($type, $forum_id);

			$this->flood_check($forum_id);

			$forum = $this->fetch_forum($forum_id);
			if ($forum === null)
			{
				throw new exception('forum_not_found', 404);
			}

			// Posting in a locked forum needs m_edit; password forums are off-limits.
			$this->assert_forum_postable($forum, $forum_id, 'm_edit');

			$result = $this->submit_content('post', $forum_id, 0, $title, $content, $topic_type, $forum, null);

			return $this->responder->success([
				'topic_id' => $result['topic_id'],
				'post_id'  => $result['post_id'],
				'url'      => $this->topic_url($result['topic_id']),
			], 201);
		});
	}

	/**
	 * POST /api/topics/{topic_id}/reply — reply to an existing topic.
	 */
	public function create_reply($topic_id)
	{
		return $this->run('topic.reply', function (\ecyaz\phpbbapihook\api\auth_context $ctx) use ($topic_id) {
			if ($ctx->is_read_only())
			{
				throw new exception('read_only_credential', 403);
			}

			$topic_id = (int) $topic_id;
			$content  = trim($this->input_string('content'));

			if ($content === '')
			{
				throw new exception('missing_fields', 400);
			}

			$topic = $this->fetch_topic($topic_id);
			if ($topic === null)
			{
				throw new exception('topic_not_found', 404);
			}

			$forum_id = (int) $topic['forum_id'];

			// Soft-deleted / unapproved topics the linked user may not see are
			// reported as not found, exactly as phpBB hides them.
			$this->assert_topic_visible($topic, $forum_id);

			if (!$ctx->can_access_forum($forum_id))
			{
				throw new exception('forum_not_allowed', 403);
			}

			if (!$this->auth->acl_get('f_read', $forum_id) || !$this->auth->acl_get('f_reply', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}

			// Replying in a locked forum needs m_lock; password forums are off-limits.
			$this->assert_forum_postable($topic, $forum_id, 'm_lock');

			if ((int) $topic['topic_status'] == ITEM_LOCKED && !$this->auth->acl_get('m_lock', $forum_id))
			{
				throw new exception('topic_locked', 403);
			}

			$this->flood_check($forum_id);

			$result = $this->submit_content('reply', $forum_id, $topic_id, (string) $topic['topic_title'], $content, POST_NORMAL, null, $topic);

			return $this->responder->success([
				'topic_id' => $topic_id,
				'post_id'  => $result['post_id'],
				'url'      => $this->topic_url($topic_id, $result['post_id']),
			], 201);
		});
	}

	/**
	 * GET /api/topics/{topic_id} — read a topic's metadata.
	 */
	public function get_topic($topic_id)
	{
		return $this->run('topic.read', function (\ecyaz\phpbbapihook\api\auth_context $ctx) use ($topic_id) {
			$topic_id = (int) $topic_id;

			$topic = $this->fetch_topic($topic_id);
			if ($topic === null)
			{
				throw new exception('topic_not_found', 404);
			}

			$forum_id = (int) $topic['forum_id'];

			$this->assert_topic_visible($topic, $forum_id);

			if (!$ctx->can_access_forum($forum_id) || !$this->auth->acl_get('f_read', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}

			if ((string) $topic['forum_password'] !== '')
			{
				throw new exception('forum_password_required', 403);
			}

			return $this->responder->success([
				'topic' => [
					'topic_id'     => (int) $topic['topic_id'],
					'forum_id'     => $forum_id,
					'title'        => (string) $topic['topic_title'],
					'poster_id'    => (int) $topic['topic_poster'],
					'post_count'   => (int) $topic['topic_posts_approved'],
					'views'        => (int) $topic['topic_views'],
					'time'         => (int) $topic['topic_time'],
					'locked'       => (int) $topic['topic_status'] == ITEM_LOCKED,
					'url'          => $this->topic_url($topic_id),
				],
			]);
		});
	}

	/**
	 * GET /api/topics/{topic_id}/posts — list posts in a topic.
	 */
	public function get_posts($topic_id)
	{
		return $this->run('topic.posts', function (\ecyaz\phpbbapihook\api\auth_context $ctx) use ($topic_id) {
			$topic_id = (int) $topic_id;

			$topic = $this->fetch_topic($topic_id);
			if ($topic === null)
			{
				throw new exception('topic_not_found', 404);
			}
			$forum_id = (int) $topic['forum_id'];
			$this->assert_topic_visible($topic, $forum_id);

			if (!$ctx->can_access_forum($forum_id))
			{
				throw new exception('forum_not_allowed', 403);
			}
			if (!$this->auth->acl_get('f_read', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}
			if ((string) $topic['forum_password'] !== '')
			{
				throw new exception('forum_password_required', 403);
			}

			$pg = $this->paginate_args();

			$sql = 'SELECT COUNT(p.post_id) AS total FROM ' . POSTS_TABLE . ' p
				WHERE p.topic_id = ' . (int) $topic_id . '
					AND ' . $this->content_visibility->get_visibility_sql('post', $forum_id, 'p.');
			$res = $this->db->sql_query($sql);
			$total = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($res);

			$sql = 'SELECT p.post_id, p.poster_id, p.post_time, p.post_subject, p.post_text,
					p.bbcode_uid, p.bbcode_bitfield, p.post_edit_count, p.post_edit_time, p.post_username, u.username
				FROM ' . POSTS_TABLE . ' p
				LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.poster_id
				WHERE p.topic_id = ' . (int) $topic_id . '
					AND ' . $this->content_visibility->get_visibility_sql('post', $forum_id, 'p.') . '
				ORDER BY p.post_time ASC, p.post_id ASC';
			$res = $this->db->sql_query_limit($sql, $pg['limit'], $pg['offset']);

			$posts = [];
			while ($row = $this->db->sql_fetchrow($res))
			{
				$rendered = $this->content_renderer->render($row);
				$poster = $this->resolve_poster($row);
				$posts[] = [
					'post_id'        => (int) $row['post_id'],
					'poster_id'      => (int) $row['poster_id'],
					'poster'         => $poster,
					'post_time'      => (int) $row['post_time'],
					'edit_count'     => (int) $row['post_edit_count'],
					'edit_time'      => (int) $row['post_edit_time'],
					'subject'        => (string) $row['post_subject'],
					'content_html'   => $rendered['content_html'],
					'content_bbcode' => $rendered['content_bbcode'],
				];
			}
			$this->db->sql_freeresult($res);

			return $this->responder->success([
				'topic'      => [
					'topic_id' => $topic_id,
					'forum_id' => $forum_id,
					'title'    => (string) $topic['topic_title'],
					'locked'   => ((int) $topic['topic_status'] === ITEM_LOCKED),
				],
				'pagination' => ['limit' => $pg['limit'], 'offset' => $pg['offset'], 'total' => $total, 'count' => count($posts)],
				'posts'      => $posts,
			]);
		});
	}

	/**
	 * Map an API topic-type string to a phpBB POST_* constant, enforcing the
	 * matching forum permission.
	 */
	protected function resolve_topic_type($type, $forum_id)
	{
		switch ($type)
		{
			case 'sticky':
				if (!$this->auth->acl_get('f_sticky', $forum_id))
				{
					throw new exception('insufficient_permissions', 403);
				}
				return POST_STICKY;

			case 'announcement':
				if (!$this->auth->acl_get('f_announce', $forum_id))
				{
					throw new exception('insufficient_permissions', 403);
				}
				return POST_ANNOUNCE;

			case 'normal':
				return POST_NORMAL;

			default:
				throw new exception('invalid_topic_type', 400);
		}
	}

	/**
	 * Reject access to a topic the linked user is not allowed to see (soft
	 * deleted, or unapproved without m_approve). Reported as 'topic_not_found'
	 * so the API never reveals the existence of a hidden topic.
	 *
	 * @param array $topic    Topic row including topic_visibility.
	 * @param int   $forum_id
	 * @throws exception
	 */
	protected function assert_topic_visible(array $topic, $forum_id)
	{
		if (!$this->content_visibility->is_visible('topic', (int) $forum_id, $topic))
		{
			throw new exception('topic_not_found', 404);
		}
	}

	/**
	 * Enforce the forum-level gates phpBB applies before posting (posting.php
	 * line 469): a password-protected forum cannot be used through the API (the
	 * forum password cannot be supplied), and a locked forum may only be posted
	 * to with the relevant moderator permission — 'm_edit' for new topics,
	 * 'm_lock' for replies.
	 *
	 * @param array  $forum_row     Row containing forum_status and forum_password.
	 * @param int    $forum_id
	 * @param string $lock_override Permission that overrides a forum lock.
	 * @throws exception
	 */
	protected function assert_forum_postable(array $forum_row, $forum_id, $lock_override)
	{
		if ((string) $forum_row['forum_password'] !== '')
		{
			throw new exception('forum_password_required', 403);
		}

		if ((int) $forum_row['forum_status'] == ITEM_LOCKED && !$this->auth->acl_get($lock_override, $forum_id))
		{
			throw new exception('forum_locked', 403);
		}
	}

	/**
	 * Replicate phpBB's posting flood check (posting.php lines 1159-1183).
	 *
	 * @throws exception 'flood_control' if the account is posting too fast.
	 */
	protected function flood_check($forum_id)
	{
		$ignore_flood = $this->auth->acl_get('u_ignoreflood') || $this->auth->acl_get('f_ignoreflood', $forum_id);

		if (!(int) $this->config['flood_interval'] || $ignore_flood)
		{
			return;
		}

		$cutoff = time() - (int) $this->config['flood_interval'];

		$sql = 'SELECT MAX(post_time) AS last_post_time
			FROM ' . POSTS_TABLE . '
			WHERE poster_id = ' . (int) $this->user->data['user_id'] . '
				AND post_time > ' . (int) $cutoff;
		$result = $this->db->sql_query($sql);
		$last_post_time = (int) $this->db->sql_fetchfield('last_post_time');
		$this->db->sql_freeresult($result);

		if ($last_post_time)
		{
			throw new exception('flood_control', 429);
		}
	}

	/**
	 * Build the submit_post() data array (mirroring posting.php) and run it.
	 *
	 * @return array{topic_id:int, post_id:int}
	 */
	protected function submit_content($mode, $forum_id, $topic_id, $title, $content, $topic_type, $forum, $topic)
	{
		if (!class_exists('parse_message'))
		{
			include $this->root_path . 'includes/message_parser.' . $this->php_ext;
		}

		if (!function_exists('submit_post'))
		{
			include $this->root_path . 'includes/functions_posting.' . $this->php_ext;
		}

		$message_parser = new \parse_message();
		$message_parser->message = $content;

		$errors = $message_parser->parse(true, true, true);
		if (!empty($errors))
		{
			throw new exception('invalid_content', 400, implode('; ', $errors));
		}

		$forum_name = ($topic !== null && isset($topic['forum_name'])) ? (string) $topic['forum_name'] : (string) ($forum['forum_name'] ?? '');

		$data = [
			'topic_title'         => $title,
			'topic_first_post_id' => ($topic !== null) ? (int) $topic['topic_first_post_id'] : 0,
			'topic_last_post_id'  => ($topic !== null) ? (int) $topic['topic_last_post_id'] : 0,
			'topic_time_limit'    => ($topic !== null) ? (int) $topic['topic_time_limit'] : 0,
			'topic_attachment'    => 0,
			'post_id'             => 0,
			'topic_id'            => (int) $topic_id,
			'forum_id'            => (int) $forum_id,
			'icon_id'             => 0,
			'poster_id'           => (int) $this->user->data['user_id'],
			'enable_sig'          => true,
			'enable_bbcode'       => true,
			'enable_smilies'      => true,
			'enable_urls'         => true,
			'enable_indexing'     => true,
			// phpBB duplicate-post detection field; not security-sensitive
			'message_md5'         => md5($message_parser->message),
			'post_checksum'       => '',
			'post_edit_reason'    => '',
			'post_edit_user'      => 0,
			'forum_parents'       => '',
			'forum_name'          => $forum_name,
			'notify'              => false,
			'notify_set'          => false,
			'poster_ip'           => (string) $this->user->ip,
			'post_edit_locked'    => 0,
			'bbcode_bitfield'     => $message_parser->bbcode_bitfield,
			'bbcode_uid'          => $message_parser->bbcode_uid,
			'message'             => $message_parser->message,
			'attachment_data'     => [],
			'filename_data'       => ['filecomment' => '', 'attachment_data' => []],
			'topic_status'        => ($topic !== null) ? (int) $topic['topic_status'] : ITEM_UNLOCKED,
		];

		$poll = [];

		submit_post($mode, $title, (string) $this->user->data['username'], $topic_type, $poll, $data);

		return [
			'topic_id' => (int) $data['topic_id'],
			'post_id'  => (int) $data['post_id'],
		];
	}

	/**
	 * @return array|null Topic row (joined with its forum name) or null.
	 */
	protected function fetch_topic($topic_id)
	{
		$sql = 'SELECT t.*, f.forum_name, f.forum_status, f.forum_password
			FROM ' . TOPICS_TABLE . ' t
			JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = t.forum_id
			WHERE t.topic_id = ' . (int) $topic_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ($row === false) ? null : $row;
	}

	/**
	 * @return array|null Forum row or null.
	 */
	protected function fetch_forum($forum_id)
	{
		$sql = 'SELECT * FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $forum_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ($row === false) ? null : $row;
	}

	protected function topic_url($topic_id, $post_id = 0)
	{
		$url = generate_board_url() . '/viewtopic.' . $this->php_ext . '?t=' . (int) $topic_id;

		if ($post_id)
		{
			$url .= '#p' . (int) $post_id;
		}

		return $url;
	}
}
