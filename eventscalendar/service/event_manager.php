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
 * Owns event CRUD (ecal_events, plus cascade-deleting its ecal_attendees
 * rows on event delete), BBCode storage/display, the optional
 * discussion-topic autopost, and outbox enqueueing on every write.
 *
 * The per-occurrence attend/un-attend toggle (day-to-day ecal_attendees
 * writes) is NOT owned here — that lives in controller/event.php's
 * toggle_attendance().
 *
 * $data passed to create()/update() is assumed already form-validated by the
 * caller (controller/event.php) — this class does not re-derive validation
 * rules, it persists.
 *
 * Task 10 (binding, revises the original decision below): event_type = 1
 * (ACP special dates) rows ALSO go through create()/update()/delete() here
 * -- the ACP special-dates page (acp/main_module.php) passes an explicit
 * 'event_type' key in $data to create(), so the SAME outbox-enqueue +
 * ICS-cache-invalidation side effects fire for special dates as for every
 * other write. update()/delete() never take an event_type argument at all
 * (event_type is immutable once created; the WHERE-by-event_id shape of
 * both methods is already type-agnostic), so no change was needed there.
 * The distinction that still matters everywhere else in the codebase is
 * EDITABILITY, not writability: controller/event.php's reject_special()
 * still 403s any front-end user attempt to edit/delete a special date --
 * only the ACP special-dates page is allowed to call update()/delete() for
 * one.
 *
 * Topic autopost (VERIFY submit_post() signature: includes/functions_posting.php:1622
 * `function submit_post($mode, $subject, $username, $topic_type, &$poll_ary,
 * &$data_ary, $update_message = true, $update_search_index = true)` — poster_id
 * for mode 'post' is taken from the global $user, which is the same singleton
 * as the injected $this->user, so no bot/user-swap is needed here unlike
 * landcrm's topic_manager) happens inside create()/update() so the "failure
 * to post must never fail the event save" guarantee is a single try/catch
 * around one already-committed write, not a cross-class contract. A
 * dedicated topic_manager-style service was not introduced (not in the
 * brief's file list); the small amount of posting logic lives here instead.
 *
 * Task 6: update()/delete() also fire an 'updated'/'cancelled' reminder
 * notice (service/notifier.php, notification/reminder.php) to every
 * occurrence's FUTURE attendees — never to the general opted-in audience,
 * only to people who actually RSVP'd (see notification/reminder.php's
 * find_users_for_notification() branch on 'reason'). "Future" is decided
 * per occurrence_ts against time() at the moment of the edit/delete, using
 * the SAME 0-for-non-recurring sentinel as ecal_attendees (0 compares
 * against the event's own start_ts).
 */
class event_manager
{
	const EVENT_TYPE_USER    = 0;
	const EVENT_TYPE_SPECIAL = 1;

	/** default bbcode_options when generate_text_for_storage() cannot run (should not happen) */
	const DEFAULT_BBCODE_OPTIONS = 7;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \ecyaz\eventscalendar\service\recurrence */
	protected $recurrence;

	/** @var \ecyaz\eventscalendar\service\outbox */
	protected $outbox;

	/** @var \ecyaz\eventscalendar\service\notifier */
	protected $notifier;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $events_table;

	/** @var string */
	protected $attendees_table;

	/** @var string */
	protected $reminded_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\ecyaz\eventscalendar\service\recurrence $recurrence,
		\ecyaz\eventscalendar\service\outbox $outbox,
		\ecyaz\eventscalendar\service\notifier $notifier,
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		$table_prefix
	)
	{
		$this->db              = $db;
		$this->config          = $config;
		$this->recurrence      = $recurrence;
		$this->outbox          = $outbox;
		$this->notifier        = $notifier;
		$this->user            = $user;
		$this->helper          = $helper;
		$this->events_table    = $table_prefix . 'ecal_events';
		$this->attendees_table = $table_prefix . 'ecal_attendees';
		$this->reminded_table  = $table_prefix . 'ecal_reminded';
	}

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	/**
	 * Creates a new event. Returns the new event_id.
	 *
	 * Expected $data keys: title, description, start_ts, end_ts, all_day,
	 * recur_type, recur_until, color, poster_id, and optionally post_topic
	 * (bool — the controller has already gated this against
	 * ecal_topic_forum_id + f_post before setting it) and event_type
	 * (defaults to EVENT_TYPE_USER; the ACP special-dates page is the only
	 * caller that ever passes EVENT_TYPE_SPECIAL — see class docblock).
	 */
	public function create(array $data): int
	{
		$this->ensure_content_functions();

		$now = time();

		$description = (string) ($data['description'] ?? '');
		$uid = $bitfield = '';
		$options = 0;
		generate_text_for_storage($description, $uid, $bitfield, $options, true, true, true);

		$event_type = (int) ($data['event_type'] ?? self::EVENT_TYPE_USER);
		$event_type = in_array($event_type, [self::EVENT_TYPE_USER, self::EVENT_TYPE_SPECIAL], true) ? $event_type : self::EVENT_TYPE_USER;

		$row = [
			'poster_id'       => (int) ($data['poster_id'] ?? $this->user->data['user_id']),
			'event_type'      => $event_type,
			'title'           => (string) $data['title'],
			'description'     => $description,
			'bbcode_uid'      => $uid,
			'bbcode_bitfield' => $bitfield,
			'bbcode_options'  => (int) $options,
			'start_ts'        => (int) $data['start_ts'],
			'end_ts'          => (int) $data['end_ts'],
			'all_day'         => !empty($data['all_day']) ? 1 : 0,
			'recur_type'      => (int) ($data['recur_type'] ?? 0),
			'recur_until'     => (int) ($data['recur_until'] ?? 0),
			'color'           => (int) ($data['color'] ?? 0),
			'topic_id'        => 0,
			'gcal_id'         => '',
			'event_time'      => $now,
			'event_edit_time' => $now,
		];

		$this->db->sql_query('INSERT INTO ' . $this->events_table . ' ' . $this->db->sql_build_array('INSERT', $row));
		$event_id = (int) $this->db->sql_nextid();

		if (!empty($data['post_topic']))
		{
			$this->try_post_discussion_topic($event_id, array_merge($row, ['event_id' => $event_id]));
		}

		$this->outbox->enqueue_upsert($event_id);

		return $event_id;
	}

	/**
	 * Updates an existing user event's editable fields. Never touches
	 * poster_id, topic_id (except to set it on first successful autopost),
	 * gcal_id, or event_time.
	 */
	public function update(int $event_id, array $data): void
	{
		$this->ensure_content_functions();

		$existing = $this->fetch_row($event_id);

		if ($existing === null)
		{
			throw new \InvalidArgumentException('event not found: ' . $event_id);
		}

		$description = (string) ($data['description'] ?? '');
		$uid = $bitfield = '';
		$options = 0;
		generate_text_for_storage($description, $uid, $bitfield, $options, true, true, true);

		$update = [
			'title'           => (string) $data['title'],
			'description'     => $description,
			'bbcode_uid'      => $uid,
			'bbcode_bitfield' => $bitfield,
			'bbcode_options'  => (int) $options,
			'start_ts'        => (int) $data['start_ts'],
			'end_ts'          => (int) $data['end_ts'],
			'all_day'         => !empty($data['all_day']) ? 1 : 0,
			'recur_type'      => (int) ($data['recur_type'] ?? 0),
			'recur_until'     => (int) ($data['recur_until'] ?? 0),
			'color'           => (int) ($data['color'] ?? 0),
			'event_edit_time' => time(),
		];

		$this->db->sql_query('UPDATE ' . $this->events_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $update) . '
			WHERE event_id = ' . (int) $event_id);

		// A topic can only ever be attached once — if the event already has
		// one, re-ticking the checkbox on edit is a silent no-op (no second
		// topic is ever created for the same event).
		if ((int) $existing['topic_id'] === 0 && !empty($data['post_topic']))
		{
			$this->try_post_discussion_topic($event_id, array_merge($existing, $update, ['event_id' => $event_id]));
		}

		$this->fire_change_notice($event_id, array_merge($existing, $update), \ecyaz\eventscalendar\notification\reminder::REASON_UPDATED);

		$this->outbox->enqueue_upsert($event_id);
	}

	/**
	 * Deletes the event, its attendee rows, and its ecal_reminded dedupe
	 * markers. Deliberately leaves the discussion topic (if any) in place —
	 * see Global Constraints.
	 *
	 * ecal_reminded cleanup (Task 6 hardening, round 2): those marker rows
	 * are otherwise keyed only by event_id/occurrence_ts/user_id with
	 * nothing else ever deleting them (cron/reminders.php's own
	 * prune_reminded_markers() is age-based and deliberately never touches
	 * occurrence_ts = 0 rows — see that method's docblock); once the event
	 * itself is gone those rows can never be re-associated with anything, so
	 * they must be swept here instead.
	 */
	public function delete(int $event_id): void
	{
		$existing = $this->fetch_row($event_id);

		if ($existing === null)
		{
			return;
		}

		// Must fire BEFORE the attendee rows are deleted below — the
		// cancelled notice's recipient list comes from them.
		$this->fire_change_notice($event_id, $existing, \ecyaz\eventscalendar\notification\reminder::REASON_CANCELLED);

		$this->outbox->enqueue_delete($event_id, (string) $existing['gcal_id']);

		$this->db->sql_query('DELETE FROM ' . $this->attendees_table . ' WHERE event_id = ' . (int) $event_id);
		$this->db->sql_query('DELETE FROM ' . $this->reminded_table . ' WHERE event_id = ' . (int) $event_id);
		$this->db->sql_query('DELETE FROM ' . $this->events_table . ' WHERE event_id = ' . (int) $event_id);
	}

	/**
	 * Fetches one event with its description rendered for display
	 * (description_html, via generate_text_for_display()). Returns null when
	 * the event does not exist.
	 */
	public function get(int $event_id): ?array
	{
		$row = $this->fetch_row($event_id);

		if ($row === null)
		{
			return null;
		}

		$this->ensure_content_functions();

		$row['description_html'] = generate_text_for_display(
			$row['description'],
			$row['bbcode_uid'],
			$row['bbcode_bitfield'],
			$row['bbcode_options']
		);

		return $row;
	}

	// ------------------------------------------------------------------
	// Task 6: edit/cancel notices
	// ------------------------------------------------------------------

	/**
	 * Fires one 'updated'/'cancelled' notice per occurrence that still has
	 * at least one FUTURE attendee (never to a past occurrence's attendees
	 * — an event that already happened does not need an "it was edited"
	 * notice). No-op when there are no future attendees at all.
	 *
	 * @param array $row Row to source title/start_ts from: the merged
	 *                    post-update row for 'updated', or the
	 *                    still-pre-delete row (fetched before any DELETE)
	 *                    for 'cancelled'.
	 */
	protected function fire_change_notice(int $event_id, array $row, string $reason): void
	{
		$attendees_by_occurrence = $this->future_attendees_by_occurrence($event_id, (int) $row['start_ts']);

		foreach ($attendees_by_occurrence as $occurrence_ts => $attendee_ids)
		{
			$occurrence_at = ($occurrence_ts === 0) ? (int) $row['start_ts'] : $occurrence_ts;

			$this->notifier->notify(\ecyaz\eventscalendar\notification\reminder::TYPE_NAME, [
				'event_id'      => $event_id,
				'occurrence_ts' => $occurrence_ts,
				'occurrence_at' => $occurrence_at,
				'title'         => (string) $row['title'],
				'reason'        => $reason,
				'attendee_ids'  => $attendee_ids,
			]);
		}
	}

	/**
	 * All attendee user_ids for $event_id whose occurrence has not started
	 * yet, keyed by occurrence_ts (the ecal_attendees identity: 0 for a
	 * non-recurring event's single instance, compared against the event's
	 * own start_ts; the occurrence's own start otherwise).
	 *
	 * @return array<int, int[]>
	 */
	protected function future_attendees_by_occurrence(int $event_id, int $start_ts): array
	{
		$now = time();

		$sql = 'SELECT occurrence_ts, user_id
			FROM ' . $this->attendees_table . '
			WHERE event_id = ' . (int) $event_id;
		$result = $this->db->sql_query($sql);

		$map = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$occurrence_ts = (int) $row['occurrence_ts'];
			$occurrence_at = ($occurrence_ts === 0) ? $start_ts : $occurrence_ts;

			if ($occurrence_at <= $now)
			{
				continue;
			}

			$map[$occurrence_ts][] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $map;
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Raw row fetch (int-cast columns, no BBCode rendering) shared by
	 * get()/update()/delete().
	 */
	protected function fetch_row(int $event_id): ?array
	{
		$sql    = 'SELECT * FROM ' . $this->events_table . ' WHERE event_id = ' . (int) $event_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$row['event_id']        = (int) $row['event_id'];
		$row['poster_id']       = (int) $row['poster_id'];
		$row['event_type']      = (int) $row['event_type'];
		$row['bbcode_options']  = (int) $row['bbcode_options'];
		$row['start_ts']        = (int) $row['start_ts'];
		$row['end_ts']          = (int) $row['end_ts'];
		$row['all_day']         = (bool) $row['all_day'];
		$row['recur_type']      = (int) $row['recur_type'];
		$row['recur_until']     = (int) $row['recur_until'];
		$row['color']           = (int) $row['color'];
		$row['topic_id']        = (int) $row['topic_id'];
		$row['event_time']      = (int) $row['event_time'];
		$row['event_edit_time'] = (int) $row['event_edit_time'];

		return $row;
	}

	/**
	 * Posts the event's discussion topic and stores its topic_id, swallowing
	 * any failure (Global Constraints: "topic-post failure must not fail the
	 * event save"). Logged to the PHP/web-server error log — there is no
	 * @log service in this class's constructor and adding one purely to
	 * report a best-effort side-channel failure was judged not worth the
	 * extra dependency (VERIFY/decision — see task-4-report.md).
	 */
	protected function try_post_discussion_topic(int $event_id, array $row): void
	{
		try
		{
			$this->post_discussion_topic($event_id, $row);
		}
		catch (\Throwable $e)
		{
			error_log('[ecyaz/eventscalendar] discussion topic post failed for event ' . $event_id . ': ' . $e->getMessage());
		}
	}

	protected function post_discussion_topic(int $event_id, array $row): void
	{
		$forum_id = (int) $this->config['ecal_topic_forum_id'];

		if ($forum_id <= 0)
		{
			return;
		}

		$this->ensure_posting_functions();

		$title = utf8_substr((string) $row['title'], 0, 100);
		$url   = $this->helper->route(
			'ecyaz_eventscalendar_event',
			['event_id' => $event_id],
			false,
			false,
			\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
		);

		$body = '[b]' . $row['title'] . '[/b]' . "\n\n" . $this->summary_line($row) . "\n\n" . 'Event: [url]' . $url . '[/url]';

		$uid = $bitfield = '';
		$options = 0;
		generate_text_for_storage($body, $uid, $bitfield, $options, true, true, true);

		$poll = [];
		$data = [
			'forum_id'             => $forum_id,
			'icon_id'              => false,
			'enable_bbcode'        => true,
			'enable_smilies'       => true,
			'enable_urls'          => true,
			'enable_sig'           => false,
			'message'              => $body,
			'message_md5'          => md5($body),
			'bbcode_bitfield'      => $bitfield,
			'bbcode_uid'           => $uid,
			'post_edit_locked'     => 0,
			'topic_title'          => $title,
			'notify_set'           => false,
			'notify'               => false,
			'post_time'            => 0,
			'forum_name'           => '',
			'enable_indexing'      => true,
			// force_approved_state alone is enough (VERIFY includes/functions_posting.php
			// ~1737-1743): if a 'force_visibility' key were also present here, submit_post()
			// would evaluate it *after* force_approved_state and let it win, and
			// `isset($data_ary['force_visibility'])` is true even when the value is `false`
			// — `(int) false === 0 === ITEM_UNAPPROVED`, silently forcing the topic
			// invisible regardless of this key. ITEM_APPROVED is defined in
			// includes/constants.php, which common.php loads unconditionally, so it is
			// always in scope here without an explicit include.
			'force_approved_state' => ITEM_APPROVED,
		];

		$redirect = submit_post('post', $title, $this->user->data['username'], POST_NORMAL, $poll, $data);

		if ($redirect !== false && !empty($data['topic_id']))
		{
			$topic_id = (int) $data['topic_id'];
			$this->db->sql_query('UPDATE ' . $this->events_table . ' SET topic_id = ' . $topic_id . ' WHERE event_id = ' . (int) $event_id);
		}
	}

	/**
	 * English-only summary line (all-day vs timed) for the autoposted topic
	 * body — mirrors landcrm's build_*_post_body() being English-only by
	 * owner decision (a posted topic is fixed text, not per-viewer).
	 */
	protected function summary_line(array $row): string
	{
		if (!empty($row['all_day']))
		{
			return 'When: ' . gmdate('Y-m-d', (int) $row['start_ts']) . ' (all day)';
		}

		return 'When: ' . gmdate('Y-m-d H:i', (int) $row['start_ts']) . ' - ' . gmdate('Y-m-d H:i', (int) $row['end_ts']) . ' UTC';
	}

	protected function ensure_posting_functions(): void
	{
		if (!function_exists('submit_post'))
		{
			global $phpbb_root_path, $phpEx;
			include $phpbb_root_path . 'includes/functions_posting.' . $phpEx;
		}
	}

	protected function ensure_content_functions(): void
	{
		if (!function_exists('generate_text_for_storage'))
		{
			global $phpbb_root_path, $phpEx;
			include $phpbb_root_path . 'includes/functions_content.' . $phpEx;
		}

		// message_parser::parse() (invoked by generate_text_for_storage())
		// reads $user->lang['TOO_FEW_CHARS'] et al when min_post_chars isn't
		// met — undefined-key PHP warnings otherwise (VERIFY: found via a
		// failing functional test; includes/message_parser.php:1171/:1262
		// reference posting.php's TOO_FEW_CHARS* keys).
		$this->user->add_lang('posting');
	}
}
