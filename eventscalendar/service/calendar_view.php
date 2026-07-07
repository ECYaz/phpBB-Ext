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
 * Read-model for calendar rendering: month grid, single day, upcoming list,
 * and title search. No writes here — event CRUD lives elsewhere.
 *
 * Day-bucketing (which calendar cell an occurrence/birthday belongs to) is
 * always done in the board's default timezone, so the shared calendar grid
 * reads the same way for every visitor; per-viewer time-of-day formatting
 * (e.g. "14:00") is the controller's job via $user->create_datetime().
 *
 * Multi-day bars: an occurrence's [start_ts, start_ts + duration) is a
 * half-open interval (see service/recurrence.php docblock) — the day of
 * (end_ts - 1 second) is the last calendar day the event occupies.
 */
class calendar_view
{
	const USER_TYPE_INACTIVE = 1;
	const USER_TYPE_IGNORE   = 2; // bot accounts

	const ENGLISH_MONTHS = [
		1  => 'January',
		2  => 'February',
		3  => 'March',
		4  => 'April',
		5  => 'May',
		6  => 'June',
		7  => 'July',
		8  => 'August',
		9  => 'September',
		10 => 'October',
		11 => 'November',
		12 => 'December',
	];

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \ecyaz\eventscalendar\service\recurrence */
	protected $recurrence;

	/** @var string */
	protected $events_table;

