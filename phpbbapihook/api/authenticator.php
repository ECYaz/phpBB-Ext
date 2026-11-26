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
 * The security gate for every API request. It validates the credential and then
 * switches the runtime to act as the credential's phpBB user, so that all
 * downstream permission checks ($auth->acl_get(...)) and content creation
 * (submit_post) run with that account's native phpBB permissions. The API can
 * therefore never do anything the linked account could not do in the board.
 */
class authenticator
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var credential_manager */
	protected $credentials;

	/** @var logger */
	protected $logger;

	/** @var string */
	protected $users_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\auth\auth $auth,
		\phpbb\db\driver\driver_interface $db,
		credential_manager $credentials,
		logger $logger,
		$users_table
	)
	{
		$this->config      = $config;
		$this->request     = $request;
		$this->user        = $user;
		$this->auth        = $auth;
		$this->db          = $db;
		$this->credentials = $credentials;
		$this->logger      = $logger;
		$this->users_table = $users_table;
	}

	/**
	 * Validate the request and become the credential's user.
	 *
	 * @return auth_context
	 * @throws exception On any authentication or authorization failure.
	 */
	public function authenticate()
	{
		if (!(int) $this->config['phpbbapihook_master_enabled'])
		{
			throw new exception('api_disabled', 503);
		}

		if ((int) $this->config['phpbbapihook_require_https'] && !$this->request->is_secure())
		{
			throw new exception('https_required', 403);
		}

		$token = $this->extract_token();

		if ($token === '')
		{
			throw new exception('missing_token', 401);
		}

		$cred = $this->credentials->find_by_token($token);

		if ($cred === null)
		{
			throw new exception('invalid_token', 401);
		}

		if (!(int) $cred['is_enabled'])
		{
			throw new exception('credential_disabled', 403);
		}

		if ((int) $cred['expiration'] > 0 && (int) $cred['expiration'] < time())
		{
			throw new exception('credential_expired', 403);
		}

		$ip = (string) $this->user->ip;

		if (!$this->ip_allowed($cred, $ip))
		{
			throw new exception('ip_not_allowed', 403);
		}

		if ((int) $cred['rate_limit'] > 0)
		{
			$window = (int) $this->config['phpbbapihook_rate_window'];
			$count  = $this->logger->count_since((int) $cred['key_id'], time() - $window);

			if ($count >= (int) $cred['rate_limit'])
			{
				throw new exception('rate_limit_exceeded', 429);
			}
		}

		$user_row = $this->load_user((int) $cred['user_id']);

		if ($user_row === null)
		{
			throw new exception('account_unavailable', 403);
		}

		// A banned account must not be able to act through the API, just as it
		// cannot log in to the board. check_ban() returns ban info (truthy) when
		// the user is banned and is not excluded; $return = true keeps it from
		// rendering phpBB's ban page and exiting.
		if ($this->user->check_ban((int) $cred['user_id'], false, false, true))
		{
			throw new exception('account_banned', 403);
		}

		// Become the credential's user: replace the current (guest) session data
		// with the linked account and load its ACL. From here on every
		// permission check and submit_post() call runs as this account.
		$this->user->data = array_merge($this->user->data, $user_row);

		// The session started as a guest. load_user() only returns real
		// accounts, so set the two flags the session code derives from the
		// user row; core and other extensions read them to tell a member
		// from a guest.
		$this->user->data['is_registered'] = true;
		$this->user->data['is_bot']        = false;
		$this->auth->acl($this->user->data);

		$this->credentials->touch((int) $cred['key_id'], $ip);

		return new auth_context($cred, $user_row);
	}

	/**
	 * Read the bearer token from the Authorization header, falling back to the
	 * X-API-Key header (some server configurations strip Authorization).
	 *
	 * @return string The token, or '' if none was supplied.
	 */
	protected function extract_token()
	{
		$authorization = trim((string) $this->request->header('Authorization'));

		if ($authorization !== '' && preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches))
		{
			return $matches[1];
		}

		return trim((string) $this->request->header('X-API-Key'));
	}

	/**
	 * @return bool True if the credential has no IP allow-list, or $ip is on it.
	 */
	protected function ip_allowed(array $cred, $ip)
	{
		$raw = trim((string) $cred['ip_allowlist']);

		if ($raw === '')
		{
			return true;
		}

		$list = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

		return in_array($ip, $list, true);
	}

	/**
	 * Load the linked phpBB user, excluding the anonymous user, bots and
	 * inactive accounts.
	 *
	 * @return array|null
	 */
	protected function load_user($user_id)
	{
		if ($user_id <= 0)
		{
			return null;
		}

		$sql = 'SELECT * FROM ' . $this->users_table . '
			WHERE user_id = ' . (int) $user_id . '
				AND user_id <> ' . ANONYMOUS . '
				AND user_type <> ' . USER_INACTIVE . '
				AND user_type <> ' . USER_IGNORE;
		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return ($row === false) ? null : $row;
	}
}
