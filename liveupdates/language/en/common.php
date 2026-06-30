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
	'LIVEUPDATES_NEW_REPLY'          => '%d new reply — click to load',
	'LIVEUPDATES_NEW_REPLIES'        => '%d new replies — click to load',
	'LIVEUPDATES_NEW_TOPIC'          => '%d new or updated topic — refresh',
	'LIVEUPDATES_NEW_TOPICS'         => '%d new or updated topics — refresh',
	'ACP_LIVEUPDATES_TITLE'          => 'Live Updates',
	'ACP_LIVEUPDATES_SETTINGS'       => 'Settings',
	'LOG_LIVEUPDATES_SETTINGS'       => '<strong>Live Updates settings updated</strong>',
]);
