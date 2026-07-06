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

	// Settings page
	'ACP_TREASUREHUNT_SETTINGS_EXPLAIN' => 'Configure global Treasure Hunt behaviour. Enable the extension, tune drop odds, cooldown, spawn presentation, and access controls.',
	'TREASUREHUNT_SETTINGS_SAVED'       => 'Treasure Hunt settings saved.',

	'TREASUREHUNT_GROUP_GENERAL'  => 'General',
	'TREASUREHUNT_ENABLE'         => 'Enable Treasure Hunt',
	'TREASUREHUNT_ENABLE_EXPLAIN' => 'Master switch. When off, no treasures spawn and the collect endpoint rejects all requests.',

	'TREASUREHUNT_DROP_RATE'         => 'Drop rate',
	'TREASUREHUNT_DROP_RATE_EXPLAIN' => 'Chance that a treasure spawns on an eligible page load, expressed as tenths of a percent (0–1000). 50 = 5 %. 0 disables spawning entirely.',

	'TREASUREHUNT_COOLDOWN'         => 'Cooldown between spawns',
	'TREASUREHUNT_COOLDOWN_EXPLAIN' => 'Minimum time between two consecutive spawns for the same member, in seconds. 0 = no cooldown (not recommended). Maximum 86 400 (24 hours).',

	'TREASUREHUNT_GROUP_SPAWN'         => 'Spawn presentation',
	'TREASUREHUNT_SPAWN_STYLE'         => 'Spawn style',
	'TREASUREHUNT_SPAWN_STYLE_EXPLAIN' => 'How the treasure is displayed when it spawns.',
	'TREASUREHUNT_SPAWN_STYLE_MODAL'   => 'Modal (centre-screen pop-up, default)',
	'TREASUREHUNT_SPAWN_STYLE_ICON'    => 'Icon (hidden on the page — member must find and click it)',
	'TREASUREHUNT_SPAWN_STYLE_HYBRID'  => 'Hybrid (hidden icon → opens modal on click)',

	'TREASUREHUNT_SPAWN_EXPIRY'         => 'Spawn expiry window',
	'TREASUREHUNT_SPAWN_EXPIRY_EXPLAIN' => 'How long (seconds) a spawned treasure remains collectable. After this window the token expires and cannot be collected. Minimum 10, maximum 3600.',

	'TREASUREHUNT_GROUP_ACCESS'        => 'Access &amp; scope',
	'TREASUREHUNT_FORUM_SCOPE'         => 'Forum scope',
	'TREASUREHUNT_FORUM_SCOPE_EXPLAIN' => 'Enter <strong>all</strong> to allow spawns in every forum, or a comma-separated list of forum IDs (e.g. <em>2,5,10</em>). Spawns only happen on topic/forum pages in scope.',

	'TREASUREHUNT_PLAY_GROUPS'         => 'Groups allowed to play',
	'TREASUREHUNT_PLAY_GROUPS_EXPLAIN' => 'Enter <strong>all</strong> to allow any group with the <em>u_treasurehunt_play</em> permission, or a comma-separated list of group IDs to restrict further.',

	'TREASUREHUNT_POSTBIT_CAP'         => 'Postbit badge cap',
	'TREASUREHUNT_POSTBIT_CAP_EXPLAIN' => 'Maximum number of badges shown in the postbit (mini-profile beside each post). 0 hides badges from postbit. Maximum 20.',

	// Note: TREASUREHUNT_RARITY_1..5 are defined in common.php (loaded on every page
	// via the event listener's load_language_on_setup handler), so they are available
	// here without re-definition.

	// Items page strings
	'ACP_TREASUREHUNT_ITEMS_EXPLAIN' => 'Manage the collectable item catalogue. Drop weights are relative: an item with weight 10 drops 10× more often than one with weight 1.',

	// Items catalogue
	'TREASUREHUNT_ADD_ITEM'             => 'Add item',
	'TREASUREHUNT_EDIT_ITEM'            => 'Edit item',
	'TREASUREHUNT_ITEM_SAVED'           => 'Item saved successfully.',
	'TREASUREHUNT_ITEM_DELETED'         => 'Item deleted.',
	'TREASUREHUNT_CONFIRM_DELETE'       => 'Are you sure you want to delete this item? Members who have already collected it keep their find records.',
	'TREASUREHUNT_NO_ITEMS'             => 'No items in the catalogue yet. Click "Add item" to create one.',
	'NO_ITEM'                           => 'Item not found.',

	'TREASUREHUNT_ITEM_NAME'            => 'Item name',
	'TREASUREHUNT_ITEM_NAME_EXPLAIN'    => 'Display name shown to members when the treasure spawns. Max 255 characters.',
	'TREASUREHUNT_ITEM_IMAGE'           => 'Image filename or URL',
	'TREASUREHUNT_ITEM_IMAGE_EXPLAIN'   => 'Either a bare filename under <code>ext/ecyaz/treasurehunt/styles/all/theme/images/</code> (e.g. <code>item_rare.png</code>) — only alphanumeric characters, hyphens, underscores, and .png/.jpg/.jpeg/.gif/.webp extensions accepted — or a full <code>https://…</code> URL ending in a supported image extension. Maximum 255 characters in either case.',
	'TREASUREHUNT_RARITY'               => 'Rarity',
	'TREASUREHUNT_RARITY_EXPLAIN'       => 'Rarity tier 1 (Common) through 5 (Legendary). Affects display colour and is available as a feat condition.',
	'TREASUREHUNT_POINTS'               => 'Points',
	'TREASUREHUNT_POINTS_EXPLAIN'       => 'Points awarded when a member collects this item. Used for leaderboard ranking and badge milestones.',
	'TREASUREHUNT_DROP_WEIGHT'          => 'Drop weight',
	'TREASUREHUNT_DROP_WEIGHT_EXPLAIN'  => 'Relative likelihood this item is chosen when a treasure spawns. Higher = more common within the enabled pool. Minimum 1.',
	'TREASUREHUNT_ITEM_ENABLED'         => 'Enabled',
	'TREASUREHUNT_ITEM_ENABLED_EXPLAIN' => 'Disabled items are never chosen for spawns but existing find records are preserved.',

	'TREASUREHUNT_ERROR_NAME_EMPTY'        => 'Item name cannot be empty.',
	'TREASUREHUNT_ERROR_NAME_LONG'         => 'Item name must not exceed 255 characters.',
	'TREASUREHUNT_ERROR_IMAGE_EMPTY'       => 'Image filename or URL cannot be empty.',
	'TREASUREHUNT_ERROR_IMAGE_LONG'        => 'Image filename or URL must not exceed 255 characters.',
	'TREASUREHUNT_ERROR_IMAGE_INVALID'     => 'Image filename must contain only alphanumeric characters, hyphens, underscores, and end with .png, .jpg, .jpeg, .gif, or .webp.',
	'TREASUREHUNT_ERROR_IMAGE_URL_INVALID' => 'Image URL must be a valid http or https URL whose path ends in .png, .jpg, .jpeg, .gif, or .webp.',
	'TREASUREHUNT_ERROR_ITEM_GONE'         => 'Item no longer exists — it may have been deleted by another administrator.',
	'TREASUREHUNT_ERROR_RARITY'            => 'Rarity must be between 1 and 5.',
	'TREASUREHUNT_ERROR_POINTS'            => 'Points must be between 0 and 99 999.',
	'TREASUREHUNT_ERROR_WEIGHT'            => 'Drop weight must be between 1 and 99 999.',

]);
