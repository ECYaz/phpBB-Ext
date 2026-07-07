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
 * Pure recurrence engine for calendar events. No DB access — every method
 * is a function of the event row array (ecal_events columns) it is given.
 *
 * Semantics (binding, see plan Global Constraints):
 *  - interval is always 1 (daily = every day, weekly = every 7 days, ...).
 *  - recur_until excludes occurrences whose START is > until; an occurrence
 *    starting exactly AT until is included. 0 = forever.
 *  - Monthly day 29/30/31 clamps to the last day of shorter months.
 *  - Duration (end_ts - start_ts) is constant across all occurrences.
 *  - occurrences() range-intersects the [start, start+duration] span of each
 *    occurrence against [range_start, range_end], including spill-in from
 *    occurrences that started before range_start.
 *  - All-day events (all_day = 1) always advance in the board's default
 *    timezone so the local wall-date is preserved across DST transitions,
 *    for every recur_type (daily/weekly step calendar days, monthly/annual
 *    step calendar months/years). Timed events always advance by a fixed
 *    UTC-second offset (daily/weekly) or fixed calendar field (monthly/
 *    annual), never board-tz-aware.
 */
class recurrence
{
	const RECUR_NONE    = 0;
	const RECUR_DAILY   = 1;
	const RECUR_WEEKLY  = 2;
	const RECUR_MONTHLY = 3;
	const RECUR_ANNUAL  = 4;

	const SECONDS_PER_DAY  = 86400;
	const SECONDS_PER_WEEK = 604800;
	const MAX_RANGE_DAYS   = 366;

	/** @var \phpbb\config\config */
	protected $config;

	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

	/**
	 * Ascending occurrence START timestamps whose [start, start+duration]
	 * span intersects [range_start, range_end].
	 *
	 * @param  array $event_row   ecal_events columns: start_ts, end_ts, all_day, recur_type, recur_until
	 * @param  int   $range_start UTC timestamp
	 * @param  int   $range_end   UTC timestamp
	 * @return int[]
	 */
	public function occurrences(array $event_row, int $range_start, int $range_end): array
	{
		$this->guard_range($range_start, $range_end);

		$start_ts = (int) $event_row['start_ts'];
		$duration = max(0, (int) $event_row['end_ts'] - $start_ts);
		$type     = (int) $event_row['recur_type'];
		$until    = (int) $event_row['recur_until'];

		if ($type === self::RECUR_NONE)
		{
			if ($start_ts <= $range_end && ($start_ts + $duration) >= $range_start)
			{
				return [$start_ts];
			}

			return [];
		}

		// Jump close to the first candidate instead of walking one period at a
		// time from the event's genesis (which may be years before the range).
		$n = max(0, $this->estimate_period_index($event_row, $range_start - $duration) - 2);

		// Correct the estimate: step back while the previous occurrence still
		// overlaps the range (calendar month/year steps are not fixed-length).
		while ($n > 0 && ($this->period_start($event_row, $n - 1) + $duration) >= $range_start)
		{
			$n--;
		}

		$result = [];

		while (true)
		{
			$occ_start = $this->period_start($event_row, $n);

			if ($occ_start > $range_end)
			{
				break;
			}

			if ($until > 0 && $occ_start > $until)
			{
				break;
			}

			if (($occ_start + $duration) >= $range_start)
			{
				$result[] = $occ_start;
			}

			$n++;
		}

		return $result;
	}

	/**
	 * RSVP validation helper: does $ts exactly match a generated occurrence
	 * start? 0 is valid iff the event is non-recurring (recur_type = 0),
	 * representing "the single instance" without needing its literal ts.
	 */
	public function is_occurrence(array $event_row, int $ts): bool
	{
		$type = (int) $event_row['recur_type'];

		if ($ts === 0)
		{
			return $type === self::RECUR_NONE;
		}

		if ($type === self::RECUR_NONE)
		{
			return $ts === (int) $event_row['start_ts'];
		}

		$until = (int) $event_row['recur_until'];

		if ($until > 0 && $ts > $until)
		{
			return false;
		}

		if ($ts < (int) $event_row['start_ts'])
		{
			return false;
		}

		$n = $this->exact_period_index($event_row, $ts);

		return $n !== null && $this->period_start($event_row, $n) === $ts;
	}

	/**
	 * First occurrence START strictly after $after, honouring recur_until.
	 * Returns null when the event has no further occurrences.
	 */
	public function next_occurrence(array $event_row, int $after): ?int
	{
		$type     = (int) $event_row['recur_type'];
		$until    = (int) $event_row['recur_until'];
		$start_ts = (int) $event_row['start_ts'];

		if ($type === self::RECUR_NONE)
		{
			if ($start_ts > $after && ($until <= 0 || $start_ts <= $until))
			{
				return $start_ts;
			}

			return null;
		}

		$n = max(0, $this->estimate_period_index($event_row, $after + 1) - 2);

		while ($n > 0 && $this->period_start($event_row, $n - 1) > $after)
		{
			$n--;
		}

		while (true)
		{
			$occ_start = $this->period_start($event_row, $n);

			if ($until > 0 && $occ_start > $until)
			{
				return null;
			}

			if ($occ_start > $after)
			{
				return $occ_start;
			}

			$n++;
		}
	}

