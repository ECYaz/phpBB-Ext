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

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The single entry point every API request goes through.
 *
 * It authenticates the credential, becomes the linked phpBB user, applies the
 * credential's restrictions, runs the endpoint, turns every failure into the
 * JSON error envelope and writes one audit row.
 *
 * Other extensions use handle() to offer their own endpoints behind the same
 * credentials, restrictions and audit log. See the README section
 * "For extension authors".
 */
class gateway
{
	/** @var authenticator */
	protected $authenticator;

	/** @var responder */
	protected $responder;

	/** @var logger */
	protected $logger;

	/** @var registry */
	protected $registry;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\symfony_request */
	protected $symfony_request;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	public function __construct(
		authenticator $authenticator,
		responder $responder,
		logger $logger,
		registry $registry,
		\phpbb\request\request_interface $request,
		\phpbb\symfony_request $symfony_request,
		\phpbb\user $user,
		\phpbb\event\dispatcher_interface $dispatcher
	)
	{
		$this->authenticator   = $authenticator;
		$this->responder       = $responder;
		$this->logger          = $logger;
		$this->registry        = $registry;
		$this->request         = $request;
		$this->symfony_request = $symfony_request;
		$this->user            = $user;
		$this->dispatcher      = $dispatcher;
	}

	/**
	 * Run one of phpbbAPIhook's own endpoints.
	 *
	 * Not for other extensions: it skips the per credential extension access,
	 * the registry and the path check. Other extensions call handle().
	 *
	 * @internal
	 * @param string   $action Logged action name, e.g. 'topic.create'.
	 * @param callable $fn     function(auth_context $ctx): JsonResponse|array
	 * @return JsonResponse
	 */
	public function run($action, callable $fn)
	{
		return $this->execute('', (string) $action, $fn);
	}

	/**
	 * Run an endpoint offered by another extension.
	 *
	 * The endpoint must have been announced through the
	 * ecyaz.phpbbapihook.collect_endpoints event, and the credential must have
	 * been given access to the extension in the ACP. Inside $fn the request
	 * already runs as the linked phpBB user, so the usual $auth->acl_get()
	 * checks apply, including the extension's own permissions.
	 *
	 * @param string   $extension The calling extension, vendor/package.
	 * @param string   $action    The action name used when registering.
	 * @param callable $fn        function(auth_context $ctx): array|JsonResponse.
	 *                            An array is sent as a success response.
	 * @return JsonResponse
	 */
	public function handle($extension, $action, callable $fn)
	{
		return $this->execute((string) $extension, (string) $action, $fn);
	}

	/**
	 * Build a success response with a status other than 200.
	 *
	 * @param array $data
	 * @param int   $status
	 * @return JsonResponse
	 */
	public function success(array $data = [], $status = 200)
	{
		return $this->responder->success($data, $status);
	}

	/**
	 * Stop the endpoint with a JSON error.
	 *
	 * @param string $error_code Machine readable code, e.g. 'topic_not_found'.
	 * @param int    $status     HTTP status.
	 * @throws exception Always.
	 */
	public function fail($error_code, $status = 400)
	{
		throw new exception((string) $error_code, (int) $status);
	}

