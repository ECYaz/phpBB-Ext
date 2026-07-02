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

class posts extends base
{
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\content_visibility */
	protected $content_visibility;
	/** @var \ecyaz\phpbbapihook\api\content_renderer */
	protected $content_renderer;

	public function __construct(
		\ecyaz\phpbbapihook\api\authenticator $authenticator,
		\ecyaz\phpbbapihook\api\responder $responder,
		\ecyaz\phpbbapihook\api\logger $logger,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\content_visibility $content_visibility,
		\ecyaz\phpbbapihook\api\content_renderer $content_renderer
	)
	{
		parent::__construct($authenticator, $responder, $logger, $request, $user);
		$this->auth = $auth;
		$this->db = $db;
		$this->content_visibility = $content_visibility;
		$this->content_renderer = $content_renderer;
	}

	public function get_post($post_id)
	{
		return $this->run('post.read', function (\ecyaz\phpbbapihook\api\auth_context $ctx) use ($post_id) {
			$post_id = (int) $post_id;

			$sql = 'SELECT p.*, t.topic_title, t.topic_status, f.forum_name, f.forum_password, u.username
				FROM ' . POSTS_TABLE . ' p
				JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = p.topic_id
				JOIN ' . FORUMS_TABLE . ' f ON f.forum_id = p.forum_id
				LEFT JOIN ' . USERS_TABLE . ' u ON u.user_id = p.poster_id
				WHERE p.post_id = ' . (int) $post_id;
			$res = $this->db->sql_query($sql);
			$row = $this->db->sql_fetchrow($res);
			$this->db->sql_freeresult($res);

			if (!$row)
			{
				throw new exception('post_not_found', 404);
			}
			$forum_id = (int) $row['forum_id'];

			if (!$this->content_visibility->is_visible('post', $forum_id, $row))
			{
				throw new exception('post_not_found', 404);
			}
			if (!$ctx->can_access_forum($forum_id))
			{
				throw new exception('forum_not_allowed', 403);
			}
			if (!$this->auth->acl_get('f_read', $forum_id))
			{
				throw new exception('insufficient_permissions', 403);
			}
			if ((string) $row['forum_password'] !== '')
			{
				throw new exception('forum_password_required', 403);
			}

			$rendered = $this->content_renderer->render($row);
			$poster = $this->resolve_poster($row);

			return $this->responder->success([
				'post' => [
					'post_id'        => (int) $row['post_id'],
					'poster_id'      => (int) $row['poster_id'],
					'poster'         => $poster,
					'post_time'      => (int) $row['post_time'],
					'edit_count'     => (int) $row['post_edit_count'],
					'edit_time'      => (int) $row['post_edit_time'],
					'subject'        => (string) $row['post_subject'],
					'content_html'   => $rendered['content_html'],
					'content_bbcode' => $rendered['content_bbcode'],
				],
				'topic' => [
					'topic_id' => (int) $row['topic_id'],
					'forum_id' => $forum_id,
					'title'    => (string) $row['topic_title'],
					'locked'   => ((int) $row['topic_status'] === ITEM_LOCKED),
				],
				'forum' => ['forum_id' => $forum_id, 'name' => (string) $row['forum_name']],
			]);
		});
	}
}
