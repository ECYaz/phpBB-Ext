<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */
namespace ecyaz\treasurehunt\service;

class collect_manager
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \ecyaz\treasurehunt\manager\badge_manager */
	protected $badge_manager;
	/** @var string */
	protected $spawns_table;
	/** @var string */
	protected $finds_table;
	/** @var string */
	protected $user_table;
	/** @var string */
	protected $items_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\ecyaz\treasurehunt\manager\badge_manager $badge_manager,
		$spawns_table,
		$finds_table,
		$user_table,
		$items_table
	)
	{
		$this->db            = $db;
		$this->badge_manager = $badge_manager;
		$this->spawns_table  = $spawns_table;
		$this->finds_table   = $finds_table;
		$this->user_table    = $user_table;
		$this->items_table   = $items_table;
	}

	/**
	 * Collect a spawned treasure. Fails quietly (['success'=>false]) for any
	 * invalid / expired / replayed / not-owned / unknown token — no points move.
	 */
	public function collect(int $user_id, string $token): array
	{
		$fail = ['success' => false];

		if ($user_id == ANONYMOUS || !preg_match('/^[a-f0-9]{40}$/', $token))
		{
			return $fail;
		}

		$now = time();

		$spawn = $this->load_spawn($token, $user_id);

		if (!self::is_collectable($spawn, $now))
		{
			return $fail;
		}

		$item_id = (int) $spawn['item_id'];
		$delta   = 0;

		$this->db->sql_transaction('begin');
		try
		{
			// Fetch the item inside the transaction so the awarded delta is
			// consistent with the committed write (Fix 2: no concurrent edit drift).
			$sql    = 'SELECT points, rarity FROM ' . $this->items_table . ' WHERE item_id = ' . (int) $item_id;
			$result = $this->db->sql_query($sql);
			$item   = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			if (!$item)
			{
				$this->db->sql_transaction('rollback');
				return $fail;
			}
			$delta = (int) $item['points'];

			// Atomic claim: only continue if we flip claimed 0->1 AND expiry still
			// holds at the DB level (Fix 1: closes TOCTOU between pre-check and CAS).
			$sql = 'UPDATE ' . $this->spawns_table . '
				SET claimed = 1
				WHERE spawn_id = ' . (int) $spawn['spawn_id'] . '
					AND claimed = 0
					AND expires_at >= ' . (int) $now;
			$this->db->sql_query($sql);
			if (!$this->db->sql_affectedrows())
			{
				$this->db->sql_transaction('rollback');
				return $fail;
			}

			// unique detection: did the user already own this item?
			$sql = 'SELECT 1 AS seen
				FROM ' . $this->finds_table . '
				WHERE user_id = ' . (int) $user_id . '
					AND item_id = ' . (int) $item_id;
			$result        = $this->db->sql_query_limit($sql, 1);
			$is_new_unique = !$this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			$this->commit_find($user_id, $spawn, $item, $is_new_unique);

			$this->db->sql_transaction('commit');
		}
		catch (\Throwable $e)
		{
			$this->db->sql_transaction('rollback');
			return $fail;
		}

		$stats      = $this->load_stats($user_id);
		$new_badges = $this->badge_manager->evaluate($user_id, $stats);

		return [
			'success'   => true,
			'points'    => (int) $stats['points'],
			'delta'     => $delta,
			'newBadges' => $new_badges,
		];
	}

	/**
	 * Pure validity gate for a fetched spawn row. Extracted for unit testing.
	 *
	 * @param array|false $spawn row from the spawns table, or false when none matched
	 * @param int         $now   current timestamp
	 */
	static public function is_collectable($spawn, int $now): bool
	{
		if (!$spawn)
		{
			return false;
		}
		if ((int) $spawn['claimed'] === 1)
		{
			return false;
		}
		return (int) $spawn['expires_at'] >= $now;
	}

	/**
	 * Load a spawn row by token and user_id. Protected seam for Phase 5 testing.
	 *
	 * @param string $token  40-char hex token
	 * @param int    $user_id
	 * @return array|null spawn row, or null when not found
	 */
	protected function load_spawn(string $token, int $user_id): ?array
	{
		$sql    = 'SELECT spawn_id, item_id, expires_at, claimed
			FROM ' . $this->spawns_table . '
			WHERE user_id = ' . (int) $user_id . "
				AND token = '" . $this->db->sql_escape($token) . "'";
		$result = $this->db->sql_query($sql);
		$spawn  = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $spawn ?: null;
	}

	/**
	 * Insert the finds row and update user aggregates. Protected seam for Phase 5 testing.
	 *
	 * @param int   $user_id
	 * @param array $spawn         spawn row (must include spawn_id, item_id)
	 * @param array $item          item row (must include points)
	 * @param bool  $is_new_unique whether this item_id was not previously found by user
	 */
	protected function commit_find(int $user_id, array $spawn, array $item, bool $is_new_unique): void
	{
		$now        = time();
		$item_id    = (int) $spawn['item_id'];
		$delta      = (int) $item['points'];
		$unique_inc = $is_new_unique ? 1 : 0;

		$this->db->sql_query('INSERT INTO ' . $this->finds_table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id'  => (int) $user_id,
			'item_id'  => $item_id,
			'found_at' => $now,
		]));

		$sql = 'UPDATE ' . $this->user_table . '
			SET points = points + ' . $delta . ',
				total_finds = total_finds + 1,
				unique_items = unique_items + ' . $unique_inc . '
			WHERE user_id = ' . (int) $user_id;
		$this->db->sql_query($sql);
		if (!$this->db->sql_affectedrows())
		{
			$this->db->sql_query('INSERT INTO ' . $this->user_table . ' ' . $this->db->sql_build_array('INSERT', [
				'user_id'      => (int) $user_id,
				'points'       => $delta,
				'total_finds'  => 1,
				'unique_items' => $unique_inc,
				'last_spawn'   => 0,
			]));
		}
	}

	/**
	 * Load the stats shape badge_manager->evaluate expects.
	 *
	 * @return array ['points','total_finds','unique_items','rarities_owned','items_total']
	 */
	protected function load_stats(int $user_id): array
	{
		$sql    = 'SELECT points, total_finds, unique_items FROM ' . $this->user_table . ' WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$u      = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		$rarities = [];
		$sql      = 'SELECT DISTINCT i.rarity
			FROM ' . $this->finds_table . ' f
			JOIN ' . $this->items_table . ' i ON i.item_id = f.item_id
			WHERE f.user_id = ' . (int) $user_id;
		$result   = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rarities[] = (int) $row['rarity'];
		}
		$this->db->sql_freeresult($result);

		$sql    = 'SELECT COUNT(item_id) AS c FROM ' . $this->items_table . ' WHERE item_enabled = 1';
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return [
			'points'         => $u ? (int) $u['points'] : 0,
			'total_finds'    => $u ? (int) $u['total_finds'] : 0,
			'unique_items'   => $u ? (int) $u['unique_items'] : 0,
			'rarities_owned' => $rarities,
			'items_total'    => (int) $row['c'],
		];
	}
}
