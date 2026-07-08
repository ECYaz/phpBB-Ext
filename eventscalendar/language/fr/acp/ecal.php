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
	'ACP_ECAL_TITLE'                     => 'Calendrier des événements',
	'ACP_ECAL_SETTINGS_EXPLAIN'          => 'Configurez l\'affichage du calendrier sur la page d\'accueil du forum et son comportement de base.',
	'ACP_ECAL_SETTINGS_SAVED'            => 'Paramètres du calendrier des événements enregistrés.',

	'ECAL_INDEX_DISPLAY'                 => 'Affichage sur la page d\'accueil',
	'ECAL_INDEX_DISPLAY_EXPLAIN'         => 'Ce que le calendrier affiche sur la page d\'accueil du forum.',
	'ECAL_INDEX_DISPLAY_OFF'             => 'Désactivé',
	'ECAL_INDEX_DISPLAY_UPCOMING'        => 'Liste des événements à venir',
	'ECAL_INDEX_DISPLAY_MINI'            => 'Mini calendrier',
	'ECAL_INDEX_DISPLAY_BOTH'            => 'Les deux',

	'ECAL_INDEX_UPCOMING_COUNT'          => 'Nombre d\'événements à venir',
	'ECAL_INDEX_UPCOMING_COUNT_EXPLAIN'  => 'Nombre d\'événements à venir affichés sur la page d\'accueil.',

	'ECAL_REMINDER_DAYS'                 => 'Délai de rappel (jours)',
	'ECAL_REMINDER_DAYS_EXPLAIN'         => 'Nombre de jours avant un événement où les participants sont rappelés. 0 désactive les rappels.',

	'ECAL_TOPIC_FORUM_ID'                => 'Forum de discussion',
	'ECAL_TOPIC_FORUM_ID_EXPLAIN'        => 'Forum où sont créés les sujets de discussion des événements. 0 désactive la création de sujets.',
	'ACP_ECAL_TOPIC_FORUM_NONE'          => 'Aucune création de sujet (désactivé)',

	'ECAL_BIRTHDAYS_ENABLE'              => 'Afficher les anniversaires des membres',
	'ECAL_BIRTHDAYS_ENABLE_EXPLAIN'      => 'Affiche les anniversaires des membres avec les événements sur le calendrier.',

	'ACP_ECAL_ICS_TITLE'                 => 'Flux de calendrier (ICS)',
	'ECAL_ICS_ENABLE'                    => 'Activer le flux de calendrier',
	'ECAL_ICS_ENABLE_EXPLAIN'            => 'Une fois activé, les événements et dates spéciales peuvent être suivis depuis une application de calendrier externe via l\'URL du flux ICS ci-dessous.',
	'ECAL_ICS_PUBLIC'                    => 'Flux public',
	'ECAL_ICS_PUBLIC_EXPLAIN'            => 'Une fois activé, l\'URL du flux ne nécessite aucune clé secrète et peut être partagée avec n\'importe qui. Désactivé, l\'URL du flux doit inclure la clé secrète ci-dessous.',
	'ECAL_ICS_TOKEN'                     => 'Clé secrète du flux',
	'ECAL_ICS_TOKEN_EXPLAIN'             => 'La clé secrète utilisée pour autoriser l\'accès à un flux privé (non public). Traitez-la comme un mot de passe — quiconque la possède peut lire le flux du calendrier.',
	'ECAL_ICS_FEED_URL'                  => 'URL du flux',

	'ACP_ECAL_ICS_REGENERATE'            => 'Régénérer la clé secrète',
	'ACP_ECAL_ICS_REGENERATE_EXPLAIN'    => 'Génère une nouvelle clé secrète et invalide immédiatement toute URL de flux privé précédemment émise. Quiconque utilise l\'ancienne URL de flux devra utiliser la nouvelle.',
	'ACP_ECAL_ICS_TOKEN_REGENERATED'     => 'La clé secrète du flux de calendrier a été régénérée.',


	'ACP_ECAL_SPECIAL_EXPLAIN'           => 'Gérez les dates spéciales du forum (jours fériés, anniversaires et autres repères fixes) affichées sur le calendrier de tous les membres.',
	'ACP_ECAL_SPECIAL_SAVED'             => 'La date spéciale a été enregistrée.',
	'ACP_ECAL_SPECIAL_DELETED'           => 'La date spéciale a été supprimée.',
	'ACP_ECAL_SPECIAL_DELETE_CONFIRM'    => 'Voulez-vous vraiment supprimer la date spéciale « %s » ?',

	'ACP_ECAL_SPECIAL_LIST_TITLE'        => 'Dates spéciales',
	'ACP_ECAL_SPECIAL_LIST_EMPTY'        => 'Aucune date spéciale n\'a encore été ajoutée.',
	'ACP_ECAL_SPECIAL_COL_TITLE'         => 'Titre',
	'ACP_ECAL_SPECIAL_COL_DATE'          => 'Date',
	'ACP_ECAL_SPECIAL_COL_ANNUAL'        => 'Annuelle',
	'ACP_ECAL_SPECIAL_COL_COLOR'         => 'Couleur',
	'ACP_ECAL_SPECIAL_COL_ACTIONS'       => 'Actions',

	'ACP_ECAL_SPECIAL_ADD'               => 'Ajouter une date spéciale',
	'ACP_ECAL_SPECIAL_EDIT'              => 'Modifier la date spéciale',
	'ACP_ECAL_SPECIAL_DATE'              => 'Date',
	'ACP_ECAL_SPECIAL_ANNUAL'            => 'Se répète chaque année',
	'ACP_ECAL_SPECIAL_ANNUAL_EXPLAIN'    => 'Une fois activée, cette date revient chaque année au même jour et au même mois.',

	'ACP_ECAL_GOOGLE_EXPLAIN'            => 'Envoie les événements vers un Google Agenda via un compte de service. Les modifications sont mises en file d\'attente et synchronisées en arrière-plan par la tâche cron du forum.',
	'ACP_ECAL_GOOGLE_SAVED'              => 'Paramètres de synchronisation Google enregistrés.',

	'ECAL_GCAL_ENABLE'                   => 'Activer la synchronisation Google Agenda',
	'ECAL_GCAL_ENABLE_EXPLAIN'           => 'Une fois activée, les créations/modifications/suppressions d\'événements sont mises en file d\'attente et envoyées vers le Google Agenda configuré.',

	'ECAL_GCAL_CALENDAR_ID'              => 'ID de l\'agenda',
	'ECAL_GCAL_CALENDAR_ID_EXPLAIN'      => 'L\'identifiant du Google Agenda vers lequel envoyer les événements (disponible dans les paramètres de l\'agenda, par ex. abc123@group.calendar.google.com).',

	'ECAL_GCAL_SA_JSON'                  => 'Clé JSON du compte de service',
	'ECAL_GCAL_SA_JSON_EXPLAIN'          => 'Collez le contenu complet du fichier de clé JSON téléchargé pour le compte de service. La clé enregistrée n\'est plus jamais affichée — laissez ce champ vide pour conserver la clé actuelle, ou collez-en une nouvelle pour la remplacer.',
	'ACP_ECAL_GOOGLE_SA_CONFIGURED'      => 'Configurée ✓',
	'ACP_ECAL_GOOGLE_SA_NOT_CONFIGURED'  => 'Non configurée',
	'ACP_ECAL_GOOGLE_SA_REPLACE_PLACEHOLDER' => 'Laissez vide pour conserver la clé actuelle',

	'ACP_ECAL_GOOGLE_STATUS'             => 'Résultat de la dernière synchronisation',

	'ACP_ECAL_GOOGLE_TEST_CONNECTION'         => 'Tester la connexion',
	'ACP_ECAL_GOOGLE_TEST_CONNECTION_EXPLAIN' => 'Vérifie la clé du compte de service et l\'ID de l\'agenda en récupérant les informations de l\'agenda depuis Google. Ne modifie aucune donnée.',
	'ACP_ECAL_GOOGLE_TEST_OK'                 => 'Connexion réussie — agenda : %s',

	'ACP_ECAL_GOOGLE_RESYNC'             => 'Tout resynchroniser',
	'ACP_ECAL_GOOGLE_RESYNC_EXPLAIN'     => 'Met en file d\'attente tous les événements en cours et à venir (ainsi que tout événement encore récurrent) pour un nouvel envoi. Utilisez ceci après la configuration initiale de la synchronisation, ou après avoir changé d\'agenda cible.',
	'ACP_ECAL_GOOGLE_RESYNC_DONE'        => 'Tous les événements éligibles ont été mis en file d\'attente pour synchronisation.',

	'ACP_ECAL_GOOGLE_QUEUE_TITLE'        => 'File de synchronisation',
	'ACP_ECAL_GOOGLE_QUEUE_EMPTY'        => 'La file de synchronisation est vide.',
	'ACP_ECAL_GOOGLE_QUEUE_EVENT'        => 'Événement',
	'ACP_ECAL_GOOGLE_QUEUE_ACTION'       => 'Action',
	'ACP_ECAL_GOOGLE_QUEUE_ATTEMPTS'     => 'Tentatives',
	'ACP_ECAL_GOOGLE_QUEUE_NEXT_RETRY'   => 'Prochain essai',
	'ACP_ECAL_GOOGLE_QUEUE_LAST_ERROR'   => 'Dernière erreur',
	'ACP_ECAL_GOOGLE_QUEUE_ACTIONS'      => 'Actions',
	'ACP_ECAL_GOOGLE_QUEUE_DEAD'         => 'abandonnée',
	'ACP_ECAL_GOOGLE_QUEUE_DELETED_EVENT' => '(événement supprimé)',
	'ACP_ECAL_GOOGLE_QUEUE_UPDATED'      => 'La file de synchronisation a été mise à jour.',
	'ACP_ECAL_GOOGLE_ACTION_UPSERT'      => 'Créer/mettre à jour',
	'ACP_ECAL_GOOGLE_ACTION_DELETE'      => 'Supprimer',
	'ACP_ECAL_GOOGLE_RETRY'              => 'Réessayer maintenant',
	'ACP_ECAL_GOOGLE_DISCARD'            => 'Ignorer',

	'ACP_ECAL_GOOGLE_SETUP_TITLE'        => 'Guide de configuration',
	'ACP_ECAL_GOOGLE_SETUP_STEP_1'       => 'Dans la console Google Cloud, créez (ou sélectionnez) un projet, activez l\'API Google Calendar, puis créez un compte de service.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_2'       => 'Sur le compte de service, créez une nouvelle clé JSON et téléchargez-la — collez son contenu complet dans le champ « Clé JSON du compte de service » ci-dessus.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_3'       => 'Dans Google Agenda, ouvrez les paramètres de l\'agenda cible et partagez-le avec l\'adresse e-mail du compte de service (le champ « client_email » de la clé JSON), en accordant « Apporter des modifications aux événements ».',
	'ACP_ECAL_GOOGLE_SETUP_STEP_4'       => 'Copiez l\'ID de cet agenda depuis la même page de paramètres dans le champ « ID de l\'agenda » ci-dessus.',
	'ACP_ECAL_GOOGLE_SETUP_STEP_5'       => 'Enregistrez vos paramètres, puis utilisez « Tester la connexion » pour vérifier que tout fonctionne avant d\'activer la synchronisation.',
]);
