<?php
namespace ecyaz\pmemaildefault\migrations;

class m1_pm_email_default extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'enable_pm_email_for_existing_users']]],
		];
	}

	/**
	 * Force the PM/email notification subscription ON for every existing real user.
	 *
	 * Portable across phpBB's supported DBs: no INSERT...SELECT against the target table
	 * (which MySQL rejects). We read the current state, UPDATE existing rows, then
	 * multi-insert rows for users who have none.
	 */
	public function enable_pm_email_for_existing_users()
	{
		$item_type = 'notification.type.pm';
		$method    = 'notification.method.email';
		$notif_tbl = $this->table_prefix . 'user_notifications';
		$users_tbl = $this->table_prefix . 'users';

		// Collect users who already have an explicit PM/email row.
		$existing = [];
		$sql = 'SELECT user_id
			FROM ' . $notif_tbl . "
			WHERE item_type = '" . $this->db->sql_escape($item_type) . "'
				AND item_id = 0
				AND method = '" . $this->db->sql_escape($method) . "'";
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$existing[(int) $row['user_id']] = true;
		}
		$this->db->sql_freeresult($result);

		// Force every existing PM/email row ON (overrides users who turned it off).
		$sql = 'UPDATE ' . $notif_tbl . "
			SET notify = 1
			WHERE item_type = '" . $this->db->sql_escape($item_type) . "'
				AND item_id = 0
				AND method = '" . $this->db->sql_escape($method) . "'";
		$this->db->sql_query($sql);

		// Insert a notify=1 row for every real user that has no row yet.
		$rows = [];
		$sql = 'SELECT user_id
			FROM ' . $users_tbl . '
			WHERE user_id <> ' . ANONYMOUS . '
				AND user_type <> ' . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_id = (int) $row['user_id'];
			if (isset($existing[$user_id]))
			{
				continue;
			}
			$rows[] = [
				'item_type'	=> $item_type,
				'item_id'	=> 0,
				'user_id'	=> $user_id,
				'method'	=> $method,
				'notify'	=> 1,
			];
		}
		$this->db->sql_freeresult($result);

		if (!empty($rows))
		{
			$this->db->sql_multi_insert($notif_tbl, $rows);
		}
	}
}
