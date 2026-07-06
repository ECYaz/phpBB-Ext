<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\acp;

class badge_info
{
	public function module()
	{
		return [
			'filename' => '\ecyaz\treasurehunt\acp\badge_module',
			'title'    => 'ACP_TH_TITLE',
			'modes'    => [
				'badges' => [
					'title' => 'ACP_TH_BADGES',
					'auth'  => 'ext_ecyaz/treasurehunt && acl_a_board',
					'cat'   => ['ACP_TH_TITLE'],
				],
			],
		];
	}
}
