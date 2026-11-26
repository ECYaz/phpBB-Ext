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
 * Owns the phpbb_apihook_keys table: creating credentials (and the one-time
 * plaintext token), looking them up by token, and the CRUD used by the ACP.
 *
 * Tokens are never stored in plaintext. A token is a 256-bit random string;
 * we store only its SHA-256 hash. SHA-256 is sufficient here (unlike a password
 * hash) because the token has full cryptographic entropy, so it cannot be
 * brute-forced from the stored hash. The deterministic hash also lets us look a
 * credential up in one indexed query.
 */
class credential_manager
{
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var string */
	protected $keys_table;

	/** Fields an admin/API may set on a credential. */
	protected static $writable = [
		'key_name', 'user_id', 'forum_ids', 'ip_allowlist',
		'rate_limit', 'read_only', 'is_enabled', 'expiration', 'ext_access',
	];

	public function __construct(\phpbb\db\driver\driver_interface $db, $keys_table)
	{
		$this->db         = $db;
		$this->keys_table = $keys_table;
	}

	/**
	 * Create a credential and return its id together with the one-time plaintext
	 * token. The caller MUST show the token to the admin now; it can never be
	 * recovered afterwards.
	 *
	 * @param array $data Overrides for the writable fields.
	 * @return array{key_id:int, token:string}
	 */
	public function create(array $data = [])
	{
		$secret = bin2hex(random_bytes(32));
		$token  = 'pbapi_' . $secret;

		$row = array_merge([
			'key_name'     => '',
			'user_id'      => 0,
			'forum_ids'    => '',
			'ip_allowlist' => '',
			'rate_limit'   => 0,
			'read_only'    => 0,
			'is_enabled'   => 1,
			'expiration'   => 0,
			'ext_access'   => '',
		], array_intersect_key($data, array_flip(self::$writable)));

		$row['key_token_hash'] = hash('sha256', $token);
		$row['key_prefix']     = substr($secret, 0, 8);
		$row['last_used']      = 0;
		$row['last_ip']        = '';
		$row['created_at']     = time();

		$sql = 'INSERT INTO ' . $this->keys_table . ' ' . $this->db->sql_build_array('INSERT', $row);
		$this->db->sql_query($sql);

		return ['key_id' => (int) $this->db->sql_nextid(), 'token' => $token];
	}

	/**
	 * Look up an enabled-or-disabled credential by its plaintext token.
	 *
	 * @param string $token
	 * @return array|null The credential row, or null if no match.
	 */
	public function find_by_token($token)
	{
		$hash = hash('sha256', (string) $token);

		$sql = 'SELECT * FROM ' . $this->keys_table . "
			WHERE key_token_hash = '" . $this->db->sql_escape($hash) . "'";
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ($row === false) ? null : $row;
	}

	public function get($key_id)
	{
		$sql = 'SELECT * FROM ' . $this->keys_table . ' WHERE key_id = ' . (int) $key_id;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ($row === false) ? null : $row;
	}

	/**
	 * @return array All credentials, newest first.
	 */
	public function all()
	{
		$rows = [];
		$sql = 'SELECT * FROM ' . $this->keys_table . ' ORDER BY key_id DESC';
		$result = $this->db->sql_query($sql);
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	public function update($key_id, array $data)
	{
		$row = array_intersect_key($data, array_flip(self::$writable));

		if (empty($row))
		{
			return;
		}

		$sql = 'UPDATE ' . $this->keys_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $row) . '
			WHERE key_id = ' . (int) $key_id;
		$this->db->sql_query($sql);
	}

	public function delete($key_id)
	{
		$sql = 'DELETE FROM ' . $this->keys_table . ' WHERE key_id = ' . (int) $key_id;
		$this->db->sql_query($sql);
	}

	/**
	 * Record that a credential was just used (last-used time and client IP).
	 */
	public function touch($key_id, $ip)
	{
		$sql = 'UPDATE ' . $this->keys_table . "
			SET last_used = " . time() . ", last_ip = '" . $this->db->sql_escape((string) $ip) . "'
			WHERE key_id = " . (int) $key_id;
		$this->db->sql_query($sql);
	}
}
