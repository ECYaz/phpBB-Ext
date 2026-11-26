<?php
/**
 *
 * Topic Freeze. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\topicfreeze\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A frozen topic stays open for replies, but every reply from a member waits
 * in the moderation queue. Members cannot edit, delete or bump posts in a
 * frozen topic, the same way core treats a locked topic.
 */
class main_listener implements EventSubscriberInterface
{
	/** Quick moderation action names, as sent by the viewtopic dropdown */
	const ACTION_FREEZE = 'topicfreeze_freeze';
	const ACTION_UNFREEZE = 'topicfreeze_unfreeze';

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $attachments_table;

	/** @var string */
	protected $posts_table;

	/** @var string */
	protected $topics_table;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\auth\auth						$auth			Auth object
	 * @param \phpbb\db\driver\driver_interface		$db				Database abstraction layer
	 * @param \phpbb\language\language				$language		Language object
	 * @param \phpbb\log\log_interface				$log			Log object
	 * @param \phpbb\request\request_interface		$request		Request object
	 * @param \phpbb\template\template				$template		Template object
	 * @param \phpbb\user							$user			User object
	 * @param string								$attachments_table	Name of the attachments table
	 * @param string								$posts_table	Name of the posts table
	 * @param string								$topics_table	Name of the topics table
	 * @param string								$root_path		phpBB root path
	 * @param string								$php_ext		PHP file extension
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\language\language $language, \phpbb\log\log_interface $log, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, $attachments_table, $posts_table, $topics_table, $root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->language = $language;
		$this->log = $log;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->attachments_table = $attachments_table;
		$this->posts_table = $posts_table;
		$this->topics_table = $topics_table;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'								=> 'load_language_on_setup',
			'core.permissions'								=> 'add_permission',
			'core.viewtopic_add_quickmod_option_before'		=> 'add_quickmod_options',
			'core.modify_quickmod_options'					=> 'handle_quickmod_action',
			'core.viewtopic_assign_template_vars_before'	=> 'viewtopic_notice',
			'core.viewtopic_modify_post_action_conditions'	=> 'viewtopic_post_actions',
			'core.viewtopic_modify_page_title'				=> 'viewtopic_hide_bump',
			'core.modify_posting_auth'						=> 'posting_auth',
			'core.posting_modify_template_vars'				=> 'posting_notice',
			'core.modify_submit_post_data'					=> 'queue_reply',
			'core.ucp_display_module_before'				=> 'ucp_attachments',
			'core.mcp_main_modify_fork_sql'					=> 'fork_topic',
			'core.viewforum_modify_topicrow'				=> 'topic_row',
			'core.mcp_view_forum_modify_topicrow'			=> 'topic_row',
		];
	}

	/**
	 * Register the extension's language file on every page. It also holds the
	 * moderator log entries, which are shown in the ACP, the MCP and on topics.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'ecyaz/topicfreeze',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	/**
	 * Show the permission next to the other topic actions.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function add_permission($event)
	{
		$permissions = $event['permissions'];
		$permissions['m_topicfreeze'] = ['lang' => 'ACL_M_TOPICFREEZE', 'cat' => 'topic_actions'];
		$event['permissions'] = $permissions;
	}

	/**
	 * Add "Freeze topic" or "Unfreeze topic" to the quick moderation dropdown.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function add_quickmod_options($event)
	{
		$can_freeze = (bool) $this->auth->acl_get('m_topicfreeze', (int) $event['forum_id']);
		$frozen = !empty($event['topic_data']['topic_frozen']);

		$quickmod_array = $event['quickmod_array'];
		$quickmod_array[self::ACTION_FREEZE] = ['TOPICFREEZE_FREEZE_TOPIC', $can_freeze && !$frozen];
		$quickmod_array[self::ACTION_UNFREEZE] = ['TOPICFREEZE_UNFREEZE_TOPIC', $can_freeze && $frozen];
		$event['quickmod_array'] = $quickmod_array;
	}

	/**
	 * Run the freeze and unfreeze quick moderation actions.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function handle_quickmod_action($event)
	{
		$action = $event['action'];

		if ($action !== self::ACTION_FREEZE && $action !== self::ACTION_UNFREEZE)
		{
			return;
		}

		$event['is_valid_action'] = true;

		$topic_ids = [$this->request->variable('t', 0)];

		if (!phpbb_check_ids($topic_ids, $this->topics_table, 'topic_id', ['m_topicfreeze']))
		{
			send_status_line(403, 'Forbidden');
			trigger_error('NOT_AUTHORISED');
		}

		$freeze = ($action === self::ACTION_FREEZE);
		$lang_key = $freeze ? 'TOPICFREEZE_FREEZE_TOPIC' : 'TOPICFREEZE_UNFREEZE_TOPIC';

		$redirect = $this->request->variable('redirect', build_url(['action', 'quickmod']));
		$redirect = reapply_sid($redirect);

		if (confirm_box(true))
		{
			$topic_data = phpbb_get_topic_data($topic_ids);

			$sql = 'UPDATE ' . $this->topics_table . '
				SET topic_frozen = ' . ($freeze ? 1 : 0) . '
				WHERE ' . $this->db->sql_in_set('topic_id', array_map('intval', $topic_ids));
			$this->db->sql_query($sql);

			foreach ($topic_data as $row)
			{
				// Nothing changed, so there is nothing to record
				if ((bool) $row['topic_frozen'] === $freeze)
				{
					continue;
				}

				$this->log->add('mod', $this->user->data['user_id'], $this->user->ip, $freeze ? 'LOG_TOPICFREEZE_FREEZE' : 'LOG_TOPICFREEZE_UNFREEZE', false, [
					'forum_id' => (int) $row['forum_id'],
					'topic_id' => (int) $row['topic_id'],
					$row['topic_title'],
				]);
			}

			meta_refresh(2, $redirect);
			$message = $this->language->lang($lang_key . '_SUCCESS');

			if (!$this->request->is_ajax())
			{
				$message .= '<br /><br />' . $this->language->lang('RETURN_PAGE', '<a href="' . $redirect . '">', '</a>');
			}

			trigger_error($message);
		}
		else
		{
			confirm_box(false, $lang_key, build_hidden_fields([
				'action'	=> $action,
				'redirect'	=> $redirect,
			]));
		}

		// The confirmation was cancelled
		redirect($redirect);
	}

	/**
	 * Tell viewers that the topic is frozen, above the posts and above the
	 * quick reply box.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function viewtopic_notice($event)
	{
		if (empty($event['topic_data']['topic_frozen']))
		{
			return;
		}

		$topic_data = $event['topic_data'];
		$forum_id = (int) $event['forum_id'];

		// Only promise "you can still reply" to someone who really can
		$locked = $topic_data['topic_status'] == ITEM_LOCKED || $topic_data['forum_status'] == ITEM_LOCKED;
		$can_reply = $this->auth->acl_get('f_reply', $forum_id) && (!$locked || $this->auth->acl_get('m_lock', $forum_id));

		$this->template->assign_vars([
			'S_TOPICFREEZE_FROZEN'	=> true,
			'S_TOPICFREEZE_QUEUED'	=> $can_reply && !$this->auth->acl_get('m_approve', $forum_id),
		]);
	}

	/**
	 * Hide the edit and delete buttons from members in a frozen topic.
	 * Core lets moderators through on its own (m_edit, m_delete, m_softdelete).
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function viewtopic_post_actions($event)
	{
		if (empty($event['topic_data']['topic_frozen']))
		{
			return;
		}

		$event['s_cannot_edit_locked'] = true;
		$event['s_cannot_delete_locked'] = true;
	}

	/**
	 * Do not offer "Bump topic" to members in a frozen topic. Core assigns the
	 * link after core.viewtopic_assign_template_vars_before, so it is cleared
	 * here, with the same condition posting_auth() uses to refuse the bump.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function viewtopic_hide_bump($event)
	{
		if (!empty($event['topic_data']['topic_frozen']) && !$this->auth->acl_get('m_edit', (int) $event['forum_id']))
		{
			$this->template->assign_var('U_BUMP_TOPIC', '');
		}
	}

	/**
	 * Refuse edits, deletions and bumps from members in a frozen topic. This
	 * is the enforcing half of viewtopic_post_actions().
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function posting_auth($event)
	{
		// A posting form acts on every attachment id it is sent: "delete file"
		// and "save draft" delete it, "submit" rewrites its comment. Core only
		// checks that it belongs to the author of the post being written.
		if ($this->has_frozen_attachment($this->get_form_attachment_ids()))
		{
			$error = $event['error'];
			$error[] = $this->language->lang('TOPICFREEZE_ATTACHMENT_DENIED');
			$event['error'] = $error;

			return;
		}

		if (empty($event['post_data']['topic_frozen']))
		{
			return;
		}

		$forum_id = (int) $event['forum_id'];

		switch ($event['mode'])
		{
			case 'edit':
			case 'bump':
				$allowed = $this->auth->acl_get('m_edit', $forum_id);
			break;

			case 'delete':
			case 'soft_delete':
				$allowed = $this->auth->acl_gets('m_edit', 'm_delete', 'm_softdelete', $forum_id);
			break;

			default:
				return;
		}

		if (!$allowed)
		{
			$error = $event['error'];
			$error[] = $this->language->lang('TOPICFREEZE_POST_ACTION_DENIED');
			$event['error'] = $error;
		}
	}

	/**
	 * Tell the member on the reply form that the reply will wait for approval.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function posting_notice($event)
	{
		if (empty($event['post_data']['topic_frozen']) || !in_array($event['mode'], ['reply', 'quote'], true))
		{
			return;
		}

		$page_data = $event['page_data'];
		$page_data['S_TOPICFREEZE_FROZEN'] = true;
		$page_data['S_TOPICFREEZE_QUEUED'] = !$this->auth->acl_get('m_approve', (int) $event['forum_id']);
		$event['page_data'] = $page_data;
	}

	/**
	 * Send every reply to a frozen topic to the moderation queue, unless the
	 * author is allowed to approve posts in that forum.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function queue_reply($event)
	{
		if (!in_array($event['mode'], ['reply', 'quote'], true))
		{
			return;
		}

		$data = $event['data'];

		if (empty($data['topic_id']) || $this->auth->acl_get('m_approve', (int) $data['forum_id']))
		{
			return;
		}

		$sql = 'SELECT topic_frozen
			FROM ' . $this->topics_table . '
			WHERE topic_id = ' . (int) $data['topic_id'];
		$result = $this->db->sql_query($sql);
		$frozen = (bool) $this->db->sql_fetchfield('topic_frozen');
		$this->db->sql_freeresult($result);

		if ($frozen)
		{
			// posting.php reads this same key to show "your post needs approval"
			$data['force_approved_state'] = ITEM_UNAPPROVED;
			$event['data'] = $data;
		}
	}

	/**
	 * Refuse to change or delete attachments of posts in a frozen topic from
	 * the UCP: the attachment list and the private message form. Core refuses
	 * the first for a locked topic, but offers no event there, so the request
	 * is checked before the module runs.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function ucp_attachments($event)
	{
		$attach_ids = [];

		if ($this->request->is_set_post('delete'))
		{
			$attach_ids = array_keys($this->request->variable('attachment', [0]));
		}

		$attach_ids = array_merge($attach_ids, $this->get_form_attachment_ids());

		if ($this->has_frozen_attachment($attach_ids))
		{
			$u_back = append_sid("{$this->root_path}ucp.{$this->php_ext}", 'i=ucp_attachments&amp;mode=attachments');
			trigger_error($this->language->lang('TOPICFREEZE_ATTACHMENT_DENIED') . '<br /><br />' . $this->language->lang('RETURN_UCP', '<a href="' . $u_back . '">', '</a>'));
		}
	}

	/**
	 * Attachment ids sent along with a posting form. Core reads them on every
	 * request of such a form, whichever button was pressed, and accepts any
	 * attachment of the author, also one that belongs to an older post.
	 *
	 * @return array
	 */
	protected function get_form_attachment_ids()
	{
		$attach_ids = [];
		$attachment_data = $this->request->variable('attachment_data', [0 => ['' => '']], true, \phpbb\request\request_interface::POST);

		foreach ($attachment_data as $attachment)
		{
			if (!empty($attachment['attach_id']))
			{
				$attach_ids[] = (int) $attachment['attach_id'];
			}
		}

		return $attach_ids;
	}

	/**
	 * Is one of these attachments part of a post in a frozen topic that the
	 * current user may not edit as a moderator? The owner of the attachment
	 * does not matter: a moderator of another forum can post the ids too.
	 *
	 * @param array	$attach_ids	Attachment ids
	 * @return bool
	 */
	protected function has_frozen_attachment(array $attach_ids)
	{
		$attach_ids = array_unique(array_filter(array_map('intval', $attach_ids)));

		if (empty($attach_ids))
		{
			return false;
		}

		$sql = 'SELECT t.forum_id
			FROM ' . $this->attachments_table . ' a, ' . $this->posts_table . ' p, ' . $this->topics_table . ' t
			WHERE ' . $this->db->sql_in_set('a.attach_id', $attach_ids) . '
				AND a.in_message = 0
				AND a.is_orphan = 0
				AND p.post_id = a.post_msg_id
				AND t.topic_id = p.topic_id
				AND t.topic_frozen = 1';
		$result = $this->db->sql_query($sql);
		$frozen = false;
		while ($row = $this->db->sql_fetchrow($result))
		{
			if (!$this->auth->acl_get('m_edit', (int) $row['forum_id']))
			{
				$frozen = true;
				break;
			}
		}
		$this->db->sql_freeresult($result);

		return $frozen;
	}

	/**
	 * A copied topic keeps the frozen state, like it keeps the locked state.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function fork_topic($event)
	{
		$topic_row = $event['topic_row'];

		if (isset($topic_row['topic_frozen']))
		{
			$sql_ary = $event['sql_ary'];
			$sql_ary['topic_frozen'] = (int) $topic_row['topic_frozen'];
			$event['sql_ary'] = $sql_ary;
		}
	}

	/**
	 * Mark frozen topics in the topic lists of viewforum and the MCP.
	 *
	 * @param \phpbb\event\data	$event	Event object
	 * @return void
	 */
	public function topic_row($event)
	{
		if (empty($event['row']['topic_frozen']))
		{
			return;
		}

		$topic_row = $event['topic_row'];
		$topic_row['S_TOPICFREEZE_FROZEN'] = true;
		$event['topic_row'] = $topic_row;
	}
}
