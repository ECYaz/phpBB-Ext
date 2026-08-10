<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

$lang = array_merge($lang ?? [], [
	'CLI_DESCRIPTION_PMPURGE_RUN'     => 'Purges the private messages of long-inactive members.',
	'CLI_DESCRIPTION_PMPURGE_DRY_RUN' => 'Report what would be removed and delete nothing.',
	'CLI_DESCRIPTION_PMPURGE_ALL'     => 'Keep going until the whole member list has been walked, instead of stopping after one batch.',
	'CLI_DESCRIPTION_PMPURGE_LIMIT'   => 'Members per batch, overriding the board setting.',

	'CLI_PMPURGE_NOT_CONFIGURED' => 'Set an inactivity period of at least one day before running a purge.',
	'CLI_PMPURGE_DRY_RUN_NOTICE' => 'Dry run: nothing was deleted.',
	'CLI_PMPURGE_PROGRESS'       => '%1$s members processed, %2$s message copies so far.',
	'CLI_PMPURGE_SUMMARY'        => '%1$s members processed, %2$s message copies removed, %3$s messages removed entirely.',
	'CLI_PMPURGE_SUMMARY_DRY'    => '%1$s members selected, holding %2$s message copies. Nothing was deleted.',
]);
