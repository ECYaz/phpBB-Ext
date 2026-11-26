<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\acp;

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	/** @var string[] Entries of the extension endpoints field that were refused. */
	protected $invalid_ext_names = [];

	public function main($id, $mode)
	{
		global $request, $template, $user, $db, $phpbb_container;

		$user->add_lang_ext('ecyaz/phpbbapihook', 'acp_phpbbapihook');

		$this->tpl_name   = 'acp_phpbbapihook';
		$this->page_title = 'ACP_PHPBBAPIHOOK_TITLE';

		/** @var \ecyaz\phpbbapihook\api\credential_manager $credential_manager */
		$credential_manager = $phpbb_container->get('ecyaz.phpbbapihook.credential_manager');

		/** @var \ecyaz\phpbbapihook\api\logger $logger */
		$logger = $phpbb_container->get('ecyaz.phpbbapihook.logger');

		/** @var \ecyaz\phpbbapihook\api\registry $registry */
		$registry = $phpbb_container->get('ecyaz.phpbbapihook.registry');

		$form_key = 'phpbbapihook';
		add_form_key($form_key);

		$action = $request->variable('action', '');
		$key_id = $request->variable('id', 0);

		// ------------------------------------------------------------------ //
		// DELETE
		// ------------------------------------------------------------------ //
		if ($action === 'delete' && $key_id)
		{
			if (confirm_box(true))
			{
				$credential_manager->delete($key_id);
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_DELETED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(
					false,
					$user->lang('ACP_PHPBBAPIHOOK_CONFIRM_DELETE'),
					build_hidden_fields([
						'action'	=> 'delete',
						'id'		=> $key_id,
					])
				);
			}
		}

		// ------------------------------------------------------------------ //
		// TOGGLE enabled/disabled
		// ------------------------------------------------------------------ //
		if ($action === 'toggle' && $key_id)
		{
			// Enabling/disabling a credential is a state change, so the GET link
			// must carry a valid per-session hash (CSRF protection).
			if (!check_link_hash($request->variable('hash', ''), 'phpbbapihook_toggle'))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}

			$row = $credential_manager->get($key_id);
			if ($row === null)
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$credential_manager->update($key_id, ['is_enabled' => ($row['is_enabled'] ? 0 : 1)]);
			trigger_error($user->lang('ACP_PHPBBAPIHOOK_UPDATED') . adm_back_link($this->u_action));
		}

		// ------------------------------------------------------------------ //
		// SAVE (edit existing)
		// ------------------------------------------------------------------ //
		if ($action === 'save' && $key_id && $request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}

			$data = $this->parse_form_data($request, $db);

			if ($data === null)
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_INVALID_USER') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if (!empty($this->invalid_ext_names))
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_INVALID_EXT_ACCESS', htmlspecialchars(implode(', ', $this->invalid_ext_names), ENT_COMPAT)) . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$credential_manager->update($key_id, $data);
			trigger_error($user->lang('ACP_PHPBBAPIHOOK_UPDATED') . adm_back_link($this->u_action));
		}

		// ------------------------------------------------------------------ //
		// ADD (create new)
		// ------------------------------------------------------------------ //
		if ($action === 'add' && $request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_WARNING);
			}

			$data = $this->parse_form_data($request, $db);

			if ($data === null)
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_INVALID_USER') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			if (!empty($this->invalid_ext_names))
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_INVALID_EXT_ACCESS', htmlspecialchars(implode(', ', $this->invalid_ext_names), ENT_COMPAT)) . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$result    = $credential_manager->create($data);
			$new_token = $result['token'];

			$template->assign_vars([
				'NEW_TOKEN'			=> $new_token,
				'NEW_KEY_ID'		=> $result['key_id'],
				'S_TOKEN_CREATED'	=> true,
			]);
		}

		// ------------------------------------------------------------------ //
		// EDIT form (show form pre-filled)
		// ------------------------------------------------------------------ //
		if ($action === 'edit' && $key_id)
		{
			$row = $credential_manager->get($key_id);
			if ($row === null)
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$expiration_str = '';
			if (!empty($row['expiration']))
			{
				$expiration_str = gmdate('Y-m-d', (int) $row['expiration']);
			}

			$template->assign_vars([
				'S_EDIT_MODE'		=> true,
				'EDIT_KEY_ID'		=> (int) $row['key_id'],
				'EDIT_KEY_NAME'		=> $row['key_name'],
				'EDIT_USER_ID'		=> (int) $row['user_id'],
				'EDIT_FORUM_IDS'	=> $row['forum_ids'] !== '' ? implode(', ', array_map('intval', json_decode($row['forum_ids'], true) ?: [])) : '',
				'EDIT_IP_ALLOWLIST'	=> $row['ip_allowlist'],
				'EDIT_RATE_LIMIT'	=> $row['rate_limit'],
				'EDIT_EXPIRATION'	=> $expiration_str,
				'EDIT_EXT_ACCESS'	=> $this->ext_access_for_form(isset($row['ext_access']) ? $row['ext_access'] : ''),
				'S_EDIT_READ_ONLY'	=> (bool) $row['read_only'],
				'S_EDIT_ENABLED'	=> (bool) $row['is_enabled'],
				'U_SAVE'			=> $this->u_action . '&amp;action=save&amp;id=' . $row['key_id'],
			]);
		}

		// ------------------------------------------------------------------ //
		// VIEW LOG
		// ------------------------------------------------------------------ //
		if ($action === 'viewlog' && $key_id)
		{
			$row = $credential_manager->get($key_id);
			if ($row === null)
			{
				trigger_error($user->lang('ACP_PHPBBAPIHOOK_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$template->assign_vars([
				'S_VIEW_LOG'		=> true,
				'LOG_KEY_NAME'		=> $row['key_name'],
				'LOG_KEY_ID'		=> $key_id,
			]);

			$log_rows = $logger->recent($key_id, 50);
			foreach ($log_rows as $log_row)
			{
				$template->assign_block_vars('logs', [
					'LOG_TIME'		=> $user->format_date($log_row['log_time']),
					'LOG_IP'		=> $log_row['log_ip'],
					'LOG_METHOD'	=> $log_row['log_method'],
					'LOG_ROUTE'		=> $log_row['log_route'],
					'LOG_ACTION'	=> $log_row['log_action'],
					'LOG_STATUS'	=> (int) $log_row['log_status'],
					'LOG_DETAIL'	=> $log_row['log_detail'],
				]);
			}
		}

		// ------------------------------------------------------------------ //
		// LIST (default view, always rendered unless an action exits early)
		// ------------------------------------------------------------------ //
		if (!in_array($action, ['edit', 'viewlog'], true) || ($action === 'add' && $request->is_set_post('submit')) || ($action === 'save' && $key_id && $request->is_set_post('submit')))
		{
			$credentials = $credential_manager->all();

			// Resolve usernames in one query
			$user_ids = array_unique(array_column($credentials, 'user_id'));
			$usernames = [];
			if (!empty($user_ids))
			{
				$sql = 'SELECT user_id, username FROM ' . USERS_TABLE . '
					WHERE ' . $db->sql_in_set('user_id', $user_ids);
				$result = $db->sql_query($sql);
				while ($u = $db->sql_fetchrow($result))
				{
					$usernames[(int) $u['user_id']] = $u['username'];
				}
				$db->sql_freeresult($result);
			}

			foreach ($credentials as $cred)
			{
				$cid      = (int) $cred['key_id'];
				$exp_str  = $cred['expiration'] ? $user->format_date($cred['expiration']) : $user->lang('ACP_PHPBBAPIHOOK_NEVER');
				$last_str = $cred['last_used'] ? $user->format_date($cred['last_used']) : $user->lang('ACP_PHPBBAPIHOOK_NEVER');

				$template->assign_block_vars('keys', [
					'KEY_ID'		=> $cid,
					'KEY_NAME'		=> $cred['key_name'],
					'KEY_PREFIX'	=> $cred['key_prefix'],
					'USERNAME'		=> isset($usernames[(int) $cred['user_id']]) ? $usernames[(int) $cred['user_id']] : $user->lang('ACP_PHPBBAPIHOOK_UNKNOWN_USER'),
					'ENABLED'		=> (bool) $cred['is_enabled'],
					'READ_ONLY'		=> (bool) $cred['read_only'],
					'RATE_LIMIT'	=> $cred['rate_limit'] ? $cred['rate_limit'] : $user->lang('ACP_PHPBBAPIHOOK_UNLIMITED'),
					'EXPIRATION'	=> $exp_str,
					'LAST_USED'		=> $last_str,
					'LAST_IP'		=> $cred['last_ip'],
					'U_EDIT'		=> $this->u_action . '&amp;action=edit&amp;id=' . $cid,
					'U_TOGGLE'		=> $this->u_action . '&amp;action=toggle&amp;id=' . $cid . '&amp;hash=' . generate_link_hash('phpbbapihook_toggle'),
					'U_DELETE'		=> $this->u_action . '&amp;action=delete&amp;id=' . $cid,
					'U_VIEWLOG'		=> $this->u_action . '&amp;action=viewlog&amp;id=' . $cid,
				]);
			}
		}

		$template->assign_vars([
			'EXT_ACCESS_AVAILABLE'	=> implode(', ', $registry->extension_names()),
			'U_ACTION'		=> $this->u_action,
			'U_ADD_ACTION'	=> $this->u_action . '&amp;action=add',
			'S_ACTION'		=> $action,
		]);
	}

	/**
	 * Parse and validate credential form fields from the request.
	 * Returns null if user_id validation fails.
	 *
	 * @param \phpbb\request\request_interface $request
	 * @param \phpbb\db\driver\driver_interface $db
	 * @return array|null
	 */
	protected function parse_form_data($request, $db)
	{
		$key_name      = $request->variable('key_name', '', true);
		$user_id       = $request->variable('user_id', 0);
		$forum_ids_raw = $request->variable('forum_ids', '');
		$ip_allowlist  = $request->variable('ip_allowlist', '');
		$rate_limit    = $request->variable('rate_limit', 0);
		$expiration_s  = $request->variable('expiration', '');
		$read_only     = $request->variable('read_only', 0);
		$is_enabled    = $request->variable('is_enabled', 0);
		$ext_access    = $this->parse_ext_access($request->variable('ext_access', ''));

		// Validate user_id if provided
		if ($user_id > 0)
		{
			$sql    = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE user_id = ' . (int) $user_id;
			$result = $db->sql_query($sql);
			$exists = $db->sql_fetchfield('user_id');
			$db->sql_freeresult($result);

			if (!$exists)
			{
				return null;
			}
		}

		// Parse forum_ids: comma/space separated ints
		$forum_ids_arr = [];
		if (trim($forum_ids_raw) !== '')
		{
			$parts = preg_split('/[^0-9]+/', $forum_ids_raw, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($parts as $part)
			{
				$int = (int) $part;
				if ($int > 0)
				{
					$forum_ids_arr[] = $int;
				}
			}
		}
		$forum_ids = empty($forum_ids_arr) ? '' : json_encode(array_values(array_unique($forum_ids_arr)));

		// Parse expiration date
		$expiration = 0;
		if (trim($expiration_s) !== '')
		{
			$ts = strtotime($expiration_s);
			$expiration = ($ts !== false && $ts > 0) ? $ts : 0;
		}

		return [
			'key_name'      => $key_name,
			'user_id'       => $user_id,
			'forum_ids'     => $forum_ids,
			'ip_allowlist'  => $ip_allowlist,
			'rate_limit'    => (int) $rate_limit,
			'read_only'     => $read_only ? 1 : 0,
			'is_enabled'    => $is_enabled ? 1 : 0,
			'expiration'    => $expiration,
			'ext_access'    => $ext_access,
		];
	}

	/**
	 * Turn the "extension endpoints" form field into its stored form.
	 *
	 * @param string $raw A star for every extension, or vendor/package names
	 *                    separated by commas or spaces.
	 * @return string '' for none, '*' for all, otherwise a JSON list of names.
	 */
	protected function parse_ext_access($raw)
	{
		$names = [];
		$all   = false;

		$this->invalid_ext_names = [];

		// The request class already HTML escaped the value; names never
		// contain characters that escaping changes, so anything that did
		// change is reported as invalid below.
		foreach (preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $part)
		{
			if ($part === '*')
			{
				$all = true;
			}
			else if (\ecyaz\phpbbapihook\api\registry::is_valid_extension_name($part))
			{
				$names[] = strtolower($part);
			}
			else
			{
				$this->invalid_ext_names[] = htmlspecialchars_decode($part, ENT_COMPAT);
			}
		}

		if ($all)
		{
			return '*';
		}

		return empty($names) ? '' : json_encode(array_values(array_unique($names)));
	}

	/**
	 * The stored extension access as the text shown in the edit form.
	 *
	 * @param string $stored
	 * @return string
	 */
	protected function ext_access_for_form($stored)
	{
		$stored = trim((string) $stored);

		if ($stored === '' || $stored === '*')
		{
			return $stored;
		}

		$names = json_decode($stored, true);

		return is_array($names) ? implode(', ', array_filter($names, 'is_string')) : '';
	}
}
