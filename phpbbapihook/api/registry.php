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
 * The list of API endpoints offered by other extensions.
 *
 * Other extensions announce their endpoints by listening to the
 * ecyaz.phpbbapihook.collect_endpoints event. Only endpoints that pass the
 * checks below are kept, and gateway::handle() refuses to run anything that is
 * not in this list.
 */
class registry
{
	/** Path prefix every extension endpoint lives under, followed by vendor/package/. */
	const PATH_PREFIX = '/api/ext/';

	/** @var \phpbb\event\dispatcher_interface */
	protected $dispatcher;

	/** @var \phpbb\extension\manager */
	protected $extension_manager;

	/** @var array|null Validated endpoints keyed by "vendor/package:action", collected once per request. */
	protected $endpoints = null;

	public function __construct(\phpbb\event\dispatcher_interface $dispatcher, \phpbb\extension\manager $extension_manager)
	{
		$this->dispatcher        = $dispatcher;
		$this->extension_manager = $extension_manager;
	}

	/**
	 * Whether a string is a well formed extension name (vendor/package).
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function is_valid_extension_name($name)
	{
		return is_string($name) && (bool) preg_match('#^[a-zA-Z0-9_]{1,60}/[a-zA-Z0-9_]{1,60}\z#', $name);
	}

	/**
	 * All registered endpoints.
	 *
	 * @return array[] Rows with extension, action, method, path, description.
	 */
	public function all()
	{
		if ($this->endpoints === null)
		{
			$this->endpoints = $this->collect();
		}

		return array_values($this->endpoints);
	}

	/**
	 * Look one endpoint up.
	 *
	 * @param string $extension vendor/package
	 * @param string $action    Action name the extension registered
	 * @return array|null The endpoint row, or null when it was never registered.
	 */
	public function find($extension, $action)
	{
		$this->all();

		$key = $extension . ':' . $action;

		return isset($this->endpoints[$key]) ? $this->endpoints[$key] : null;
	}

	/**
	 * Names of the extensions that offer at least one endpoint.
	 *
	 * @return string[]
	 */
	public function extension_names()
	{
		$names = [];

		foreach ($this->all() as $endpoint)
		{
			$names[$endpoint['extension']] = true;
		}

		$names = array_keys($names);
		sort($names);

		return $names;
	}

	/**
	 * Fire the registration event and keep the entries that are valid.
	 *
	 * @return array
	 */
	protected function collect()
	{
		$endpoints = [];

		/**
		 * Announce API endpoints that your extension offers through phpbbAPIhook.
		 *
		 * Add one row per endpoint to the endpoints array:
		 *
		 *     $endpoints = $event['endpoints'];
		 *     $endpoints[] = [
		 *         'extension'   => 'acme/demo',
		 *         'action'      => 'topic.freeze',
		 *         'method'      => 'POST',
		 *         'path'        => '/api/ext/acme/demo/topics/{topic_id}/freeze',
		 *         'description' => 'Freeze a topic.',
		 *     ];
		 *     $event['endpoints'] = $endpoints;
		 *
		 * The path must start with /api/ext/ followed by your own extension
		 * name, and must match a route of your extension. Rows that do not
		 * pass these checks are ignored.
		 *
		 * @event ecyaz.phpbbapihook.collect_endpoints
		 * @var array	endpoints	List of endpoint rows (extension, action, method, path, description)
		 * @since 1.2.0
		 */
		$vars = ['endpoints'];
		extract($this->dispatcher->trigger_event('ecyaz.phpbbapihook.collect_endpoints', compact($vars)));

		$valid = [];

		if (!is_array($endpoints))
		{
			return $valid;
		}

		foreach ($endpoints as $row)
		{
			$clean = $this->validate($row);

			if ($clean !== null)
			{
				$valid[$clean['extension'] . ':' . $clean['action']] = $clean;
			}
		}

		return $valid;
	}

	/**
	 * Check one announced endpoint.
	 *
	 * @param mixed $row
	 * @return array|null The cleaned row, or null when it has to be ignored.
	 */
	protected function validate($row)
	{
		if (!is_array($row) || !isset($row['extension'], $row['action'], $row['method'], $row['path']))
		{
			return null;
		}

		$extension = $row['extension'];
		$action    = $row['action'];
		$method    = is_string($row['method']) ? strtoupper($row['method']) : '';
		$path      = $row['path'];

		if (!self::is_valid_extension_name($extension) || !$this->extension_manager->is_enabled($extension))
		{
			return null;
		}

		if (!is_string($action) || !preg_match('#^[a-z0-9_.]{1,100}\z#', $action))
		{
			return null;
		}

		if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true))
		{
			return null;
		}

		if (!is_string($path) || strpos($path, self::PATH_PREFIX . $extension . '/') !== 0)
		{
			return null;
		}

		$description = (isset($row['description']) && is_string($row['description'])) ? $row['description'] : '';

		return [
			'extension'   => $extension,
			'action'      => $action,
			'method'      => $method,
			'path'        => utf8_substr($path, 0, 255),
			'description' => utf8_substr($description, 0, 255),
		];
	}
}
