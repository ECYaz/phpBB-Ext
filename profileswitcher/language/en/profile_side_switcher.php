<?php
/**
*
* profileSwitcher [English]
*
* @package language profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'PSS_LEFT'			=> 'Postprofile on the left',
	'PSS_RIGHT'			=> 'Postprofile on the right',
	'PSS_VIEW_LEFT'		=> 'Display postprofile on the left',
));
