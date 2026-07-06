<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\service;

class stats_repository
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $th_user_table;

	/** @var string */
	protected $finds_table;

	/** @var string */
	protected $items_table;

	/** @var string */
	protected $badges_table;

	/** @var string */
	protected $user_badges_table;

	/**
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param string                            $table_prefix
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, $table_prefix)
	{
		$this->db                = $db;
		$this->th_user_table     = $table_prefix . 'treasurehunt_user';
		$this->finds_table       = $table_prefix . 'treasurehunt_finds';
		$this->items_table       = $table_prefix . 'treasurehunt_items';
		$this->badges_table      = $table_prefix . 'treasurehunt_badges';
		$this->user_badges_table = $table_prefix . 'treasurehunt_user_badges';
	}

	/**
	 * Fetch the aggregate row for a single user.
	 *
	 * @param int $user_id
	 * @return array assoc row with points, total_finds, unique_items, last_spawn
	 *               or empty array if user has no row yet.
	 */
	public function get(int $user_id): array
	{
		$sql    = 'SELECT user_id, points, total_finds, unique_items, last_spawn
			FROM ' . $this->th_user_table . '
			WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $row ?: [];
	}

	/**
	 * Return top collectors ordered by points DESC.
	 * Includes phpBB username/avatar fields and each user's lowest badge_id
	 * (stand-in for "top badge" until Phase 4 defines badge ranking).
	 *
	 * @param int $start  OFFSET for pagination.
	 * @param int $limit  Page size.
	 * @return array      Rows with keys: user_id, points, total_finds,
	 *                    unique_items, username, user_colour, user_avatar,
	 *                    user_avatar_type, user_avatar_width, user_avatar_height,
	 *                    top_badge_name (null if none), top_badge_image (null).
	 */
	public function top_collectors(int $start, int $limit): array
	{
		// TODO(Phase 4): MIN(badge_id) is an insertion-order proxy; replace with real badge ranking (rarity/points) once badges have a rank.
		$sql = 'SELECT tu.user_id, tu.points, tu.total_finds, tu.unique_items,
				u.username, u.user_colour,
				u.user_avatar, u.user_avatar_type,
				u.user_avatar_width, u.user_avatar_height,
				b.badge_name AS top_badge_name,
				b.badge_image AS top_badge_image
			FROM ' . $this->th_user_table . ' tu
			INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = tu.user_id
			LEFT JOIN (
				SELECT user_id, MIN(badge_id) AS badge_id
				FROM ' . $this->user_badges_table . '
				GROUP BY user_id
			) ub ON ub.user_id = tu.user_id
			LEFT JOIN ' . $this->badges_table . ' b ON b.badge_id = ub.badge_id
			ORDER BY tu.points DESC, tu.total_finds DESC';

		$result = $this->db->sql_query_limit($sql, $limit, $start);
		$rows   = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Total number of players who have a row in treasurehunt_user.
	 * Used to drive pagination total for the top-collectors view.
	 *
	 * @return int
	 */
	public function total_players(): int
	{
		$sql    = 'SELECT COUNT(user_id) AS cnt FROM ' . $this->th_user_table;
		$result = $this->db->sql_query($sql);
		$cnt    = (int) $this->db->sql_fetchfield('cnt');
		$this->db->sql_freeresult($result);

		return $cnt;
	}

	/**
	 * Most recent epic/legendary finds (rarity >= RARITY_EPIC=4), newest first.
	 * Joins finds to items to users; returns finder username/colour with item info.
	 *
	 * @param int $limit  Maximum rows to return.
	 * @return array      Rows with keys: found_at, item_name, item_image,
	 *                    rarity, points, username, user_colour.
	 */
	public function rarest_finds(int $limit): array
	{
		$sql = 'SELECT f.found_at, i.item_name, i.item_image,
				i.rarity, i.points,
				u.username, u.user_colour
			FROM ' . $this->finds_table . ' f
			INNER JOIN ' . $this->items_table . ' i ON i.item_id = f.item_id
			INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = f.user_id
			WHERE i.rarity >= ' . \ecyaz\treasurehunt\ext::RARITY_EPIC . '
			ORDER BY f.found_at DESC';

		$result = $this->db->sql_query_limit($sql, $limit);
		$rows   = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Return the requesting user's rank (1-based count of users with strictly
	 * more points than this user, +1), their aggregate row, and progress fields.
	 *
	 * Rank tie-breaking: users with equal points all share the same rank
	 * (standard competition ranking — "count above").
	 *
	 * @param int $user_id
	 * @return array  Keys: user_id, points, total_finds, unique_items,
	 *                username, user_avatar, user_avatar_type,
	 *                user_avatar_width, user_avatar_height,
	 *                rank (int, 1-based), items_total (int).
	 *                Returns [] if the user has no aggregate row yet.
	 */
	public function user_rank(int $user_id): array
	{
		// 1. Own aggregate row
		$sql = 'SELECT tu.user_id, tu.points, tu.total_finds, tu.unique_items,
				u.username,
				u.user_avatar, u.user_avatar_type,
				u.user_avatar_width, u.user_avatar_height
			FROM ' . $this->th_user_table . ' tu
			INNER JOIN ' . USERS_TABLE . ' u ON u.user_id = tu.user_id
			WHERE tu.user_id = ' . (int) $user_id;

		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return [];
		}

		// 2. Rank = count of users with strictly more points + 1
		$sql    = 'SELECT COUNT(user_id) AS above
			FROM ' . $this->th_user_table . '
			WHERE points > ' . (int) $row['points'];
		$result = $this->db->sql_query($sql);
		$above  = (int) $this->db->sql_fetchfield('above');
		$this->db->sql_freeresult($result);

		// 3. Total enabled items (denominator for collection progress bar)
		$sql         = 'SELECT COUNT(item_id) AS items_total
			FROM ' . $this->items_table . '
			WHERE item_enabled = 1';
		$result      = $this->db->sql_query($sql);
		$items_total = (int) $this->db->sql_fetchfield('items_total');
		$this->db->sql_freeresult($result);

		return array_merge($row, [
			'rank'        => $above + 1,
			'items_total' => $items_total,
		]);
	}
}
