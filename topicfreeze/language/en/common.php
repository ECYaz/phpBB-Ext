<?php
/**
 *
 * Topic Freeze. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
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
	'TOPICFREEZE_FROZEN'			=> 'Frozen topic',
	'TOPICFREEZE_NOTICE'			=> 'This topic is frozen. Replies from members only appear after a moderator has approved them.',
	'TOPICFREEZE_NOTICE_QUEUED'		=> 'This topic is frozen. You can still reply, but your reply will only appear after a moderator has approved it.',
	'TOPICFREEZE_POST_ACTION_DENIED'	=> 'This topic is frozen. You cannot edit, delete or bump posts in it.',
	'TOPICFREEZE_ATTACHMENT_DENIED'		=> 'At least one of the selected attachments belongs to a post in a frozen topic. Attachments in a frozen topic cannot be changed or deleted.',

	'TOPICFREEZE_FREEZE_TOPIC'				=> 'Freeze topic',
	'TOPICFREEZE_FREEZE_TOPIC_CONFIRM'		=> 'Are you sure you want to freeze this topic? Members can still reply, but every reply will wait for moderator approval.',
	'TOPICFREEZE_FREEZE_TOPIC_SUCCESS'		=> 'The topic has been frozen.',
	'TOPICFREEZE_UNFREEZE_TOPIC'			=> 'Unfreeze topic',
	'TOPICFREEZE_UNFREEZE_TOPIC_CONFIRM'	=> 'Are you sure you want to unfreeze this topic? New replies will no longer wait for moderator approval because of the freeze.',
	'TOPICFREEZE_UNFREEZE_TOPIC_SUCCESS'	=> 'The topic has been unfrozen.',

	'LOG_TOPICFREEZE_FREEZE'	=> '<strong>Froze topic</strong><br />» %s',
	'LOG_TOPICFREEZE_UNFREEZE'	=> '<strong>Unfroze topic</strong><br />» %s',
]);
