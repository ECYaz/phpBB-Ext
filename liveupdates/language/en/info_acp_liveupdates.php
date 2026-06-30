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
	'LIVEUPDATES_SAVED'                     => 'Live Updates settings have been saved.',
	'LIVEUPDATES_SETTINGS_EXPLAIN'          => 'Control how the board polls for new content and which surfaces update live. Polling is the only cost; the adaptive client backs off when a tab is hidden or idle.',
	'LIVEUPDATES_GENERAL'                   => 'General',
	'LIVEUPDATES_ENABLED'                   => 'Enable Live Updates',
	'LIVEUPDATES_POSTURE'                   => 'Polling posture',
	'LIVEUPDATES_POSTURE_EXPLAIN'           => 'Preset focused-tab polling speed for logged-in users. All presets back off when the tab is hidden or idle.',
	'LIVEUPDATES_POSTURE_BALANCED'          => 'Balanced (≈10s)',
	'LIVEUPDATES_POSTURE_SNAPPY'            => 'Snappy (≈5s)',
	'LIVEUPDATES_POSTURE_CONSERVATIVE'      => 'Conservative (≈25s)',
	'LIVEUPDATES_INTERVAL_OVERRIDE'         => 'Custom interval override',
	'LIVEUPDATES_INTERVAL_OVERRIDE_EXPLAIN' => 'If greater than zero, overrides the posture preset for logged-in users.',
	'LIVEUPDATES_MIN_INTERVAL'              => 'Minimum interval',
	'LIVEUPDATES_MIN_INTERVAL_EXPLAIN'      => 'Server-enforced floor. The poll endpoint will not serve a given session faster than this.',
	'LIVEUPDATES_SECONDS'                   => 'seconds',
	'LIVEUPDATES_GUESTS'                    => 'Guests',
	'LIVEUPDATES_GUEST_ENABLED'             => 'Poll for guests',
	'LIVEUPDATES_GUEST_INTERVAL'            => 'Guest interval',
	'LIVEUPDATES_SURFACES'                  => 'Surfaces',
	'LIVEUPDATES_SURFACE_TOPIC'             => 'Live posts in topics',
	'LIVEUPDATES_SURFACE_NOTIFY'            => 'Live notification counter',
	'LIVEUPDATES_SURFACE_INDEX'             => 'Live board / forum index updates',
	'LIVEUPDATES_SURFACE_PM'               => 'Live private-message counter',
	'LIVEUPDATES_SURFACE_ONLINE'           => 'Live "who is online" count (board index)',
	'LIVEUPDATES_SURFACE_STATS'            => 'Live board statistics (board index)',
]);
