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
	'ACL_CAT_ECAL'      => 'Calendrier des événements',

	'ACL_U_ECAL_VIEW'   => 'Peut consulter le calendrier des événements',
	'ACL_U_ECAL_POST'   => 'Peut créer des événements (modifier/supprimer les siens)',
	'ACL_U_ECAL_ATTEND' => 'Peut confirmer sa présence aux événements',
	'ACL_M_ECAL_MANAGE' => 'Peut modifier/supprimer tout événement',
	'ACL_A_ECAL_MANAGE' => 'Peut accéder au module ACP du calendrier des événements',
]);
