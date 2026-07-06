<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */
namespace ecyaz\treasurehunt\service;

class item_repository
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var string */
	protected $items_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $items_table)
	{
		$this->db          = $db;
		$this->items_table = $items_table;
	}

	/**
	 * Pick one enabled item, weighted by drop_weight.
	 *
	 * @return array|null assoc item row, or null if the catalog has no eligible item.
	 */
	public function pick_weighted(): ?array
	{
		$sql = 'SELECT item_id, item_name, item_image, rarity, points, drop_weight
			FROM ' . $this->items_table . '
			WHERE item_enabled = 1
				AND drop_weight > 0';
		$result = $this->db->sql_query($sql);

		$rows  = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $this->pick_from_items($rows);
	}

	/**
	 * Pure weighted pick over already-loaded rows. Overridable seam for testing.
	 *
	 * @param array $rows each row must carry an integer 'drop_weight'
	 * @return array|null
	 */
	protected function pick_from_items(array $rows): ?array
	{
		$total = 0;
		foreach ($rows as $row)
		{
			$total += (int) $row['drop_weight'];
		}

		if (empty($rows) || $total <= 0)
		{
			return null;
		}

		return self::select_by_roll($rows, random_int(1, $total));
	}

	/**
	 * Pure weighted selector: walk cumulative weights and return the row whose
	 * bucket contains $roll (1-based). Extracted so it is unit-testable without a DB.
	 *
	 * @param array $rows each row must carry an integer 'drop_weight'
	 * @param int   $roll 1..Σdrop_weight
	 * @return array|null
	 */
	static public function select_by_roll(array $rows, int $roll): ?array
	{
		$cumulative = 0;
		foreach ($rows as $row)
		{
			$cumulative += (int) $row['drop_weight'];
			if ($roll <= $cumulative)
			{
				return $row;
			}
		}

		// Fallback (rounding / empty): last row or null.
		return empty($rows) ? null : $rows[count($rows) - 1];
	}
}
