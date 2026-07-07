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
	'ACL_CAT_ECAL'      => 'Events Calendar',

	'ACL_U_ECAL_VIEW'   => 'Can view the events calendar',
	'ACL_U_ECAL_POST'   => 'Can create events (edit/delete own)',
	'ACL_U_ECAL_ATTEND' => 'Can RSVP to events',
	'ACL_M_ECAL_MANAGE' => 'Can edit/delete any event',
	'ACL_A_ECAL_MANAGE' => 'Can access the Events Calendar ACP module',
]);
