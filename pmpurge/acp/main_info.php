<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\ecyaz\pmpurge\acp\main_module',
			'title'    => 'ACP_PMPURGE_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_PMPURGE_SETTINGS',
					'auth'  => 'ext_ecyaz/pmpurge && acl_a_userdel',
					'cat'   => ['ACP_PMPURGE_TITLE'],
				],
			],
		];
	}
}
