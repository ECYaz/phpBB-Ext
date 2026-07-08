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

/**
 * Groups the three ACP pages under their own "Events Calendar" category so the
 * Extensions tab shows a proper left-hand menu block. v1.0.1 and earlier added
 * the modes directly under ACP_CAT_DOT_MODS (works, but renders without a
 * grouped sidebar entry).
 *
 * The move is done in 'custom' steps on purpose: the migrator auto-reverses
 * every plain tool call in update_data() on purge (phpbb/db/migration/
 * helper.php::reverse_update_data()), and the reverse of a module.remove is a
 * module.add with only a langname — which recreates the removed modes as bogus
 * empty categories and then collides with revert_data(). 'custom' steps are
 * excluded from auto-reversal, so update and revert below run exactly once
 * each, in the order written. container_aware_migration gives the custom
 * steps access to the migrator.tool.module service.
 */
class acp_category extends \phpbb\db\migration\container_aware_migration
{
	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_gcal_config'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT module_id
			FROM ' . $this->table_prefix . "modules
			WHERE module_class = 'acp'
				AND module_langname = 'ACP_EVENTSCALENDAR_TITLE'";
		$result = $this->db->sql_query($sql);
		$module_id = (int) $this->db->sql_fetchfield('module_id');
		$this->db->sql_freeresult($result);

		return (bool) $module_id;
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'move_modes_under_category']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'move_modes_back_to_tab_root']]],
		];
	}

	/**
	 * Detach the flat v1.0.x modes from the Extensions tab root, create the
	 * category, and re-add the modes underneath it.
	 */
	public function move_modes_under_category()
	{
		$tool = $this->get_module_tool();

		foreach (['ACP_ECAL_SETTINGS', 'ACP_ECAL_GOOGLE', 'ACP_ECAL_SPECIAL'] as $langname)
		{
			$tool->remove('acp', 'ACP_CAT_DOT_MODS', $langname);
		}

		$tool->add('acp', 'ACP_CAT_DOT_MODS', 'ACP_EVENTSCALENDAR_TITLE');

		foreach ($this->mode_data() as $mode_data)
		{
			$tool->add('acp', 'ACP_EVENTSCALENDAR_TITLE', $mode_data);
		}
	}

	/**
	 * Mirror image: put the modes back under the Extensions tab root and drop
	 * the category, so install_acp_module's own revert removes them cleanly
	 * on purge.
	 */
	public function move_modes_back_to_tab_root()
	{
		$tool = $this->get_module_tool();

		foreach (['ACP_ECAL_SETTINGS', 'ACP_ECAL_GOOGLE', 'ACP_ECAL_SPECIAL'] as $langname)
		{
			$tool->remove('acp', 'ACP_EVENTSCALENDAR_TITLE', $langname);
		}

		$tool->remove('acp', 'ACP_CAT_DOT_MODS', 'ACP_EVENTSCALENDAR_TITLE');

		foreach ($this->mode_data() as $mode_data)
		{
			$tool->add('acp', 'ACP_CAT_DOT_MODS', $mode_data);
		}
	}

	/**
	 * @return array[] module.add data arrays for the three modes
	 */
	protected function mode_data()
	{
		$modes = [
			'settings' => 'ACP_ECAL_SETTINGS',
			'google'   => 'ACP_ECAL_GOOGLE',
			'special'  => 'ACP_ECAL_SPECIAL',
		];

		$data = [];
		foreach ($modes as $mode => $langname)
		{
			$data[] = [
				'module_basename' => '\ecyaz\eventscalendar\acp\main_module',
				'module_langname' => $langname,
				'module_mode'     => $mode,
				'module_auth'     => 'acl_a_ecal_manage',
			];
		}

		return $data;
	}

	/**
	 * @return \phpbb\db\migration\tool\module
	 */
	protected function get_module_tool()
	{
		return $this->container->get('migrator.tool.module');
	}
}
