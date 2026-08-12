<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\migrations;

class m2_pm_email_config extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->config->offsetExists('pmemaildefault_enabled');
	}

	public static function depends_on()
	{
		return ['\ecyaz\pmemaildefault\migrations\m1_pm_email_default'];
	}

	public function update_data()
	{
		// Default ON, matching the behaviour of 1.0.0 (PM email on for everyone).
		return [
			['config.add', ['pmemaildefault_enabled', 1]],
		];
	}
}
