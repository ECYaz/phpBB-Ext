<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang ?? [], [
	'ACP_PMPURGE_TITLE'    => 'Old PMs Purger',
	'ACP_PMPURGE_SETTINGS' => 'Old PMs Purger',

	'PMPURGE_SETTINGS_EXPLAIN' => 'Removes the private messages of members who have not visited for a long time. Only messages are deleted: member accounts, posts and profiles are never touched. Deletion goes through phpBB’s own routine, so folder counts, unread counters, notifications and message attachments are all kept in step.',
	'PMPURGE_SAVED'            => 'Old PMs Purger settings have been saved.',
	'PMPURGE_NOT_CONFIGURED'   => 'Set an inactivity period of at least one day before running a purge.',
	'PMPURGE_NEVER'            => 'Never',
	'PMPURGE_DAYS'             => 'days',
	'PMPURGE_SECONDS'          => 'seconds',
	'PMPURGE_MEMBERS'          => 'members',

	'PMPURGE_SCHEDULE'          => 'Schedule',
	'PMPURGE_ENABLED'           => 'Purge automatically',
	'PMPURGE_ENABLED_EXPLAIN'   => 'Run a batch whenever the board’s cron fires. Turn this off to leave purging entirely manual.',
	'PMPURGE_DRY_RUN'           => 'Dry run',
	'PMPURGE_DRY_RUN_EXPLAIN'   => 'Report what would be removed and delete nothing. Leave this on until a preview shows the numbers you expect.',
	'PMPURGE_GC'                => 'Run at most once every',
	'PMPURGE_GC_EXPLAIN'        => 'Minimum gap between automatic runs.',
	'PMPURGE_BATCH_USERS'       => 'Members per run',
	'PMPURGE_BATCH_USERS_EXPLAIN' => 'How many members one batch handles. Cron runs inside a page request, so keep this modest and let successive runs work through the member list.',
	'PMPURGE_LAST_GC'           => 'Last automatic run',

	'PMPURGE_SELECTION'                   => 'Whose messages get purged',
	'PMPURGE_SELECTION_EXPLAIN'           => 'These settings pick the members whose private messages are candidates. The members themselves are never deleted.',
	'PMPURGE_INACTIVE'                    => 'Purge the messages of members inactive for',
	'PMPURGE_INACTIVE_DAYS_EXPLAIN'       => 'A member’s messages become candidates once their last visit is older than this. The account itself stays exactly as it is. Choose a rolling period, which keeps working as time passes, or a fixed date for a one off sweep.',
	'PMPURGE_INACTIVE_DAYS'               => 'this many days',
	'PMPURGE_INACTIVE_BEFORE'             => 'or last visited before',

	'PMPURGE_MSG_AGE'                     => 'Which of their messages',
	'PMPURGE_MSG_AGE_EXPLAIN'             => 'Restrict the purge to messages of a certain age. Leave the day count at 0 to take every message a selected member holds, whatever its age. With a date range, either end may be left empty for an open bound.',
	'PMPURGE_PM_AGE_DAYS'                 => 'older than',
	'PMPURGE_MSG_BETWEEN'                 => 'or sent between',
	'PMPURGE_AND'                         => 'and',
	'PMPURGE_DATE_FORMAT'                 => 'YYYY-MM-DD',
	'PMPURGE_DATE_INVALID'                => 'The date “%s” is not a valid YYYY-MM-DD date.',
	'PMPURGE_DATE_REQUIRED'               => 'Enter a date to purge the messages of members who last visited before it, or switch back to a rolling number of days.',
	'PMPURGE_DATE_RANGE_INVALID'          => 'The start of the message date range must fall before its end.',
	'PMPURGE_INCLUDE_NEVER'               => 'Include members who never logged in',
	'PMPURGE_INCLUDE_NEVER_EXPLAIN'       => 'These members have no last visit to measure, so their registration date is used instead. An old board can hold a great many of them.',
	'PMPURGE_SKIP_FOUNDERS'               => 'Never purge founders’ messages',
	'PMPURGE_EXEMPT_GROUPS'               => 'Never purge the messages of these groups',
	'PMPURGE_EXEMPT_GROUPS_EXPLAIN'       => 'Comma separated group IDs, for example the administrator and moderator groups. Leave empty for no exemptions.',
	'PMPURGE_INCLUDE_OUTBOX'              => 'Include the outbox',
	'PMPURGE_INCLUDE_OUTBOX_EXPLAIN'      => 'The outbox holds sent messages nobody has read yet. phpBB blanks the text of such a message when its sender deletes it, so switching this on can empty a message that an active member still has waiting. Off is the safe choice.',

	'PMPURGE_RUN'                => 'Run now',
	'PMPURGE_RUN_EXPLAIN'        => 'Preview counts what the settings above select and changes nothing. Purge now works through the whole member list in batches; on a large board prefer the command line, which has no request timeout over it: <samp>php bin/phpbbcli.php pmpurge:run --all</samp>',
	'PMPURGE_PREVIEW'            => 'Preview',
	'PMPURGE_PURGE_NOW'          => 'Purge now',
	'PMPURGE_RUN_AS_DRY'         => 'Report only, delete nothing',
	'PMPURGE_CONFIRM_RUN'        => 'Are you sure you want to purge the private messages of every member the settings select? This cannot be undone.',

	'PMPURGE_PREVIEW_RESULT'     => 'Preview',
	'PMPURGE_PREVIEW_USERS'      => 'Members whose messages are selected',
	'PMPURGE_PREVIEW_ROWS'       => 'Message copies removed',
	'PMPURGE_PREVIEW_ROWS_EXPLAIN' => 'One copy per participant. Removing a copy frees a row, not the message text.',
	'PMPURGE_PREVIEW_MSGS'       => 'Messages removed entirely',
	'PMPURGE_PREVIEW_MSGS_EXPLAIN' => 'phpBB stores a message body once, however many people it was sent to, and can only drop it when the last participant’s copy goes. This is the number that frees space.',

	'PMPURGE_RUNNING'            => 'Purge in progress',
	'PMPURGE_RUNNING_EXPLAIN'    => 'Processed so far: %1$s members, %2$s message copies, %3$s messages removed entirely. This page continues on its own.',
	'PMPURGE_CONTINUE'           => 'Continue now',
	'PMPURGE_RUN_DONE'           => 'Purge complete.',
	'PMPURGE_RUN_TOTALS'         => '%1$s members processed, %2$s message copies removed, %3$s messages removed entirely.',
	'PMPURGE_DRY_RUN_DONE'       => 'Dry run complete, nothing was deleted.',
	'PMPURGE_DRY_RUN_TOTALS'     => '%1$s members selected, holding %2$s message copies. Use Preview for the number of messages that would be removed entirely.',
]);
