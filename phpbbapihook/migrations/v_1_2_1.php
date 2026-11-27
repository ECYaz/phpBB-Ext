<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\migrations;

/**
 * 1.2.1: the audit log is pruned by a cron task.
 */
class v_1_2_1 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\phpbbapihook\migrations\v_1_2_0'];
	}

	public function effectively_installed()
	{
		return isset($this->config['phpbbapihook_log_days']);
	}

	public function update_data()
	{
		return [
			['config.add', ['phpbbapihook_log_days', 90]],
			['config.add', ['phpbbapihook_log_gc_last', 0, true]],
		];
	}
}
