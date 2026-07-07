<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Header nav link + board-index blocks (upcoming events / mini calendar),
 * gated on u_ecal_view and the ecal_index_display config (0 off, 1 upcoming,
 * 2 mini-calendar, 3 both).
 */
class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \ecyaz\eventscalendar\service\calendar_view */
	protected $calendar_view;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\ecyaz\eventscalendar\service\calendar_view $calendar_view
	)
	{
		$this->auth          = $auth;
		$this->config        = $config;
		$this->helper        = $helper;
		$this->language      = $language;
		$this->template      = $template;
		$this->user          = $user;
		$this->calendar_view = $calendar_view;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'              => 'load_language_on_setup',
			'core.page_header'              => 'add_nav_link',
			'core.index_modify_page_title' => 'add_index_blocks',
		];
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext     = $event['lang_set_ext'];
		$lang_set_ext[]   = [
			'ext_name' => 'ecyaz/eventscalendar',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_nav_link($event)
	{
		if (!$this->auth->acl_get('u_ecal_view'))
		{
			return;
		}

		$this->template->assign_vars([
			'S_ECAL_NAV'      => true,
			'U_ECAL_CALENDAR' => $this->helper->route('ecyaz_eventscalendar_month'),
		]);
	}

	public function add_index_blocks($event)
	{
		$display = (int) $this->config['ecal_index_display'];

		if ($display === 0 || !$this->auth->acl_get('u_ecal_view'))
		{
			return;
		}

		$this->language->add_lang('common', 'ecyaz/eventscalendar');

		if ($display === 1 || $display === 3)
		{
			$this->assign_upcoming_block();
		}

		if ($display === 2 || $display === 3)
		{
			$this->assign_mini_calendar_block();
		}
	}

	protected function assign_upcoming_block(): void
	{
		$limit  = max(1, (int) $this->config['ecal_index_upcoming_count']);
		$events = $this->calendar_view->upcoming($limit);

		foreach ($events as $event)
		{
			$this->template->assign_block_vars('ecal_upcoming', [
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
			'S_ECAL_UPCOMING'     => true,
			'S_ECAL_HAS_UPCOMING' => (count($events) > 0),
			'U_ECAL_CALENDAR'     => $this->helper->route('ecyaz_eventscalendar_month'),
		]);
	}

	protected function assign_mini_calendar_block(): void
	{
		$tz    = $this->board_timezone();
		$now   = new \DateTimeImmutable('now', $tz);
		$year  = (int) $now->format('Y');
		$month = (int) $now->format('n');

		$weeks = $this->calendar_view->month_grid($year, $month);

		foreach ($weeks as $week)
		{
			$this->template->assign_block_vars('ecal_mini_weeks', []);

			foreach ($week as $cell)
			{
				$this->template->assign_block_vars('ecal_mini_weeks.days', [
					'DAY_NUM'        => $cell['day_num'],
					'IS_OTHER_MONTH' => $cell['is_other_month'],
					'IS_TODAY'       => $cell['today'],
					'HAS_EVENTS'     => (count($cell['events']) > 0),
					'HAS_BIRTHDAYS'  => (count($cell['birthdays']) > 0),
					'U_DAY'          => $this->helper->route('ecyaz_eventscalendar_day', $this->day_route_params($cell['day_ts'])),
				]);
			}
		}

		$month_key = $this->calendar_view->month_key($month);

		$this->template->assign_vars([
			'S_ECAL_MINI'     => true,
			'ECAL_MINI_LABEL' => ($month_key !== '' ? $this->user->lang['datetime'][$month_key] : '') . ' ' . $year,
			'U_ECAL_CALENDAR' => $this->helper->route('ecyaz_eventscalendar_month'),
		]);
	}

	protected function day_route_params(int $ts): array
	{
		$dt = (new \DateTimeImmutable('@' . $ts))->setTimezone($this->board_timezone());

		return [
			'year'  => (int) $dt->format('Y'),
			'month' => (int) $dt->format('n'),
			'day'   => (int) $dt->format('j'),
		];
	}

	protected function board_timezone(): \DateTimeZone
	{
		$tz = (string) $this->config['board_timezone'];

		return new \DateTimeZone($tz !== '' ? $tz : 'UTC');
	}
}
