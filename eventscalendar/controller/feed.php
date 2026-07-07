<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * ICS (iCalendar) feed controller — GET-only, route ecyaz_eventscalendar_ics
 * (/calendar/feed.ics). No template render: this always returns a raw
 * Symfony Response (VERIFY pattern: core's phpbb\feed\controller\feed::
 * send_feed_do() builds `new Response($content)` and sets headers directly
 * via $response->headers->set(), the same shape used here).
 *
 * Access control (binding, task-7 brief):
 *  - !ecal_ics_enable -> 404 (PAGE_NOT_FOUND, a core lang key — no new
 *    lang string needed for this task; ACP-facing strings are Task 10).
 *  - enabled + ecal_ics_public -> open to anyone, no auth/permission check
 *    at all. A public ICS feed is meant to be pulled by external calendar
 *    clients that cannot log in or hold phpBB permissions.
 *  - enabled + NOT public -> the `key` GET param must hash_equals() the
 *    configured ecal_ics_token, else 403 (NOT_AUTHORISED, core lang key).
 *  Deliberately NOT gated on u_ecal_view: the whole point of the token is
 *  to authorize access WITHOUT a phpBB session (an external calendar app
 *  has neither).
 *
 * Caching (binding): the generated body is cached under
 * \ecyaz\eventscalendar\service\outbox::ICS_CACHE_KEY ('_ecal_ics') for
 * CACHE_TTL seconds — service/outbox.php's enqueue_upsert()/enqueue_delete()
 * already destroy that key on every event mutation (Task 4), so this class
 * is purely the read side of that contract. One cached body is shared by
 * every caller regardless of access mode — nothing viewer-specific is ever
 * encoded into the feed (see service/ics.php), so sharing one cache entry
 * between a public and a token-gated fetch of the same board is safe.
 */
class feed
{
	/** 15 minutes (binding, task-7 brief). */
	const CACHE_TTL = 900;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \ecyaz\eventscalendar\service\ics */
	protected $ics;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\request\request $request,
		\ecyaz\eventscalendar\service\ics $ics
	)
	{
		$this->config  = $config;
		$this->cache   = $cache;
		$this->request = $request;
		$this->ics     = $ics;
	}

	public function feed()
	{
		if (empty($this->config['ecal_ics_enable']))
		{
			throw new \phpbb\exception\http_exception(404, 'PAGE_NOT_FOUND');
		}

		if (empty($this->config['ecal_ics_public']))
		{
			$key   = $this->request->variable('key', '');
			$token = (string) $this->config['ecal_ics_token'];

			// An unset/empty configured token must NEVER authorise access,
			// even when $key is also '' (e.g. no `key` param at all, which
			// request->variable() defaults to '' the same as an explicit
			// `key=`) -- hash_equals('', '') returns true, which would make
			// a private feed with the shipped default token ('', see
			// migrations/install_config.php) world-readable by anyone who
			// omits the key param entirely. Reject on an empty token first,
			// before ever reaching hash_equals().
			//
			// hash_equals() first arg is the KNOWN string (the configured
			// token), second is user input -- constant-time against the
			// attacker-controlled side, per its own documented contract.
			if ($token === '' || !hash_equals($token, $key))
			{
				throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
			}
		}

		$body = $this->cache->get(\ecyaz\eventscalendar\service\outbox::ICS_CACHE_KEY);

		if ($body === false)
		{
			$body = $this->ics->generate();
			$this->cache->put(\ecyaz\eventscalendar\service\outbox::ICS_CACHE_KEY, $body, self::CACHE_TTL);
		}

		$response = new Response($body);
		$response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
		$response->headers->set('Content-Disposition', 'inline; filename="calendar.ics"');

		return $response;
	}
}
