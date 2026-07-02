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
 * Forum read endpoint. Only forums the credential's user can see (f_list) and
 * the credential is allowed to use are returned.
 */
class forums extends base
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\content_visibility */
	protected $content_visibility;

	/** @var array<int,string> */
	protected static $topic_type_names = [
		POST_NORMAL   => 'normal',
		POST_STICKY   => 'sticky',
		POST_ANNOUNCE => 'announcement',
		POST_GLOBAL   => 'global',
	];

	public function __construct(
		\ecyaz\phpbbapihook\api\authenticator $authenticator,
		\ecyaz\phpbbapihook\api\responder $responder,
		\ecyaz\phpbbapihook\api\logger $logger,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\content_visibility $content_visibility
	)
	{
		parent::__construct($authenticator, $responder, $logger, $request, $user);
		$this->auth               = $auth;
		$this->db                 = $db;
		$this->content_visibility = $content_visibility;
	}

	/**
	 * GET /api/forums — list forums visible to the credential.
	 */
	public function list_forums()
	{
		return $this->run('forum.list', function (\ecyaz\phpbbapihook\api\auth_context $ctx) {
			$forums = [];

			$sql = 'SELECT forum_id, parent_id, forum_name, forum_type
				FROM ' . FORUMS_TABLE . '
				ORDER BY left_id ASC';
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				$forum_id = (int) $row['forum_id'];

				if (!$this->auth->acl_get('f_list', $forum_id) || !$ctx->can_access_forum($forum_id))
				{
					continue;
				}

				$forums[] = [
					'forum_id'  => $forum_id,
					'parent_id' => (int) $row['parent_id'],
					'name'      => (string) $row['forum_name'],
					'type'      => (int) $row['forum_type'],
					'can_read'  => (bool) $this->auth->acl_get('f_read', $forum_id),
					'can_post'  => (bool) $this->auth->acl_get('f_post', $forum_id),
					'can_reply' => (bool) $this->auth->acl_get('f_reply', $forum_id),
				];
			}
			$this->db->sql_freeresult($result);

			return $this->responder->success(['forums' => $forums]);
		});
	}

	/**
	 * GET /api/forums/{forum_id}/topics — list topics in a forum.
	 *
	 * @param int $forum_id
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	public function list_topics($forum_id)
	{
		return $this->run('forum.topics', function (\ecyaz\phpbbapihook\api\auth_context $ctx) use ($forum_id) {
			$forum_id = (int) $forum_id;

			$sql = 'SELECT forum_id, forum_name, forum_password FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int) $forum_id;
			$res = $this->db->sql_query($sql);
			$forum = $this->db->sql_fetchrow($res);
			$this->db->sql_freeresult($res);
			if (!$forum)
			{
				throw new exception('forum_not_found', 404);
			}
			if (!$ctx->can_access_forum($forum_id))
			{
				throw new exception('forum_not_allowed', 403);
			}
			if (!$this->auth->acl_get('f_read', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}
			if ((string) $forum['forum_password'] !== '')
			{
				throw new exception('forum_password_required', 403);
			}

			$pg = $this->paginate_args();

			$sql = 'SELECT COUNT(t.topic_id) AS total FROM ' . TOPICS_TABLE . ' t
				WHERE ((t.forum_id = ' . (int) $forum_id . ' AND ' . $this->content_visibility->get_visibility_sql('topic', $forum_id, 't.') . ')
					OR (t.forum_id = 0 AND t.topic_type = ' . POST_GLOBAL . ' AND ' . $this->content_visibility->get_visibility_sql('topic', 0, 't.') . '))';
			$res = $this->db->sql_query($sql);
			$total = (int) $this->db->sql_fetchfield('total');
			$this->db->sql_freeresult($res);

			$sql = 'SELECT t.topic_id, t.forum_id, t.topic_title, t.topic_type, t.topic_poster,
					t.topic_first_poster_name, t.topic_posts_approved, t.topic_views, t.topic_time,
					t.topic_last_post_time, t.topic_last_poster_id, t.topic_last_poster_name, t.topic_status
				FROM ' . TOPICS_TABLE . ' t
				WHERE ((t.forum_id = ' . (int) $forum_id . ' AND ' . $this->content_visibility->get_visibility_sql('topic', $forum_id, 't.') . ')
					OR (t.forum_id = 0 AND t.topic_type = ' . POST_GLOBAL . ' AND ' . $this->content_visibility->get_visibility_sql('topic', 0, 't.') . '))
				ORDER BY t.topic_type DESC, t.topic_last_post_time DESC, t.topic_id DESC';
			$res = $this->db->sql_query_limit($sql, $pg['limit'], $pg['offset']);

			$topics = [];
			while ($row = $this->db->sql_fetchrow($res))
			{
				$type = (int) $row['topic_type'];
				$topics[] = [
					'topic_id'        => (int) $row['topic_id'],
					'forum_id'        => (int) $row['forum_id'],
					'title'           => (string) $row['topic_title'],
					'type'            => isset(self::$topic_type_names[$type]) ? self::$topic_type_names[$type] : 'normal',
					'poster_id'       => (int) $row['topic_poster'],
					'poster'          => (string) $row['topic_first_poster_name'],
					'replies'         => max(0, (int) $row['topic_posts_approved'] - 1),
					'views'           => (int) $row['topic_views'],
					'first_post_time' => (int) $row['topic_time'],
					'last_post_time'  => (int) $row['topic_last_post_time'],
					'last_poster_id'  => (int) $row['topic_last_poster_id'],
					'last_poster'     => (string) $row['topic_last_poster_name'],
					'locked'          => ((int) $row['topic_status'] === ITEM_LOCKED),
				];
			}
			$this->db->sql_freeresult($res);

			return $this->responder->success([
				'forum'      => ['forum_id' => $forum_id, 'name' => (string) $forum['forum_name']],
				'pagination' => ['limit' => $pg['limit'], 'offset' => $pg['offset'], 'total' => $total, 'count' => count($topics)],
				'topics'     => $topics,
			]);
		});
	}
}
