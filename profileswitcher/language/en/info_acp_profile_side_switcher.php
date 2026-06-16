<?php
/**
*
* @package profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
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
	'ACP_PROFILE_SIDE_SWITCHER_TITLE'	=> 'Profile Side Switcher',
	'ACP_PROFILE_SIDE_SWITCHER'			=> 'Profile Side Switcher Settings',
]);
