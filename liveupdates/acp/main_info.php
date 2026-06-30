<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\acp;

class main_info
{
	public function module()
	{
		return [
			'filename' => '\ecyaz\liveupdates\acp\main_module',
			'title'    => 'ACP_LIVEUPDATES_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_LIVEUPDATES_SETTINGS',
					'auth'  => 'ext_ecyaz/liveupdates && acl_a_board',
					'cat'   => ['ACP_LIVEUPDATES_TITLE'],
				],
			],
		];
	}
}
