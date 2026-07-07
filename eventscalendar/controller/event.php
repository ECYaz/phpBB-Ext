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
 * Event CRUD controller: view/add/edit/delete for user events (event_type =
 * 0). Special dates (event_type = 1, ACP-managed) are never editable here —
 * reject_special() 403s any edit/delete attempt against one.
 *
 * Authorization: add requires u_ecal_post; edit/delete require u_ecal_post
 * AND (own event OR m_ecal_manage) — mirrors landcrm's controller-owns-
 * authorization pattern (gating lives in the controller, not the service).
 *
 * Delete uses the core confirm_box() flow (VERIFY includes/functions.php:2163
 * `function confirm_box($check, $title = '', $hidden = '', $html_body =
 * 'confirm_body.html', $u_action = '')`): confirm_box(false, ...) calls
 * page_header()/page_footer() and exits internally (the trailing exit is
 * unreachable-by-design, per the core comment above it), so delete()'s "no"
 * branch never returns to our render() call — this mirrors every core usage
 * (e.g. includes/ucp/ucp_groups.php).
 */
class event
{
	const FORM_KEY = 'ecyaz_ecal';

	const COLOR_COUNT = 8;

	const RECUR_NONE    = 0;
	const RECUR_DAILY   = 1;
	const RECUR_WEEKLY  = 2;
	const RECUR_MONTHLY = 3;
	const RECUR_ANNUAL  = 4;

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

	/** @var \ecyaz\eventscalendar\service\event_manager */
	protected $event_manager;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \ecyaz\eventscalendar\service\recurrence */
	protected $recurrence;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	/** @var string */
	protected $attendees_table;

