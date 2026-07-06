<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class collect
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \ecyaz\treasurehunt\service\collect_manager */
	protected $collect_manager;

	public function __construct(
		\phpbb\user $user,
		\phpbb\request\request_interface $request,
		\ecyaz\treasurehunt\service\collect_manager $collect_manager
	)
	{
		$this->user            = $user;
		$this->request         = $request;
		$this->collect_manager = $collect_manager;
	}

	/**
	 * POST /treasurehunt/collect  body: token=<40hex>&hash=<csrf>
	 *
	 * @return JsonResponse
	 */
	public function handle(): JsonResponse
	{
		if ((int) $this->user->data['user_id'] === ANONYMOUS)
		{
			return new JsonResponse(['success' => false], 403);
		}

		$hash = $this->request->variable('hash', '');
		if (!check_link_hash($hash, 'treasurehunt_collect'))
		{
			return new JsonResponse(['success' => false], 403);
		}

		$token = $this->request->variable('token', '');
		if (!preg_match('/^[a-f0-9]{40}$/', $token))
		{
			return new JsonResponse(['success' => false], 400);
		}

		try
		{
			$r = $this->collect_manager->collect((int) $this->user->data['user_id'], $token);
		}
		catch (\Throwable $e)
		{
			return new JsonResponse(['success' => false], 500);
		}

		return new JsonResponse([
			'success'   => (bool) ($r['success'] ?? false),
			'points'    => (int) ($r['points'] ?? 0),
			'delta'     => (int) ($r['delta'] ?? 0),
			'newBadges' => $r['newBadges'] ?? [],
		]);
	}
}
