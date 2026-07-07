<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\service;

/**
 * Google OAuth2 service-account authentication (RFC 7523 JWT bearer grant).
 *
 * get_access_token() is the sole public entry point: it turns the admin's
 * pasted service-account JSON key (config_text key ecal_gcal_sa_json) into
 * a short-lived Calendar API access token, caching it (phpBB cache driver,
 * key TOKEN_CACHE_KEY, TTL CACHE_TTL) so a burst of pushes only round-trips
 * to Google's token endpoint once. Every failure mode — malformed JSON,
 * a missing client_email/private_key, a private key OpenSSL cannot parse,
 * or Google rejecting the assertion — throws \RuntimeException with a
 * message written for the board admin reading the ACP sync-status field
 * (Task 9), not a developer reading a stack trace.
 *
 * This class only ever talks to Google's OAuth token endpoint. Calendar
 * API v3 calls (the actual event push/delete/test-connection) live in
 * gcal_client, which depends on this class purely for get_access_token().
 * All HTTP goes through http_client — this class never touches cURL.
 */
class gcal_auth
{
	/** Requested at token-mint time (JWT `scope` claim). */
	const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

	/** Google's OAuth2 token endpoint — also the JWT `aud` claim. */
	const TOKEN_URI = 'https://oauth2.googleapis.com/token';

	/** JWT lifetime in seconds (`exp` = `iat` + this). Google's own hard cap is 3600s. */
	const JWT_TTL = 3600;

	/** phpBB cache key the minted access token is stored under. */
	const TOKEN_CACHE_KEY = '_ecal_gcal_token';

	/**
	 * Cache TTL for the minted token — intentionally shorter than JWT_TTL
	 * (3600s) so a cached token is never handed to a caller within the last
	 * 300s of its real Google-side validity window (clock-skew / in-flight
	 * request margin).
	 */
	const CACHE_TTL = 3300;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\config\db_text */
	protected $config_text;

	/** @var http_client */
	protected $http;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\config\db_text $config_text,
		http_client $http,
		\phpbb\cache\driver\driver_interface $cache
	)
	{
		$this->config      = $config;
		$this->config_text = $config_text;
		$this->http        = $http;
		$this->cache       = $cache;
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * A valid Calendar API access token — from cache when available,
	 * otherwise minted fresh via the JWT bearer grant and cached for
	 * CACHE_TTL seconds.
	 *
	 * @throws \RuntimeException admin-actionable message on bad JSON, a
	 *         missing/unparseable key, or a non-200 Google response.
	 */
	public function get_access_token(): string
	{
		$cached = $this->cache->get(self::TOKEN_CACHE_KEY);

		if ($cached !== false && $cached !== null && $cached !== '')
		{
			return (string) $cached;
		}

		$service_account = $this->load_service_account();
		$jwt              = $this->build_jwt($service_account);

		$response = $this->http->request(
			'POST',
			self::TOKEN_URI,
			['Content-Type: application/x-www-form-urlencoded'],
			'grant_type=' . rawurlencode('urn:ietf:params:oauth:grant-type:jwt-bearer') . '&assertion=' . rawurlencode($jwt)
		);

		if ($response['status'] !== 200)
		{
			throw new \RuntimeException(
				'Google rejected the Calendar service-account token request (HTTP '
				. $response['status'] . '): ' . text_util::error_snippet($response['body'])
			);
		}

		$decoded = json_decode($response['body'], true);

		if (!is_array($decoded) || empty($decoded['access_token']))
		{
			throw new \RuntimeException(
				'Google\'s token response did not include an access_token: ' . text_util::error_snippet($response['body'])
			);
		}

		$access_token = (string) $decoded['access_token'];

		$this->cache->put(self::TOKEN_CACHE_KEY, $access_token, self::CACHE_TTL);

		return $access_token;
	}

	// ------------------------------------------------------------------
	// JWT construction
	// ------------------------------------------------------------------

	/**
	 * Decodes/validates the admin-pasted service-account JSON and returns
	 * ['client_email' => string, 'private_key' => string].
	 *
	 * @throws \RuntimeException on malformed JSON or missing required fields.
	 */
	protected function load_service_account(): array
	{
		$raw = (string) $this->config_text->get('ecal_gcal_sa_json');

		if (trim($raw) === '')
		{
			throw new \RuntimeException('No Google service-account JSON key has been configured for the calendar sync.');
		}

		$decoded = json_decode($raw, true);

		if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE)
		{
			throw new \RuntimeException('The configured Google service-account key is not valid JSON: ' . json_last_error_msg());
		}

		$client_email = (string) ($decoded['client_email'] ?? '');
		$private_key  = (string) ($decoded['private_key'] ?? '');

		if ($client_email === '' || $private_key === '')
		{
			throw new \RuntimeException('The configured Google service-account key is missing the required "client_email" and/or "private_key" field.');
		}

		return [
			'client_email' => $client_email,
			'private_key'  => $private_key,
		];
	}

	/**
	 * Builds and signs the RFC 7523 JWT bearer assertion. Header
	 * `{"alg":"RS256","typ":"JWT"}`; claims iss/scope/aud/iat/exp per the
	 * task-8 brief. base64url per RFC 4648 §5 (no padding).
	 *
	 * @param array $service_account ['client_email' => string, 'private_key' => string]
	 *
	 * @throws \RuntimeException when the private key cannot be parsed by
	 *         OpenSSL, or signing itself fails.
	 */
	protected function build_jwt(array $service_account): string
	{
		$now = time();

		$header = [
			'alg' => 'RS256',
			'typ' => 'JWT',
		];

		$claims = [
			'iss'   => $service_account['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URI,
			'iat'   => $now,
			'exp'   => $now + self::JWT_TTL,
		];

		$signing_input = self::base64url(json_encode($header)) . '.' . self::base64url(json_encode($claims));

		$private_key = openssl_pkey_get_private($service_account['private_key']);

		if ($private_key === false)
		{
			throw new \RuntimeException('The configured Google service-account private key could not be parsed by OpenSSL — check that it was pasted in full, including the BEGIN/END PRIVATE KEY lines.');
		}

		$signed = openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);

		if (!$signed)
		{
			throw new \RuntimeException('Failed to sign the Google service-account JWT — the private key may be corrupt.');
		}

		return $signing_input . '.' . self::base64url($signature);
	}

	/** RFC 4648 §5 base64url, no padding. */
	protected static function base64url(string $data): string
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
}
