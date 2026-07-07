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

class install_schema extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'ecal_events');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'ecal_events' => [
					'COLUMNS' => [
						'event_id'         => ['UINT', null, 'auto_increment'],
						'poster_id'        => ['UINT', 0],
						'event_type'       => ['TINT:4', 0], // 0 = user event, 1 = ACP special date
						'title'            => ['VCHAR:255', ''],
						'description'      => ['MTEXT_UNI', ''], // BBCode source
						'bbcode_uid'       => ['VCHAR:8', ''],
						'bbcode_bitfield'  => ['VCHAR:255', ''],
						'bbcode_options'   => ['UINT', 7], // OPTION_FLAG_BBCODE|SMILIES|LINKS
						'start_ts'         => ['TIMESTAMP', 0], // UTC
						'end_ts'           => ['UINT:11', 0], // UTC; >= start_ts; = start_ts if none
						'all_day'          => ['BOOL', 0],
						'recur_type'       => ['TINT:4', 0], // 0 none, 1 daily, 2 weekly, 3 monthly, 4 annual
						'recur_until'      => ['UINT:11', 0], // 0 = no end
						'color'            => ['TINT:4', 0], // fixed accessible palette index
						'topic_id'         => ['UINT', 0], // linked discussion topic, 0 = none
						'gcal_id'          => ['VCHAR:255', ''], // Google event id, '' = never pushed
						'event_time'       => ['UINT:11', 0], // created
						'event_edit_time'  => ['UINT:11', 0], // last edited
					],
					'PRIMARY_KEY' => 'event_id',
					'KEYS' => [
						'ecal_start_ts' => ['INDEX', 'start_ts'],
						'ecal_topic'    => ['INDEX', 'topic_id'],
					],
				],
				$this->table_prefix . 'ecal_attendees' => [
					'COLUMNS' => [
						'event_id'       => ['UINT', 0], // composite PK part, FK-by-convention
						'user_id'        => ['UINT', 0], // composite PK part
						'occurrence_ts'  => ['UINT:11', 0], // composite PK part; 0 for non-recurring
						'attend_ts'      => ['UINT:11', 0], // when RSVP'd
					],
					'PRIMARY_KEY' => ['event_id', 'user_id', 'occurrence_ts'],
					'KEYS' => [
						'ecal_att_user' => ['INDEX', 'user_id'],
					],
				],
				$this->table_prefix . 'ecal_outbox' => [
					'COLUMNS' => [
						'outbox_id'      => ['UINT', null, 'auto_increment'],
						'event_id'       => ['UINT', 0],
						'action'         => ['TINT:4', 0], // 0 upsert, 1 delete
						'gcal_id'        => ['VCHAR:255', ''], // snapshot for deletes
						'attempts'       => ['TINT:4', 0],
						'next_retry_ts'  => ['UINT:11', 0], // exponential backoff; dead at 6 attempts
						'last_error'     => ['VCHAR:255', ''], // truncated error for ACP display
					],
					'PRIMARY_KEY' => 'outbox_id',
					'KEYS' => [
						'ecal_ob_retry' => ['INDEX', 'next_retry_ts'],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'ecal_events',
				$this->table_prefix . 'ecal_attendees',
				$this->table_prefix . 'ecal_outbox',
			],
		];
	}
}
