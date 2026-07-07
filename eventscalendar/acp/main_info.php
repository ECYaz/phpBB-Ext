<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\ecyaz\eventscalendar\acp\main_module',
			'title'    => 'ACP_ECAL_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_ECAL_SETTINGS',
					'auth'  => 'acl_a_ecal_manage',
					'cat'   => ['ACP_CAT_DOT_MODS'],
				],
				'google' => [
					'title' => 'ACP_ECAL_GOOGLE',
					'auth'  => 'acl_a_ecal_manage',
					'cat'   => ['ACP_CAT_DOT_MODS'],
				],
				'special' => [
					'title' => 'ACP_ECAL_SPECIAL',
					'auth'  => 'acl_a_ecal_manage',
					'cat'   => ['ACP_CAT_DOT_MODS'],
				],
			],
		];
	}
}
