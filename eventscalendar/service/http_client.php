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
 * Minimal synchronous HTTP client. This is the ONLY file in the extension
 * that uses cURL — gcal_auth (OAuth token exchange) and gcal_client
 * (Calendar API v3 calls) both go through request() rather than calling
 * curl_* directly, so every outbound HTTP call in the extension shares one
 * timeout/redirect policy and is trivially stubbable in unit tests (an
 * anonymous class implementing this same public method, recording calls
 * and returning canned responses — no live network access, ever, in
 * tests/service/gcal_auth_test.php / gcal_client_test.php).
 *
 * No JSON encode/decode here — callers own their own request/response
 * bodies; this class only moves bytes over HTTP.
 */
class http_client
{
	/** Connect AND total timeout, in seconds — both are capped identically per the task-8 brief. */
	const TIMEOUT_SECONDS = 10;

	/**
	 * @param  string      $method  HTTP verb (GET/POST/PUT/DELETE/...).
	 * @param  string      $url     Absolute URL.
	 * @param  array       $headers Raw header lines, e.g. ['Content-Type: application/json'].
	 * @param  string|null $body    Raw request body, or null for a bodyless request (GET/DELETE).
	 * @return array{status:int, body:string}
	 *
	 * @throws \RuntimeException on a transport-level failure (DNS, connect,
	 *         timeout, TLS, ...) — anything that never produced an HTTP
	 *         response at all. A non-2xx HTTP response is NOT an exception
	 *         here; it is returned normally so callers (gcal_auth,
	 *         gcal_client) can inspect status/body and produce their own
	 *         admin-actionable error messages.
	 */
	public function request(string $method, string $url, array $headers, ?string $body): array
	{
		$ch = curl_init();

		curl_setopt_array($ch, [
			CURLOPT_URL            => $url,
			CURLOPT_CUSTOMREQUEST  => strtoupper($method),
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
		]);

		if ($body !== null)
		{
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}

		$response_body = curl_exec($ch);

		if ($response_body === false)
		{
			$error = curl_error($ch);
			curl_close($ch);

			throw new \RuntimeException('HTTP request to ' . $url . ' failed: ' . $error);
		}

		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);

		return [
			'status' => $status,
			'body'   => (string) $response_body,
		];
	}
}
