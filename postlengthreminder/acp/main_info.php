<?php
/**
 *
 * Post Length Reminder. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\postlengthreminder\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\ecyaz\postlengthreminder\acp\main_module',
			'title'		=> 'ACP_POSTLENGTHREMINDER_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_POSTLENGTHREMINDER_SETTINGS',
					'auth'	=> 'ext_ecyaz/postlengthreminder && acl_a_board',
					'cat'	=> ['ACP_CAT_DOT_MODS'],
				],
			],
		];
	}
}
