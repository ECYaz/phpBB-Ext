<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACP_ECAL_TITLE'                     => 'Events Calendar',
	'ACP_ECAL_SETTINGS_EXPLAIN'          => 'Configure how the calendar appears on the board index and its basic behaviour.',
	'ACP_ECAL_SETTINGS_SAVED'            => 'Events Calendar settings saved.',

	'ECAL_INDEX_DISPLAY'                 => 'Board index display',
	'ECAL_INDEX_DISPLAY_EXPLAIN'         => 'What the calendar shows on the board index.',
	'ECAL_INDEX_DISPLAY_OFF'             => 'Off',
	'ECAL_INDEX_DISPLAY_UPCOMING'        => 'Upcoming events list',
	'ECAL_INDEX_DISPLAY_MINI'            => 'Mini calendar',
	'ECAL_INDEX_DISPLAY_BOTH'            => 'Both',

	'ECAL_INDEX_UPCOMING_COUNT'          => 'Upcoming events count',
	'ECAL_INDEX_UPCOMING_COUNT_EXPLAIN'  => 'Number of upcoming events to show on the board index.',

	'ECAL_REMINDER_DAYS'                 => 'Reminder lead time (days)',
	'ECAL_REMINDER_DAYS_EXPLAIN'         => 'How many days before an event attendees are reminded. 0 disables reminders.',

	'ECAL_TOPIC_FORUM_ID'                => 'Discussion forum',
	'ECAL_TOPIC_FORUM_ID_EXPLAIN'        => 'Forum where event discussion topics are created. 0 disables topic creation.',
	'ACP_ECAL_TOPIC_FORUM_NONE'          => 'No topic creation (disabled)',

	'ECAL_BIRTHDAYS_ENABLE'              => 'Show member birthdays',
	'ECAL_BIRTHDAYS_ENABLE_EXPLAIN'      => 'Display member birthdays alongside events on the calendar.',

	'ACP_ECAL_ICS_TITLE'                 => 'Calendar feed (ICS)',
	'ECAL_ICS_ENABLE'                    => 'Enable calendar feed',
	'ECAL_ICS_ENABLE_EXPLAIN'            => 'When enabled, events and special dates can be subscribed to from an external calendar application via the ICS feed URL below.',
	'ECAL_ICS_PUBLIC'                    => 'Public feed',
	'ECAL_ICS_PUBLIC_EXPLAIN'            => 'When enabled, the feed URL requires no secret key and can be shared with anyone. When disabled, the feed URL must include the secret key below.',
	'ECAL_ICS_TOKEN'                     => 'Feed secret key',
	'ECAL_ICS_TOKEN_EXPLAIN'             => 'The secret key used to authorise access to a private (non-public) feed. Treat it like a password — anyone with it can read the calendar feed.',
	'ECAL_ICS_FEED_URL'                  => 'Feed URL',

	'ACP_ECAL_ICS_REGENERATE'            => 'Regenerate secret key',
	'ACP_ECAL_ICS_REGENERATE_EXPLAIN'    => 'Generates a new secret key and immediately invalidates every previously issued private feed URL. Anyone using the old feed URL will need the new one.',
	'ACP_ECAL_ICS_TOKEN_REGENERATED'     => 'The calendar feed secret key has been regenerated.',


	'ACP_ECAL_SPECIAL_EXPLAIN'           => 'Manage board-wide special dates (holidays, anniversaries, and similar fixed markers) shown on the calendar to every member.',
	'ACP_ECAL_SPECIAL_SAVED'             => 'The special date has been saved.',
	'ACP_ECAL_SPECIAL_DELETED'           => 'The special date has been deleted.',
	'ACP_ECAL_SPECIAL_DELETE_CONFIRM'    => 'Are you sure you want to delete the special date "%s"?',

	'ACP_ECAL_SPECIAL_LIST_TITLE'        => 'Special dates',
	'ACP_ECAL_SPECIAL_LIST_EMPTY'        => 'No special dates have been added yet.',
	'ACP_ECAL_SPECIAL_COL_TITLE'         => 'Title',
	'ACP_ECAL_SPECIAL_COL_DATE'          => 'Date',
	'ACP_ECAL_SPECIAL_COL_ANNUAL'        => 'Annual',
	'ACP_ECAL_SPECIAL_COL_COLOR'         => 'Colour',
	'ACP_ECAL_SPECIAL_COL_ACTIONS'       => 'Actions',

	'ACP_ECAL_SPECIAL_ADD'               => 'Add special date',
	'ACP_ECAL_SPECIAL_EDIT'              => 'Edit special date',
	'ACP_ECAL_SPECIAL_DATE'              => 'Date',
	'ACP_ECAL_SPECIAL_ANNUAL'            => 'Repeats annually',
	'ACP_ECAL_SPECIAL_ANNUAL_EXPLAIN'    => 'When enabled, this date recurs every year on the same day and month.',

	'ACP_ECAL_GOOGLE_EXPLAIN'            => 'Push events to a Google Calendar via a service account. Changes are queued and synced in the background by the board cron.',
	'ACP_ECAL_GOOGLE_SAVED'              => 'Google sync settings saved.',

	'ECAL_GCAL_ENABLE'                   => 'Enable Google Calendar sync',
	'ECAL_GCAL_ENABLE_EXPLAIN'           => 'When enabled, event creates/edits/deletes are queued and pushed to the configured Google Calendar.',

	'ECAL_GCAL_CALENDAR_ID'              => 'Calendar ID',
	'ECAL_GCAL_CALENDAR_ID_EXPLAIN'      => 'The Google Calendar ID to push events to (found in the calendar\'s settings, e.g. abc123@group.calendar.google.com).',

	'ECAL_GCAL_SA_JSON'                  => 'Service account JSON key',
	'ECAL_GCAL_SA_JSON_EXPLAIN'          => 'Paste the full JSON key file downloaded for the service account. The stored key is never shown again — leave this field empty to keep the current key, or paste a new one to replace it.',
	'ACP_ECAL_GOOGLE_SA_CONFIGURED'      => 'Configured ✓',
	'ACP_ECAL_GOOGLE_SA_NOT_CONFIGURED'  => 'Not configured',
	'ACP_ECAL_GOOGLE_SA_REPLACE_PLACEHOLDER' => 'Leave empty to keep the current key',

	'ACP_ECAL_GOOGLE_STATUS'             => 'Last sync result',

	'ACP_ECAL_GOOGLE_TEST_CONNECTION'         => 'Test connection',
	'ACP_ECAL_GOOGLE_TEST_CONNECTION_EXPLAIN' => 'Verifies the service account key and calendar ID by fetching the calendar\'s details from Google. Does not change any data.',
	'ACP_ECAL_GOOGLE_TEST_OK'                 => 'Connection successful — calendar: %s',

	'ACP_ECAL_GOOGLE_RESYNC'             => 'Resync all',
	'ACP_ECAL_GOOGLE_RESYNC_EXPLAIN'     => 'Queues every current and future event (and every still-recurring event) to be pushed again. Use this after first configuring sync, or after changing the target calendar.',
	'ACP_ECAL_GOOGLE_RESYNC_DONE'        => 'All eligible events have been queued for sync.',

	'ACP_ECAL_GOOGLE_QUEUE_TITLE'        => 'Sync queue',
	'ACP_ECAL_GOOGLE_QUEUE_EMPTY'        => 'The sync queue is empty.',
	'ACP_ECAL_GOOGLE_QUEUE_EVENT'        => 'Event',
	'ACP_ECAL_GOOGLE_QUEUE_ACTION'       => 'Action',
	'ACP_ECAL_GOOGLE_QUEUE_ATTEMPTS'     => 'Attempts',
	'ACP_ECAL_GOOGLE_QUEUE_NEXT_RETRY'   => 'Next retry',
	'ACP_ECAL_GOOGLE_QUEUE_LAST_ERROR'   => 'Last error',
	'ACP_ECAL_GOOGLE_QUEUE_ACTIONS'      => 'Actions',
	'ACP_ECAL_GOOGLE_QUEUE_DEAD'         => 'dead',
	'ACP_ECAL_GOOGLE_QUEUE_DELETED_EVENT' => '(deleted event)',
	'ACP_ECAL_GOOGLE_QUEUE_UPDATED'      => 'The sync queue has been updated.',
	'ACP_ECAL_GOOGLE_ACTION_UPSERT'      => 'Create/update',
	'ACP_ECAL_GOOGLE_ACTION_DELETE'      => 'Delete',
	'ACP_ECAL_GOOGLE_RETRY'              => 'Retry now',
	'ACP_ECAL_GOOGLE_DISCARD'            => 'Discard',

	'ACP_ECAL_GOOGLE_SETUP_TITLE'        => 'Setup walkthrough',
	'ACP_ECAL_GOOGLE_SETUP_STEP_1'       => 'In the Google Cloud Console, create (or pick) a project, enable the Google Calendar API, then create a Service Account.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_2'       => 'On the service account, create a new JSON key and download it — paste its full contents into the "Service account JSON key" field above.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_3'       => 'In Google Calendar, open the target calendar\'s settings and share it with the service account\'s email address (the "client_email" field in the JSON key), granting "Make changes to events".',
	'ACP_ECAL_GOOGLE_SETUP_STEP_4'       => 'Copy that calendar\'s Calendar ID from the same settings page into the "Calendar ID" field above.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_5'       => 'Save your settings, then use "Test connection" to confirm everything is wired up before enabling sync.',
]);
