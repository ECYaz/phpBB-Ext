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
 * Discovery endpoint: lists the endpoints other extensions offer through the
 * API, limited to the extensions the credential was given access to.
 */
class extensions extends base
{
	/** @var \ecyaz\phpbbapihook\api\registry */
	protected $registry;

	public function __construct(
		\ecyaz\phpbbapihook\api\gateway $gateway,
		\ecyaz\phpbbapihook\api\responder $responder,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\ecyaz\phpbbapihook\api\registry $registry
	)
	{
		parent::__construct($gateway, $responder, $request, $user);

		$this->registry = $registry;
	}

	/**
	 * GET /api/extensions — endpoints of other extensions this credential may call.
	 */
	public function list_endpoints()
	{
		return $this->run('extensions.list', function (\ecyaz\phpbbapihook\api\auth_context $ctx) {
			$grouped = [];

			foreach ($this->registry->all() as $endpoint)
			{
				$name = $endpoint['extension'];

				if (!$ctx->can_use_extension($name))
				{
					continue;
				}

				if (!isset($grouped[$name]))
				{
					$grouped[$name] = ['name' => $name, 'endpoints' => []];
				}

				$grouped[$name]['endpoints'][] = [
					'action'      => $endpoint['action'],
					'method'      => $endpoint['method'],
					'path'        => $endpoint['path'],
					'description' => $endpoint['description'],
				];
			}

			ksort($grouped);

			return $this->responder->success(['extensions' => array_values($grouped)]);
		});
	}
}