	/** Task 5: how many upcoming occurrences the event page lists for a recurring event. */
	const RSVP_OCCURRENCE_COUNT = 6;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\request\request $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		\ecyaz\eventscalendar\service\event_manager $event_manager,
		\phpbb\db\driver\driver_interface $db,
		\ecyaz\eventscalendar\service\recurrence $recurrence,
		\phpbb\user_loader $user_loader,
		$table_prefix
	)
	{
		$this->auth            = $auth;
		$this->config          = $config;
		$this->language        = $language;
		$this->request         = $request;
		$this->template        = $template;
		$this->user            = $user;
		$this->helper          = $helper;
		$this->event_manager   = $event_manager;
		$this->db              = $db;
		$this->recurrence      = $recurrence;
		$this->user_loader     = $user_loader;
		$this->attendees_table = $table_prefix . 'ecal_attendees';
	}

	// ------------------------------------------------------------------
	// Gates + shared helpers
	// ------------------------------------------------------------------

	protected function require_view(): void
	{
		if (!$this->auth->acl_get('u_ecal_view'))
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}

		$this->language->add_lang('common', 'ecyaz/eventscalendar');
	}

	protected function require_post(): void
	{
		if (!$this->auth->acl_get('u_ecal_view') || !$this->auth->acl_get('u_ecal_post'))
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}

		$this->language->add_lang('common', 'ecyaz/eventscalendar');
	}

	/**
	 * Gates the RSVP toggle (Task 5). Mirrors require_post()'s "view AND
	 * the specific action permission" shape: u_ecal_attend alone would let
	 * a user with u_ecal_attend but somehow not u_ecal_view (not a normal
	 * configuration, but not enforced against) RSVP to an event they
	 * cannot see.
	 */
	protected function require_attend(): void
	{
		if (!$this->auth->acl_get('u_ecal_view') || !$this->auth->acl_get('u_ecal_attend'))
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}

		$this->language->add_lang('common', 'ecyaz/eventscalendar');
	}

	/**
	 * "own event OR m_ecal_manage" (Global Constraints) — deliberately NOT
	 * require_post() first: a moderator with m_ecal_manage but no
	 * u_ecal_post of their own must still be able to manage other users'
	 * events; u_ecal_post only gates the owner path.
	 */
	protected function require_owner_or_manage(array $event): void
	{
		$this->require_view();

		$can_manage = ($this->is_owner($event) && $this->auth->acl_get('u_ecal_post')) || $this->auth->acl_get('m_ecal_manage');

		if (!$can_manage)
		{
			throw new \phpbb\exception\http_exception(403, 'NOT_AUTHORISED');
		}
	}

	protected function is_owner(array $event): bool
	{
		return ((int) $event['poster_id'] > 0) && ((int) $event['poster_id'] === (int) $this->user->data['user_id']);
	}

	protected function load_event(int $event_id): array
	{
		$event = $this->event_manager->get($event_id);

		if ($event === null)
		{
			throw new \phpbb\exception\http_exception(404, 'ECAL_EVENT_NOT_FOUND');
		}

		return $event;
	}

	protected function reject_special(array $event): void
	{
		if ((int) $event['event_type'] === 1)
		{
			throw new \phpbb\exception\http_exception(403, 'ECAL_SPECIAL_NOT_EDITABLE');
		}
	}

	protected function board_timezone(): \DateTimeZone
	{
		$tz = (string) $this->config['board_timezone'];

		return new \DateTimeZone($tz !== '' ? $tz : 'UTC');
	}

	/**
	 * The "post discussion topic" checkbox is only ever offered/honoured
	 * when a topic forum is configured AND the acting user can post there.
	 */
	protected function can_offer_topic_checkbox(): bool
	{
		$forum_id = (int) $this->config['ecal_topic_forum_id'];

		return ($forum_id > 0) && $this->auth->acl_get('f_post', $forum_id);
	}

	protected function topic_url(int $topic_id): string
	{
		if ($topic_id <= 0)
		{
			return '';
		}

		global $phpbb_root_path, $phpEx;

		return append_sid($phpbb_root_path . 'viewtopic.' . $phpEx, 't=' . $topic_id);
	}

	/**
	 * Ensures generate_text_for_edit()/generate_text_for_storage() are
	 * loaded (mirrors service/event_manager.php's own
	 * ensure_content_functions()) — needed here because form_prefill()'s
	 * edit branch decodes the stored s9e-XML description back to its
	 * original BBCode source for the textarea (C1 fix), and this
	 * controller, unlike event_manager, never otherwise touches
	 * includes/functions_content.php.
	 */
	protected function ensure_content_functions(): void
	{
		if (!function_exists('generate_text_for_edit'))
		{
			global $phpbb_root_path, $phpEx;
			include $phpbb_root_path . 'includes/functions_content.' . $phpEx;
		}
	}

	// ------------------------------------------------------------------
	// Controller actions
	// ------------------------------------------------------------------

	public function view($event_id)
	{
		$this->require_view();

		$event = $this->load_event((int) $event_id);

		$can_manage = ($this->is_owner($event) && $this->auth->acl_get('u_ecal_post')) || $this->auth->acl_get('m_ecal_manage');

		// M3 fix: the event page's own START/END/occurrence WHEN labels must
		// be formatted in the VIEWER's timezone, consistent with the grid
		// views (controller/calendar.php's assign_event_row() TIME field
		// already uses $user->create_datetime()) — board_timezone() is still
		// used elsewhere in this class for day-bucketing/storage identity
		// (form parsing, occurrence_ts math), which must stay in the board's
		// timezone; only display formatting moves here. occurrence_ts/start_ts/
		// end_ts columns themselves stay UTC epoch — display-only change.
		$this->template->assign_vars([
			'EVENT_ID'     => $event['event_id'],
			'TITLE'        => $event['title'],
			'DESCRIPTION'  => $event['description_html'],
			'COLOR'        => $event['color'],
			'ALL_DAY'      => $event['all_day'],
			'IS_SPECIAL'   => ($event['event_type'] === 1),
			'START'        => $this->user->create_datetime('@' . $event['start_ts'])->format('Y-m-d H:i'),
			'END'          => $this->user->create_datetime('@' . $event['end_ts'])->format('Y-m-d H:i'),
			'RECUR_LABEL'  => $this->recur_label($event['recur_type']),
			'S_HAS_TOPIC'  => ($event['topic_id'] > 0),
			'U_TOPIC'      => $this->topic_url($event['topic_id']),
			'S_CAN_MANAGE' => $can_manage,
			'U_EDIT'       => $can_manage ? $this->helper->route('ecyaz_eventscalendar_event_edit', ['event_id' => $event['event_id']]) : '',
			'U_DELETE'     => $can_manage ? $this->helper->route('ecyaz_eventscalendar_event_delete', ['event_id' => $event['event_id']]) : '',
			'U_BACK'       => $this->helper->route('ecyaz_eventscalendar_month'),
		]);

		$this->assign_rsvp_template_vars($event);

		return $this->helper->render('ecyaz_eventscalendar_event_body.html', $event['title']);
	}

	public function attend($event_id)
	{
		$event_id = (int) $event_id;

		$this->require_attend();

		// CSRF check before any load/DB access — mirrors landcrm's
		// visit_action() ordering (schedule.php).
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error('FORM_INVALID');
		}

		$event = $this->load_event($event_id);

		$occurrence_ts = $this->request->variable('occurrence_ts', 0);

		// Binding RSVP identity rule: occurrence_ts must be a real occurrence
		// start for recurring events, or exactly 0 (valid only when the
		// event does not recur) — validated BEFORE any insert.
		if (!$this->recurrence->is_occurrence($event, $occurrence_ts))
		{
			trigger_error('ECAL_ERR_INVALID_OCCURRENCE');
		}

		$this->toggle_attendance($event_id, (int) $this->user->data['user_id'], $occurrence_ts);

		redirect($this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event_id], false));
	}

	// ------------------------------------------------------------------
	// RSVP (Task 5)
	// ------------------------------------------------------------------

	/**
	 * Toggle semantics: an existing (event_id, user_id, occurrence_ts) row
	 * is deleted (un-attend); otherwise a new row is inserted with
	 * attend_ts = now.
	 */
	protected function toggle_attendance(int $event_id, int $user_id, int $occurrence_ts): void
	{
		$sql = 'SELECT user_id
			FROM ' . $this->attendees_table . '
			WHERE event_id = ' . (int) $event_id . '
				AND user_id = ' . (int) $user_id . '
				AND occurrence_ts = ' . (int) $occurrence_ts;
		$result   = $this->db->sql_query($sql);
		$existing = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($existing)
		{
			$this->db->sql_query('DELETE FROM ' . $this->attendees_table . '
				WHERE event_id = ' . (int) $event_id . '
					AND user_id = ' . (int) $user_id . '
					AND occurrence_ts = ' . (int) $occurrence_ts);

			return;
		}

		$row = [
			'event_id'      => (int) $event_id,
			'user_id'       => (int) $user_id,
			'occurrence_ts' => (int) $occurrence_ts,
			'attend_ts'     => time(),
		];

		// Two rapid POSTs can both pass the SELECT above and race into this
		// INSERT; the composite PK on (event_id, user_id, occurrence_ts)
		// stops the duplicate row but would otherwise surface phpBB's
		// generic SQL error page to the losing request. Suppress the error
		// with sql_return_on_error() and treat any failure here as a no-op:
		// the user's desired end state (attending) either already exists
		// from the winning request, or the next page load shows the truth.
		$this->db->sql_return_on_error(true);
		$this->db->sql_query('INSERT INTO ' . $this->attendees_table . ' ' . $this->db->sql_build_array('INSERT', $row));
		$this->db->sql_return_on_error(false);
	}

	/**
	 * The occurrence_ts list the event page lists: [0] for a non-recurring
	 * event (the RSVP identity rule's "single instance" sentinel), or up to
	 * RSVP_OCCURRENCE_COUNT upcoming occurrence starts (strictly after now,
	 * via next_occurrence() iteration) for a recurring one.
	 */
	protected function occurrence_list(array $event): array
	{
		if ((int) $event['recur_type'] === self::RECUR_NONE)
		{
			return [0];
		}

		$list  = [];
		$after = time();

		for ($i = 0; $i < self::RSVP_OCCURRENCE_COUNT; $i++)
		{
			$next = $this->recurrence->next_occurrence($event, $after);

			if ($next === null)
			{
				break;
			}

			$list[] = $next;
			$after  = $next;
		}

		return $list;
	}

	/**
	 * All attendee user_ids for $event_id, keyed by occurrence_ts, in a
	 * single query covering every listed occurrence (Global Constraints:
	 * batch-load, not one query per occurrence).
	 */
	protected function load_attendees_by_occurrence(int $event_id, array $occurrence_ts_list): array
	{
		$map = array_fill_keys($occurrence_ts_list, []);

		if (empty($occurrence_ts_list))
		{
			return $map;
		}

		$sql = 'SELECT user_id, occurrence_ts
			FROM ' . $this->attendees_table . '
			WHERE event_id = ' . (int) $event_id . '
				AND ' . $this->db->sql_in_set('occurrence_ts', array_map('intval', $occurrence_ts_list));
		$result = $this->db->sql_query($sql);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$map[(int) $row['occurrence_ts']][] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $map;
	}

	/**
	 * M3 fix: formatted in the VIEWER's timezone (via $user->create_datetime()),
	 * not the board's — see view()'s comment. occurrence_ts/start_ts/end_ts
	 * themselves stay UTC epoch; only this display string moves.
	 */
	protected function format_occurrence_when(array $event, int $occurrence_ts): string
	{
		$occ_start = ($occurrence_ts === 0) ? (int) $event['start_ts'] : $occurrence_ts;
		$occ_end   = $occ_start + (max(0, (int) $event['end_ts'] - (int) $event['start_ts']));

		if (!empty($event['all_day']))
		{
			return $this->user->create_datetime('@' . $occ_start)->format('Y-m-d');
		}

		return $this->user->create_datetime('@' . $occ_start)->format('Y-m-d H:i')
			. ' – '
			. $this->user->create_datetime('@' . $occ_end)->format('Y-m-d H:i');
	}

	/**
	 * Assigns the occurrence/attendee block vars view() renders. Attendee
	 * lists are shown to anyone who can view the event (require_view()
	 * already ran); the attend/un-attend button/form is shown only when
	 * u_ecal_attend is held (Global Constraints: guests without
	 * u_ecal_attend see attendee lists but no buttons).
	 */
	protected function assign_rsvp_template_vars(array $event): void
	{
		$occurrences = $this->occurrence_list($event);
		$by_occ      = $this->load_attendees_by_occurrence($event['event_id'], $occurrences);

		// Batch-load every listed occurrence's attendees' users in ONE
		// query, then read usernames/avatars from the loader's cache below
		// (no further per-user queries).
		$all_user_ids = [];

		foreach ($by_occ as $user_ids)
		{
			$all_user_ids = array_merge($all_user_ids, $user_ids);
		}

		$this->user_loader->load_users(array_unique($all_user_ids));

		$can_attend = $this->auth->acl_get('u_ecal_attend');

		if ($can_attend)
		{
			add_form_key(self::FORM_KEY);
		}

		$current_user_id = (int) $this->user->data['user_id'];

		$this->template->assign_vars([
			'S_IS_RECURRING' => ((int) $event['recur_type'] !== self::RECUR_NONE),
			'S_CAN_ATTEND'   => $can_attend,
			'U_ATTEND'       => $this->helper->route('ecyaz_eventscalendar_event_attend', ['event_id' => $event['event_id']]),
		]);

		foreach ($occurrences as $occurrence_ts)
		{
			$attendee_ids = $by_occ[$occurrence_ts] ?? [];

			$this->template->assign_block_vars('occurrence', [
				'OCCURRENCE_TS'  => $occurrence_ts,
				'WHEN'           => $this->format_occurrence_when($event, $occurrence_ts),
				'S_ATTENDING'    => in_array($current_user_id, $attendee_ids, true),
				'ATTENDEE_COUNT' => count($attendee_ids),
			]);

			foreach ($attendee_ids as $attendee_id)
			{
				$this->template->assign_block_vars('occurrence.attendee', [
					'AVATAR'   => $this->user_loader->get_avatar($attendee_id),
					'USERNAME' => $this->user_loader->get_username($attendee_id, 'full'),
				]);
			}
		}
	}

	public function add()
	{
		$this->require_post();

		add_form_key(self::FORM_KEY);

		$errors = [];

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error('FORM_INVALID');
			}

			$data = $this->collect_form_data($errors);

			if (empty($errors))
			{
				$data['poster_id'] = (int) $this->user->data['user_id'];
				$event_id          = $this->event_manager->create($data);

				redirect($this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event_id], false));
			}
		}

		$this->assign_form_template_vars($errors, null);

		$this->template->assign_vars([
			'S_EDIT'   => false,
			'U_ACTION' => $this->helper->route('ecyaz_eventscalendar_event_add'),
			'U_BACK'   => $this->helper->route('ecyaz_eventscalendar_month'),
		]);

		return $this->helper->render('ecyaz_eventscalendar_event_form_body.html', $this->language->lang('ECAL_NEW_EVENT'));
	}

	public function edit($event_id)
	{
		$event_id = (int) $event_id;

		// Mirrors view()'s order: require_view() (loads the extension lang file
		// and 403s on missing u_ecal_view) must run before load_event()/
		// reject_special() so their ECAL_EVENT_NOT_FOUND/ECAL_SPECIAL_NOT_EDITABLE
		// lang keys render translated rather than as raw keys. The subsequent
		// require_owner_or_manage() call below re-invokes require_view()
		// internally (idempotent -- add_lang() is a no-op on an already-loaded
		// file) and performs the actual owner/manage authorization check, so
		// authorization semantics are unchanged.
		$this->require_view();

		$event = $this->load_event($event_id);

		$this->reject_special($event);
		$this->require_owner_or_manage($event);

		add_form_key(self::FORM_KEY);

		$errors = [];

		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key(self::FORM_KEY))
			{
				trigger_error('FORM_INVALID');
			}

			$data = $this->collect_form_data($errors);

			if (empty($errors))
			{
				$this->event_manager->update($event_id, $data);

				redirect($this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event_id], false));
			}
		}

		$this->assign_form_template_vars($errors, $event);

		$this->template->assign_vars([
			'S_EDIT'   => true,
			'U_ACTION' => $this->helper->route('ecyaz_eventscalendar_event_edit', ['event_id' => $event_id]),
			'U_BACK'   => $this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $event_id]),
		]);

		return $this->helper->render('ecyaz_eventscalendar_event_form_body.html', $this->language->lang('ECAL_EDIT_EVENT'));
	}

	public function delete($event_id)
	{
		$event_id = (int) $event_id;

		// See edit() above for why require_view() must precede load_event().
		$this->require_view();

		$event = $this->load_event($event_id);

		$this->reject_special($event);
		$this->require_owner_or_manage($event);

		if (confirm_box(true))
		{
			$this->event_manager->delete($event_id);

			redirect($this->helper->route('ecyaz_eventscalendar_month', [], false));
		}
		else
		{
			confirm_box(
				false,
				$this->language->lang('ECAL_DELETE_CONFIRM', $event['title']),
				build_hidden_fields(['event_id' => $event_id])
			);
		}

		// Unreachable: both branches above exit (redirect()/confirm_box(false)
		// both call exit() internally — see class docblock).
	}

	// ------------------------------------------------------------------
	// Form handling
	// ------------------------------------------------------------------

	protected function recur_label(int $recur_type): string
	{
		$keys = [
			self::RECUR_NONE    => 'ECAL_RECUR_NONE',
			self::RECUR_DAILY   => 'ECAL_RECUR_DAILY',
			self::RECUR_WEEKLY  => 'ECAL_RECUR_WEEKLY',
			self::RECUR_MONTHLY => 'ECAL_RECUR_MONTHLY',
			self::RECUR_ANNUAL  => 'ECAL_RECUR_ANNUAL',
		];

		return $this->language->lang($keys[$recur_type] ?? $keys[self::RECUR_NONE]);
	}

	/**
	 * $end_of_day mirrors calendar.php's parse_date_param() convention: an
	 * "end date" picked by the user is inclusive of that whole day, so its
	 * timestamp must be the day's last second (start-of-day + 86399), not
	 * its first. Used for: an all-day event's end_date (otherwise a
	 * multi-day all-day span's duration falls one day short — the last
	 * calendar day never renders) and recur_until (otherwise an occurrence
	 * starting later the same day as the cutoff is excluded, since
	 * recurrence.php only includes occurrences whose start <= until).
	 */
	protected function parse_date_only(string $date, array &$errors, string $error_key, bool $end_of_day = false): ?int
	{
		$date = trim($date);

		if ($date === '')
		{
			$errors[] = $error_key;

			return null;
		}

		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $this->board_timezone());

		// Round-trip check: createFromFormat() is lenient about out-of-range
		// fields (e.g. day 32 silently overflows into the next month), so an
		// invalid calendar date must be caught by comparing the re-formatted
		// value back against the input, not just checking $dt is truthy.
		if (!$dt || $dt->format('Y-m-d') !== $date)
		{
			$errors[] = $error_key;

			return null;
		}

		return $dt->setTime(0, 0, 0)->getTimestamp() + ($end_of_day ? 86399 : 0);
	}

	protected function parse_datetime(string $date, string $time, array &$errors, string $error_key): ?int
	{
		$date = trim($date);
		$time = (trim($time) !== '') ? trim($time) : '00:00';

		if ($date === '')
		{
			$errors[] = $error_key;

			return null;
		}

		$value = $date . ' ' . $time;
		$dt    = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value, $this->board_timezone());

		if (!$dt || $dt->format('Y-m-d H:i') !== $value)
		{
			$errors[] = $error_key;

			return null;
		}

		return $dt->getTimestamp();
	}

	protected function validate_recurrence_duration(int $start_ts, int $end_ts, int $recur_type, array &$errors): void
	{
		$max_by_type = [
			self::RECUR_DAILY   => 86400,
			self::RECUR_WEEKLY  => 604800,
			self::RECUR_MONTHLY => 28 * 86400,
			self::RECUR_ANNUAL  => 365 * 86400,
		];

		if (!isset($max_by_type[$recur_type]))
		{
			return;
		}

		if (($end_ts - $start_ts) >= $max_by_type[$recur_type])
		{
			$errors[] = 'ECAL_ERR_RECUR_DURATION';
		}
	}

	/**
	 * Reads and validates the submitted form into a create()/update()-ready
	 * $data array, appending any validation failures (as lang keys) to
	 * $errors by reference.
	 */
	protected function collect_form_data(array &$errors): array
	{
		$title       = $this->request->variable('title', '', true);
		$description = $this->request->variable('description', '', true);
		$all_day     = $this->request->variable('all_day', false);
		$recur_type  = $this->request->variable('recur_type', 0);
		$recur_until = $this->request->variable('recur_until', '');
		$color       = $this->request->variable('color', 0);
		$post_topic  = $this->request->variable('post_topic', false);

		$start_date = $this->request->variable('start_date', '');
		$start_time = $this->request->variable('start_time', '');
		$end_date   = $this->request->variable('end_date', '');
		$end_time   = $this->request->variable('end_time', '');

		if ($title === '')
		{
			$errors[] = 'ECAL_ERR_TITLE_REQUIRED';
		}
		else if (utf8_strlen($title) > 255)
		{
			$errors[] = 'ECAL_ERR_TITLE_TOO_LONG';
		}

		if ($all_day)
		{
			$start_ts = $this->parse_date_only($start_date, $errors, 'ECAL_ERR_START_INVALID');
			// end_of_day = true: the picked end date is inclusive of its whole
			// day (see parse_date_only() docblock) — required for multi-day
			// all-day spans to actually cover their last calendar day.
			$end_ts   = ($end_date !== '')
				? $this->parse_date_only($end_date, $errors, 'ECAL_ERR_END_INVALID', true)
				: (($start_ts !== null) ? $start_ts + 86399 : null);
		}
		else
		{
			$start_ts = $this->parse_datetime($start_date, $start_time, $errors, 'ECAL_ERR_START_INVALID');
			$end_ts   = ($end_date !== '') ? $this->parse_datetime($end_date, $end_time, $errors, 'ECAL_ERR_END_INVALID') : $start_ts;
		}

		if ($start_ts !== null && $end_ts !== null && $end_ts < $start_ts)
		{
			$errors[] = 'ECAL_ERR_END_BEFORE_START';
		}

		$recur_type = ($recur_type >= self::RECUR_NONE && $recur_type <= self::RECUR_ANNUAL) ? $recur_type : self::RECUR_NONE;

		$recur_until_ts = 0;

		if ($recur_type !== self::RECUR_NONE && trim($recur_until) !== '')
		{
			// end_of_day = true: "repeat until 5 March" must still include an
			// occurrence starting later on 5 March itself (see parse_date_only()
			// docblock) — recurrence.php excludes any occurrence whose start
			// is strictly after recur_until.
			$until_ts = $this->parse_date_only($recur_until, $errors, 'ECAL_ERR_UNTIL_INVALID', true);

			if ($until_ts !== null)
			{
				if ($start_ts !== null && $until_ts < $start_ts)
				{
					$errors[] = 'ECAL_ERR_UNTIL_BEFORE_START';
				}

				$recur_until_ts = $until_ts;
			}
		}

		if ($start_ts !== null && $end_ts !== null)
		{
			$this->validate_recurrence_duration($start_ts, $end_ts, $recur_type, $errors);
		}

		$color = ($color >= 0 && $color < self::COLOR_COUNT) ? $color : 0;

		return [
			'title'       => $title,
			'description' => $description,
			'start_ts'    => $start_ts ?? 0,
			'end_ts'      => $end_ts ?? ($start_ts ?? 0),
			'all_day'     => (bool) $all_day,
			'recur_type'  => $recur_type,
			'recur_until' => $recur_until_ts,
			'color'       => $color,
			'post_topic'  => ($this->can_offer_topic_checkbox() && (bool) $post_topic),
		];
	}

	/**
	 * Prefill values for the form: after a failed validation POST, echoes
	 * back exactly what was submitted; otherwise reflects $event (edit) or
	 * sensible new-event defaults (add).
	 */
	protected function form_prefill(?array $event): array
	{
		if ($this->request->is_set_post('submit'))
		{
			return [
				'title'       => $this->request->variable('title', '', true),
				'description' => $this->request->variable('description', '', true),
				'start_date'  => $this->request->variable('start_date', ''),
				'start_time'  => $this->request->variable('start_time', ''),
				'end_date'    => $this->request->variable('end_date', ''),
				'end_time'    => $this->request->variable('end_time', ''),
				'all_day'     => $this->request->variable('all_day', false),
				'recur_type'  => $this->request->variable('recur_type', 0),
				'recur_until' => $this->request->variable('recur_until', ''),
				'color'       => $this->request->variable('color', 0),
				'post_topic'  => $this->request->variable('post_topic', false),
			];
		}

		$tz = $this->board_timezone();

		if ($event !== null)
		{
			$start = (new \DateTimeImmutable('@' . $event['start_ts']))->setTimezone($tz);
			$end   = (new \DateTimeImmutable('@' . $event['end_ts']))->setTimezone($tz);

			// C1 fix: $event['description'] is the RAW stored s9e-XML/BBCode-
			// UID-tagged text (event_manager::create()/update()'s
			// generate_text_for_storage() output) — echoing it straight into
			// the textarea would both show the reviewer/editor markup
			// instead of the original BBCode source AND, on an unchanged
			// resubmit, run generate_text_for_storage() a SECOND time over
			// already-encoded text, corrupting the description. Decode it
			// back to plain BBCode via generate_text_for_edit() (VERIFIED
			// includes/functions_content.php: `function
			// generate_text_for_edit($text, $uid, $flags)`, returns an array
			// whose 'text' member is the decoded source; $flags is the
			// stored bbcode_options bitfield) — the exact inverse of
			// generate_text_for_storage(), same as every core edit form
			// (e.g. posting.php).
			$this->ensure_content_functions();
			$decoded = generate_text_for_edit($event['description'], $event['bbcode_uid'], $event['bbcode_options']);

			return [
				'title'       => $event['title'],
				'description' => $decoded['text'],
				'start_date'  => $start->format('Y-m-d'),
				'start_time'  => $start->format('H:i'),
				'end_date'    => $end->format('Y-m-d'),
				'end_time'    => $end->format('H:i'),
				'all_day'     => $event['all_day'],
				'recur_type'  => $event['recur_type'],
				'recur_until' => ($event['recur_until'] > 0) ? (new \DateTimeImmutable('@' . $event['recur_until']))->setTimezone($tz)->format('Y-m-d') : '',
				'color'       => $event['color'],
				'post_topic'  => false,
			];
		}

		$now = new \DateTimeImmutable('now', $tz);

		return [
			'title'       => '',
			'description' => '',
			'start_date'  => $now->format('Y-m-d'),
			'start_time'  => $now->format('H:i'),
			'end_date'    => $now->format('Y-m-d'),
			'end_time'    => $now->modify('+1 hour')->format('H:i'),
			'all_day'     => false,
			'recur_type'  => self::RECUR_NONE,
			'recur_until' => '',
			'color'       => 0,
			'post_topic'  => false,
		];
	}

	protected function assign_form_template_vars(array $errors, ?array $event): void
	{
		$prefill = $this->form_prefill($event);

		$error_text = '';

		foreach ($errors as $key)
		{
			$error_text .= ($error_text !== '' ? '<br />' : '') . $this->language->lang($key);
		}

		foreach ([self::RECUR_NONE, self::RECUR_DAILY, self::RECUR_WEEKLY, self::RECUR_MONTHLY, self::RECUR_ANNUAL] as $value)
		{
			$this->template->assign_block_vars('recur_options', [
				'VALUE'      => $value,
				'LABEL'      => $this->recur_label($value),
				'S_SELECTED' => ($value === (int) $prefill['recur_type']),
			]);
		}

		for ($c = 0; $c < self::COLOR_COUNT; $c++)
		{
			$this->template->assign_block_vars('color_options', [
				'VALUE'      => $c,
				'LABEL'      => $this->language->lang('ECAL_COLOR_' . $c),
				'S_SELECTED' => ($c === (int) $prefill['color']),
			]);
		}

		$this->template->assign_vars([
			'ERROR'         => $error_text,
			'TITLE'         => $prefill['title'],
			'DESCRIPTION'   => $prefill['description'],
			'START_DATE'    => $prefill['start_date'],
			'START_TIME'    => $prefill['start_time'],
			'END_DATE'      => $prefill['end_date'],
			'END_TIME'      => $prefill['end_time'],
			'S_ALL_DAY'     => $prefill['all_day'],
			'RECUR_UNTIL'   => $prefill['recur_until'],
			'S_POST_TOPIC'  => $prefill['post_topic'],
			'S_CAN_TOPIC'   => $this->can_offer_topic_checkbox(),
		]);
	}
}
