<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\ecyaz\pmemaildefault\acp\main_module',
			'title'		=> 'ACP_PMEMAILDEFAULT_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_PMEMAILDEFAULT_SETTINGS',
					'auth'	=> 'ext_ecyaz/pmemaildefault && acl_a_board',
					'cat'	=> ['ACP_CAT_DOT_MODS'],
				],
			],
		];
	}
}
