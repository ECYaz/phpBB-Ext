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

class install_config extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_schema'];
	}

	public function effectively_installed()
	{
		return $this->config->offsetExists('ecal_version');
	}

	public function update_data()
	{
		return [
			['config.add', ['ecal_version', '1.0.0']],
			['config.add', ['ecal_index_display', 0]], // 0 off, 1 upcoming, 2 mini-calendar, 3 both
			['config.add', ['ecal_index_upcoming_count', 5]],
			['config.add', ['ecal_topic_forum_id', 0]],
			['config.add', ['ecal_reminder_days', 3]],
			['config.add', ['ecal_birthdays_enable', 0]],
			['config.add', ['ecal_ics_enable', 0]],
			['config.add', ['ecal_ics_public', 0]],
			['config.add', ['ecal_ics_token', '']],
			['config.add', ['ecal_gcal_enable', 0]],
			['config.add', ['ecal_gcal_calendar_id', '']],
			['config.add', ['ecal_gcal_status', '']], // last sync result
			['config_text.add', ['ecal_gcal_sa_json', '']], // service-account JSON key
		];
	}
}
