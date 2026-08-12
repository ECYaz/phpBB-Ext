<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\acp;

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $config, $request, $template, $user, $db, $table_prefix;

		$user->add_lang_ext('ecyaz/pmemaildefault', 'acp/pmemaildefault');

		$this->tpl_name   = 'acp_pmemaildefault';
		$this->page_title = 'ACP_PMEMAILDEFAULT_SETTINGS';

		$form_key = 'acp_pmemaildefault';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$enabled = $request->variable('pmemaildefault_enabled', 0) ? 1 : 0;

			$config->set('pmemaildefault_enabled', $enabled);

			// Apply the chosen default to every existing member straight away.
			$this->apply_to_existing_users($db, $table_prefix, $enabled);

			trigger_error($user->lang('ACP_PMEMAILDEFAULT_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'PMEMAILDEFAULT_ENABLED'	=> !empty($config['pmemaildefault_enabled']),
			'U_ACTION'					=> $this->u_action,
		]);
	}

	/**
	 * Force the PM/email notification subscription to $notify for every existing real
	 * user. Portable across phpBB's supported DBs: read current state, UPDATE existing
	 * rows, then multi-insert rows for users who have none (no INSERT...SELECT against
	 * the target table, which MySQL rejects).
	 *
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param string $table_prefix
	 * @param int    $notify       1 to switch PM email on for everyone, 0 for off
	 */
	protected function apply_to_existing_users($db, $table_prefix, $notify)
	{
		$notify    = (int) $notify;
		$item_type = 'notification.type.pm';
		$method    = 'notification.method.email';
		$notif_tbl = $table_prefix . 'user_notifications';
		$users_tbl = $table_prefix . 'users';

		// Collect users who already have an explicit PM/email row.
		$existing = [];
		$sql = 'SELECT user_id
			FROM ' . $notif_tbl . "
			WHERE item_type = '" . $db->sql_escape($item_type) . "'
				AND item_id = 0
				AND method = '" . $db->sql_escape($method) . "'";
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$existing[(int) $row['user_id']] = true;
		}
		$db->sql_freeresult($result);

		// Set every existing PM/email row to the chosen state.
		$sql = 'UPDATE ' . $notif_tbl . '
			SET notify = ' . $notify . "
			WHERE item_type = '" . $db->sql_escape($item_type) . "'
				AND item_id = 0
				AND method = '" . $db->sql_escape($method) . "'";
		$db->sql_query($sql);

		// Insert a row in the chosen state for every real user that has none yet.
		$rows = [];
		$sql = 'SELECT user_id
			FROM ' . $users_tbl . '
			WHERE user_id <> ' . ANONYMOUS . '
				AND user_type <> ' . USER_IGNORE;
		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			$user_id = (int) $row['user_id'];
			if (isset($existing[$user_id]))
			{
				continue;
			}
			$rows[] = [
				'item_type'	=> $item_type,
				'item_id'	=> 0,
				'user_id'	=> $user_id,
				'method'	=> $method,
				'notify'	=> $notify,
			];

			// Flush in batches so neither the PHP array nor the INSERT statement
			// grows unbounded on a very large board.
			if (count($rows) >= 500)
			{
				$db->sql_multi_insert($notif_tbl, $rows);
				$rows = [];
			}
		}
		$db->sql_freeresult($result);

		if (!empty($rows))
		{
			$db->sql_multi_insert($notif_tbl, $rows);
		}
	}
}
