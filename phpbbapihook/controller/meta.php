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
 * Introspection endpoint: tells the caller which account the credential acts as
 * and what the credential is restricted to. Useful for clients to verify their
 * access before attempting writes.
 */
class meta extends base
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	public function __construct(
		\ecyaz\phpbbapihook\api\gateway $gateway,
		\ecyaz\phpbbapihook\api\responder $responder,
		\phpbb\request\request_interface $request,
		\phpbb\user $user,
		\phpbb\auth\auth $auth
	)
	{
		parent::__construct($gateway, $responder, $request, $user);

		$this->auth = $auth;
	}

	/**
	 * GET /api/me/permissions — identity and access summary for the credential.
	 */
	public function my_permissions()
	{
		return $this->run('me.permissions', function (\ecyaz\phpbbapihook\api\auth_context $ctx) {
			return $this->responder->success([
				'user_id'        => (int) $this->user->data['user_id'],
				'username'       => $this->plain($this->user->data['username']),
				'read_only'      => $ctx->is_read_only(),
				'is_founder'     => (int) $this->user->data['user_type'] === USER_FOUNDER,
				'allowed_forums' => $ctx->get_allowed_forums(),
				'allowed_extensions' => $ctx->get_allowed_extensions(),
			]);
		});
	}
}
