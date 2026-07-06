<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\repository;

class badge_repository
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $badges_table;

	/** @var string */
	protected $user_badges_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		$badges_table,
		$user_badges_table
	)
	{
		$this->db                = $db;
		$this->badges_table      = $badges_table;
		$this->user_badges_table = $user_badges_table;
	}

	/**
	 * All enabled point-threshold badges, ordered by threshold ascending.
	 * Each row: badge_id, badge_name, badge_image, condition_type, condition_value.
	 *
	 * @return array
	 */
	public function enabled_point_badges(): array
	{
		$sql = 'SELECT badge_id, badge_name, badge_image, condition_type, condition_value
				FROM ' . $this->badges_table . "
				WHERE badge_enabled = 1
					AND condition_type = 'points'
				ORDER BY " . $this->db->cast_expr_to_bigint('condition_value') . ' ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * All enabled feat badges.
	 *
	 * @return array
	 */
	public function enabled_feat_badges(): array
	{
		$sql = "SELECT badge_id, badge_name, badge_image, condition_type, condition_value
				FROM {$this->badges_table}
				WHERE badge_enabled = 1
					AND condition_type = 'feat'
				ORDER BY badge_id ASC";
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Returns the set of badge_ids the user already owns.
	 *
	 * @param int $user_id
	 * @return int[]
	 */
	public function user_badge_ids(int $user_id): array
	{
		$sql = 'SELECT badge_id
				FROM ' . $this->user_badges_table . '
				WHERE user_id = ' . (int) $user_id;
		$result = $this->db->sql_query($sql);

		$ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['badge_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Record a badge award. Idempotent — INSERT IGNORE prevents duplicate rows
	 * even if called more than once for the same (user_id, badge_id) pair.
	 *
	 * @param int $user_id
	 * @param int $badge_id
	 * @return void
	 */
	public function award(int $user_id, int $badge_id): void
	{
		$sql = 'INSERT IGNORE INTO ' . $this->user_badges_table . ' ' . $this->db->sql_build_array('INSERT', [
			'user_id'   => (int) $user_id,
			'badge_id'  => (int) $badge_id,
			'earned_at' => time(),
		]);
		$this->db->sql_query($sql);
	}

	/**
	 * Full badge rows for a single user, joined with badge catalog, for profile rendering.
	 * Ordered by earned_at ascending.
	 *
	 * @param int $user_id
	 * @return array  Each row: badge_id, badge_name, badge_image, earned_at.
	 */
	public function user_badges(int $user_id): array
	{
		$sql = 'SELECT ub.badge_id, b.badge_name, b.badge_image, ub.earned_at
				FROM ' . $this->user_badges_table . ' ub
				JOIN ' . $this->badges_table . ' b ON b.badge_id = ub.badge_id
				WHERE ub.user_id = ' . (int) $user_id . '
				ORDER BY ub.earned_at ASC';
		$result = $this->db->sql_query($sql);

		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Batch badge fetch for multiple users (postbit N+1 prevention).
	 * Returns array keyed by user_id => [['badge_name'=>..,'badge_image'=>..], ...].
	 * Only fetches users with positive IDs; guests (id 0) are silently skipped.
	 *
	 * @param int[] $user_ids
	 * @return array
	 */
	public function user_badges_bulk(array $user_ids): array
	{
		$user_ids = array_values(array_unique(array_filter(array_map('intval', $user_ids), function ($id) {
			return $id > 0;
		})));

		if (empty($user_ids))
		{
			return [];
		}

		$sql = 'SELECT ub.user_id, b.badge_name, b.badge_image
				FROM ' . $this->user_badges_table . ' ub
				JOIN ' . $this->badges_table . ' b ON b.badge_id = ub.badge_id
				WHERE ' . $this->db->sql_in_set('ub.user_id', $user_ids) . '
				ORDER BY ub.user_id ASC, ub.badge_id ASC';
		$result = $this->db->sql_query($sql);

		$map = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$uid        = (int) $row['user_id'];
			$map[$uid][] = [
				'badge_name'  => $row['badge_name'],
				'badge_image' => $row['badge_image'],
			];
		}
		$this->db->sql_freeresult($result);

		return $map;
	}


}
