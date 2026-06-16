<?php
/**
*
* @package profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace ecyaz\profileSwitcher\migrations\v1xx;

class v_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['pss_version']) && version_compare($this->config['pss_version'], '1.0.0', '>=');
	}

	static public function depends_on()
	{
			return array('\ecyaz\profileSwitcher\migrations\v0xx\v_0_0_1');
	}

	public function update_data()
	{
		return array(
			// Current version
			array('config.update', array('pss_version', '1.0.0')),
		);
	}
}
