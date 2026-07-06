<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\migrations;

class m_add_badge_catalog_acp extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = "SELECT module_id FROM {$this->table_prefix}modules
				WHERE module_class = 'acp'
					AND module_langname = 'ACP_TH_BADGES'";
		$result    = $this->db->sql_query($sql);
		$module_id = (int) $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return (bool) $module_id;
	}

	static public function depends_on()
	{
		return ['\ecyaz\treasurehunt\migrations\m4_seed'];
	}

	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_TREASUREHUNT_TITLE',
				[
					'module_basename' => '\ecyaz\treasurehunt\acp\badge_module',
					'module_langname' => 'ACP_TH_BADGES',
					'module_mode'     => 'badges',
					'module_auth'     => 'ext_ecyaz/treasurehunt && acl_a_board',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['module.remove', [
				'acp',
				'ACP_TREASUREHUNT_TITLE',
				'ACP_TH_BADGES',
			]],
		];
	}
}
