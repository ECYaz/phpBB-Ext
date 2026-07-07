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
 * ICS (iCalendar, RFC 5545) feed generator.
 *
 * generate() builds the full VCALENDAR body for every row in ecal_events —
 * event_type 0 (user events) AND 1 (ACP special dates) are BOTH included, no
 * WHERE filter needed (those are the only two values the column ever holds).
 * Birthdays are never in this or any table, so they can never appear here.
 *
 * No occurrence expansion: a recurring event emits ONE VEVENT (DTSTART/DTEND
 * of its first occurrence, plus RRULE) — calendar clients expand it
 * themselves. ecyaz.eventscalendar.recurrence is therefore not a dependency
 * of this class at all.
 *
 * VEVENT construction is split so the formatting/escaping/folding/RRULE
 * logic is a pure function of a plain array — build_vevent() touches no
 * service, DB, or global — making it directly unit-testable with hand-built
 * rows (tests/service/ics_test.php), per the task-7 brief's stated
 * preference. generate() itself owns the DB read plus the two per-row
 * lookups build_vevent() cannot do purely: BBCode -> plain text
 * (generate_text_for_display(), needs the live functions_content include and
 * touches globals) and the absolute event URL (controller.helper is a
 * concrete service, not a pure value) — see prepare_row().
 *
 * Cache contract (binding, wired in Task 4 on the write side): this class
 * does NOT itself read/write the phpBB cache — service/outbox.php's
 * enqueue_upsert()/enqueue_delete() already destroy the '_ecal_ics' key
 * (outbox::ICS_CACHE_KEY) on every event mutation. The READ side (cache
 * get/put around generate(), 15-minute TTL) lives in controller/feed.php,
 * which owns the HTTP response and is where a cache hit/miss naturally
 * belongs — generate() is always a full, uncached rebuild.
 */
class ics
{
	const PRODID = '-//ecyaz//eventscalendar//EN';

	/** RFC 5545 §3.1 content-line fold limit, in octets, excluding the CRLF. */
	const FOLD_LIMIT = 75;

