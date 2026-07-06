<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
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
	'ACP_TREASUREHUNT_TITLE'    => 'Treasure Hunt',
	'ACP_TREASUREHUNT_SETTINGS' => 'Settings',
	'ACP_TREASUREHUNT_ITEMS'    => 'Item Catalogue',
	'ACP_TH_TITLE'              => 'Treasure Hunt',
	'ACP_TH_BADGES'             => 'Badge Catalog',
	'LOG_TREASUREHUNT_SETTINGS'     => '<strong>Treasure Hunt settings updated</strong>',
	'LOG_TREASUREHUNT_ITEM_ADD'     => '<strong>Treasure Hunt: item added</strong> &#187; %s',
	'LOG_TREASUREHUNT_ITEM_EDIT'    => '<strong>Treasure Hunt: item edited</strong> &#187; %s',
	'LOG_TREASUREHUNT_ITEM_DELETED' => '<strong>Treasure Hunt: item deleted</strong> (ID %d)',
]);
