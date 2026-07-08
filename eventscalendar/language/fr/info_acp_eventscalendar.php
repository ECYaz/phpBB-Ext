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

// Noms du module et de la catégorie affichés dans le menu de gauche du PCA.
$lang = array_merge($lang, [
	'ACP_EVENTSCALENDAR_TITLE' => 'Calendrier des événements',
	'ACP_ECAL_SETTINGS'        => 'Paramètres du calendrier des événements',
	'ACP_ECAL_GOOGLE'          => 'Synchronisation Google',
	'ACP_ECAL_SPECIAL'         => 'Dates spéciales',
]);
