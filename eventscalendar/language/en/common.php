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

$lang = array_merge($lang ?? [], [
	'EVENTSCALENDAR_TITLE' => 'Events Calendar',

	'ECAL_NAV_CALENDAR'      => 'Calendar',
	'ECAL_INVALID_DATE'      => 'The requested calendar date is not valid.',

	'ECAL_PREV'              => 'Previous',
	'ECAL_NEXT'              => 'Next',
	'ECAL_TODAY'             => 'Today',
	'ECAL_YEAR_VIEW'         => 'Year',
	'ECAL_BACK_TO_MONTH'     => 'Back to month',

	'ECAL_SEARCH'            => 'Search events',
	'ECAL_SEARCH_PLACEHOLDER' => 'Search events…',
	'ECAL_SEARCH_FROM'       => 'From',
	'ECAL_SEARCH_TO'         => 'To',
	'ECAL_SEARCH_RESULTS'    => 'Search results',
	'ECAL_NO_SEARCH_RESULTS' => 'No events matched your search.',

	'ECAL_ALL_DAY'           => 'All day',
	'ECAL_NO_EVENTS_TODAY'   => 'No events today.',

	'ECAL_BIRTHDAYS'         => 'Birthdays',

	'ECAL_UPCOMING_EVENTS'    => 'Upcoming Events',
	'ECAL_NO_UPCOMING_EVENTS' => 'No upcoming events.',
	'ECAL_VIEW_CALENDAR'      => 'View calendar',
	'ECAL_ADD_EVENT'          => 'Add event',

	'ECAL_NEW_EVENT'            => 'New event',
	'ECAL_EDIT_EVENT'           => 'Edit event',
	'ECAL_TITLE'                => 'Title',
	'ECAL_DESCRIPTION'          => 'Description',
	'ECAL_START'                => 'Starts',
	'ECAL_END'                  => 'Ends',
	'ECAL_RECURRENCE'           => 'Repeats',
	'ECAL_RECUR_UNTIL'          => 'Repeat until',
	'ECAL_COLOR'                => 'Colour',
	'ECAL_POST_TOPIC'           => 'Post a discussion topic for this event',
	'ECAL_EDIT'                 => 'Edit',
	'ECAL_DELETE'                => 'Delete',
	'ECAL_DELETE_CONFIRM'       => 'Are you sure you want to delete the event "%s"? Its discussion topic (if any) will not be deleted.',
	'ECAL_VIEW_TOPIC'           => 'View discussion topic',
	'ECAL_EVENT_NOT_FOUND'      => 'The requested event does not exist.',
	'ECAL_SPECIAL_NOT_EDITABLE' => 'Special dates can only be managed from the ACP.',

	'ECAL_RECUR_NONE'    => 'Does not repeat',
	'ECAL_RECUR_DAILY'   => 'Daily',
	'ECAL_RECUR_WEEKLY'  => 'Weekly',
	'ECAL_RECUR_MONTHLY' => 'Monthly',
	'ECAL_RECUR_ANNUAL'  => 'Annually',

	'ECAL_COLOR_0' => 'Blue',
	'ECAL_COLOR_1' => 'Green',
	'ECAL_COLOR_2' => 'Red',
	'ECAL_COLOR_3' => 'Purple',
	'ECAL_COLOR_4' => 'Amber',
	'ECAL_COLOR_5' => 'Teal',
	'ECAL_COLOR_6' => 'Magenta',
	'ECAL_COLOR_7' => 'Slate',

	'ECAL_ERR_TITLE_REQUIRED'   => 'Please enter a title for the event.',
	'ECAL_ERR_TITLE_TOO_LONG'   => 'The title must be 255 characters or fewer.',
	'ECAL_ERR_START_INVALID'    => 'The start date/time is not valid.',
	'ECAL_ERR_END_INVALID'      => 'The end date/time is not valid.',
	'ECAL_ERR_END_BEFORE_START' => 'The end date/time must not be before the start.',
	'ECAL_ERR_UNTIL_INVALID'    => 'The "repeat until" date is not valid.',
	'ECAL_ERR_UNTIL_BEFORE_START' => 'The "repeat until" date must not be before the start.',
	'ECAL_ERR_RECUR_DURATION'   => 'A repeating event must be shorter than the time between its occurrences.',

	'ECAL_RSVP_ATTENDEES'             => 'Attendees',
	'ECAL_RSVP_UPCOMING_OCCURRENCES'  => 'Upcoming occurrences',
	'ECAL_RSVP_ATTEND'                => 'Attend',
	'ECAL_RSVP_UNATTEND'              => 'Cancel RSVP',
	'ECAL_RSVP_NO_ATTENDEES'          => 'No one has RSVP\'d yet.',
	'ECAL_ERR_INVALID_OCCURRENCE'     => 'That occurrence does not exist for this event.',

	'NOTIFICATION_GROUP_ECAL'       => 'Calendar',
	'NOTIFICATION_TYPE_ECAL_REMINDER' => 'Upcoming calendar events',
	'NOTIFICATION_ECAL_REMINDER'    => 'Reminder: “%s” is coming up',
	'NOTIFICATION_ECAL_UPDATED'     => '“%s” was updated',
	'NOTIFICATION_ECAL_CANCELLED'   => '“%s” was cancelled',
]);
