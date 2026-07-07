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
 * Google Calendar API v3 client: push (insert/update) an event row, delete
 * a pushed event, and a connection-test probe for the ACP status field
 * (Task 9). All HTTP goes through http_client — this class never touches
 * cURL — and every call is authenticated via gcal_auth::get_access_token().
 *
 * build_payload() is a pure mapping (\$event_row -> Calendar API request
 * body) and is public specifically so it can be unit-tested directly with
 * hand-built rows, mirroring service/ics.php's build_vevent() split between
 * pure formatting and impure DB-backed row preparation. $event_row here is
 * the SAME kind of already-normalized row ics::build_vevent() takes — not
 * the raw ecal_events DB row: 'description' is BBCode-rendered HTML (the
 * direct output of generate_text_for_display(), not pre-converted to plain
 * text by the caller) and 'url' is the absolute event URL, both already
 * resolved by whatever calls push_upsert()/push_delete() (Task 9's sync
 * path; out of scope here). build_payload() is the SOLE HTML -> plain-text
 * converter in this pipeline (see its use of text_util::
 * html_to_plain_text() below) — callers must NOT pre-convert 'description'
 * themselves, or the second strip_tags() pass silently eats any literal
 * "<"/">" that survived the first conversion (e.g. "revenue < $100"). This
 * class intentionally has no dbal.conn / controller.helper dependency of
 * its own, exactly so it never needs to re-derive either value itself.
 *
 * Date/recurrence logic is reused from service/ics.php rather than
 * reimplemented: ics::board_date() supplies the board-timezone,
 * DST-safe, end-exclusive-aware all-day date math, and ics::build_rrule()
 * supplies the FREQ/UNTIL RRULE text — including the UNTIL value-type
 * branch (DATE for all-day, DATE-TIME(Z) for timed) — verbatim, since
 * Google's `recurrence` array is literal RFC 5545 RRULE text and that
 * branch is identical to the ICS feed's (task-8 brief, VERIFY). Only the
 * *field* format differs from ICS: Google's start/end objects want RFC
 * 3339 (`YYYY-MM-DD` / `...T...Z`), not ICS's compact `Ymd`/`Ymd\THis`, so
 * this class does its own light punctuation reformatting around the
 * reused board_date()/format_utc_datetime() values rather than duplicating
 * their date arithmetic.
 */
class gcal_client
{
	/** Calendar API v3 base URL. */
	const API_BASE = 'https://www.googleapis.com/calendar/v3';

	/** @var gcal_auth */
	protected $auth;

	/** @var http_client */
	protected $http;

	/** @var \phpbb\config\config */
	protected $config;

	/**
	 * @var recurrence
	 *
	 * Not called directly today — build_payload()'s RRULE mapping reuses
	 * ics::build_rrule()/ics::board_date() (both pure statics, see class
	 * docblock) rather than this service's instance methods, since this
	 * class pushes ONE event row as-is (no occurrence expansion), the same
	 * design ics.php itself uses for the feed. Injected per the task-8
	 * brief's constructor signature so gcal_client's wiring is consistent
	 * with the rest of the module's recurrence-aware services
	 * (event_manager, cron.reminders) and is available without another
	 * services.yml change if a future task needs it (e.g. per-occurrence
	 * push).
	 */
	protected $recurrence;

