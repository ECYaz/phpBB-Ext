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

/**
 * Calendar view controller: month/year/day grids + title search.
 *
 * GATE: u_ecal_view on every action, mirrored on landcrm's controllers —
 * a single trigger via http_exception(403, NOT_AUTHORISED) for anyone
 * (guest or member) lacking the permission; no special guest login_box
 * branch, matching the landcrm gating pattern the brief points to.
 *
 * DAY BUCKETING: all grid/day math is done in the board's default timezone
 * (via calendar_view); display strings (times) are formatted per-viewer via
 * $user->create_datetime().
 */
class calendar
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \ecyaz\eventscalendar\service\calendar_view */
	protected $calendar_view;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		\ecyaz\eventscalendar\service\calendar_view $calendar_view
	)
	{
		$this->auth          = $auth;
		$this->config        = $config;
		$this->language      = $language;
		$this->request       = $request;
		$this->template      = $template;
		$this->user          = $user;
		$this->helper        = $helper;
		$this->calendar_view = $calendar_view;
	}

	// ------------------------------------------------------------------
	// Gate + shared helpers
	// ------------------------------------------------------------------

	protected function require_view(): void
	{
		if (!$this->auth->acl_get('u_ecal_view'))
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}

		$this->language->add_lang('common', 'ecyaz/eventscalendar');
	}

	protected function board_timezone(): \DateTimeZone
	{
		$tz = (string) $this->config['board_timezone'];

		return new \DateTimeZone($tz !== '' ? $tz : 'UTC');
	}

	protected function month_label(int $month, int $year): string
	{
		$key = $this->calendar_view->month_key($month);

		return ($key !== '' ? $this->user->lang['datetime'][$key] : '') . ' ' . $year;
	}

	protected function day_route_params($when): array
	{
		if (is_int($when))
		{
			$when = (new \DateTimeImmutable('@' . $when))->setTimezone($this->board_timezone());
		}

		return [
			'year'  => (int) $when->format('Y'),
			'month' => (int) $when->format('n'),
			'day'   => (int) $when->format('j'),
		];
	}

	protected function assign_weekday_headers(): void
	{
		foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $key)
		{
			$this->template->assign_block_vars('weekday_headers', [
				'LABEL' => $this->user->lang['datetime'][$key],
			]);
		}
	}

	protected function assign_event_row(string $block, array $event): void
	{
		$this->template->assign_block_vars($block, [
			'EVENT_ID'   => $event['event_id'],
			'TITLE'      => $event['title'],
			'COLOR'      => $event['color'],
			'BAR'        => $event['bar'] ?? 'single',
			'ALL_DAY'    => $event['all_day'],
			'IS_SPECIAL' => ($event['event_type'] === 1),
			'TIME'       => $event['all_day'] ? '' : $this->user->create_datetime('@' . $event['start_ts'])->format('H:i'),
			// I2 fix: click-through to the event page. Special dates
			// (event_type = 1) are included too — view() never rejects
			// them, only edit/delete do (see controller/event.php's
			// reject_special()).
			'U_EVENT'    => $this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event['event_id']]),
		]);
	}

	/**
	 * I2 fix: "Add event" affordance shared by month()/day() — gated on
	 * u_ecal_post (the same permission controller/event.php::require_post()
	 * checks), independent of u_ecal_view (already required to reach any
	 * calendar page at all via require_view()).
	 */
	protected function assign_add_event_vars(): void
	{
		$this->template->assign_vars([
			'S_CAN_POST'  => $this->auth->acl_get('u_ecal_post'),
			'U_EVENT_ADD' => $this->helper->route('ecyaz_eventscalendar_event_add'),
		]);
	}

	// ------------------------------------------------------------------
	// Controller actions
	// ------------------------------------------------------------------

	public function month($year = 0, $month = 0)
	{
		$this->require_view();

		$tz  = $this->board_timezone();
		$now = new \DateTimeImmutable('now', $tz);

		$year  = (int) $year;
		$month = (int) $month;

		if ($year <= 0 || $month <= 0)
		{
			$year  = (int) $now->format('Y');
			$month = (int) $now->format('n');
		}

		if ($month < 1 || $month > 12)
		{
			throw new \phpbb\exception\http_exception(404, 'ECAL_INVALID_DATE');
		}

		$weeks    = $this->calendar_view->month_grid($year, $month);
		$month_dt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz);
		$prev_dt  = $month_dt->modify('-1 month');
		$next_dt  = $month_dt->modify('+1 month');

		$this->assign_weekday_headers();
		$this->assign_add_event_vars();

		foreach ($weeks as $week)
		{
			$this->template->assign_block_vars('weeks', []);

			foreach ($week as $cell)
			{
				$this->template->assign_block_vars('weeks.days', [
					'DAY_NUM'        => $cell['day_num'],
					'IS_OTHER_MONTH' => $cell['is_other_month'],
					'IS_TODAY'       => $cell['today'],
					'U_DAY'          => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($cell['day_ts'])),
				]);

				foreach ($cell['events'] as $event)
				{
					$this->assign_event_row('weeks.days.events', $event);
				}

				foreach ($cell['birthdays'] as $bday)
				{
					$this->template->assign_block_vars('weeks.days.birthdays', [
						'USERNAME' => $bday['username'],
					]);
				}
			}
		}

		$this->template->assign_vars([
			'MONTH_LABEL' => $this->month_label($month, $year),
			'U_PREV'      => $this->helper->route('ecyaz_eventscalendar_month', ['year' => (int) $prev_dt->format('Y'), 'month' => (int) $prev_dt->format('n')]),
			'U_NEXT'      => $this->helper->route('ecyaz_eventscalendar_month', ['year' => (int) $next_dt->format('Y'), 'month' => (int) $next_dt->format('n')]),
			'U_TODAY'     => $this->helper->route('ecyaz_eventscalendar_month', ['year' => (int) $now->format('Y'), 'month' => (int) $now->format('n')]),
			'U_YEAR'      => $this->helper->route('ecyaz_eventscalendar_year', ['year' => $year]),
			'U_SEARCH'    => $this->helper->route('ecyaz_eventscalendar_search'),
		]);

		return $this->helper->render('ecyaz_eventscalendar_month_body.html', $this->month_label($month, $year));
	}

	public function year($year)
	{
		$this->require_view();

		$year = (int) $year;

		for ($m = 1; $m <= 12; $m++)
		{
			$weeks = $this->calendar_view->month_grid($year, $m);

			$this->template->assign_block_vars('months', [
				'LABEL'  => $this->month_label($m, $year),
				'U_VIEW' => $this->helper->route('ecyaz_eventscalendar_month', ['year' => $year, 'month' => $m]),
			]);

			foreach ($weeks as $week)
			{
				$this->template->assign_block_vars('months.weeks', []);

				foreach ($week as $cell)
				{
					$this->template->assign_block_vars('months.weeks.days', [
						'DAY_NUM'        => $cell['day_num'],
						'IS_OTHER_MONTH' => $cell['is_other_month'],
						'IS_TODAY'       => $cell['today'],
						'HAS_EVENTS'     => (count($cell['events']) > 0),
						'HAS_BIRTHDAYS'  => (count($cell['birthdays']) > 0),
						'U_DAY'          => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($cell['day_ts'])),
					]);
				}
			}
		}

		$this->template->assign_vars([
			'YEAR'   => $year,
			'U_PREV' => $this->helper->route('ecyaz_eventscalendar_year', ['year' => $year - 1]),
			'U_NEXT' => $this->helper->route('ecyaz_eventscalendar_year', ['year' => $year + 1]),
		]);

		return $this->helper->render('ecyaz_eventscalendar_year_body.html', (string) $year);
	}

	public function day($year, $month, $day)
	{
		$this->require_view();

		$year  = (int) $year;
		$month = (int) $month;
		$day   = (int) $day;

		if (!checkdate($month, $day, $year))
		{
			throw new \phpbb\exception\http_exception(404, 'ECAL_INVALID_DATE');
		}

		$data   = $this->calendar_view->day_events($year, $month, $day);
		$tz     = $this->board_timezone();
		$day_dt = (new \DateTimeImmutable('@' . $data['day_ts']))->setTimezone($tz);

		$this->assign_add_event_vars();

		foreach ($data['events'] as $event)
		{
			$this->assign_event_row('events', $event);
		}

		foreach ($data['birthdays'] as $bday)
		{
			$this->template->assign_block_vars('birthdays', [
				'USERNAME' => $bday['username'],
			]);
		}

		$day_label = $day_dt->format('j') . ' ' . $this->month_label((int) $day_dt->format('n'), (int) $day_dt->format('Y'));

		$this->template->assign_vars([
			'DAY_LABEL' => $day_label,
			'S_HAS_EVENTS' => (count($data['events']) > 0),
			'U_PREV'    => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($day_dt->modify('-1 day'))),
			'U_NEXT'    => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($day_dt->modify('+1 day'))),
			'U_MONTH'   => $this->helper->route('ecyaz_eventscalendar_month', ['year' => (int) $day_dt->format('Y'), 'month' => (int) $day_dt->format('n')]),
		]);

		return $this->helper->render('ecyaz_eventscalendar_day_body.html', $day_label);
	}

	public function search()
	{
		$this->require_view();

		$q    = $this->request->variable('q', '', true);
		$from = $this->request->variable('from', '');
		$to   = $this->request->variable('to', '');

		$from_ts = ($from !== '') ? $this->parse_date_param($from) : null;
		$to_ts   = ($to !== '') ? $this->parse_date_param($to, true) : null;

		$results = ($q !== '') ? $this->calendar_view->search($q, $from_ts, $to_ts) : [];

		foreach ($results as $event)
		{
			$this->template->assign_block_vars('results', [
				'EVENT_ID'   => $event['event_id'],
				'TITLE'      => $event['title'],
				'COLOR'      => $event['color'],
				'ALL_DAY'    => $event['all_day'],
				'IS_SPECIAL' => ($event['event_type'] === 1),
				'DATE'       => $this->user->create_datetime('@' . $event['start_ts'])->format('Y-m-d'),
				'TIME'       => $event['all_day'] ? '' : $this->user->create_datetime('@' . $event['start_ts'])->format('H:i'),
				'U_DAY'      => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($event['start_ts'])),
				// I2 fix: click-through straight to the event, not just its day.
				'U_EVENT'    => $this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event['event_id']]),
			]);
		}

		$this->template->assign_vars([
			'SEARCH_Q'      => $q,
			'SEARCH_FROM'   => $from,
			'SEARCH_TO'     => $to,
			'S_HAS_RESULTS' => (count($results) > 0),
			'S_HAS_QUERY'   => ($q !== ''),
			'U_SEARCH'      => $this->helper->route('ecyaz_eventscalendar_search'),
		]);

		return $this->helper->render('ecyaz_eventscalendar_search_body.html', $this->language->lang('ECAL_SEARCH'));
	}

	/**
	 * Parses a Y-m-d search param to a board-TZ start-of-day timestamp.
	 * With $end_of_day, returns the last second of that day (start-of-day +
	 * 86399) instead, so a "to" bound includes events later that same day.
	 *
	 * M2 fix: round-trip check, mirroring controller/event.php::
	 * parse_date_only() — createFromFormat() is lenient about out-of-range
	 * fields (e.g. day 31 of February silently overflows into March), so an
	 * invalid calendar date must be caught by comparing the re-formatted
	 * value back against the input, not just checking $dt is truthy.
	 */
	protected function parse_date_param(string $value, bool $end_of_day = false): ?int
	{
		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $value, $this->board_timezone());

		if (!$dt || $dt->format('Y-m-d') !== $value)
		{
			return null;
		}

		return $dt->getTimestamp() + ($end_of_day ? 86399 : 0);
	}
}
