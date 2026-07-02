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

class search extends base
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
	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;
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
		\phpbb\event\dispatcher_interface $dispatcher,
		$root_path,
		$php_ext
	)
	{
		parent::__construct($authenticator, $responder, $logger, $request, $user);
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->content_visibility = $content_visibility;
		$this->content_renderer = $content_renderer;
		$this->dispatcher = $dispatcher;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	public function search()
	{
		return $this->run('search', function (\ecyaz\phpbbapihook\api\auth_context $ctx) {
			if (empty($this->config['load_search']))
			{
				throw new exception('search_unavailable', 503);
			}
			if (!$this->auth->acl_getf_global('f_search'))
			{
				throw new exception('insufficient_permissions', 403);
			}

			$keywords = trim($this->request->variable('q', '', true));
			if ($keywords === '')
			{
				throw new exception('missing_fields', 400);
			}
			// Preserve the caller's query for the response envelope: split_keywords()
			// takes its argument by reference and the MySQL backend mutates it.
			$original_keywords = $keywords;
			$type = $this->request->variable('type', 'posts');
			$type = ($type === 'topics') ? 'topics' : 'posts';
			$forum_filter = (int) $this->request->variable('forum_id', 0);
			$author = trim($this->request->variable('author', '', true));
			$pg = $this->paginate_args();

			$search_type = (string) $this->config['search_type'];
			if (!class_exists($search_type))
			{
				throw new exception('search_unavailable', 503);
			}
			$error = false;
			$search = new $search_type($error, $this->root_path, $this->php_ext, $this->auth, $this->config, $this->db, $this->user, $this->dispatcher);
			if ($error)
			{
				throw new exception('search_unavailable', 503);
			}

			// Forums to EXCLUDE: any the user cannot read/search or the credential disallows,
			// plus (when a filter is given) every forum other than the requested one.
			$ex_fid_ary = [];
			$sql = 'SELECT forum_id, forum_password FROM ' . FORUMS_TABLE;
			$res = $this->db->sql_query($sql);
			while ($frow = $this->db->sql_fetchrow($res))
			{
				$fid = (int) $frow['forum_id'];
				$blocked = !$this->auth->acl_get('f_read', $fid)
					|| !$this->auth->acl_get('f_search', $fid)
					|| !$ctx->can_access_forum($fid)
					|| ((string) $frow['forum_password'] !== '')
					|| ($forum_filter > 0 && $fid !== $forum_filter);
				if ($blocked)
				{
					$ex_fid_ary[] = $fid;
				}
			}
			$this->db->sql_freeresult($res);

			// Optional author filter -> user ids
			$author_ary = [];
			$author_name = '';
			if ($author !== '')
			{
				// sql_like_expression() escapes its argument internally (driver.php escapes + neutralises wildcards)
				$sql = 'SELECT user_id FROM ' . USERS_TABLE . '
					WHERE username_clean ' . $this->db->sql_like_expression(utf8_clean_string($author) . $this->db->get_any_char());
				$res = $this->db->sql_query_limit($sql, 100);
				while ($arow = $this->db->sql_fetchrow($res))
				{
					$author_ary[] = (int) $arow['user_id'];
				}
				$this->db->sql_freeresult($res);
				if (empty($author_ary))
				{
					// No such author -> empty result set, not an error.
					return $this->responder->success([
						'type'       => $type,
						'query'      => $original_keywords,
						'pagination' => ['limit' => $pg['limit'], 'offset' => $pg['offset'], 'total' => 0, 'count' => 0],
						'results'    => [],
					]);
				}
			}

			$correct_query = $search->split_keywords($keywords, 'all');
			if (!$correct_query || !$search->get_search_query())
			{
				throw new exception('search_query_too_short', 400);
			}

			$post_visibility = $this->content_visibility->get_global_visibility_sql('post', $ex_fid_ary, 'p.');

			$sort_by_sql = ['t' => ($type === 'posts') ? 'p.post_time' : 't.topic_last_post_time'];
			$sort_key = 't';
			$sort_dir = 'd';
			$sort_days = 0;
			$id_ary = [];
			// keyword_search() clamps $start by reference when it is >= total, which
			// would return the last page under a bogus offset. Capture the requested
			// offset so we can return an empty page instead (matching the SQL endpoints).
			$requested_offset = $pg['offset'];
			$start = $requested_offset;

			$total = $search->keyword_search(
				$type, 'all', 'all', $sort_by_sql, $sort_key, $sort_dir, $sort_days,
				$ex_fid_ary, $post_visibility, 0, $author_ary, $author_name,
				$id_ary, $start, $pg['limit']
			);

			if ($requested_offset >= (int) $total)
			{
				$id_ary = array();
			}

			$id_ary = array_map('intval', (array) $id_ary);
			$results = ($type === 'posts') ? $this->hydrate_posts($id_ary) : $this->hydrate_topics($id_ary);

			return $this->responder->success([
				'type'       => $type,
				'query'      => $original_keywords,
				'pagination' => ['limit' => $pg['limit'], 'offset' => $requested_offset, 'total' => (int) $total, 'count' => count($results)],
				'results'    => $results,
			]);
		});
	}

	protected function hydrate_posts(array $post_ids)
	{
		if (empty($post_ids))
		{
			return [];
		}
		$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.poster_id, p.post_time, p.post_username,
				p.post_text, p.bbcode_uid, p.bbcode_bitfield, u.username
			FROM ' . POSTS_TABLE . ' p
			LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.poster_id
			WHERE ' . $this->db->sql_in_set('p.post_id', $post_ids);
		$res = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($res))
		{
			$rows[(int) $row['post_id']] = $row;
		}
		$this->db->sql_freeresult($res);

		$out = [];
		foreach ($post_ids as $pid)   // preserve search rank order
		{
			if (!isset($rows[$pid]))
			{
				continue;
			}
			$row = $rows[$pid];
			$rendered = $this->content_renderer->render($row);
			$poster = $this->resolve_poster($row);
			$out[] = [
				'post_id'   => (int) $row['post_id'],
				'topic_id'  => (int) $row['topic_id'],
				'forum_id'  => (int) $row['forum_id'],
				'poster'    => $poster,
				'post_time' => (int) $row['post_time'],
				'snippet'   => $this->snippet($rendered['content_bbcode']),
			];
		}
		return $out;
	}

	protected function hydrate_topics(array $topic_ids)
	{
		if (empty($topic_ids))
		{
			return [];
		}
		$sql = 'SELECT topic_id, forum_id, topic_title, topic_first_poster_name, topic_last_post_time
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $this->db->sql_in_set('topic_id', $topic_ids);
		$res = $this->db->sql_query($sql);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($res))
		{
			$rows[(int) $row['topic_id']] = $row;
		}
		$this->db->sql_freeresult($res);

		$out = [];
		foreach ($topic_ids as $tid)
		{
			if (!isset($rows[$tid]))
			{
				continue;
			}
			$row = $rows[$tid];
			$out[] = [
				'topic_id'       => (int) $row['topic_id'],
				'forum_id'       => (int) $row['forum_id'],
				'title'          => (string) $row['topic_title'],
				'poster'         => (string) $row['topic_first_poster_name'],
				'last_post_time' => (int) $row['topic_last_post_time'],
			];
		}
		return $out;
	}

	protected function snippet($bbcode_text, $length = 200)
	{
		$plain = preg_replace('/\[\/?[^\]]+\]/', '', (string) $bbcode_text);
		$plain = trim(preg_replace('/\s+/', ' ', $plain));
		if (utf8_strlen($plain) > $length)
		{
			$plain = utf8_substr($plain, 0, $length) . '…';
		}
		return $plain;
	}
}
