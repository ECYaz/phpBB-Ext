<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\migrations;

class install_liveupdates extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['ecyaz_liveupdates_enabled']);
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_data()
	{
		return [
			['config.add', ['ecyaz_liveupdates_enabled', 1]],
			['config.add', ['ecyaz_liveupdates_posture', 'balanced']],
			['config.add', ['ecyaz_liveupdates_interval_override', 0]],
			['config.add', ['ecyaz_liveupdates_guest_enabled', 0]],
			['config.add', ['ecyaz_liveupdates_guest_interval', 30]],
			['config.add', ['ecyaz_liveupdates_min_interval', 3]],
			['config.add', ['ecyaz_liveupdates_surface_topic', 1]],
			['config.add', ['ecyaz_liveupdates_surface_notify', 1]],
			['config.add', ['ecyaz_liveupdates_surface_index', 1]],

			['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_LIVEUPDATES_TITLE']],
			['module.add', ['acp', 'ACP_LIVEUPDATES_TITLE', [
				'module_basename' => '\ecyaz\liveupdates\acp\main_module',
				'modes'           => ['settings'],
			]]],
		];
	}
}
