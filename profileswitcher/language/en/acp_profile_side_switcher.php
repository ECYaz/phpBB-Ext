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
	'ACP_PSS_RIBBON_POSITION'		=> 'Online ribbon position',
	'ACP_PSS_RIBBON_RIGHT'			=> 'Right (original — language image, top-right corner)',
	'ACP_PSS_RIBBON_LEFT_OPT'		=> 'Left, mirrored (CSS ribbon, top-left corner)',
	'ACP_PSS_RIBBON_EXPLAIN'		=> 'When the profile column is on the left, choose how the "Online" ribbon appears. <strong>Right</strong> keeps the original per-language GIF banner in the top-right corner of the profile cell. <strong>Left, mirrored</strong> uses a CSS-drawn banner in the top-left corner so the ribbon does not overlap profile content.',
	'ACP_PSS_SETTINGS_SAVED'		=> 'Profile Side Switcher settings saved successfully.',
]);
