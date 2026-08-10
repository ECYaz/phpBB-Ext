<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\migrations;

class install_pmpurge extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['ecyaz_pmpurge_inactive_days']);
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_data()
	{
		return [
			// Automatic purging stays off, and the first runs only report, until
			// an admin has looked at what the settings actually select.
			['config.add', ['ecyaz_pmpurge_enabled', 0]],
			['config.add', ['ecyaz_pmpurge_dry_run', 1]],

			// Each cutoff can be a rolling number of days, which is what makes
			// the cron useful, or a fixed calendar date for a one off sweep.
			['config.add', ['ecyaz_pmpurge_inactive_mode', 'days']],
			['config.add', ['ecyaz_pmpurge_inactive_days', 1095]],
			['config.add', ['ecyaz_pmpurge_inactive_before', 0]],

			['config.add', ['ecyaz_pmpurge_msg_mode', 'days']],
			['config.add', ['ecyaz_pmpurge_pm_age_days', 0]],
			['config.add', ['ecyaz_pmpurge_msg_after', 0]],
			['config.add', ['ecyaz_pmpurge_msg_before', 0]],

			['config.add', ['ecyaz_pmpurge_include_outbox', 0]],
			['config.add', ['ecyaz_pmpurge_include_never_visited', 1]],
			['config.add', ['ecyaz_pmpurge_skip_founders', 1]],
			['config.add', ['ecyaz_pmpurge_exempt_groups', '']],
			['config.add', ['ecyaz_pmpurge_batch_users', 25]],

			['config.add', ['ecyaz_pmpurge_cursor', 0, true]],
			['config.add', ['ecyaz_pmpurge_gc', 86400]],
			['config.add', ['ecyaz_pmpurge_last_gc', 0, true]],

			// The Maintenance tab lists sub categories, not bare modules, so
			// the module needs a category of its own to appear at all.
			['module.add', ['acp', 'ACP_CAT_MAINTENANCE', 'ACP_PMPURGE_TITLE']],
			['module.add', ['acp', 'ACP_PMPURGE_TITLE', [
				'module_basename' => '\ecyaz\pmpurge\acp\main_module',
				'modes'           => ['settings'],
			]]],
		];
	}
}