	/**
	 * @param string   $extension '' for a core endpoint, else vendor/package.
	 * @param string   $action
	 * @param callable $fn
	 * @return JsonResponse
	 */
	protected function execute($extension, $action, callable $fn)
	{
		$key_id     = 0;
		$log_action = ($extension !== '') ? $extension . ':' . $action : $action;

		try
		{
			$ctx    = $this->authenticator->authenticate();
			$key_id = $ctx->get_key_id();

			$method   = strtoupper((string) $this->request->server('REQUEST_METHOD', 'GET'));
			$is_write = !in_array($method, ['GET', 'HEAD'], true);

			if ($extension !== '')
			{
				$this->check_extension_endpoint($ctx, $extension, $action, $method);
			}

			// A read only credential never changes anything, whichever endpoint
			// it calls. Checked here so that no endpoint can forget it.
			if ($is_write && $ctx->is_read_only())
			{
				throw new exception('read_only_credential', 403);
			}

			$user_id      = $ctx->get_user_id();
			$read_only    = $ctx->is_read_only();
			$error        = '';
			$error_status = 403;

			/**
			 * A request has been authenticated and is about to run.
			 *
			 * Set error to a machine readable code (lower case letters, digits,
			 * dots and underscores) to refuse the request; it is returned to the
			 * client with the HTTP status in error_status. Any other value that
			 * is not empty refuses the request as request_refused. The other
			 * variables are for reading only; changes to them are ignored.
			 *
			 * @event ecyaz.phpbbapihook.request_authenticated
			 * @var string	extension		Extension offering the endpoint (vendor/package), empty for core endpoints
			 * @var string	action			Action name, e.g. topic.create
			 * @var string	method			HTTP method
			 * @var int		key_id			Id of the API credential
			 * @var int		user_id			Id of the user the request runs as
			 * @var bool	read_only		Whether the credential is read only
			 * @var string	error			Set to an error code to refuse the request
			 * @var int		error_status	HTTP status sent with the error, 403 by default
			 * @since 1.2.0
			 */
			$vars = [
				'extension',
				'action',
				'method',
				'key_id',
				'user_id',
				'read_only',
				'error',
				'error_status',
			];
			$event = $this->dispatcher->trigger_event('ecyaz.phpbbapihook.request_authenticated', compact($vars));

			// Only the refusal is read back. Everything else is context for the
			// listener and stays as the gateway set it.
			if (!empty($event['error']))
			{
				$error = (is_string($event['error']) && preg_match('#^[a-z0-9_.]{1,100}\z#', $event['error'])) ? $event['error'] : 'request_refused';

				throw new exception($error, $this->clean_error_status($event['error_status']));
			}

			$result = $fn($ctx);

			if (is_array($result))
			{
				$result = $this->responder->success($result);
			}

			if (!($result instanceof JsonResponse))
			{
				throw new \UnexpectedValueException('API endpoint returned neither an array nor a JSON response.');
			}

			$response = $this->filter_response($extension, $action, $ctx, $result);

			$this->audit($key_id, $log_action, $response->getStatusCode(), '');

			return $response;
		}
		catch (exception $e)
		{
			$this->audit($key_id, $log_action, $e->get_status_code(), $e->get_error_code());

			return $this->responder->from_exception($e);
		}
		catch (\Throwable $e)
		{
			$this->audit($key_id, $log_action, 500, 'internal_error');

			return $this->responder->error('internal_error', 500);
		}
	}

	/**
	 * Checks that apply only to endpoints offered by other extensions.
	 *
	 * @throws exception
	 */
	protected function check_extension_endpoint(auth_context $ctx, $extension, $action, $method)
	{
		// A credential without access to the extension gets the same answer
		// whether or not the action was announced.
		if (!registry::is_valid_extension_name($extension) || !$ctx->can_use_extension($extension))
		{
			throw new exception('extension_not_allowed', 403);
		}

		$endpoint = $this->registry->find($extension, $action);

		if ($endpoint === null)
		{
			throw new exception('endpoint_not_registered', 404);
		}

		$expected = ($method === 'HEAD') ? 'GET' : $method;

		if ($endpoint['method'] !== $expected)
		{
			throw new exception('method_not_allowed', 405);
		}

		// Extension endpoints live under their own /api/ext/vendor/package/
		// prefix, so they can never shadow a core endpoint or one another.
		$prefix = registry::PATH_PREFIX . $extension . '/';

		if (strpos((string) $this->symfony_request->getPathInfo(), $prefix) !== 0)
		{
			throw new exception('endpoint_path_mismatch', 404);
		}
	}

	/**
	 * Let other extensions add to (or change) the data of a success response.
	 *
	 * @return JsonResponse
	 */
	protected function filter_response($extension, $action, auth_context $ctx, JsonResponse $response)
	{
		$status = $response->getStatusCode();

		if ($status < 200 || $status >= 300)
		{
			return $response;
		}

		$data = json_decode($response->getContent(), true);

		if (!is_array($data))
		{
			return $response;
		}

		$key_id   = $ctx->get_key_id();
		$user_id  = $ctx->get_user_id();

		/**
		 * A request succeeded and its JSON response is about to be sent.
		 *
		 * Change data to add fields to the response; changes to the other
		 * variables are ignored. The request still runs
		 * as the linked user here, so $auth->acl_get() can be used to decide
		 * what that user may see.
		 *
		 * @event ecyaz.phpbbapihook.response_before
		 * @var string	extension	Extension offering the endpoint (vendor/package), empty for core endpoints
		 * @var string	action		Action name, e.g. topic.read
		 * @var int		key_id		Id of the API credential
		 * @var int		user_id		Id of the user the request runs as
		 * @var int		status		HTTP status of the response
		 * @var array	data		Response body as an array
		 * @since 1.2.0
		 */
		$vars = [
			'extension',
			'action',
			'key_id',
			'user_id',
			'status',
			'data',
		];
		$event = $this->dispatcher->trigger_event('ecyaz.phpbbapihook.response_before', compact($vars));

		// Only the response body is read back.
		if (is_array($event['data']) && $event['data'] !== $data)
		{
			$response->setData($event['data']);
		}

		return $response;
	}

	/**
	 * @param mixed $status
	 * @return int A client or server error status.
	 */
	protected function clean_error_status($status)
	{
		$status = (int) $status;

		return ($status >= 400 && $status <= 599) ? $status : 403;
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
}
