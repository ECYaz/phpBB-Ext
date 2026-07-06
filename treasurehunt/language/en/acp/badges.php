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

	// Page header / explain
	'ACP_TH_BADGES_EXPLAIN' => 'Manage badge definitions. Badges are awarded automatically when a member meets the condition (points threshold or achievement feat).',

	// Form labels
	'ACP_TH_BADGE_NAME'         => 'Badge name',
	'ACP_TH_BADGE_NAME_EXPLAIN' => 'Display name for this badge. Max 255 characters.',

	'ACP_TH_BADGE_IMAGE'         => 'Image filename or URL',
	'ACP_TH_BADGE_IMAGE_EXPLAIN' => 'Either a bare filename under <code>ext/ecyaz/treasurehunt/styles/all/theme/images/</code> (e.g. <code>badge_first_find.png</code>) — only alphanumeric characters, hyphens, underscores, and .png/.jpg/.jpeg/.gif/.webp extensions accepted — or a full <code>https://…</code> URL ending in a supported image extension. Maximum 255 characters.',

	'ACP_TH_CONDITION_TYPE'         => 'Condition type',
	'ACP_TH_CONDITION_TYPE_EXPLAIN' => 'Choose whether the badge is awarded for accumulating a points threshold or achieving a specific feat.',
	'ACP_TH_COND_POINTS'            => 'Points milestone',
	'ACP_TH_COND_FEAT'              => 'Achievement feat',

	'ACP_TH_POINTS_THRESHOLD_LABEL'   => 'Points threshold',
	'ACP_TH_POINTS_THRESHOLD_EXPLAIN' => 'Minimum total points a member must have earned to receive this badge.',
	'ACP_TH_POINTS_THRESHOLD'         => '%d pts',

	'ACP_TH_FEAT_LABEL'   => 'Feat',
	'ACP_TH_FEAT_EXPLAIN' => 'The specific achievement that triggers this badge award.',

	'ACP_TH_BADGE_ENABLED'         => 'Enabled',
	'ACP_TH_BADGE_ENABLED_EXPLAIN' => 'Disabled badges are not awarded to members. Existing awards are preserved.',

	// Add / edit form headings
	'ACP_TH_BADGE_ADD'  => 'Add badge',
	'ACP_TH_BADGE_EDIT' => 'Edit badge',

	// Success / info messages
	'ACP_TH_BADGE_ADDED'   => 'Badge added successfully.',
	'ACP_TH_BADGE_SAVED'   => 'Badge saved successfully.',
	'ACP_TH_BADGE_DELETED' => 'Badge deleted.',
	'ACP_TH_NO_BADGES'     => 'No badges defined yet. Click "Add badge" to create one.',

	// Confirm delete
	'ACP_TH_BADGE_CONFIRM_DELETE' => 'Are you sure you want to delete this badge? Members who have already earned it keep their award records.',

	// Table column heading
	'ACP_TH_CONDITION' => 'Condition',

	// Validation errors
	'ACP_TH_BADGE_NAME_EMPTY'        => 'Badge name cannot be empty.',
	'ACP_TH_BADGE_NAME_LONG'         => 'Badge name must not exceed 255 characters.',
	'ACP_TH_BADGE_IMAGE_EMPTY'       => 'Image filename or URL cannot be empty.',
	'ACP_TH_BADGE_IMAGE_LONG'        => 'Image filename or URL must not exceed 255 characters.',
	'ACP_TH_BADGE_IMAGE_INVALID'     => 'Image filename must contain only alphanumeric characters, hyphens, underscores, and end with .png, .jpg, .jpeg, .gif, or .webp.',
	'ACP_TH_BADGE_IMAGE_URL_INVALID' => 'Image URL must be a valid http or https URL whose path ends in .png, .jpg, .jpeg, .gif, or .webp.',
	'ACP_TH_BADGE_INVALID_TYPE'      => 'Condition type must be either "points" or "feat".',
	'ACP_TH_BADGE_INVALID_FEAT'      => 'Invalid feat key selected.',
	'ACP_TH_BADGE_NOT_FOUND'         => 'Badge not found.',

	// Feat key labels (ACP_TH_FEAT_<KEY> — key uppercased)
	'ACP_TH_FEAT_FIRST_FIND'       => 'First find ever',
	'ACP_TH_FEAT_FIRST_RARE'       => 'First Rare find',
	'ACP_TH_FEAT_FIRST_LEGENDARY'  => 'First Legendary find',
	'ACP_TH_FEAT_UNIQUE_10'        => '10 unique items collected',
	'ACP_TH_FEAT_UNIQUE_50'        => '50 unique items collected',
	'ACP_TH_FEAT_UNIQUE_100'       => '100 unique items collected',
	'ACP_TH_FEAT_FINDS_100'        => '100 total finds',
	'ACP_TH_FEAT_FINDS_500'        => '500 total finds',
	'ACP_TH_FEAT_FINDS_1000'       => '1 000 total finds',
	'ACP_TH_FEAT_ONE_EACH_RARITY'  => 'One of each rarity tier',
	'ACP_TH_FEAT_COMPLETIONIST'    => 'Completionist (every item)',

	// Admin log entries
	'LOG_TH_BADGE_ADDED'   => '<strong>Treasure Hunt: badge added</strong> &#187; %s',
	'LOG_TH_BADGE_UPDATED' => '<strong>Treasure Hunt: badge updated</strong> &#187; %s',
	'LOG_TH_BADGE_DELETED' => '<strong>Treasure Hunt: badge deleted</strong> (ID %d)',

]);
