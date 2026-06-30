<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\migrations;

class add_surfaces extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['ecyaz_liveupdates_surface_pm']);
	}

	public static function depends_on()
	{
		return ['\ecyaz\liveupdates\migrations\install_liveupdates'];
	}

	public function update_data()
	{
		return [
			['config.add', ['ecyaz_liveupdates_surface_pm', 1]],
			['config.add', ['ecyaz_liveupdates_surface_online', 1]],
			['config.add', ['ecyaz_liveupdates_surface_stats', 1]],
		];
	}
}
