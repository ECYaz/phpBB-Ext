<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\acp;

class badge_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	/** Valid feat keys — must match the shared-contract table in the plan. */
	private static $feat_keys = [
		'first_find', 'first_rare', 'first_legendary',
		'unique_10', 'unique_50', 'unique_100',
		'finds_100', 'finds_500', 'finds_1000',
		'one_each_rarity', 'completionist',
	];

	public function main($id, $mode)
	{
		global $db, $request, $table_prefix, $template, $language, $phpbb_log, $user;

		$badges_table = $table_prefix . 'treasurehunt_badges';

		$language->add_lang(['acp/badges'], 'ecyaz/treasurehunt');

		$this->tpl_name   = 'acp_th_badges';
		$this->page_title = 'ACP_TH_BADGES';

		$form_key = 'ecyaz_th_badges';
		add_form_key($form_key);

		$action   = $request->variable('action', '');
		$badge_id = (int) $request->variable('badge_id', 0);

		// ── DELETE ──────────────────────────────────────────────────────────
		if ($action === 'delete')
		{
			if ($badge_id <= 0)
			{
				trigger_error($language->lang('ACP_TH_BADGE_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			if (confirm_box(true))
			{
				$sql = 'DELETE FROM ' . $badges_table . ' WHERE badge_id = ' . (int) $badge_id;
				$db->sql_query($sql);

				$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TH_BADGE_DELETED', time(), [$badge_id]);
				trigger_error($language->lang('ACP_TH_BADGE_DELETED') . adm_back_link($this->u_action));
			}
			else
			{
				confirm_box(
					false,
					$language->lang('ACP_TH_BADGE_CONFIRM_DELETE'),
					build_hidden_fields(['action' => 'delete', 'badge_id' => $badge_id])
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

			$badge_name    = $request->variable('badge_name', '', true);
			$badge_image   = $request->variable('badge_image', '');
			$cond_type     = $request->variable('condition_type', 'points');
			$badge_enabled = (int) $request->variable('badge_enabled', 0);

			$errors = [];

			// Validate badge name
			if ($badge_name === '')
			{
				$errors[] = $language->lang('ACP_TH_BADGE_NAME_EMPTY');
			}
			else if (mb_strlen($badge_name) > 255)
			{
				$errors[] = $language->lang('ACP_TH_BADGE_NAME_LONG');
			}

			// Validate condition type
			if (!in_array($cond_type, ['points', 'feat'], true))
			{
				$errors[] = $language->lang('ACP_TH_BADGE_INVALID_TYPE');
			}

			// Validate badge_image — mirror items mode Task 1.6 hardened logic exactly
			$badge_image = trim($badge_image);
			if ($badge_image === '')
			{
				$errors[] = $language->lang('ACP_TH_BADGE_IMAGE_EMPTY');
			}
			else if (strpos($badge_image, '://') !== false)
			{
				// Full URL: validate as http/https URL with an image-extension path
				if (mb_strlen($badge_image) > 255)
				{
					$errors[] = $language->lang('ACP_TH_BADGE_IMAGE_LONG');
				}
				else if (
					!filter_var($badge_image, FILTER_VALIDATE_URL) ||
					!preg_match('#^https?://#i', $badge_image) ||
					!preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', parse_url($badge_image, PHP_URL_PATH) ?: '')
				)
				{
					$errors[] = $language->lang('ACP_TH_BADGE_IMAGE_URL_INVALID');
				}
				// else: validated URL — $badge_image stored verbatim
			}
			else
			{
				// Bare filename
				$badge_image = basename($badge_image);
				if (mb_strlen($badge_image) > 255)
				{
					$errors[] = $language->lang('ACP_TH_BADGE_IMAGE_LONG');
				}
				else if (!preg_match('/^[a-zA-Z0-9_\-]+\.(png|jpg|jpeg|gif|webp)$/i', $badge_image))
				{
					$errors[] = $language->lang('ACP_TH_BADGE_IMAGE_INVALID');
				}
			}

			// Validate condition_value (only if condition_type is valid)
			$cond_value = '';
			if (in_array($cond_type, ['points', 'feat'], true))
			{
				if ($cond_type === 'points')
				{
					$cond_value = (string) max(0, (int) $request->variable('condition_value_points', 0));
				}
				else
				{
					$cond_value = $request->variable('condition_value_feat', '');
					if (!in_array($cond_value, self::$feat_keys, true))
					{
						$errors[] = $language->lang('ACP_TH_BADGE_INVALID_FEAT');
					}
				}
			}

			if (!$errors)
			{
				$row_data = [
					'badge_name'      => $badge_name,
					'badge_image'     => $badge_image,
					'condition_type'  => $cond_type,
					'condition_value' => $cond_value,
					'badge_enabled'   => ($badge_enabled === 1) ? 1 : 0,
				];

				if ($action === 'edit' && $badge_id > 0)
				{
					$sql = 'UPDATE ' . $badges_table . ' SET '
						. $db->sql_build_array('UPDATE', $row_data)
						. ' WHERE badge_id = ' . (int) $badge_id;
					$db->sql_query($sql);

					if ($db->sql_affectedrows() === 0)
					{
						trigger_error($language->lang('ACP_TH_BADGE_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
						return;
					}

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TH_BADGE_UPDATED', time(), [$badge_name]);
					trigger_error($language->lang('ACP_TH_BADGE_SAVED') . adm_back_link($this->u_action));
				}
				else
				{
					$sql = 'INSERT INTO ' . $badges_table . ' ' . $db->sql_build_array('INSERT', $row_data);
					$db->sql_query($sql);

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_TH_BADGE_ADDED', time(), [$badge_name]);
					trigger_error($language->lang('ACP_TH_BADGE_ADDED') . adm_back_link($this->u_action));
				}
				return;
			}

			// Fall through to form render with errors
			foreach ($errors as $error)
			{
				$template->assign_block_vars('errors', ['MESSAGE' => $error]);
			}

			// Re-populate form with submitted values
			$is_points = ($cond_type === 'points');
			$this->assign_form_vars($template, $language, $action, $badge_id, [
				'badge_name'      => $badge_name,
				'badge_image'     => $badge_image,
				'condition_type'  => $cond_type,
				'condition_value' => $cond_value,
				'badge_enabled'   => $badge_enabled,
			]);
			return;
		}

		// ── ADD FORM (GET) ───────────────────────────────────────────────────
		if ($action === 'add')
		{
			$this->assign_form_vars($template, $language, 'add', 0, [
				'badge_name'      => '',
				'badge_image'     => '',
				'condition_type'  => 'points',
				'condition_value' => '0',
				'badge_enabled'   => 1,
			]);
			return;
		}

		// ── EDIT FORM (GET) ──────────────────────────────────────────────────
		if ($action === 'edit')
		{
			if ($badge_id <= 0)
			{
				trigger_error($language->lang('ACP_TH_BADGE_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$sql    = 'SELECT badge_id, badge_name, badge_image, condition_type, condition_value, badge_enabled
				FROM ' . $badges_table . ' WHERE badge_id = ' . (int) $badge_id;
			$result = $db->sql_query($sql);
			$row    = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			if (!$row)
			{
				trigger_error($language->lang('ACP_TH_BADGE_NOT_FOUND') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$this->assign_form_vars($template, $language, 'edit', $badge_id, $row);
			return;
		}

		// ── LIST (default) ───────────────────────────────────────────────────
		$sql    = 'SELECT badge_id, badge_name, badge_image, condition_type, condition_value, badge_enabled
			FROM ' . $badges_table . ' ORDER BY badge_id ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$cond_label = $row['condition_type'] === 'points'
				? $language->lang('ACP_TH_POINTS_THRESHOLD', (int) $row['condition_value'])
				: $language->lang('ACP_TH_FEAT_' . strtoupper($row['condition_value']));

			$template->assign_block_vars('badges', [
				'BADGE_ID'   => (int) $row['badge_id'],
				'NAME'       => $row['badge_name'],
				'IMAGE'      => $row['badge_image'],
				'COND_LABEL' => $cond_label,
				'ENABLED'    => (bool) $row['badge_enabled'],
				'U_EDIT'     => $this->u_action . '&amp;action=edit&amp;badge_id=' . (int) $row['badge_id'],
				'U_DELETE'   => $this->u_action . '&amp;action=delete&amp;badge_id=' . (int) $row['badge_id'],
			]);
		}
		$db->sql_freeresult($result);

		$template->assign_vars([
			'U_ACTION'    => $this->u_action,
			'U_ADD_BADGE' => $this->u_action . '&amp;action=add',
			'ACTION_FORM' => false,
		]);
	}

	protected function assign_form_vars($template, $language, $action, $badge_id, array $row)
	{
		$is_points  = ($row['condition_type'] === 'points');
		$cond_value = $row['condition_value'];

		foreach (self::$feat_keys as $key)
		{
			$template->assign_block_vars('feat_options', [
				'VALUE'    => $key,
				'LABEL'    => $language->lang('ACP_TH_FEAT_' . strtoupper($key)),
				'SELECTED' => (!$is_points && $cond_value === $key),
			]);
		}

		$template->assign_vars([
			'U_ACTION'          => $this->u_action,
			'U_BACK'            => $this->u_action,
			'ACTION_FORM'       => true,
			'EDIT_BADGE_ID'     => ($action === 'edit') ? $badge_id : 0,
			'BADGE_NAME'        => $row['badge_name'],
			'BADGE_IMAGE'       => $row['badge_image'],
			'BADGE_ENABLED'     => (bool) $row['badge_enabled'],
			'COND_TYPE_POINTS'  => $is_points,
			'COND_VALUE_POINTS' => $is_points ? (int) $cond_value : 0,
		]);
	}
}
