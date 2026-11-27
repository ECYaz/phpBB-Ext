<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\controller;

/**
 * Shared plumbing for every API controller: it hands the request to the
 * gateway (authentication, credential restrictions, JSON errors, audit log)
 * and parses JSON (or form) input.
 *
 * Subclasses implement the individual endpoints and call run() to execute them
 * inside this envelope.
 */
abstract class base
{
	/** @var \ecyaz\phpbbapihook\api\gateway */
	protected $gateway;

	/** @var \ecyaz\phpbbapihook\api\responder */
	protected $responder;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var array|null Parsed JSON request body, cached. */
	private $body_cache = null;

	public function __construct(
		\ecyaz\phpbbapihook\api\gateway $gateway,
		\ecyaz\phpbbapihook\api\responder $responder,
		\phpbb\request\request_interface $request,
		\phpbb\user $user
	)
	{
		$this->gateway   = $gateway;
		$this->responder = $responder;
		$this->request   = $request;
		$this->user      = $user;
	}

	/**
	 * Authenticate, run an action, and always return a JSON response.
	 *
	 * @param string   $action_name Logged action identifier, e.g. 'topic.create'.
	 * @param callable $fn          function(auth_context $ctx): \Symfony\Component\HttpFoundation\JsonResponse
	 * @return \Symfony\Component\HttpFoundation\JsonResponse
	 */
	protected function run($action_name, callable $fn)
	{
		return $this->gateway->run($action_name, $fn);
	}

	/**
	 * Parsed JSON request body (empty array if the body is not valid JSON).
	 *
	 * @return array
	 */
	protected function body()
	{
		if ($this->body_cache === null)
		{
			$decoded = json_decode((string) file_get_contents('php://input'), true);
			$this->body_cache = is_array($decoded) ? $decoded : [];
		}

		return $this->body_cache;
	}

	/**
	 * Read a string input: JSON body first, then a form/query parameter.
	 */
	protected function input_string($name, $default = '')
	{
		$body = $this->body();

		if (array_key_exists($name, $body))
		{
			if (!is_scalar($body[$name]))
			{
				return $default;
			}

			// A JSON value gets what the request class gives a form value:
			// trimmed, HTML escaped, NFC normalised, invalid UTF-8 refused.
			$value = '';
			$type_cast_helper = new \phpbb\request\type_cast_helper();
			$type_cast_helper->set_var($value, $body[$name], 'string', true);

			return $value;
		}

		return (string) $this->request->variable($name, $default, true);
	}

	/**
	 * Text as it leaves the API.
	 *
	 * phpBB stores titles, subjects, names and search words HTML escaped. A
	 * JSON client expects plain text, and escapes it again when it sends the
	 * value back, so the escaping is taken off here. Emoji are stored as
	 * character references (utf8_encode_ucr) and are turned back into
	 * characters first; a reference that a user typed is stored with an
	 * escaped ampersand and stays text.
	 *
	 * @param mixed $text
	 * @return string
	 */
	protected function plain($text)
	{
		$text = preg_replace_callback('/&#(x[0-9A-Fa-f]{5,6}|[0-9]{5,7});/', function ($match) {
			$code_point = ($match[1][0] === 'x') ? hexdec(substr($match[1], 1)) : (int) $match[1];

			return ($code_point >= 0x10000 && $code_point <= 0x10FFFF) ? utf8_chr($code_point) : $match[0];
		}, (string) $text);

		return htmlspecialchars_decode($text, ENT_COMPAT);
	}

	/**
	 * Read an integer input: JSON body first, then a form/query parameter.
	 */
	protected function input_int($name, $default = 0)
	{
		$body = $this->body();

		if (array_key_exists($name, $body))
		{
			return (int) $body[$name];
		}

		return (int) $this->request->variable($name, $default);
	}

	/**
	 * Resolve the display name for a post row.
	 *
	 * Anonymous posts use the stored guest name (post_username). For a
	 * registered poster we use the LEFT-JOINed username; if that is null/empty
	 * (the account was hard-deleted) we fall back to any stored post_username.
	 *
	 * @param array $row Row with poster_id, username, post_username.
	 * @return string
	 */
	protected function resolve_poster(array $row)
	{
		$username      = isset($row['username']) ? (string) $row['username'] : '';
		$post_username = isset($row['post_username']) ? (string) $row['post_username'] : '';

		if ((int) $row['poster_id'] === ANONYMOUS)
		{
			return $this->plain(($post_username !== '') ? $post_username : $username);
		}

		if ($username !== '')
		{
			return $this->plain($username);
		}

		// Hard-deleted registered poster: fall back to any stored guest name.
		return $this->plain($post_username);
	}

	/**
	 * Parse limit/offset query parameters with clamping.
	 *
	 * @param int $default_limit Default number of rows per page.
	 * @param int $max_limit     Hard upper bound for limit.
	 * @return array{limit:int,offset:int}
	 */
	protected function paginate_args($default_limit = 25, $max_limit = 100)
	{
		$limit  = (int) $this->request->variable('limit', $default_limit);
		$offset = (int) $this->request->variable('offset', 0);

		if ($limit < 1)
		{
			$limit = $default_limit;
		}
		if ($limit > $max_limit)
		{
			$limit = $max_limit;
		}
		if ($offset < 0)
		{
			$offset = 0;
		}

		return ['limit' => $limit, 'offset' => $offset];
	}
}
