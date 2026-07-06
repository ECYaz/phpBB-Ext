<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\ecyaz\treasurehunt\acp\main_module',
			'title'    => 'ACP_TREASUREHUNT_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_TREASUREHUNT_SETTINGS',
					'auth'  => 'ext_ecyaz/treasurehunt && acl_a_board',
					'cat'   => ['ACP_TREASUREHUNT_TITLE'],
				],
				'items' => [
					'title' => 'ACP_TREASUREHUNT_ITEMS',
					'auth'  => 'ext_ecyaz/treasurehunt && acl_a_board',
					'cat'   => ['ACP_TREASUREHUNT_TITLE'],
				],
			],
		];
	}
}
