<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\migrations;

class m1_schema extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'treasurehunt_items');
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'treasurehunt_items' => [
					'COLUMNS' => [
						'item_id'      => ['UINT', null, 'auto_increment'],
						'item_name'    => ['VCHAR_UNI:255', ''],
						'item_image'   => ['VCHAR:255', ''],
						'rarity'       => ['USINT', 1],
						'points'       => ['UINT', 0],
						'drop_weight'  => ['UINT', 1],
						'item_enabled' => ['BOOL', 1],
					],
					'PRIMARY_KEY' => 'item_id',
					'KEYS' => [
						'rarity'   => ['INDEX', 'rarity'],
						'enabled'  => ['INDEX', 'item_enabled'],
					],
				],
				$this->table_prefix . 'treasurehunt_spawns' => [
					'COLUMNS' => [
						'spawn_id'   => ['UINT', null, 'auto_increment'],
						'user_id'    => ['UINT', 0],
						'item_id'    => ['UINT', 0],
						'token'      => ['VCHAR:40', ''],
						'spawned_at' => ['TIMESTAMP', 0],
						'expires_at' => ['TIMESTAMP', 0],
						'claimed'    => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'spawn_id',
					'KEYS' => [
						'user_token'  => ['INDEX', ['user_id', 'token']],
						'expires_at'  => ['INDEX', 'expires_at'],
					],
				],
				$this->table_prefix . 'treasurehunt_finds' => [
					'COLUMNS' => [
						'find_id'  => ['UINT', null, 'auto_increment'],
						'user_id'  => ['UINT', 0],
						'item_id'  => ['UINT', 0],
						'found_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'find_id',
					'KEYS' => [
						'user_id' => ['INDEX', 'user_id'],
						'item_id' => ['INDEX', 'item_id'],
					],
				],
				$this->table_prefix . 'treasurehunt_user' => [
					'COLUMNS' => [
						'user_id'      => ['UINT', 0],
						'points'       => ['UINT', 0],
						'total_finds'  => ['UINT', 0],
						'unique_items' => ['UINT', 0],
						'last_spawn'   => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => 'user_id',
				],
				$this->table_prefix . 'treasurehunt_badges' => [
					'COLUMNS' => [
						'badge_id'        => ['UINT', null, 'auto_increment'],
						'badge_name'      => ['VCHAR_UNI:255', ''],
						'badge_image'     => ['VCHAR:255', ''],
						'condition_type'  => ['VCHAR:20', 'points'],
						'condition_value' => ['VCHAR:50', '0'],
						'badge_enabled'   => ['BOOL', 1],
					],
					'PRIMARY_KEY' => 'badge_id',
				],
				$this->table_prefix . 'treasurehunt_user_badges' => [
					'COLUMNS' => [
						'user_id'   => ['UINT', 0],
						'badge_id'  => ['UINT', 0],
						'earned_at' => ['TIMESTAMP', 0],
					],
					'PRIMARY_KEY' => ['user_id', 'badge_id'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'treasurehunt_user_badges',
				$this->table_prefix . 'treasurehunt_badges',
				$this->table_prefix . 'treasurehunt_user',
				$this->table_prefix . 'treasurehunt_finds',
				$this->table_prefix . 'treasurehunt_spawns',
				$this->table_prefix . 'treasurehunt_items',
			],
		];
	}
}
