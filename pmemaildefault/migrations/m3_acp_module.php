<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\migrations;

class m3_acp_module extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_PMEMAILDEFAULT_SETTINGS'";
		$result = $this->db->sql_query($sql);
		$module_id = (int) $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return (bool) $module_id;
	}

	public static function depends_on()
	{
		return ['\ecyaz\pmemaildefault\migrations\m2_pm_email_config'];
	}

	public function update_data()
	{
		return [
			// Own category under the Extensions tab → renders as the sidebar section
			// header (depth 1). phpBB only draws a left-hand menu block for a tab when
			// it contains a category that in turn contains a mode (the l_block1 >
			// l_block2 > l_block3 chain in adm/style/overall_header.html), so the mode
			// must live one level below its own category, not directly on the tab.
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_PMEMAILDEFAULT_TITLE',
			]],

			// Settings mode nested under our category → the clickable left-nav link (depth 2).
			['module.add', [
				'acp',
				'ACP_PMEMAILDEFAULT_TITLE',
				[
					'module_basename'	=> '\ecyaz\pmemaildefault\acp\main_module',
					'module_langname'	=> 'ACP_PMEMAILDEFAULT_SETTINGS',
					'module_mode'		=> 'settings',
					'module_auth'		=> 'ext_ecyaz/pmemaildefault && acl_a_board',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['module.remove', [
				'acp',
				'ACP_PMEMAILDEFAULT_TITLE',
				'ACP_PMEMAILDEFAULT_SETTINGS',
			]],
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_PMEMAILDEFAULT_TITLE',
			]],
		];
	}
}