	/**
	 * The UTC start timestamp of occurrence index $n (0-based, n=0 is the
	 * event's own start_ts).
	 */
	protected function period_start(array $event_row, int $n): int
	{
		$start_ts = (int) $event_row['start_ts'];

		if ($n === 0)
		{
			return $start_ts;
		}

		switch ((int) $event_row['recur_type'])
		{
			case self::RECUR_DAILY:
				// All-day events advance a calendar day in board tz (see
				// add_calendar_days()); timed events keep the fixed UTC offset.
				if (!empty($event_row['all_day']))
				{
					return $this->add_calendar_days($event_row, $n, 1);
				}

				return $start_ts + ($n * self::SECONDS_PER_DAY);

			case self::RECUR_WEEKLY:
				// Same board-tz reasoning as daily, 7 calendar days per step.
				if (!empty($event_row['all_day']))
				{
					return $this->add_calendar_days($event_row, $n, 7);
				}

				return $start_ts + ($n * self::SECONDS_PER_WEEK);

			case self::RECUR_MONTHLY:
				return $this->add_calendar_units($event_row, $n, 'months');

			case self::RECUR_ANNUAL:
				return $this->add_calendar_units($event_row, $n, 'years');

			default:
				return $start_ts;
		}
	}

	/**
	 * Advance start_ts by $n * $days_per_period calendar days in the board
	 * timezone, re-anchoring to the event's original local time-of-day
	 * (local midnight for all-day events). Unlike month/year math, day
	 * arithmetic has no overflow-clamp concern, but a fixed-second offset
	 * (n * 86400 / 604800) still drifts the local wall-date whenever a DST
	 * transition falls inside the span — DateTimeImmutable::modify('+N days')
	 * on a tz-localized instance preserves the local wall time and lets PHP
	 * resolve the correct UTC instant either side of the transition.
	 */
	protected function add_calendar_days(array $event_row, int $n, int $days_per_period): int
	{
		$tz = $this->calendar_timezone($event_row);
		$dt = (new \DateTimeImmutable('@' . (int) $event_row['start_ts']))->setTimezone($tz);

		return $dt->modify('+' . ($n * $days_per_period) . ' days')->getTimestamp();
	}

	/**
	 * Advance start_ts by $n months or years using explicit year/month
	 * fields plus a day clamp — NEVER DateTime::modify('+1 month'), which
	 * overflows on short months (Jan 31 + "1 month" = Mar 3, not Feb 28) and
	 * would silently corrupt the recurrence's day-of-month.
	 *
	 * All-day events reason in the board's default timezone so a "day 14"
	 * event stays on local day 14 across DST transitions; timed events
	 * reason in UTC per the module's date-math convention.
	 */
	protected function add_calendar_units(array $event_row, int $n, string $unit): int
	{
		$tz = $this->calendar_timezone($event_row);
		$dt = (new \DateTimeImmutable('@' . (int) $event_row['start_ts']))->setTimezone($tz);

		$year  = (int) $dt->format('Y');
		$month = (int) $dt->format('n');
		$day   = (int) $dt->format('j');

		if ($unit === 'months')
		{
			$total_months  = ($month - 1) + $n;
			$target_year   = $year + intdiv($total_months, 12);
			$target_month  = ($total_months % 12) + 1;
		}
		else // years
		{
			$target_year  = $year + $n;
			$target_month = $month;
		}

		$last_day   = $this->days_in_month($target_year, $target_month, $tz);
		$target_day = min($day, $last_day);

		return $dt->setDate($target_year, $target_month, $target_day)->getTimestamp();
	}

