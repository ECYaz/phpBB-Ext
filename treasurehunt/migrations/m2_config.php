<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\migrations;

class m2_config extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\ecyaz\treasurehunt\migrations\m1_schema'];
	}

	public function effectively_installed()
	{
		return $this->config->offsetExists('treasurehunt_enable');
	}

	public function update_data()
	{
		return [
			['config.add', ['treasurehunt_enable',       0]],
			['config.add', ['treasurehunt_drop_rate',    50]],
			['config.add', ['treasurehunt_cooldown',     300]],
			['config.add', ['treasurehunt_spawn_style',  'modal']],
			['config.add', ['treasurehunt_spawn_expiry', 60]],
			['config.add', ['treasurehunt_forum_scope',  'all']],
			['config.add', ['treasurehunt_play_groups',  'all']],
			['config.add', ['treasurehunt_postbit_cap',  3]],
			['config.add', ['treasurehunt_version',      '0.1.0']],
			['permission.add', ['u_treasurehunt_play', true]],
			['permission.permission_set', ['REGISTERED', 'u_treasurehunt_play', 'group']],
		];
	}

	public function revert_data()
	{
		return [
			['permission.remove', ['u_treasurehunt_play', true]],
			['config.remove', ['treasurehunt_version']],
			['config.remove', ['treasurehunt_postbit_cap']],
			['config.remove', ['treasurehunt_play_groups']],
			['config.remove', ['treasurehunt_forum_scope']],
			['config.remove', ['treasurehunt_spawn_expiry']],
			['config.remove', ['treasurehunt_spawn_style']],
			['config.remove', ['treasurehunt_cooldown']],
			['config.remove', ['treasurehunt_drop_rate']],
			['config.remove', ['treasurehunt_enable']],
		];
	}
}
