<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\migrations;

/**
 * Task 9: adds the last-run stamp cron\gcal_sync::should_run() gates on
 * (a minimum 5-minute gap between runs, see that class's docblock). A
 * separate tail migration rather than folding into install_config.php since
 * that migration is already applied on every board that installed Tasks
 * 0-8 -- phpBB migrations are append-only once shipped.
 */
class install_gcal_config extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_notifications'];
	}

	public function effectively_installed()
	{
		return $this->config->offsetExists('ecal_gcal_last_run');
	}

	public function update_data()
	{
		return [
			['config.add', ['ecal_gcal_last_run', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['ecal_gcal_last_run']],
		];
	}
}
