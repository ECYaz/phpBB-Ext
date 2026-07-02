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

use ecyaz\phpbbapihook\api\exception;

/**
 * Shared plumbing for every API controller: it authenticates the request,
 * dispatches the action, converts any api\exception into a JSON error, writes
 * one audit-log row per request, and parses JSON (or form) input.
 *
 * Subclasses implement the individual endpoints and call run() to execute them
 * inside this envelope.
 */
abstract class base
{
	/** @var \ecyaz\phpbbapihook\api\authenticator */
	protected $authenticator;

	/** @var \ecyaz\phpbbapihook\api\responder */
	protected $responder;

	/** @var \ecyaz\phpbbapihook\api\logger */
	protected $logger;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var array|null Parsed JSON request body, cached. */
	private $body_cache = null;

	public function __construct(
		\ecyaz\phpbbapihook\api\authenticator $authenticator,
		\ecyaz\phpbbapihook\api\responder $responder,
		\ecyaz\phpbbapihook\api\logger $logger,
		\phpbb\request\request_interface $request,
		\phpbb\user $user
	)
	{
		$this->authenticator = $authenticator;
		$this->responder     = $responder;
		$this->logger        = $logger;
		$this->request       = $request;
		$this->user          = $user;
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
		$key_id = 0;

		try
		{
			$ctx    = $this->authenticator->authenticate();
			$key_id = $ctx->get_key_id();

			$response = $fn($ctx);

			$this->audit($key_id, $action_name, $response->getStatusCode(), '');

			return $response;
		}
		catch (exception $e)
		{
			$this->audit($key_id, $action_name, $e->get_status_code(), $e->get_error_code());

			return $this->responder->from_exception($e);
		}
		catch (\Exception $e)
		{
			$this->audit($key_id, $action_name, 500, 'internal_error');

			return $this->responder->error('internal_error', 500);
		}
	}

	protected function audit($key_id, $action, $status, $detail)
	{
		$this->logger->log(
			$key_id,
			(string) $this->user->ip,
			(string) $this->request->server('REQUEST_METHOD'),
			(string) $this->request->server('REQUEST_URI'),
			$action,
			$status,
			$detail
		);
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
			return is_scalar($body[$name]) ? (string) $body[$name] : $default;
		}

		return (string) $this->request->variable($name, $default, true);
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
			return ($post_username !== '') ? $post_username : $username;
		}

		if ($username !== '')
		{
			return $username;
		}

		// Hard-deleted registered poster: fall back to any stored guest name.
		return $post_username;
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
