<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
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

// This extension operates silently (an event listener and a one-time migration);
// it has no user-facing strings of its own. The English language directory is kept
// because the phpBB Extensions validation policy requires every extension to ship
// a language/en/ folder. The displayed name comes from composer.json's display-name.
$lang = array_merge($lang, [
]);