	/** @var string */
	protected $users_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\ecyaz\eventscalendar\service\recurrence $recurrence,
		$table_prefix
	)
	{
		$this->db           = $db;
		$this->config       = $config;
		$this->recurrence   = $recurrence;
		$this->events_table = $table_prefix . 'ecal_events';
		$this->users_table  = $table_prefix . 'users';
	}

	/**
	 * English key (matching phpBB's language 'datetime' array) for a 1-12
	 * month number. Callers localize via $user->lang['datetime'][key].
	 */
	public function month_key(int $month): string
	{
		return self::ENGLISH_MONTHS[$month] ?? '';
	}

	/**
	 * 6x7 Monday-anchored month grid. Returns weeks[] of days[] cells:
	 * day_ts, day_num, is_other_month, today, events[], birthdays[].
	 *
	 * @return array<int, array<int, array>>
	 */
	public function month_grid(int $year, int $month): array
	{
		if ($month < 1 || $month > 12)
		{
			throw new \InvalidArgumentException('month must be between 1 and 12');
		}

		$tz = $this->board_timezone();

		try
		{
			$month_start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz);
		}
		catch (\Exception $e)
		{
			throw new \InvalidArgumentException('invalid year/month', 0, $e);
		}

		$dow        = (int) $month_start->format('N'); // ISO 1=Mon..7=Sun
		$grid_start = $month_start->modify('-' . ($dow - 1) . ' days');
		$grid_end   = $grid_start->modify('+41 days'); // 6 weeks x 7 days - 1

		$range_start_ts = $grid_start->getTimestamp();
		$range_end_ts   = $grid_end->modify('+1 day')->getTimestamp() - 1;

		$events_by_day    = $this->events_by_day($range_start_ts, $range_end_ts, $tz);
		$birthdays_by_day = $this->birthdays_by_day($grid_start->format('Y-m-d'), $grid_end->format('Y-m-d'), $tz);

		$today_str = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');

		$weeks  = [];
		$cursor = $grid_start;

		for ($w = 0; $w < 6; $w++)
		{
			$days = [];

			for ($d = 0; $d < 7; $d++)
			{
				$date_key = $cursor->format('Y-m-d');

				$days[] = [
					'day_ts'         => $cursor->getTimestamp(),
					'day_num'        => (int) $cursor->format('j'),
					'is_other_month' => ((int) $cursor->format('n') !== $month),
					'today'          => ($date_key === $today_str),
					'events'         => $events_by_day[$date_key] ?? [],
					'birthdays'      => $birthdays_by_day[$date_key] ?? [],
				];

				$cursor = $cursor->modify('+1 day');
			}

			$weeks[] = $days;
		}

		return $weeks;
	}

	/**
	 * Single-day view: events and birthdays occurring on the given date.
	 */
	public function day_events(int $year, int $month, int $day): array
	{
		$tz = $this->board_timezone();

		try
		{
			$day_start = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), $tz);
		}
		catch (\Exception $e)
		{
			throw new \InvalidArgumentException('invalid year/month/day', 0, $e);
		}

		$range_start_ts = $day_start->getTimestamp();
		$range_end_ts   = $day_start->modify('+1 day')->getTimestamp() - 1;
		$date_key       = $day_start->format('Y-m-d');

		$events_by_day    = $this->events_by_day($range_start_ts, $range_end_ts, $tz);
		$birthdays_by_day = $this->birthdays_by_day($date_key, $date_key, $tz);

		return [
			'day_ts'    => $range_start_ts,
			'events'    => $events_by_day[$date_key] ?? [],
			'birthdays' => $birthdays_by_day[$date_key] ?? [],
		];
	}

	/**
	 * Next occurrence (strictly after now) for every candidate event,
	 * ascending, capped at $limit. One row per event (its soonest future
	 * occurrence) — matches the "upcoming events" index/nav widget shape.
	 */
	public function upcoming(int $limit): array
	{
		$now = time();

		$sql = 'SELECT event_id, event_type, title, start_ts, end_ts, all_day, recur_type, recur_until, color
			FROM ' . $this->events_table . '
			WHERE recur_type > 0 OR end_ts >= ' . (int) $now . '
			ORDER BY start_ts ASC';
		$result = $this->db->sql_query($sql);

		$candidates = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$next_ts = $this->recurrence->next_occurrence($row, $now);

			if ($next_ts !== null)
			{
				$candidates[] = [
					'event_id'   => (int) $row['event_id'],
					'title'      => (string) $row['title'],
					'color'      => (int) $row['color'],
					'all_day'    => (bool) $row['all_day'],
					'start_ts'   => $next_ts,
					'event_type' => (int) $row['event_type'],
				];
			}
		}
		$this->db->sql_freeresult($result);

		usort($candidates, function ($a, $b)
		{
			return $a['start_ts'] <=> $b['start_ts'];
		});

		return array_slice($candidates, 0, max(0, $limit));
	}

	/**
	 * Title/description search (LIKE, escaped), optionally bounded to events
	 * overlapping [from, to]. Matches the event definitions, not expanded
	 * occurrences.
	 *
	 * M6 fix: also matches against the stored description, not just the
	 * title, per spec. 'description' holds the s9e-XML/BBCode-UID-tagged
	 * text generate_text_for_storage() persists (see event_manager.php), not
	 * plain text — but a LIKE against it still matches the user's own words,
	 * since generate_text_for_storage() only wraps/tags markup around the
	 * original text, it never removes or re-encodes the plain words
	 * themselves. A false-negative is possible only for a search term that
	 * exactly straddles an inserted XML tag boundary — accepted as a minor
	 * edge case rather than adding a rendered-plain-text column/index just
	 * for search.
	 *
	 * Mirrors fetch_events()'s recurring-event escape hatch: a recurring
	 * event's base row may start before $from (or even before $to), but its
	 * live occurrences can still fall inside [from, to] — so recur_type > 0
	 * rows are never excluded by the $from bound.
	 *
	 * Hard-capped at 50 rows.
	 */
	public function search(string $q, ?int $from, ?int $to): array
	{
		$q = trim($q);

		if ($q === '')
		{
			return [];
		}

		$like = $this->db->sql_like_expression($this->db->get_any_char() . $q . $this->db->get_any_char());

		$sql = 'SELECT event_id, event_type, title, start_ts, end_ts, all_day, recur_type, color
			FROM ' . $this->events_table . '
			WHERE (title ' . $like . ' OR description ' . $like . ')';

		if ($from !== null)
		{
			$sql .= ' AND (recur_type > 0 OR end_ts >= ' . (int) $from . ')';
		}

		if ($to !== null)
		{
			$sql .= ' AND start_ts <= ' . (int) $to;
		}

		$sql .= ' ORDER BY start_ts ASC';

		$result = $this->db->sql_query_limit($sql, 50);
		$rows   = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'event_id'   => (int) $row['event_id'],
				'title'      => (string) $row['title'],
				'color'      => (int) $row['color'],
				'all_day'    => (bool) $row['all_day'],
				'start_ts'   => (int) $row['start_ts'],
				'end_ts'     => (int) $row['end_ts'],
				'event_type' => (int) $row['event_type'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	protected function fetch_events(int $range_start, int $range_end): array
	{
		$sql = 'SELECT event_id, event_type, title, start_ts, end_ts, all_day, recur_type, recur_until, color
			FROM ' . $this->events_table . '
			WHERE start_ts <= ' . (int) $range_end . '
				AND (recur_type > 0 OR end_ts >= ' . (int) $range_start . ')
			ORDER BY start_ts ASC';
		$result = $this->db->sql_query($sql);
		$rows   = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Expand every row's occurrences intersecting [range_start, range_end]
	 * into a Y-m-d-keyed map of per-day chip data, with start/mid/end/single
	 * bar classes for multi-day spans (clipped to the visible range).
	 */
	protected function events_by_day(int $range_start, int $range_end, \DateTimeZone $tz): array
	{
		$rows = $this->fetch_events($range_start, $range_end);

		if (empty($rows))
		{
			return [];
		}

		$range_start_date = (new \DateTimeImmutable('@' . $range_start))->setTimezone($tz)->format('Y-m-d');
		$range_end_date   = (new \DateTimeImmutable('@' . $range_end))->setTimezone($tz)->format('Y-m-d');

		$by_day = [];

		foreach ($rows as $row)
		{
			$duration    = max(0, (int) $row['end_ts'] - (int) $row['start_ts']);
			$occurrences = $this->recurrence->occurrences($row, $range_start, $range_end);

			foreach ($occurrences as $occ_start)
			{
				$span_end_ts = ($duration > 0) ? ($occ_start + $duration - 1) : $occ_start;

				$first_date = (new \DateTimeImmutable('@' . $occ_start))->setTimezone($tz)->format('Y-m-d');
				$last_date  = (new \DateTimeImmutable('@' . $span_end_ts))->setTimezone($tz)->format('Y-m-d');

				// Clip the day walk to the visible range — an event's own
				// span may extend far outside the grid we're rendering.
				$walk_start = max($first_date, $range_start_date);
				$walk_end   = min($last_date, $range_end_date);

				$is_multiday = ($first_date !== $last_date);
				$cursor      = new \DateTimeImmutable($walk_start, $tz);
				$end_cursor  = new \DateTimeImmutable($walk_end, $tz);

				while ($cursor <= $end_cursor)
				{
					$date_key = $cursor->format('Y-m-d');

					$bar = 'single';
					if ($is_multiday)
					{
						if ($date_key === $first_date)
						{
							$bar = 'start';
						}
						else if ($date_key === $last_date)
						{
							$bar = 'end';
						}
						else
						{
							$bar = 'mid';
						}
					}

					$by_day[$date_key][] = [
						'event_id'   => (int) $row['event_id'],
						'title'      => (string) $row['title'],
						'color'      => (int) $row['color'],
						'bar'        => $bar,
						'all_day'    => (bool) $row['all_day'],
						'start_ts'   => $occ_start,
						'event_type' => (int) $row['event_type'],
					];

					$cursor = $cursor->modify('+1 day');
				}
			}
		}

		return $by_day;
	}

	/**
	 * Birthdays overlay: only when ecal_birthdays_enable, excluding bots
	 * (USER_TYPE_IGNORE) and inactive accounts (USER_TYPE_INACTIVE).
	 * phpBB stores user_birthday as "d-m-Y" text (year may be blanked with
	 * spaces for privacy) — parsed defensively; day/month of 0 is skipped.
	 * Display-only, never stored/synced by this extension.
	 */
	protected function birthdays_by_day(string $range_start_date, string $range_end_date, \DateTimeZone $tz): array
	{
		if (empty($this->config['ecal_birthdays_enable']))
		{
			return [];
		}

		$sql = "SELECT user_id, username, user_birthday
			FROM {$this->users_table}
			WHERE user_birthday <> ''
				AND user_type NOT IN (" . self::USER_TYPE_IGNORE . ', ' . self::USER_TYPE_INACTIVE . ')';
		$result = $this->db->sql_query($sql);

		$by_month_day = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$parts = explode('-', (string) $row['user_birthday']);

			if (count($parts) !== 3)
			{
				continue;
			}

			$day   = (int) trim($parts[0]);
			$month = (int) trim($parts[1]);

			if ($day <= 0 || $month <= 0 || $day > 31 || $month > 12)
			{
				continue;
			}

			$key                  = sprintf('%02d-%02d', $month, $day);
			$by_month_day[$key][] = [
				'user_id'  => (int) $row['user_id'],
				'username' => (string) $row['username'],
			];
		}
		$this->db->sql_freeresult($result);

		if (empty($by_month_day))
		{
			return [];
		}

		$by_day = [];
		$cursor = new \DateTimeImmutable($range_start_date, $tz);
		$end    = new \DateTimeImmutable($range_end_date, $tz);

		while ($cursor <= $end)
		{
			$key = $cursor->format('m-d');

			if (isset($by_month_day[$key]))
			{
				$by_day[$cursor->format('Y-m-d')] = $by_month_day[$key];
			}

			$cursor = $cursor->modify('+1 day');
		}

		return $by_day;
	}

	protected function board_timezone(): \DateTimeZone
	{
		$tz = isset($this->config['board_timezone']) ? (string) $this->config['board_timezone'] : '';

		return new \DateTimeZone($tz !== '' ? $tz : 'UTC');
	}
}
