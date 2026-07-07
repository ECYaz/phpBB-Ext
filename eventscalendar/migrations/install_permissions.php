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

class install_permissions extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_config'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT auth_option_id
			FROM ' . ACL_OPTIONS_TABLE . "
			WHERE auth_option = 'u_ecal_view'";
		$result = $this->db->sql_query($sql);
		$auth_option_id = $this->db->sql_fetchfield('auth_option_id');
		$this->db->sql_freeresult($result);

		return $auth_option_id !== false;
	}

	public function update_data()
	{
		return [
			['permission.add', ['u_ecal_view']],
			['permission.add', ['u_ecal_post']],
			['permission.add', ['u_ecal_attend']],
			['permission.add', ['m_ecal_manage', false]],
			['permission.add', ['a_ecal_manage']],

			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ecal_view', 'role']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ecal_post', 'role']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_ecal_attend', 'role']],
			['permission.permission_set', ['ROLE_MOD_STANDARD', 'm_ecal_manage', 'role']],
			['permission.permission_set', ['ROLE_ADMIN_STANDARD', 'a_ecal_manage', 'role']],
		];
	}
}
