<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\acp;

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
		global $config, $db, $request, $table_prefix, $template, $language, $phpbb_log, $user;

		$language->add_lang(['acp_treasurehunt', 'info_acp_treasurehunt'], 'ecyaz/treasurehunt');

		switch ($mode)
		{
			case 'settings':
				$this->mode_settings($config, $request, $template, $language, $phpbb_log, $user);
			break;

			case 'items':
				$this->mode_items($db, $request, $template, $language, $phpbb_log, $user, $table_prefix);
			break;
		}
	}

	// -----------------------------------------------------------------------
	// Settings mode
	// -----------------------------------------------------------------------

	protected function mode_settings($config, $request, $template, $language, $phpbb_log, $user)
	{
		$this->tpl_name   = 'acp_treasurehunt_settings';
		$this->page_title = 'ACP_TREASUREHUNT_SETTINGS';

		$form_key = 'ecyaz_treasurehunt_settings';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$spawn_style = $request->variable('treasurehunt_spawn_style', 'modal');
			if (!in_array($spawn_style, ['modal', 'icon', 'hybrid'], true))
			{
				$spawn_style = 'modal';
			}

			$forum_scope = $request->variable('treasurehunt_forum_scope', 'all');
			$forum_scope = trim($forum_scope);
			if ($forum_scope !== 'all')
			{
				// Normalise to comma-joined ints: strip anything that isn't a digit or comma
				$parts = array_filter(
					array_map('intval', explode(',', $forum_scope)),
					function ($v) { return $v > 0; }
				);
				$forum_scope = $parts ? implode(',', $parts) : 'all';
			}

			$play_groups = $request->variable('treasurehunt_play_groups', 'all');
			$play_groups = trim($play_groups);
			if ($play_groups !== 'all')
			{
				$parts = array_filter(
					array_map('intval', explode(',', $play_groups)),
					function ($v) { return $v > 0; }
				);
				$play_groups = $parts ? implode(',', $parts) : 'all';
			}

			$config->set('treasurehunt_enable',       (int) $request->variable('treasurehunt_enable', 0));
			$config->set('treasurehunt_drop_rate',    min(1000, max(0, (int) $request->variable('treasurehunt_drop_rate', 50))));
			$config->set('treasurehunt_cooldown',     min(86400, max(0, (int) $request->variable('treasurehunt_cooldown', 300))));
			$config->set('treasurehunt_spawn_style',  $spawn_style);
			$config->set('treasurehunt_spawn_expiry', min(3600, max(10, (int) $request->variable('treasurehunt_spawn_expiry', 60))));
			$config->set('treasurehunt_forum_scope',  $forum_scope);
			$config->set('treasurehunt_play_groups',  $play_groups);
			$config->set('treasurehunt_postbit_cap',  min(20, max(0, (int) $request->variable('treasurehunt_postbit_cap', 3))));

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TREASUREHUNT_SETTINGS', time());
			trigger_error($language->lang('TREASUREHUNT_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$spawn_styles = ['modal', 'icon', 'hybrid'];
		foreach ($spawn_styles as $style)
		{
			$template->assign_block_vars('spawn_styles', [
				'VALUE'    => $style,
				'LABEL'    => $language->lang('TREASUREHUNT_SPAWN_STYLE_' . strtoupper($style)),
				'SELECTED' => ((string) $config['treasurehunt_spawn_style'] === $style),
			]);
		}

		$template->assign_vars([
			'U_ACTION'                    => $this->u_action,
			'TREASUREHUNT_ENABLE'         => (bool) $config['treasurehunt_enable'],
			'TREASUREHUNT_DROP_RATE'      => (int) $config['treasurehunt_drop_rate'],
			'TREASUREHUNT_COOLDOWN'       => (int) $config['treasurehunt_cooldown'],
			'TREASUREHUNT_SPAWN_EXPIRY'   => (int) $config['treasurehunt_spawn_expiry'],
			'TREASUREHUNT_FORUM_SCOPE'    => (string) $config['treasurehunt_forum_scope'],
			'TREASUREHUNT_PLAY_GROUPS'    => (string) $config['treasurehunt_play_groups'],
			'TREASUREHUNT_POSTBIT_CAP'    => (int) $config['treasurehunt_postbit_cap'],
		]);
	}

	// -----------------------------------------------------------------------
	// Items mode  — full CRUD (Task 1.6)
	// -----------------------------------------------------------------------

	protected function mode_items($db, $request, $template, $language, $phpbb_log, $user, $table_prefix)
	{
		$this->tpl_name   = 'acp_treasurehunt_items';
		$this->page_title = 'ACP_TREASUREHUNT_ITEMS';

		$form_key = 'ecyaz_treasurehunt_items';
		add_form_key($form_key);

		$action  = $request->variable('action', '');
		$item_id = (int) $request->variable('item_id', 0);

		// ── DELETE ──────────────────────────────────────────────────────────
		if ($action === 'delete')
		{
			if ($item_id <= 0)
			{
				trigger_error($language->lang('NO_ITEM') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			if (confirm_box(true))
			{
				$sql = 'DELETE FROM ' . $table_prefix . 'treasurehunt_items
					WHERE item_id = ' . (int) $item_id;
				$db->sql_query($sql);

				$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TREASUREHUNT_ITEM_DELETED', time(), [$item_id]);
				trigger_error($language->lang('TREASUREHUNT_ITEM_DELETED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(
					false,
					$language->lang('TREASUREHUNT_CONFIRM_DELETE'),
					build_hidden_fields(['action' => 'delete', 'item_id' => $item_id])
				);
			}
			return;
		}

		// ── ADD / EDIT FORM (POST) ───────────────────────────────────────────
		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$item_name   = $request->variable('item_name', '', true);
			$item_image  = $request->variable('item_image', '');
			$rarity      = (int) $request->variable('rarity', 1);
			$points      = (int) $request->variable('points', 0);
			$drop_weight = (int) $request->variable('drop_weight', 1);
			$item_enabled = (int) $request->variable('item_enabled', 1);

			// Validate
			$errors = [];

			if ($item_name === '')
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_NAME_EMPTY');
			}
			else if (utf8_strlen($item_name) > 255)
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_NAME_LONG');
			}

			$item_image = trim($item_image);
			if ($item_image === '')
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_IMAGE_EMPTY');
			}
			else if (strpos($item_image, '://') !== false)
			{
				// Full URL path: validate as http/https URL with an image-extension path
				if (mb_strlen($item_image) > 255)
				{
					$errors[] = $language->lang('TREASUREHUNT_ERROR_IMAGE_LONG');
				}
				else if (
					!filter_var($item_image, FILTER_VALIDATE_URL) ||
					!preg_match('#^https?://#i', $item_image) ||
					!preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', parse_url($item_image, PHP_URL_PATH) ?: '')
				)
				{
					$errors[] = $language->lang('TREASUREHUNT_ERROR_IMAGE_URL_INVALID');
				}
				// else: validated URL — $item_image is stored verbatim
			}
			else
			{
				// Bare filename
				$item_image = basename($item_image);
				if (mb_strlen($item_image) > 255)
				{
					$errors[] = $language->lang('TREASUREHUNT_ERROR_IMAGE_LONG');
				}
				else if (!preg_match('/^[a-zA-Z0-9_\-]+\.(png|jpg|jpeg|gif|webp)$/i', $item_image))
				{
					$errors[] = $language->lang('TREASUREHUNT_ERROR_IMAGE_INVALID');
				}
			}

			if ($rarity < 1 || $rarity > 5)
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_RARITY');
			}

			if ($points < 0 || $points > 99999)
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_POINTS');
			}

			if ($drop_weight < 1 || $drop_weight > 99999)
			{
				$errors[] = $language->lang('TREASUREHUNT_ERROR_WEIGHT');
			}

			$item_enabled = ($item_enabled === 1) ? 1 : 0;

			if (!$errors)
			{
				$row_data = [
					'item_name'    => $item_name,
					'item_image'   => $item_image,
					'rarity'       => $rarity,
					'points'       => $points,
					'drop_weight'  => $drop_weight,
					'item_enabled' => $item_enabled,
				];

				if ($action === 'edit' && $item_id > 0)
				{
					$sql = 'UPDATE ' . $table_prefix . 'treasurehunt_items SET '
						. $db->sql_build_array('UPDATE', $row_data)
						. ' WHERE item_id = ' . (int) $item_id;
					$db->sql_query($sql);

					if ($db->sql_affectedrows() === 0)
					{
						trigger_error($language->lang('TREASUREHUNT_ERROR_ITEM_GONE') . adm_back_link($this->u_action), E_USER_WARNING);
						return;
					}

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TREASUREHUNT_ITEM_EDIT', time(), [$item_name]);
					trigger_error($language->lang('TREASUREHUNT_ITEM_SAVED') . adm_back_link($this->u_action));
				}
				else
				{
					$sql = 'INSERT INTO ' . $table_prefix . 'treasurehunt_items '
						. $db->sql_build_array('INSERT', $row_data);
					$db->sql_query($sql);

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TREASUREHUNT_ITEM_ADD', time(), [$item_name]);
					trigger_error($language->lang('TREASUREHUNT_ITEM_SAVED') . adm_back_link($this->u_action));
				}
				return;
			}

			// Fall through to form render with errors
			foreach ($errors as $error)
			{
				$template->assign_block_vars('errors', ['MESSAGE' => $error]);
			}

			// Re-populate form with submitted values
			$template->assign_vars([
				'U_ACTION'         => $this->u_action,
				'U_BACK'           => $this->u_action,
				'ACTION_FORM'      => true,
				'EDIT_ITEM_ID'     => ($action === 'edit') ? $item_id : 0,
				'FORM_ITEM_NAME'   => $item_name,
				'FORM_ITEM_IMAGE'  => $item_image,
				'FORM_RARITY'      => $rarity,
				'FORM_POINTS'      => $points,
				'FORM_DROP_WEIGHT' => $drop_weight,
				'FORM_ENABLED'     => (bool) $item_enabled,
			]);
			$this->assign_rarity_block_vars($template, $language, $rarity);
			return;
		}

		// ── ADD FORM (GET) ───────────────────────────────────────────────────
		if ($action === 'add')
		{
			$template->assign_vars([
				'U_ACTION'         => $this->u_action,
				'U_BACK'           => $this->u_action,
				'ACTION_FORM'      => true,
				'EDIT_ITEM_ID'     => 0,
				'FORM_ITEM_NAME'   => '',
				'FORM_ITEM_IMAGE'  => '',
				'FORM_RARITY'      => 1,
				'FORM_POINTS'      => 10,
				'FORM_DROP_WEIGHT' => 10,
				'FORM_ENABLED'     => true,
			]);
			$this->assign_rarity_block_vars($template, $language, 1);
			return;
		}

		// ── EDIT FORM (GET) ──────────────────────────────────────────────────
		if ($action === 'edit')
		{
			if ($item_id <= 0)
			{
				trigger_error($language->lang('NO_ITEM') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$sql = 'SELECT item_id, item_name, item_image, rarity, points, drop_weight, item_enabled
				FROM ' . $table_prefix . 'treasurehunt_items WHERE item_id = ' . (int) $item_id;
			$result = $db->sql_query($sql);
			$row    = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($language->lang('NO_ITEM') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$template->assign_vars([
				'U_ACTION'         => $this->u_action,
				'U_BACK'           => $this->u_action,
				'ACTION_FORM'      => true,
				'EDIT_ITEM_ID'     => $item_id,
				'FORM_ITEM_NAME'   => $row['item_name'],
				'FORM_ITEM_IMAGE'  => $row['item_image'],
				'FORM_RARITY'      => (int) $row['rarity'],
				'FORM_POINTS'      => (int) $row['points'],
				'FORM_DROP_WEIGHT' => (int) $row['drop_weight'],
				'FORM_ENABLED'     => (bool) $row['item_enabled'],
			]);
			$this->assign_rarity_block_vars($template, $language, (int) $row['rarity']);
			return;
		}

		// ── LIST (default) ───────────────────────────────────────────────────
		$sql    = 'SELECT item_id, item_name, item_image, rarity, points, drop_weight, item_enabled
			FROM ' . $table_prefix . 'treasurehunt_items ORDER BY rarity ASC, item_id ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('items', [
				'ITEM_ID'      => (int) $row['item_id'],
				'ITEM_NAME'    => $row['item_name'],
				'ITEM_IMAGE'   => $row['item_image'],
				'RARITY'       => (int) $row['rarity'],
				'RARITY_NAME'  => $language->lang('TREASUREHUNT_RARITY_' . (int) $row['rarity']),
				'POINTS'       => (int) $row['points'],
				'DROP_WEIGHT'  => (int) $row['drop_weight'],
				'ITEM_ENABLED' => (bool) $row['item_enabled'],
				'U_EDIT'       => $this->u_action . '&amp;action=edit&amp;item_id=' . (int) $row['item_id'],
				'U_DELETE'     => $this->u_action . '&amp;action=delete&amp;item_id=' . (int) $row['item_id'],
			]);
		}
		$db->sql_freeresult($result);

		$template->assign_vars([
			'U_ACTION'    => $this->u_action,
			'U_ADD_ITEM'  => $this->u_action . '&amp;action=add',
			'ACTION_FORM' => false,
		]);
	}

	protected function assign_rarity_block_vars($template, $language, $selected_rarity)
	{
		for ($r = 1; $r <= 5; $r++)
		{
			$template->assign_block_vars('rarities', [
				'VALUE'    => $r,
				'LABEL'    => $language->lang('TREASUREHUNT_RARITY_' . $r),
				'SELECTED' => ($r === $selected_rarity),
			]);
		}
	}
}