	protected function days_in_month(int $year, int $month, \DateTimeZone $tz): int
	{
		return (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz))->format('t');
	}

	/**
	 * Whole calendar-day count between $start_ts's and $target_ts's
	 * board-local dates (time-of-day ignored, DST-hour-shift-proof) — used
	 * by estimate/exact_period_index() for all-day daily/weekly so a
	 * transition inside the span never perturbs the day count, mirroring
	 * add_calendar_units()'s Y/m/d field approach for monthly/annual.
	 */
	protected function board_local_day_diff(array $event_row, int $start_ts, int $target_ts): int
	{
		$tz     = $this->calendar_timezone($event_row);
		$dstart = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($tz);
		$dtgt   = (new \DateTimeImmutable('@' . $target_ts))->setTimezone($tz);

		$utc        = new \DateTimeZone('UTC');
		$date_start = new \DateTimeImmutable($dstart->format('Y-m-d'), $utc);
		$date_tgt   = new \DateTimeImmutable($dtgt->format('Y-m-d'), $utc);

		$diff = $date_start->diff($date_tgt);

		return ((int) $diff->days) * ($diff->invert ? -1 : 1);
	}

	/**
	 * Timezone used for calendar (month/day) reasoning: board default for
	 * all-day events, UTC otherwise. See class docblock / add_calendar_units().
	 */
	protected function calendar_timezone(array $event_row): \DateTimeZone
	{
		if (!empty($event_row['all_day']))
		{
			return new \DateTimeZone($this->board_timezone());
		}

		return new \DateTimeZone('UTC');
	}

	protected function board_timezone(): string
	{
		$tz = isset($this->config['board_timezone']) ? (string) $this->config['board_timezone'] : '';

		return ($tz !== '') ? $tz : 'UTC';
	}

	/**
	 * Approximate occurrence index whose start is at or after $target_ts.
	 * Exact for daily/weekly (fixed-length periods) and for monthly/annual
	 * (a pure calendar-field difference); callers correct with a small
	 * backward walk since day-of-month clamping can shift the exact instant.
	 */
	protected function estimate_period_index(array $event_row, int $target_ts): int
	{
		$start_ts = (int) $event_row['start_ts'];

		if ($target_ts <= $start_ts)
		{
			return 0;
		}

		switch ((int) $event_row['recur_type'])
		{
			case self::RECUR_DAILY:
				// All-day: estimate from the board-local calendar-day count, not
				// a fixed 86400s offset, so a DST hour-shift inside the span
				// never perturbs the day count (see board_local_day_diff()).
				if (!empty($event_row['all_day']))
				{
					return intdiv($this->board_local_day_diff($event_row, $start_ts, $target_ts), 1);
				}

				return intdiv($target_ts - $start_ts, self::SECONDS_PER_DAY);

			case self::RECUR_WEEKLY:
				if (!empty($event_row['all_day']))
				{
					return intdiv($this->board_local_day_diff($event_row, $start_ts, $target_ts), 7);
				}

				return intdiv($target_ts - $start_ts, self::SECONDS_PER_WEEK);

			case self::RECUR_MONTHLY:
			case self::RECUR_ANNUAL:
				$tz     = $this->calendar_timezone($event_row);
				$dstart = (new \DateTimeImmutable('@' . $start_ts))->setTimezone($tz);
				$dtgt   = (new \DateTimeImmutable('@' . $target_ts))->setTimezone($tz);

				if ((int) $event_row['recur_type'] === self::RECUR_MONTHLY)
				{
					return (((int) $dtgt->format('Y') - (int) $dstart->format('Y')) * 12)
						+ ((int) $dtgt->format('n') - (int) $dstart->format('n'));
				}

				return (int) $dtgt->format('Y') - (int) $dstart->format('Y');

			default:
				return 0;
		}
	}

	/**
	 * Exact occurrence index for a candidate timestamp, or null when $ts
	 * cannot possibly be a generated occurrence (used by is_occurrence()).
	 */
	protected function exact_period_index(array $event_row, int $ts): ?int
	{
		$start_ts = (int) $event_row['start_ts'];
		$diff     = $ts - $start_ts;

		switch ((int) $event_row['recur_type'])
		{
			case self::RECUR_DAILY:
				// All-day: exact index is the board-local calendar-day count;
				// period_start() re-derives the instant and the caller
				// (is_occurrence()) confirms it matches $ts exactly.
				if (!empty($event_row['all_day']))
				{
					$day_diff = $this->board_local_day_diff($event_row, $start_ts, $ts);

					return ($day_diff >= 0) ? $day_diff : null;
				}

				if ($diff < 0 || ($diff % self::SECONDS_PER_DAY) !== 0)
				{
					return null;
				}

				return intdiv($diff, self::SECONDS_PER_DAY);

			case self::RECUR_WEEKLY:
				if (!empty($event_row['all_day']))
				{
					$day_diff = $this->board_local_day_diff($event_row, $start_ts, $ts);

					if ($day_diff < 0 || ($day_diff % 7) !== 0)
					{
						return null;
					}

					return intdiv($day_diff, 7);
				}

				if ($diff < 0 || ($diff % self::SECONDS_PER_WEEK) !== 0)
				{
					return null;
				}

				return intdiv($diff, self::SECONDS_PER_WEEK);

			case self::RECUR_MONTHLY:
			case self::RECUR_ANNUAL:
				$n = $this->estimate_period_index($event_row, $ts);

				return ($n >= 0) ? $n : null;

			default:
				return null;
		}
	}

	protected function guard_range(int $range_start, int $range_end): void
	{
		if ($range_end < $range_start)
		{
			throw new \InvalidArgumentException('range_end must be >= range_start');
		}

		if (($range_end - $range_start) > (self::MAX_RANGE_DAYS * self::SECONDS_PER_DAY))
		{
			throw new \InvalidArgumentException('occurrences() range must not exceed ' . self::MAX_RANGE_DAYS . ' days');
		}
	}
}
