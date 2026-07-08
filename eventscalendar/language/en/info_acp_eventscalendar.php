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

// Module and category names shown in the ACP left-hand menu.
// Files named info_acp_*.php are loaded automatically for every ACP page
// (includes/functions_module.php::add_mod_info()), so these keys are always
// available — including on the module pages themselves.
$lang = array_merge($lang, [
	'ACP_EVENTSCALENDAR_TITLE' => 'Events Calendar',
	'ACP_ECAL_SETTINGS'        => 'Events Calendar settings',
	'ACP_ECAL_GOOGLE'          => 'Google sync',
	'ACP_ECAL_SPECIAL'         => 'Special dates',
]);
