<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\migrations;

class m3_module extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\ecyaz\treasurehunt\migrations\m2_config'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_TREASUREHUNT_SETTINGS'";
		$result    = $this->db->sql_query($sql);
		$module_id = (int) $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return (bool) $module_id;
	}

	public function update_data()
	{
		return [
			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_TREASUREHUNT_TITLE']],
			['module.add', ['acp', 'ACP_TREASUREHUNT_TITLE', [
				'module_basename' => '\ecyaz\treasurehunt\acp\main_module',
				'module_langname' => 'ACP_TREASUREHUNT_SETTINGS',
				'module_mode'     => 'settings',
				'module_auth'     => 'ext_ecyaz/treasurehunt && acl_a_board',
			]]],
			['module.add', ['acp', 'ACP_TREASUREHUNT_TITLE', [
				'module_basename' => '\ecyaz\treasurehunt\acp\main_module',
				'module_langname' => 'ACP_TREASUREHUNT_ITEMS',
				'module_mode'     => 'items',
				'module_auth'     => 'ext_ecyaz/treasurehunt && acl_a_board',
			]]],
		];
	}

	public function revert_data()
	{
		return [
			['module.remove', ['acp', 'ACP_TREASUREHUNT_TITLE', 'ACP_TREASUREHUNT_ITEMS']],
			['module.remove', ['acp', 'ACP_TREASUREHUNT_TITLE', 'ACP_TREASUREHUNT_SETTINGS']],
			['module.remove', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_TREASUREHUNT_TITLE']],
		];
	}
}
