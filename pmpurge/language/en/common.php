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
	'LOG_PMPURGE_SETTINGS' => '<strong>Old PMs Purger settings changed</strong>',
	'LOG_PMPURGE_RUN'      => '<strong>Old private messages purged</strong><br />» %1$s members, %2$s message copies, %3$s messages removed entirely',
	'LOG_PMPURGE_DRY_RUN'  => '<strong>Old PMs Purger dry run</strong><br />» %1$s members selected, holding %2$s message copies, nothing deleted',
]);
