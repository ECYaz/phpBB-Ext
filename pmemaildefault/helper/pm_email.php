<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\helper;

/**
 * Applies the board-wide PM email default to every existing member.
 *
 * Shared by the ACP module (as the ecyaz.pmemaildefault.helper service) and by
 * migrations/m1_pm_email_default.php (instantiated directly, since migrations
 * have no service container).
 */
class pm_email
{
	/** @var string Notification item the extension manages */
	const ITEM_TYPE = 'notification.type.pm';

	/** @var string Notification method the extension manages */
	const METHOD = 'notification.method.email';

	/** @var int Rows handled per SELECT/INSERT round trip */
	const BATCH_SIZE = 500;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $users_table;

	/** @var string */
	protected $user_notifications_table;

	/**
	 * @param \phpbb\db\driver\driver_interface $db
	 * @param string $users_table              Users table incl. prefix
	 * @param string $user_notifications_table User notifications table incl. prefix
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, $users_table, $user_notifications_table)
	{
		$this->db = $db;
		$this->users_table = $users_table;
		$this->user_notifications_table = $user_notifications_table;
	}

	/**
	 * Force the PM/email notification subscription to $notify for every existing
	 * real user. Portable across phpBB's supported DBs: no INSERT...SELECT against
	 * the target table (which MySQL rejects) and no hand-written LIMIT clause.
	 * Memory stays bounded: users are read and inserted in batches of BATCH_SIZE.
	 *
	 * @param int $notify 1 to switch PM email on for everyone, 0 for off
	 */
	public function apply_to_existing_users($notify)
	{
		$notify = (int) $notify;

		// Set every existing PM/email row to the chosen state.
		$sql = 'UPDATE ' . $this->user_notifications_table . '
			SET notify = ' . $notify . "
			WHERE item_type = '" . $this->db->sql_escape(self::ITEM_TYPE) . "'
				AND item_id = 0
				AND method = '" . $this->db->sql_escape(self::METHOD) . "'";
		$this->db->sql_query($sql);

		// Insert a row in the chosen state for every real user that has none yet.
		// Each round inserts the first BATCH_SIZE uncovered users; the join then
		// no longer matches them, so the next round selects the following batch.
		$sql = 'SELECT u.user_id
			FROM ' . $this->users_table . ' u
			LEFT JOIN ' . $this->user_notifications_table . " n
				ON n.user_id = u.user_id
					AND n.item_type = '" . $this->db->sql_escape(self::ITEM_TYPE) . "'
					AND n.item_id = 0
					AND n.method = '" . $this->db->sql_escape(self::METHOD) . "'
			WHERE u.user_id <> " . ANONYMOUS . '
				AND u.user_type <> ' . USER_IGNORE . '
				AND n.user_id IS NULL
			ORDER BY u.user_id';

		do
		{
			$result = $this->db->sql_query_limit($sql, self::BATCH_SIZE);

			$rows = [];
			while ($row = $this->db->sql_fetchrow($result))
			{
				$rows[] = [
					'item_type'	=> self::ITEM_TYPE,
					'item_id'	=> 0,
					'user_id'	=> (int) $row['user_id'],
					'method'	=> self::METHOD,
					'notify'	=> $notify,
				];
			}
			$this->db->sql_freeresult($result);

			if (!empty($rows))
			{
				$this->db->sql_multi_insert($this->user_notifications_table, $rows);
			}
		}
		while (count($rows) == self::BATCH_SIZE);
	}
}
