<?php
/**
*
* @package profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace ecyaz\profileSwitcher\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\ecyaz\profileSwitcher\acp\main_module',
			'title'		=> 'ACP_PROFILE_SIDE_SWITCHER_TITLE',
			'modes'		=> [
				'settings'	=> [
					'title'	=> 'ACP_PROFILE_SIDE_SWITCHER',
					'auth'	=> 'acl_a_board',
					'cat'	=> ['ACP_CAT_DOT_MODS'],
				],
			],
		];
	}
}
