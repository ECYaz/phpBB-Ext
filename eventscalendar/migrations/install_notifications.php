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
 * container_aware_migration (not the plain migration base) so revert_data's
 * 'custom' step can reach the notification_manager service to disable +
 * purge the reminder notification type on purge (VERIFY, see task-6-report.md:
 * no dedicated "notification.type enable" migration tool exists anywhere in
 * core — phpbb/db/migration/tool/ only has config, config_text, module,
 * permission. get_notification_type_id() (phpbb/notification/manager.php
 * ~952-985) lazily INSERTs the phpbb_notification_types row itself, already
 * enabled=1, the first time the type is ever used — so there is nothing to
 * "enable" here on install. On purge we still explicitly disable + purge it
 * so a purge is symmetric even for a board that fired at least one
 * reminder.)
 */
class install_notifications extends \phpbb\db\migration\container_aware_migration
{
	const NOTIFICATION_TYPE = \ecyaz\eventscalendar\notification\reminder::TYPE_NAME;

	public static function depends_on()
	{
		return ['\ecyaz\eventscalendar\migrations\install_acp_module'];
	}

	public function effectively_installed()
	{
		return $this->config->offsetExists('ecal_reminders_last_run');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// Dedupe marker for the hourly reminder cron (VERIFY dedupe
				// decision, task-6-report.md): notification_manager::
				// add_notifications_for_users() only re-checks "already
				// notified" by item_id (event_id), never item_parent_id
				// (occurrence_ts), so it cannot tell two different
				// occurrences of the same recurring event apart. This table
				// is the sole source of truth for "has (event_id,
				// occurrence_ts) already been reminded to user_id" instead.
				$this->table_prefix . 'ecal_reminded' => [
					'COLUMNS' => [
						'event_id'      => ['UINT', 0], // composite PK part
						'occurrence_ts' => ['UINT:11', 0], // composite PK part; 0 for non-recurring, matches ecal_attendees
						'user_id'       => ['UINT', 0], // composite PK part
						'reminded_ts'   => ['UINT:11', 0],
					],
					'PRIMARY_KEY' => ['event_id', 'occurrence_ts', 'user_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'ecal_reminded',
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['ecal_reminders_last_run', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'disable_notification_type']]],
			['config.remove', ['ecal_reminders_last_run']],
		];
	}

	public function disable_notification_type()
	{
		/** @var \phpbb\notification\manager $notification_manager */
		$notification_manager = $this->container->get('notification_manager');

		$notification_manager->disable_notifications(self::NOTIFICATION_TYPE);
		$notification_manager->purge_notifications(self::NOTIFICATION_TYPE);
	}
}
