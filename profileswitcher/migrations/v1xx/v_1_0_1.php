<?php
/**
*
* @package profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace ecyaz\profileSwitcher\migrations\v1xx;

class v_1_0_1 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->config->offsetExists('pss_ribbon_left');
	}

	public static function depends_on()
	{
		return ['\ecyaz\profileSwitcher\migrations\v1xx\v_1_0_0'];
	}

	public function update_data()
	{
		return [
			['config.add', ['pss_ribbon_left', '0']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename'	=> '\ecyaz\profileSwitcher\acp\main_module',
					'module_langname'	=> 'ACP_PROFILE_SIDE_SWITCHER',
					'module_mode'		=> 'settings',
					'module_auth'		=> 'acl_a_board',
				],
			]],
			['config.update', ['pss_version', '1.0.1']],
		];
	}

	public function revert_data()
	{
		return [
			['config.update', ['pss_version', '1.0.0']],
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_PROFILE_SIDE_SWITCHER',
			]],
			['config.remove', ['pss_ribbon_left']],
		];
	}
}
