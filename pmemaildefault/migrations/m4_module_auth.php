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

class m4_module_auth extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		$sql = 'SELECT module_auth
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_PMEMAILDEFAULT_SETTINGS'";
		$result = $this->db->sql_query($sql);
		$module_auth = $this->db->sql_fetchfield('module_auth');
		$this->db->sql_freeresult($result);

		// Nothing to do when the module is absent or already carries the check
		// (fresh 1.0.3 installs get it straight from m3_acp_module).
		return $module_auth === false || strpos($module_auth, 'ext_ecyaz/pmemaildefault') !== false;
	}

	public static function depends_on()
	{
		return ['\ecyaz\pmemaildefault\migrations\m3_acp_module'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'update_module_auth']]],
		];
	}

	/**
	 * Boards that installed 1.0.2 already have the ACP module row with a bare
	 * acl_a_board auth. Add the extension enabled check so the module disappears
	 * (and stops working) when the extension is disabled.
	 */
	public function update_module_auth()
	{
		$sql = 'UPDATE ' . $this->table_prefix . "modules
			SET module_auth = 'ext_ecyaz/pmemaildefault && acl_a_board'
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_PMEMAILDEFAULT_SETTINGS'";
		$this->db->sql_query($sql);
	}
}