	/** recur_type (ecal_events) => RRULE FREQ value. interval is always 1 (Global Constraints), so no INTERVAL= part is ever emitted. */
	const FREQ_MAP = [
		1 => 'DAILY',
		2 => 'WEEKLY',
		3 => 'MONTHLY',
		4 => 'YEARLY',
	];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $events_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		$table_prefix
	)
	{
		$this->db           = $db;
		$this->config       = $config;
		$this->helper       = $helper;
		$this->events_table = $table_prefix . 'ecal_events';
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Full VCALENDAR body: CRLF line endings throughout, every content line
	 * folded to at most FOLD_LIMIT octets. Always ends with a trailing CRLF.
	 */
	public function generate(): string
	{
		$this->ensure_content_functions();

		$host    = $this->board_host();
		$tz_name = $this->board_timezone_name();

		$lines   = [];
		$lines[] = 'BEGIN:VCALENDAR';
		$lines[] = 'VERSION:2.0';
		$lines[] = 'PRODID:' . self::PRODID;
		$lines[] = 'CALSCALE:GREGORIAN';

		// No WHERE clause: event_type 0 and 1 are the only values the column
		// ever holds and BOTH belong in the feed (binding, see class docblock).
		$sql    = 'SELECT * FROM ' . $this->events_table . ' ORDER BY start_ts ASC';
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$lines = array_merge($lines, $this->build_vevent($this->prepare_row($row, $host), $tz_name));
		}
		$this->db->sql_freeresult($result);

		$lines[] = 'END:VCALENDAR';

		$folded = array_map([self::class, 'fold_line'], $lines);

		return implode("\r\n", $folded) . "\r\n";
	}

	/**
	 * Pure VEVENT builder: no DB, no service, no global state — every input
	 * it needs is in $row (already-normalized, already-plain-text values)
	 * and $tz_name (board timezone, for all-day board-local dates). Returns
	 * an array of UNFOLDED, escaped content lines (fold_line() is applied
	 * once, centrally, in generate()) so unit tests can assert on individual
	 * lines directly.
	 *
	 * Expected $row keys: event_id (int), host (string, for UID), title
	 * (string, raw — escaped here), description (string, already
	 * plain-text — escaped here), url (string, absolute, already built),
	 * start_ts/end_ts (int, UTC), all_day (bool), recur_type (int, 0-4),
	 * recur_until (int, 0 = none), dtstamp_ts (int — see prepare_row()'s
	 * "DTSTAMP decision" for how this is derived).
	 *
	 * @return string[]
	 */
	public function build_vevent(array $row, string $tz_name = 'UTC'): array
	{
		$tz = new \DateTimeZone($tz_name);

		$event_id    = (int) $row['event_id'];
		$all_day     = !empty($row['all_day']);
		$start_ts    = (int) $row['start_ts'];
		$end_ts      = (int) $row['end_ts'];
		$recur_type  = (int) ($row['recur_type'] ?? 0);
		$recur_until = (int) ($row['recur_until'] ?? 0);
		$dtstamp_ts  = (int) ($row['dtstamp_ts'] ?? $start_ts);

		$lines   = [];
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $event_id . '@' . (string) ($row['host'] ?? '');
		$lines[] = 'DTSTAMP:' . self::format_utc_datetime($dtstamp_ts);

		if ($all_day)
		{
			// End-exclusive per RFC 5545: end_ts is the LAST INCLUSIVE second
			// (23:59:59 board-local of the last calendar day, see
			// controller/event.php's parse_date_only() end_of_day convention),
			// so DTEND;VALUE=DATE is that day's board-local date PLUS one.
			$lines[] = 'DTSTART;VALUE=DATE:' . self::board_date($start_ts, $tz);
			$lines[] = 'DTEND;VALUE=DATE:' . self::board_date($end_ts, $tz, 1);
		}
		else
		{
			$lines[] = 'DTSTART:' . self::format_utc_datetime($start_ts);
			$lines[] = 'DTEND:' . self::format_utc_datetime($end_ts);
		}

		$rrule = self::build_rrule($recur_type, $recur_until, $all_day, $tz);

		if ($rrule !== '')
		{
			$lines[] = 'RRULE:' . $rrule;
		}

		$lines[] = 'SUMMARY:' . self::escape_text((string) ($row['title'] ?? ''));

		$description = (string) ($row['description'] ?? '');

		if ($description !== '')
		{
			$lines[] = 'DESCRIPTION:' . self::escape_text($description);
		}

		if (!empty($row['url']))
		{
			// URL is a URI-typed property (RFC 5545 §3.8.4.6), not a TEXT
			// property -- escape_text()'s "\;"/"\," escaping is a TEXT-only
			// rule (§3.3.11) and must NOT be applied here, or the emitted
			// URI itself would be corrupted (e.g. a literal ';' in a query
			// string, which route()-generated URLs never contain today but
			// the property type contract still forbids escaping). The raw
			// absolute URL is emitted as-is; it remains subject to the same
			// line folding as every other content line (fold_line(), applied
			// centrally in generate()).
			$lines[] = 'URL:' . (string) $row['url'];
		}

		$lines[] = 'END:VEVENT';

		return $lines;
	}

	// ------------------------------------------------------------------
	// Pure formatting helpers (unit-tested directly)
	// ------------------------------------------------------------------

	/**
	 * RFC 5545 §3.3.11 TEXT escaping: backslash MUST be escaped first (else
	 * the backslashes inserted by the later replacements would themselves
	 * get re-escaped), then newlines (CRLF/CR/LF all normalise to the
	 * two-character literal "\n"), then semicolon, then comma.
	 */
	public static function escape_text(string $value): string
	{
		$value = str_replace('\\', '\\\\', $value);
		$value = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
		$value = str_replace(';', '\\;', $value);
		$value = str_replace(',', '\\,', $value);

		return $value;
	}

	/**
	 * RFC 5545 §3.1 line folding: a content line longer than FOLD_LIMIT
	 * octets is split into multiple physical lines by inserting CRLF +
	 * a single SPACE before continuing. The fold point must never split a
	 * UTF-8 multibyte sequence — this walks whole characters (via a Unicode
	 * -aware split), not raw bytes, so a fold point always falls on a
	 * character boundary. The first physical line carries up to FOLD_LIMIT
	 * octets of content; every continuation line carries up to
	 * FOLD_LIMIT - 1 octets of content (the 1 octet budget difference is the
	 * leading space itself), so every physical line — continuation prefix
	 * included — is at most FOLD_LIMIT octets.
	 */
	public static function fold_line(string $line): string
	{
		if (strlen($line) <= self::FOLD_LIMIT)
		{
			return $line;
		}

		$chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);

		if ($chars === false)
		{
			// Not valid UTF-8 -- should not happen (phpBB content is UTF-8
			// throughout) -- fall back to raw-byte chunking rather than risk
			// preg_split() silently producing no output.
			$chars = str_split($line, 1);
		}

		$out     = '';
		$current = '';
		$limit   = self::FOLD_LIMIT;

		foreach ($chars as $ch)
		{
			if ((strlen($current) + strlen($ch)) > $limit)
			{
				$out    .= ($out === '' ? '' : "\r\n ") . $current;
				$current = $ch;
				$limit   = self::FOLD_LIMIT - 1;
			}
			else
			{
				$current .= $ch;
			}
		}

		$out .= ($out === '' ? '' : "\r\n ") . $current;

		return $out;
	}

	/**
	 * RRULE value (without the "RRULE:" property name), or '' when
	 * recur_type does not map to a FREQ (0 / unknown -> no recurrence).
	 *
	 * UNTIL value-type rule (binding, task-7 brief, RFC 5545 §3.3.10): UNTIL
	 * MUST match DTSTART's value type. Timed events -> DATE-TIME in UTC
	 * (Ymd\THis + Z). All-day events -> a bare DATE (Ymd, no time, no Z) —
	 * $recur_until is interpreted as the board-local calendar date it falls
	 * on (same board-timezone convention as DTSTART/DTEND for all-day
	 * events), NOT reformatted as a UTC instant.
	 */
	public static function build_rrule(int $recur_type, int $recur_until, bool $all_day, \DateTimeZone $tz): string
	{
		if (!isset(self::FREQ_MAP[$recur_type]))
		{
			return '';
		}

		$rrule = 'FREQ=' . self::FREQ_MAP[$recur_type];

		if ($recur_until > 0)
		{
			$rrule .= ';UNTIL=' . ($all_day
				? self::board_date($recur_until, $tz)
				: self::format_utc_datetime($recur_until));
		}

		return $rrule;
	}

	/** UTC DATE-TIME form: Ymd\THis + trailing Z (RFC 5545 §3.3.5). */
	public static function format_utc_datetime(int $ts): string
	{
		return gmdate('Ymd\THis', $ts) . 'Z';
	}

	/**
	 * Board-local DATE form (Ymd, no time, no Z — RFC 5545 §3.3.4), optionally
	 * advanced by $add_days whole calendar days (used for all-day DTEND's
	 * end-exclusive +1).
	 */
	public static function board_date(int $ts, \DateTimeZone $tz, int $add_days = 0): string
	{
		$dt = (new \DateTimeImmutable('@' . $ts))->setTimezone($tz);

		if ($add_days !== 0)
		{
			$dt = $dt->modify(($add_days > 0 ? '+' : '') . $add_days . ' day');
		}

		return $dt->format('Ymd');
	}

	// ------------------------------------------------------------------
	// DB-backed row preparation (NOT pure — talks to the DB row, BBCode
	// rendering, and controller.helper)
	// ------------------------------------------------------------------

	/**
	 * Normalizes one raw ecal_events row into build_vevent()'s pure input
	 * shape: BBCode -> plain text, absolute event URL, DTSTAMP source.
	 *
	 * DTSTAMP decision (binding, recorded per task-7 brief's VERIFY):
	 * event_edit_time ?: event_time. Using "now" would make every cached
	 * feed fetch produce a different DTSTAMP for every VEVENT even when
	 * nothing changed, which is both non-deterministic (unit-untestable)
	 * and needlessly cache-hostile (a client comparing DTSTAMP between
	 * fetches would see spurious "updates" on every re-generation). Using
	 * the event's own edit/create time is deterministic, matches "when was
	 * this VEVENT's data last true", and is naturally invalidated only when
	 * the underlying row actually changes (which is also exactly when the
	 * outbox destroys the ICS cache — see class docblock).
	 */
	protected function prepare_row(array $row, string $host): array
	{
		$event_id = (int) $row['event_id'];

		$description_html = generate_text_for_display(
			(string) $row['description'],
			(string) $row['bbcode_uid'],
			(string) $row['bbcode_bitfield'],
			(int) $row['bbcode_options']
		);

		$description_text = text_util::html_to_plain_text($description_html);

		// VERIFY / decision (found via this task's manual-parse check, NOT
		// by inspection): controller.helper::route()'s $session_id parameter
		// of `false` does NOT mean "no session id" -- includes/functions.php
		// append_sid() treats `false` as "use the CURRENT global $_SID",
		// only an explicit '' (empty string) suppresses it. notification/
		// reminder.php's get_url()/get_email_template_variables() use `false`
		// here (a one-off link in a per-recipient email, where a transient
		// sid is harmless), but that pattern is WRONG for this feed: the
		// generated body is cached and served to every requester for up to
		// CACHE_TTL (controller/feed.php) -- embedding one particular
		// visitor's/bot's session id in a document shared by everyone who
		// fetches the feed during that window would leak session state
		// across viewers. '' is passed explicitly here for that reason.
		$url = $this->helper->route(
			'ecyaz_eventscalendar_event',
			['event_id' => $event_id],
			false,
			'',
			\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
		);

		$event_time      = (int) $row['event_time'];
		$event_edit_time = (int) $row['event_edit_time'];

		return [
			'event_id'    => $event_id,
			'host'        => $host,
			'title'       => (string) $row['title'],
			'description' => $description_text,
			'url'         => $url,
			'start_ts'    => (int) $row['start_ts'],
			'end_ts'      => (int) $row['end_ts'],
			'all_day'     => !empty($row['all_day']),
			'recur_type'  => (int) $row['recur_type'],
			'recur_until' => (int) $row['recur_until'],
			'dtstamp_ts'  => $event_edit_time ?: $event_time,
		];
	}

	/**
	 * Board host for UID's "@host" part. VERIFY: 'server_name' is the board
	 * config key (confirmed via includes/functions.php's own use of
	 * $config['server_name'] for building absolute URLs).
	 */
	protected function board_host(): string
	{
		return (string) $this->config['server_name'];
	}

	protected function board_timezone_name(): string
	{
		$tz = (string) $this->config['board_timezone'];

		return ($tz !== '') ? $tz : 'UTC';
	}

	protected function ensure_content_functions(): void
	{
		if (!function_exists('generate_text_for_display'))
		{
			global $phpbb_root_path, $phpEx;
			include $phpbb_root_path . 'includes/functions_content.' . $phpEx;
		}
	}
}
