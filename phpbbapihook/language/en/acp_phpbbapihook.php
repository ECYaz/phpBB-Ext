<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
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
	// Module title / navigation
	'ACP_PHPBBAPIHOOK_TITLE'			=> 'phpBB API Hook',
	'ACP_PHPBBAPIHOOK_MANAGE'			=> 'Manage API Keys',

	// Page headings
	'ACP_PHPBBAPIHOOK_KEYS_TITLE'		=> 'API Credentials',
	'ACP_PHPBBAPIHOOK_ADD_TITLE'		=> 'Add API Credential',
	'ACP_PHPBBAPIHOOK_EDIT_TITLE'		=> 'Edit API Credential',
	'ACP_PHPBBAPIHOOK_LOG_TITLE'		=> 'Audit Log',

	// Table column headers
	'ACP_PHPBBAPIHOOK_COL_ID'			=> 'ID',
	'ACP_PHPBBAPIHOOK_COL_NAME'			=> 'Name',
	'ACP_PHPBBAPIHOOK_COL_PREFIX'		=> 'Token Prefix',
	'ACP_PHPBBAPIHOOK_COL_USER'			=> 'Acts As User',
	'ACP_PHPBBAPIHOOK_COL_STATUS'		=> 'Status',
	'ACP_PHPBBAPIHOOK_COL_RATE'			=> 'Rate Limit',
	'ACP_PHPBBAPIHOOK_COL_EXPIRES'		=> 'Expires',
	'ACP_PHPBBAPIHOOK_COL_LAST_USED'	=> 'Last Used',
	'ACP_PHPBBAPIHOOK_COL_ACTIONS'		=> 'Actions',

	// Status labels
	'ACP_PHPBBAPIHOOK_ENABLED'			=> 'Enabled',
	'ACP_PHPBBAPIHOOK_DISABLED'			=> 'Disabled',
	'ACP_PHPBBAPIHOOK_READ_ONLY'		=> 'Read-only',
	'ACP_PHPBBAPIHOOK_READ_WRITE'		=> 'Read/Write',
	'ACP_PHPBBAPIHOOK_NEVER'			=> 'Never',
	'ACP_PHPBBAPIHOOK_UNLIMITED'		=> 'Unlimited',
	'ACP_PHPBBAPIHOOK_UNKNOWN_USER'		=> '(unknown)',

	// Field labels
	'ACP_PHPBBAPIHOOK_KEY_NAME'			=> 'Credential Name',
	'ACP_PHPBBAPIHOOK_KEY_NAME_EXPLAIN'	=> 'A human-readable label for this API credential.',
	'ACP_PHPBBAPIHOOK_USER_ID'			=> 'User ID',
	'ACP_PHPBBAPIHOOK_USER_ID_EXPLAIN'	=> 'The phpBB user ID whose permissions this credential acts with. It must be an active member account; guests and bots cannot be used.',
	'ACP_PHPBBAPIHOOK_FORUM_IDS'		=> 'Allowed Forum IDs',
	'ACP_PHPBBAPIHOOK_FORUM_IDS_EXPLAIN'	=> 'Comma-separated forum IDs this credential may access. Leave blank to allow all forums.',
	'ACP_PHPBBAPIHOOK_IP_ALLOWLIST'		=> 'IP Allowlist',
	'ACP_PHPBBAPIHOOK_IP_ALLOWLIST_EXPLAIN'	=> 'Comma or space-separated list of allowed client IPs. Leave blank to allow any IP.',
	'ACP_PHPBBAPIHOOK_RATE_LIMIT'		=> 'Rate Limit',
	'ACP_PHPBBAPIHOOK_RATE_LIMIT_EXPLAIN'	=> 'Maximum API requests per rate window (one hour unless changed in the settings below). Set to 0 for unlimited.',
	'ACP_PHPBBAPIHOOK_EXPIRATION'		=> 'Expiration Date',
	'ACP_PHPBBAPIHOOK_EXPIRATION_EXPLAIN'	=> 'Date this credential expires in YYYY-MM-DD format. Leave blank for no expiration.',
	'ACP_PHPBBAPIHOOK_READ_ONLY_FIELD'	=> 'Read-only',
	'ACP_PHPBBAPIHOOK_READ_ONLY_EXPLAIN'	=> 'If checked, this credential may only perform read operations (GET requests).',
	'ACP_PHPBBAPIHOOK_EXT_ACCESS'		=> 'Extension endpoints',
	'ACP_PHPBBAPIHOOK_EXT_ACCESS_EXPLAIN'	=> 'Other extensions can offer their own endpoints through this API. Enter the extensions this credential may use as vendor/package names separated by commas, or a single * for every extension. Leave blank to allow none. The permissions of the linked user still apply to every request.',
	'ACP_PHPBBAPIHOOK_EXT_ACCESS_AVAILABLE'	=> 'Extensions offering endpoints on this board: %s',
	'ACP_PHPBBAPIHOOK_EXT_ACCESS_NONE'	=> 'No installed extension offers endpoints at the moment.',
	'ACP_PHPBBAPIHOOK_IS_ENABLED'		=> 'Enabled',
	'ACP_PHPBBAPIHOOK_IS_ENABLED_EXPLAIN'	=> 'Uncheck to temporarily disable this credential without deleting it.',

	// Log column headers
	'ACP_PHPBBAPIHOOK_LOG_COL_TIME'		=> 'Time',
	'ACP_PHPBBAPIHOOK_LOG_COL_IP'		=> 'IP',
	'ACP_PHPBBAPIHOOK_LOG_COL_METHOD'	=> 'Method',
	'ACP_PHPBBAPIHOOK_LOG_COL_ROUTE'	=> 'Route',
	'ACP_PHPBBAPIHOOK_LOG_COL_ACTION'	=> 'Action',
	'ACP_PHPBBAPIHOOK_LOG_COL_STATUS'	=> 'Status',
	'ACP_PHPBBAPIHOOK_LOG_COL_DETAIL'	=> 'Detail',
	'ACP_PHPBBAPIHOOK_LOG_EMPTY'		=> 'No log entries found for this credential.',

	// Action links
	'ACP_PHPBBAPIHOOK_ACTION_EDIT'		=> 'Edit',
	'ACP_PHPBBAPIHOOK_ACTION_ENABLE'	=> 'Enable',
	'ACP_PHPBBAPIHOOK_ACTION_DISABLE'	=> 'Disable',
	'ACP_PHPBBAPIHOOK_ACTION_DELETE'	=> 'Delete',
	'ACP_PHPBBAPIHOOK_ACTION_VIEWLOG'	=> 'View Log',
	'ACP_PHPBBAPIHOOK_BACK_TO_LIST'		=> 'Back to credentials list',

	// New token notice (shown once after creation)
	'ACP_PHPBBAPIHOOK_TOKEN_NOTICE'		=> 'API Credential Created — Save Your Token Now',
	'ACP_PHPBBAPIHOOK_TOKEN_WARNING'	=> '<strong>This is the only time this token will be displayed.</strong> Copy it now and store it securely. It cannot be recovered after you leave this page.',
	'ACP_PHPBBAPIHOOK_TOKEN_LABEL'		=> 'API Token',

	// Success / error messages
	'ACP_PHPBBAPIHOOK_UPDATED'			=> 'API credential updated successfully.',
	'ACP_PHPBBAPIHOOK_DELETED'			=> 'API credential deleted successfully.',
	'ACP_PHPBBAPIHOOK_NOT_FOUND'		=> 'The requested API credential could not be found.',
	'ACP_PHPBBAPIHOOK_INVALID_EXT_ACCESS'	=> 'These entries of the extension endpoints field are not valid extension names: %s<br />Use vendor/package names (letters, digits and underscores), or a single * for every extension.',
	'ACP_PHPBBAPIHOOK_INVALID_EXPIRATION'	=> 'The expiration date could not be read. Enter it as YYYY-MM-DD, for example 2027-12-31, or leave the field blank for no expiration.',
	'ACP_PHPBBAPIHOOK_INVALID_USER'		=> 'The specified User ID does not exist. Please enter a valid phpBB user ID.',
	'ACP_PHPBBAPIHOOK_CONFIRM_DELETE'	=> 'Are you sure you want to permanently delete this API credential? Any applications using it will immediately lose access.',
	'ACP_PHPBBAPIHOOK_LOG_NO_KEY'		=> 'Requests without a valid token',
	'ACP_PHPBBAPIHOOK_LOG_NO_KEY_LINK'	=> 'View the log of requests that came without a valid token',

	// Settings
	'ACP_PHPBBAPIHOOK_SETTINGS_TITLE'	=> 'API settings',
	'ACP_PHPBBAPIHOOK_SETTINGS_SAVED'	=> 'API settings saved.',
	'ACP_PHPBBAPIHOOK_MASTER_ENABLED'	=> 'API enabled',
	'ACP_PHPBBAPIHOOK_MASTER_ENABLED_EXPLAIN'	=> 'Uncheck to switch the whole API off. Every request is then answered with api_disabled.',
	'ACP_PHPBBAPIHOOK_REQUIRE_HTTPS'	=> 'Require HTTPS',
	'ACP_PHPBBAPIHOOK_REQUIRE_HTTPS_EXPLAIN'	=> 'Refuse requests that do not come over HTTPS. Switch this off only on a test board; over plain HTTP a token can be read by anyone on the network.',
	'ACP_PHPBBAPIHOOK_RATE_WINDOW'		=> 'Rate window in seconds',
	'ACP_PHPBBAPIHOOK_RATE_WINDOW_EXPLAIN'	=> 'The period the rate limit of a credential is counted over. 3600 is one hour; the minimum is 60.',
	'ACP_PHPBBAPIHOOK_LOG_DAYS'			=> 'Keep the audit log for (days)',
	'ACP_PHPBBAPIHOOK_LOG_DAYS_EXPLAIN'	=> 'Older log entries are deleted once a day. Set to 0 to keep them forever.',
	'ACP_PHPBBAPIHOOK_NO_KEYS'			=> 'No API credentials have been created yet.',
]);
