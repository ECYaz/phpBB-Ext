<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\api;

/**
 * Owns the phpbb_apihook_log audit table. Every API request — success or
 * failure — is recorded here, and the same table is the source of truth for
 * per-credential rate limiting (count_since).
 */
class logger
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $log_table;

	public function __construct(\phpbb\db\driver\driver_interface $db, $log_table)
	{
		$this->db        = $db;
		$this->log_table = $log_table;
	}

	/**
	 * Append an audit entry.
	 *
	 * @param int    $key_id  Credential id (0 if the request never authenticated)
	 * @param string $ip      Client IP
	 * @param string $method  HTTP method
	 * @param string $route   Route name / path
	 * @param string $action  Logical action, e.g. 'topic.create' or 'auth.fail'
	 * @param int    $status  HTTP status returned
	 * @param string $detail  Optional detail / error code
	 */
	public function log($key_id, $ip, $method, $route, $action, $status, $detail = '')
	{
		$row = [
			'key_id'     => (int) $key_id,
			'log_time'   => time(),
			'log_ip'     => substr((string) $ip, 0, 40),
			'log_method' => substr((string) $method, 0, 8),
			'log_route'  => utf8_substr((string) $route, 0, 255),
			'log_action' => utf8_substr((string) $action, 0, 255),
			'log_status' => (int) $status,
			'log_detail' => (string) $detail,
		];

		$sql = 'INSERT INTO ' . $this->log_table . ' ' . $this->db->sql_build_array('INSERT', $row);
		$this->db->sql_query($sql);
	}

	/**
	 * Number of requests logged for a credential since a unix timestamp. Used
	 * for rate limiting. Requests that were refused for the rate limit itself
	 * are not counted, or a client that keeps trying would never get back in.
	 */
	public function count_since($key_id, $since)
	{
		$sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->log_table . '
			WHERE key_id = ' . (int) $key_id . '
				AND log_time > ' . (int) $since . '
				AND log_status <> 429';
		$result = $this->db->sql_query($sql);
		$cnt = (int) $this->db->sql_fetchfield('cnt');
		$this->db->sql_freeresult($result);

		return $cnt;
	}

	/**
	 * Delete audit entries older than a unix timestamp.
	 *
	 * @param int $before
	 */
	public function prune($before)
	{
		$sql = 'DELETE FROM ' . $this->log_table . '
			WHERE log_time < ' . (int) $before;
		$this->db->sql_query($sql);
	}

	/**
	 * Most recent audit entries for a credential (for the ACP usage view).
	 * Key id 0 holds the requests that came without a valid token.
	 *
	 * @return array
	 */
	public function recent($key_id, $limit = 50)
	{
		$rows = [];
		$sql = 'SELECT * FROM ' . $this->log_table . '
			WHERE key_id = ' . (int) $key_id . '
			ORDER BY log_id DESC';
		$result = $this->db->sql_query_limit($sql, (int) $limit);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}
}
