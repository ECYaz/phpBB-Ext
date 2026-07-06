<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\migrations;

class m4_seed extends \phpbb\db\migration\migration
{
	static public function depends_on()
	{
		return ['\ecyaz\treasurehunt\migrations\m3_module'];
	}

	public function effectively_installed()
	{
		$sql = 'SELECT item_id
			FROM ' . $this->table_prefix . "treasurehunt_items
			WHERE item_name = 'Copper Coin'";
		$result = $this->db->sql_query_limit($sql, 1);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return (bool) $row;
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'insert_seed_items']]],
			['custom', [[$this, 'insert_seed_badges']]],
		];
	}

	public function revert_data()
	{
		return [
			['custom', [[$this, 'remove_seed_badges']]],
			['custom', [[$this, 'remove_seed_items']]],
		];
	}

	public function insert_seed_items()
	{
		$items = [
			[
				'item_name'    => 'Copper Coin',
				'item_image'   => 'item_common.png',
				'rarity'       => 1,
				'points'       => 5,
				'drop_weight'  => 60,
				'item_enabled' => 1,
			],
			[
				'item_name'    => 'Silver Coin',
				'item_image'   => 'item_uncommon.png',
				'rarity'       => 2,
				'points'       => 15,
				'drop_weight'  => 25,
				'item_enabled' => 1,
			],
			[
				'item_name'    => 'Gold Coin',
				'item_image'   => 'item_rare.png',
				'rarity'       => 3,
				'points'       => 40,
				'drop_weight'  => 10,
				'item_enabled' => 1,
			],
			[
				'item_name'    => 'Sapphire Gem',
				'item_image'   => 'item_epic.png',
				'rarity'       => 4,
				'points'       => 100,
				'drop_weight'  => 4,
				'item_enabled' => 1,
			],
			[
				'item_name'    => 'Ruby Crystal',
				'item_image'   => 'item_legendary.png',
				'rarity'       => 5,
				'points'       => 250,
				'drop_weight'  => 1,
				'item_enabled' => 1,
			],
		];
		$this->db->sql_multi_insert($this->table_prefix . 'treasurehunt_items', $items);
	}

	public function insert_seed_badges()
	{
		$badges = [
			[
				'badge_name'      => 'Treasure Hunter',
				'badge_image'     => 'badge_milestone.png',
				'condition_type'  => 'points',
				'condition_value' => '50',
				'badge_enabled'   => 1,
			],
			[
				'badge_name'      => 'First Find',
				'badge_image'     => 'badge_first_find.png',
				'condition_type'  => 'feat',
				'condition_value' => 'first_find',
				'badge_enabled'   => 1,
			],
		];
		$this->db->sql_multi_insert($this->table_prefix . 'treasurehunt_badges', $badges);
	}

	public function remove_seed_items()
	{
		$seed_names = ['Copper Coin', 'Silver Coin', 'Gold Coin', 'Sapphire Gem', 'Ruby Crystal'];
		$sql = 'DELETE FROM ' . $this->table_prefix . 'treasurehunt_items
			WHERE ' . $this->db->sql_in_set('item_name', $seed_names);
		$this->db->sql_query($sql);
	}

	public function remove_seed_badges()
	{
		$seed_names = ['Treasure Hunter', 'First Find'];
		$sql = 'DELETE FROM ' . $this->table_prefix . 'treasurehunt_badges
			WHERE ' . $this->db->sql_in_set('badge_name', $seed_names);
		$this->db->sql_query($sql);
	}
}
