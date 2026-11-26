<?php
/**
 *
 * Topic Freeze. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\topicfreeze;

class ext extends \phpbb\extension\base
{
	/**
	 * The install migration uses the permission.role_exists migration tool,
	 * which phpBB offers from 3.3.2 on.
	 *
	 * @return bool
	 */
	public function is_enableable()
	{
		return phpbb_version_compare(PHPBB_VERSION, '3.3.2', '>=');
	}
}
