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

$lang = array_merge($lang ?? [], [
	'EVENTSCALENDAR_TITLE' => 'Calendrier des événements',

	'ECAL_NAV_CALENDAR'      => 'Calendrier',
	'ECAL_INVALID_DATE'      => 'La date de calendrier demandée n’est pas valide.',

	'ECAL_PREV'              => 'Précédent',
	'ECAL_NEXT'              => 'Suivant',
	'ECAL_TODAY'             => 'Aujourd’hui',
	'ECAL_YEAR_VIEW'         => 'Année',
	'ECAL_BACK_TO_MONTH'     => 'Retour au mois',

	'ECAL_SEARCH'            => 'Rechercher des événements',
	'ECAL_SEARCH_PLACEHOLDER' => 'Rechercher des événements…',
	'ECAL_SEARCH_FROM'       => 'Du',
	'ECAL_SEARCH_TO'         => 'Au',
	'ECAL_SEARCH_RESULTS'    => 'Résultats de recherche',
	'ECAL_NO_SEARCH_RESULTS' => 'Aucun événement ne correspond à votre recherche.',

	'ECAL_ALL_DAY'           => 'Toute la journée',
	'ECAL_NO_EVENTS_TODAY'   => 'Aucun événement aujourd’hui.',

	'ECAL_BIRTHDAYS'         => 'Anniversaires',

	'ECAL_UPCOMING_EVENTS'    => 'Événements à venir',
	'ECAL_NO_UPCOMING_EVENTS' => 'Aucun événement à venir.',
	'ECAL_VIEW_CALENDAR'      => 'Voir le calendrier',
	'ECAL_ADD_EVENT'          => 'Ajouter un événement',

	'ECAL_NEW_EVENT'            => 'Nouvel événement',
	'ECAL_EDIT_EVENT'           => 'Modifier l’événement',
	'ECAL_TITLE'                => 'Titre',
	'ECAL_DESCRIPTION'          => 'Description',
	'ECAL_START'                => 'Début',
	'ECAL_END'                  => 'Fin',
	'ECAL_RECURRENCE'           => 'Répétition',
	'ECAL_RECUR_UNTIL'          => 'Répéter jusqu’au',
	'ECAL_COLOR'                => 'Couleur',
	'ECAL_POST_TOPIC'           => 'Publier un sujet de discussion pour cet événement',
	'ECAL_EDIT'                 => 'Modifier',
	'ECAL_DELETE'                => 'Supprimer',
	'ECAL_DELETE_CONFIRM'       => 'Voulez-vous vraiment supprimer l’événement « %s » ? Son sujet de discussion (le cas échéant) ne sera pas supprimé.',
	'ECAL_VIEW_TOPIC'           => 'Voir le sujet de discussion',
	'ECAL_EVENT_NOT_FOUND'      => 'L’événement demandé n’existe pas.',
	'ECAL_SPECIAL_NOT_EDITABLE' => 'Les dates spéciales ne peuvent être gérées que depuis l’ACP.',

	'ECAL_RECUR_NONE'    => 'Ne se répète pas',
	'ECAL_RECUR_DAILY'   => 'Quotidienne',
	'ECAL_RECUR_WEEKLY'  => 'Hebdomadaire',
	'ECAL_RECUR_MONTHLY' => 'Mensuelle',
	'ECAL_RECUR_ANNUAL'  => 'Annuelle',

	'ECAL_COLOR_0' => 'Bleu',
	'ECAL_COLOR_1' => 'Vert',
	'ECAL_COLOR_2' => 'Rouge',
	'ECAL_COLOR_3' => 'Violet',
	'ECAL_COLOR_4' => 'Ambre',
	'ECAL_COLOR_5' => 'Sarcelle',
	'ECAL_COLOR_6' => 'Magenta',
	'ECAL_COLOR_7' => 'Ardoise',

	'ECAL_ERR_TITLE_REQUIRED'   => 'Veuillez saisir un titre pour l’événement.',
	'ECAL_ERR_TITLE_TOO_LONG'   => 'Le titre doit comporter 255 caractères maximum.',
	'ECAL_ERR_START_INVALID'    => 'La date/heure de début n’est pas valide.',
	'ECAL_ERR_END_INVALID'      => 'La date/heure de fin n’est pas valide.',
	'ECAL_ERR_END_BEFORE_START' => 'La date/heure de fin ne doit pas précéder le début.',
	'ECAL_ERR_UNTIL_INVALID'    => 'La date « répéter jusqu’au » n’est pas valide.',
	'ECAL_ERR_UNTIL_BEFORE_START' => 'La date « répéter jusqu’au » ne doit pas précéder le début.',
	'ECAL_ERR_RECUR_DURATION'   => 'Un événement récurrent doit être plus court que l’intervalle entre ses occurrences.',

	'ECAL_RSVP_ATTENDEES'             => 'Participants',
	'ECAL_RSVP_UPCOMING_OCCURRENCES'  => 'Prochaines occurrences',
	'ECAL_RSVP_ATTEND'                => 'Participer',
	'ECAL_RSVP_UNATTEND'              => 'Annuler ma participation',
	'ECAL_RSVP_NO_ATTENDEES'          => 'Personne n’a encore confirmé sa participation.',
	'ECAL_ERR_INVALID_OCCURRENCE'     => 'Cette occurrence n’existe pas pour cet événement.',

	'NOTIFICATION_GROUP_ECAL'       => 'Calendrier',
	'NOTIFICATION_TYPE_ECAL_REMINDER' => 'Événements à venir',
	'NOTIFICATION_ECAL_REMINDER'    => 'Rappel : « %s » approche',
	'NOTIFICATION_ECAL_UPDATED'     => '« %s » a été modifié',
	'NOTIFICATION_ECAL_CANCELLED'   => '« %s » a été annulé',
]);
