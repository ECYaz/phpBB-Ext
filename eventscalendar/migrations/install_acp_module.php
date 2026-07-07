<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\migrations;

class install_acp_module extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_permissions'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_ECAL_SETTINGS'";
		$result = $this->db->sql_query($sql);
		$module_id = (int) $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return (bool) $module_id;
	}

	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename' => '\ecyaz\eventscalendar\acp\main_module',
					'module_langname' => 'ACP_ECAL_SETTINGS',
					'module_mode'     => 'settings',
					'module_auth'     => 'acl_a_ecal_manage',
				],
			]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename' => '\ecyaz\eventscalendar\acp\main_module',
					'module_langname' => 'ACP_ECAL_GOOGLE',
					'module_mode'     => 'google',
					'module_auth'     => 'acl_a_ecal_manage',
				],
			]],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename' => '\ecyaz\eventscalendar\acp\main_module',
					'module_langname' => 'ACP_ECAL_SPECIAL',
					'module_mode'     => 'special',
					'module_auth'     => 'acl_a_ecal_manage',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_ECAL_SETTINGS',
			]],
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_ECAL_GOOGLE',
			]],
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_ECAL_SPECIAL',
			]],
		];
	}
}
