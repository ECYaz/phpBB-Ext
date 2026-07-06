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

	// Rarity tier labels — used in JS bootstrap (Phase 2), postbit (Phase 4), and leaderboard (Phase 3)
	'TREASUREHUNT_RARITY_1' => 'Common',
	'TREASUREHUNT_RARITY_2' => 'Uncommon',
	'TREASUREHUNT_RARITY_3' => 'Rare',
	'TREASUREHUNT_RARITY_4' => 'Epic',
	'TREASUREHUNT_RARITY_5' => 'Legendary',

	// Spawn / collect UI strings — injected into JS bootstrap in Phase 2
	'TREASUREHUNT_FOUND'          => 'You found a',
	'TREASUREHUNT_COLLECT'        => 'Collect',
	'TREASUREHUNT_POINTS_SUFFIX'  => 'pts',
	'TREASUREHUNT_BADGE_UNLOCKED' => 'Badge unlocked!',

	// Error strings returned by the collect endpoint (Phase 2) and shown in JS toast
	'TREASUREHUNT_NOT_PERMITTED'    => 'You do not have permission to collect treasures.',

	// Leaderboard page (Phase 3)
	'TREASUREHUNT_LEADERBOARD'      => 'Treasure Hunt Leaderboard',
	'TREASUREHUNT_YOUR_STATS'       => 'Your Stats',
	'TREASUREHUNT_RANK'             => 'Rank',
	'TREASUREHUNT_POINTS'           => 'Points',
	'TREASUREHUNT_TOTAL_FINDS'      => 'Total Finds',
	'TREASUREHUNT_UNIQUE_ITEMS'     => 'Unique Items',
	'TREASUREHUNT_NO_FINDS_YET'     => 'No treasures collected yet. Be the first!',

	// Leaderboard template keys (Task 3.3)
	'TREASUREHUNT_LB_TAB_TOP'       => 'Top Collectors',
	'TREASUREHUNT_LB_TAB_RARE'      => 'Rarest Finds',
	'TREASUREHUNT_LB_TAB_ME'        => 'Your Stats',
	'TREASUREHUNT_LB_RANK'          => 'Rank',
	'TREASUREHUNT_LB_PLAYER'        => 'Player',
	'TREASUREHUNT_LB_POINTS'        => 'Points',
	'TREASUREHUNT_LB_FINDS'         => 'Finds',
	'TREASUREHUNT_LB_UNIQUE'        => 'Unique',
	'TREASUREHUNT_LB_TOP_BADGE'     => 'Top Badge',
	'TREASUREHUNT_LB_NO_PLAYERS'    => 'No players yet.',
	'TREASUREHUNT_LB_ITEM'          => 'Item',
	'TREASUREHUNT_LB_RARITY'        => 'Rarity',
	'TREASUREHUNT_LB_FOUND_AT'      => 'Found At',
	'TREASUREHUNT_LB_NO_RARE_FINDS' => 'No rare finds yet.',
	'TREASUREHUNT_LB_COLLECTION'    => 'Collection',
	'TREASUREHUNT_LB_ME_NO_DATA'    => 'You have not collected anything yet.',

	// Profile / postbit (Phase 4)
	'TREASUREHUNT_PROFILE_HEADING'     => 'Treasure Hunt',
	'TREASUREHUNT_COLLECTION_PROGRESS' => 'Collection Progress',
	'TREASUREHUNT_BADGES_EARNED'       => 'Badges Earned',

]);
