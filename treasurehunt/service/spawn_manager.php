<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */
namespace ecyaz\treasurehunt\service;

class spawn_manager
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;
	/** @var \phpbb\config\config */
	protected $config;
	/** @var \phpbb\auth\auth */
	protected $auth;
	/** @var \ecyaz\treasurehunt\service\item_repository */
	protected $item_repository;
	/** @var string */
	protected $spawns_table;
	/** @var string */
	protected $user_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\phpbb\auth\auth $auth,
		\ecyaz\treasurehunt\service\item_repository $item_repository,
		string $spawns_table,
		string $user_table
	)
	{
		$this->db              = $db;
		$this->config          = $config;
		$this->auth            = $auth;
		$this->item_repository = $item_repository;
		$this->spawns_table    = $spawns_table;
		$this->user_table      = $user_table;
	}

	/**
	 * Decide whether to authorize a treasure for this user on this page load,
	 * and if so persist the pending spawn. Server-authoritative — the client
	 * never influences odds, cooldown, or which item drops.
	 *
	 * Cooldown limits roll ATTEMPTS (un-farmable), not just drops. last_spawn is
	 * stamped as soon as the cooldown gate clears, before the roll, so a miss
	 * still consumes the window and prevents reload-farming.
	 *
	 * Order: enabled → anon → permission → forum-scope → group → cooldown-clear
	 *        → stamp last_spawn=now → roll → (hit: insert spawn row + return item;
	 *          miss: return null, cooldown already consumed).
	 *
	 * @param int $user_id  The user to evaluate (must not be ANONYMOUS).
	 * @param int $forum_id The forum being viewed (used for scope gating).
	 * @return array|null   ['item' => assoc item row, 'token' => 40-hex string] or null.
	 */
	public function maybe_spawn(int $user_id, int $forum_id): ?array
	{
		if ($user_id === ANONYMOUS || !(int) $this->config['treasurehunt_enable'])
		{
			return null;
		}
		if (!$this->auth->acl_get('u_treasurehunt_play'))
		{
			return null;
		}
		if (!self::forum_in_scope($forum_id, (string) $this->config['treasurehunt_forum_scope']))
		{
			return null;
		}
		if (!$this->user_in_play_groups($user_id))
		{
			return null;
		}

		$now = time();
		if (self::on_cooldown($this->get_last_spawn($user_id), $now, (int) $this->config['treasurehunt_cooldown']))
		{
			return null;
		}

		// Stamp BEFORE the roll so the cooldown window is consumed regardless of
		// outcome — prevents reload-farming on a miss.
		$this->stamp_last_spawn($user_id, $now);

		if (!self::passes_roll((int) $this->config['treasurehunt_drop_rate'], random_int(1, 1000)))
		{
			return null;
		}

		$item = $this->item_repository->pick_weighted();
		if ($item === null)
		{
			return null;
		}

		$token = bin2hex(random_bytes(20)); // 40 hex chars
		$data  = [
			'user_id'    => $user_id,
			'item_id'    => (int) $item['item_id'],
			'token'      => $token,
			'spawned_at' => $now,
			'expires_at' => $now + (int) $this->config['treasurehunt_spawn_expiry'],
			'claimed'    => 0,
		];
		$this->db->sql_query('INSERT INTO ' . $this->spawns_table . ' ' . $this->db->sql_build_array('INSERT', $data));

		return ['item' => $item, 'token' => $token];
	}

	/* ---------- pure, unit-testable decision helpers ---------- */

	/**
	 * Returns true when the user is still within the cooldown window.
	 *
	 * @param int $last_spawn Unix timestamp of last spawn (0 = never).
	 * @param int $now        Current Unix timestamp.
	 * @param int $cooldown   Cooldown period in seconds.
	 * @return bool
	 */
	static public function on_cooldown(int $last_spawn, int $now, int $cooldown): bool
	{
		return ($now - $last_spawn) < $cooldown;
	}

	/**
	 * Returns true when the roll beats the drop rate.
	 *
	 * @param int $drop_rate  0..1000 (tenths of a percent; 50 = 5%)
	 * @param int $roll_1000  A value from random_int(1, 1000)
	 * @return bool
	 */
	static public function passes_roll(int $drop_rate, int $roll_1000): bool
	{
		return $roll_1000 <= $drop_rate;
	}

	/**
	 * Returns true when forum_id is allowed by the scope setting.
	 *
	 * @param int    $forum_id
	 * @param string $scope 'all', empty string, or comma-separated forum IDs
	 * @return bool
	 */
	static public function forum_in_scope(int $forum_id, string $scope): bool
	{
		$scope = trim($scope);
		if ($scope === '' || $scope === 'all')
		{
			return true;
		}
		$ids = array_filter(array_map('intval', explode(',', $scope)));
		return in_array($forum_id, $ids, true);
	}

	/**
	 * Returns true when at least one of the user's group IDs is in play_groups.
	 *
	 * @param array  $user_group_ids
	 * @param string $play_groups 'all', empty string, or comma-separated group IDs
	 * @return bool
	 */
	static public function groups_allowed(array $user_group_ids, string $play_groups): bool
	{
		$play_groups = trim($play_groups);
		if ($play_groups === '' || $play_groups === 'all')
		{
			return true;
		}
		$allowed = array_filter(array_map('intval', explode(',', $play_groups)));
		foreach ($user_group_ids as $gid)
		{
			if (in_array((int) $gid, $allowed, true))
			{
				return true;
			}
		}
		return false;
	}

	/* ---------- DB-backed helpers ---------- */

	/**
	 * @param int $user_id
	 * @return int Unix timestamp of last spawn, 0 if no record.
	 */
	protected function get_last_spawn(int $user_id): int
	{
		$sql    = 'SELECT last_spawn FROM ' . $this->user_table . ' WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);
		return $row ? (int) $row['last_spawn'] : 0;
	}

	/**
	 * UPSERT last_spawn for the given user.
	 *
	 * @param int $user_id
	 * @param int $now
	 * @return void
	 */
	protected function stamp_last_spawn(int $user_id, int $now): void
	{
		$sql = 'UPDATE ' . $this->user_table . '
			SET last_spawn = ' . (int) $now . '
			WHERE user_id = ' . (int) $user_id;
		$this->db->sql_query($sql);

		if (!$this->db->sql_affectedrows())
		{
			$data = [
				'user_id'      => $user_id,
				'points'       => 0,
				'total_finds'  => 0,
				'unique_items' => 0,
				'last_spawn'   => $now,
			];
			$this->db->sql_query('INSERT INTO ' . $this->user_table . ' ' . $this->db->sql_build_array('INSERT', $data));
		}
	}

	/**
	 * Check whether the user belongs to at least one allowed play group.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	protected function user_in_play_groups(int $user_id): bool
	{
		$play_groups = trim((string) $this->config['treasurehunt_play_groups']);
		if ($play_groups === '' || $play_groups === 'all')
		{
			return true;
		}

		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . (int) $user_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query($sql);
		$gids   = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$gids[] = (int) $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		return self::groups_allowed($gids, $play_groups);
	}
}