	public function __construct(
		gcal_auth $auth,
		http_client $http,
		\phpbb\config\config $config,
		recurrence $recurrence
	)
	{
		$this->auth       = $auth;
		$this->http       = $http;
		$this->config     = $config;
		$this->recurrence = $recurrence;
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Inserts or updates the Google Calendar event for $event_row and
	 * returns the Google event id (unchanged on a successful update; a
	 * NEW id on insert, including the update-404-heals-to-insert path).
	 *
	 * @throws \RuntimeException on a non-2xx Google response (other than
	 *         the update path's 404, which is handled internally).
	 */
	public function push_upsert(array $event_row): string
	{
		$headers      = $this->auth_headers();
		$calendar_id  = rawurlencode($this->calendar_id());
		$body         = (string) json_encode($this->build_payload($event_row));
		$existing_id  = (string) ($event_row['gcal_id'] ?? '');

		if ($existing_id === '')
		{
			return $this->insert_event($calendar_id, $body, $headers);
		}

		$response = $this->http->request(
			'PUT',
			self::API_BASE . '/calendars/' . $calendar_id . '/events/' . rawurlencode($existing_id),
			$headers,
			$body
		);

		if ($response['status'] === 404)
		{
			// The gcal-side event is gone (deleted directly in Google
			// Calendar, calendar swapped, ...) — heal by inserting fresh
			// rather than leaving this row permanently un-syncable.
			return $this->insert_event($calendar_id, $body, $headers);
		}

		if (!self::is_success($response['status']))
		{
			throw new \RuntimeException(
				'Google Calendar event update failed (HTTP ' . $response['status'] . '): ' . text_util::error_snippet($response['body'])
			);
		}

		return self::extract_id($response['body']);
	}

	/**
	 * Deletes the Google Calendar event with id $gcal_id. A no-op (not an
	 * error) when $gcal_id is empty (nothing was ever pushed) or Google
	 * reports the event already gone (404/410 — deleted directly in Google
	 * Calendar, or a repeat delivery of the same delete).
	 *
	 * @throws \RuntimeException on any other non-2xx Google response.
	 */
	public function push_delete(string $gcal_id): void
	{
		if ($gcal_id === '')
		{
			return;
		}

		$response = $this->http->request(
			'DELETE',
			self::API_BASE . '/calendars/' . rawurlencode($this->calendar_id()) . '/events/' . rawurlencode($gcal_id),
			$this->auth_headers(),
			null
		);

		if ($response['status'] === 404 || $response['status'] === 410)
		{
			return;
		}

		if (!self::is_success($response['status']))
		{
			throw new \RuntimeException(
				'Google Calendar event deletion failed (HTTP ' . $response['status'] . '): ' . text_util::error_snippet($response['body'])
			);
		}
	}

	/**
	 * Connection-test probe for the ACP sync-status field (Task 9): fetches
	 * the configured calendar's metadata and returns its summary (display
	 * name) on success.
	 *
	 * @throws \RuntimeException on a non-200 Google response.
	 */
	public function test_connection(): string
	{
		$response = $this->http->request(
			'GET',
			self::API_BASE . '/calendars/' . rawurlencode($this->calendar_id()),
			$this->auth_headers(),
			null
		);

		if ($response['status'] !== 200)
		{
			throw new \RuntimeException(
				'Google Calendar connection test failed (HTTP ' . $response['status'] . '): ' . text_util::error_snippet($response['body'])
			);
		}

		$decoded = json_decode($response['body'], true);

		return is_array($decoded) ? (string) ($decoded['summary'] ?? '') : '';
	}

	/**
	 * Maps an event row to a Calendar API v3 Events#insert/update request
	 * body. Public so unit tests can assert on the mapping directly without
	 * an HTTP round trip. See class docblock for $event_row's expected
	 * shape and the reuse strategy behind the date/recurrence fields.
	 *
	 * Deliberately omits an 'id' field: Google assigns one on insert, and
	 * update targets the id already in the request URL.
	 */
	public function build_payload(array $event_row): array
	{
		self::require_keys($event_row, [
			'title', 'description', 'url', 'start_ts', 'end_ts', 'all_day', 'recur_type', 'recur_until',
		]);

		$start_ts    = (int) $event_row['start_ts'];
		$end_ts      = (int) $event_row['end_ts'];
		$all_day     = !empty($event_row['all_day']);
		$recur_type  = (int) $event_row['recur_type'];
		$recur_until = (int) $event_row['recur_until'];
		$title       = (string) $event_row['title'];
		$url         = (string) $event_row['url'];

		$description = text_util::html_to_plain_text((string) $event_row['description']);

		$tz_name = $this->board_timezone();
		$tz      = new \DateTimeZone($tz_name);

		$payload = [
			'summary'     => $title,
			'description' => self::compose_description($description, $url),
		];

		// I3 fix: Google's `start`/`end` objects need an explicit `timeZone`
		// so a `recurrence` RRULE expands in the SAME timezone this payload's
		// dates were computed in — without it Google falls back to the
		// target calendar's own timezone, which silently mis-times every
		// occurrence whenever that differs from the board's. Added to BOTH
		// start and end, and for every event (recurring or not) — harmless
		// for a one-off event, and keeps the mapping uniform rather than
		// conditional on recur_type.
		if ($all_day)
		{
			// board_date()'s date arithmetic (board-tz, DST-safe, +1 day
			// end-exclusive) is reused verbatim from ics.php — only its
			// Ymd string is reformatted here to Google's YYYY-MM-DD.
			$payload['start'] = ['date' => self::google_date(ics::board_date($start_ts, $tz)), 'timeZone' => $tz_name];
			$payload['end']   = ['date' => self::google_date(ics::board_date($end_ts, $tz, 1)), 'timeZone' => $tz_name];
		}
		else
		{
			$payload['start'] = ['dateTime' => self::google_datetime($start_ts), 'timeZone' => $tz_name];
			$payload['end']   = ['dateTime' => self::google_datetime($end_ts), 'timeZone' => $tz_name];
		}

		// ics::build_rrule() supplies FREQ + the UNTIL value-type branch
		// (DATE for all-day, DATE-TIME Z for timed) unchanged — Google's
		// `recurrence` array holds literal RFC 5545 RRULE text.
		$rrule = ics::build_rrule($recur_type, $recur_until, $all_day, $tz);

		if ($rrule !== '')
		{
			$payload['recurrence'] = ['RRULE:' . $rrule];
		}

		if ($url !== '')
		{
			$payload['source'] = [
				'url'   => $url,
				'title' => $title,
			];
		}

		return $payload;
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	protected function insert_event(string $calendar_id, string $body, array $headers): string
	{
		$response = $this->http->request(
			'POST',
			self::API_BASE . '/calendars/' . $calendar_id . '/events',
			$headers,
			$body
		);

		if (!self::is_success($response['status']))
		{
			throw new \RuntimeException(
				'Google Calendar event creation failed (HTTP ' . $response['status'] . '): ' . text_util::error_snippet($response['body'])
			);
		}

		return self::extract_id($response['body']);
	}

	protected function auth_headers(): array
	{
		return [
			'Authorization: Bearer ' . $this->auth->get_access_token(),
			'Content-Type: application/json',
		];
	}

	/**
	 * @throws \RuntimeException when the ACP's gcal calendar id setting is
	 *         still empty, so a misconfigured board fails loudly here
	 *         rather than sending every request to a malformed URL.
	 */
	protected function calendar_id(): string
	{
		$calendar_id = (string) $this->config['ecal_gcal_calendar_id'];

		if ($calendar_id === '')
		{
			throw new \RuntimeException('Google Calendar ID is not configured');
		}

		return $calendar_id;
	}

	/**
	 * Validates that $row carries every key build_payload() consumes. $row
	 * is the same already-normalized event row described in this class's
	 * docblock; a missing key means the caller's row-normalization step has
	 * a bug, and defaulting it away (e.g. via `??`) would instead push a
	 * malformed event to Google silently.
	 *
	 * @throws \InvalidArgumentException naming the first missing key.
	 */
	protected static function require_keys(array $row, array $keys): void
	{
		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $row))
			{
				throw new \InvalidArgumentException(
					"gcal_client::build_payload(): event row is missing required key '{$key}'"
				);
			}
		}
	}

	protected function board_timezone(): string
	{
		$tz = (string) $this->config['board_timezone'];

		return ($tz !== '') ? $tz : 'UTC';
	}

	protected static function is_success(int $status): bool
	{
		return $status >= 200 && $status < 300;
	}

	protected static function compose_description(string $description, string $url): string
	{
		if ($description === '')
		{
			return $url;
		}

		if ($url === '')
		{
			return $description;
		}

		return $description . "\n\n" . $url;
	}

	/** ics::board_date()'s 'Ymd' -> Google's 'YYYY-MM-DD'. */
	protected static function google_date(string $ymd): string
	{
		return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
	}

	/** RFC 3339 UTC date-time, e.g. 2026-07-14T18:00:00Z. */
	protected static function google_datetime(int $ts): string
	{
		return gmdate('Y-m-d\TH:i:s', $ts) . 'Z';
	}

	protected static function extract_id(string $body): string
	{
		$decoded = json_decode($body, true);

		if (!is_array($decoded) || empty($decoded['id']))
		{
			throw new \RuntimeException('Google Calendar response did not include an event id: ' . text_util::error_snippet($body));
		}

		return (string) $decoded['id'];
	}
}
