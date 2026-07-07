<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\cron;

/**
 * Hourly reminder scan: every event with an occurrence starting in
 * [now, now + ecal_reminder_days*86400] gets a reminder notice fired to its
 * attendees + opted-in users (service/notifier.php), deduped per (event_id,
 * occurrence_ts, user_id) via the ecal_reminded marker table — see
 * service/notifier.php's class docblock for why a marker table is used
 * instead of the notification framework's own (item_id-only) dedupe.
 */
class reminders extends \phpbb\cron\task\base
{
	const HOURLY_SECONDS = 3600;
	const NOTIFICATION_TYPE = \ecyaz\eventscalendar\notification\reminder::TYPE_NAME;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \ecyaz\eventscalendar\service\recurrence */
	protected $recurrence;

	/** @var \ecyaz\eventscalendar\service\notifier */
	protected $notifier;

	/** @var string */
	protected $events_table;

	/** @var string */
	protected $attendees_table;

	/** @var string */
	protected $reminded_table;

	/**
	 * Per-run cache (perf fix, review round 1): the full non-attendee-
	 * specific opt-in audience, resolved once via opted_in_users() and
	 * reused for every occurrence of every due event in this run() call,
	 * instead of notification\type\reminder::find_users_for_notification()
	 * re-running check_user_notification_options(false, ...)'s all-users
	 * scan per occurrence (was O(occurrences x users)). null = not yet
	 * resolved this run.
	 *
	 * @var array|null [user_id => [method_name, ...]]
	 */
	protected $opted_in_users_cache;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\ecyaz\eventscalendar\service\recurrence $recurrence,
		\ecyaz\eventscalendar\service\notifier $notifier,
		$table_prefix
	)
	{
		$this->config          = $config;
		$this->db              = $db;
		$this->recurrence      = $recurrence;
		$this->notifier        = $notifier;
		$this->events_table    = $table_prefix . 'ecal_events';
		$this->attendees_table = $table_prefix . 'ecal_attendees';
		$this->reminded_table  = $table_prefix . 'ecal_reminded';
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_run()
	{
		return (int) $this->config['ecal_reminders_last_run'] < (time() - self::HOURLY_SECONDS);
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$this->config->set('ecal_reminders_last_run', time());

		// Reset the per-run audience cache (see $opted_in_users_cache
		// docblock) so each run() call resolves it fresh — the underlying
		// UCP preferences can change between runs.
		$this->opted_in_users_cache = null;

		$now = time();

		// Fixed 86400s-per-day window bound (review note, round 1): this is
		// a wall-clock-agnostic UTC-second offset, not calendar-day-aware,
		// so the window's local end-of-day boundary can shift by up to 1
		// hour across a DST transition that falls inside
		// [now, window_end]. Acceptable: it only ever widens or narrows the
		// window by up to an hour at the edge, never causes a double- or
		// missed reminder outside that edge, and events near the boundary
		// still get reminded on a later hourly run before the window closes.
		$window_end = $now + (max(0, (int) $this->config['ecal_reminder_days']) * 86400);

		if ($window_end <= $now)
		{
			return;
		}

		foreach ($this->due_events($now, $window_end) as $event)
		{
			foreach ($this->recurrence->occurrences($event, $now, $window_end) as $occurrence_at)
			{
				// Only occurrences that START inside the window get reminded
				// (occurrences() also returns multi-day spans that merely
				// overlap the window from before $now).
				if ($occurrence_at < $now || $occurrence_at > $window_end)
				{
					continue;
				}

				$this->remind_occurrence($event, $this->occurrence_key($event, $occurrence_at), $occurrence_at);
			}
		}

		$this->prune_reminded_markers();
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * occurrence_ts identity used everywhere else in the extension
	 * (ecal_attendees, RSVP) — 0 for a non-recurring event's single
	 * instance, else the occurrence's own start.
	 */
	protected function occurrence_key(array $event, int $occurrence_at): int
	{
		return ((int) $event['recur_type'] === \ecyaz\eventscalendar\service\recurrence::RECUR_NONE) ? 0 : $occurrence_at;
	}

	/**
	 * The full non-attendee-specific opt-in audience, resolved once per
	 * run() and cached (see $opted_in_users_cache docblock). Uses
	 * attendee_ids => [] so notification\type\reminder::
	 * find_users_for_notification() takes the plain
	 * check_user_notification_options(false, ...) path with no per-
	 * occurrence attendee override baked into the cached result — each
	 * occurrence applies its OWN attendee override on top of this shared
	 * base in remind_occurrence().
	 */
	protected function opted_in_users(): array
	{
		if ($this->opted_in_users_cache === null)
		{
			$this->opted_in_users_cache = $this->notifier->resolve(self::NOTIFICATION_TYPE, [
				'reason'       => \ecyaz\eventscalendar\notification\reminder::REASON_REMINDER,
				'attendee_ids' => [],
			]);
		}

		return $this->opted_in_users_cache;
	}

	protected function remind_occurrence(array $event, int $occurrence_key, int $occurrence_at): void
	{
		$event_id = (int) $event['event_id'];

		$type_data = [
			'event_id'       => $event_id,
			'occurrence_ts'  => $occurrence_key,
			'occurrence_at'  => $occurrence_at,
			'title'          => (string) $event['title'],
			'reason'         => \ecyaz\eventscalendar\notification\reminder::REASON_REMINDER,
			'attendee_ids'   => $this->attendee_ids($event_id, $occurrence_key),
			// Per-run cache reuse (perf fix, round 1) — see opted_in_users()
			// and notification\type\reminder's class docblock.
			'opted_in_users' => $this->opted_in_users(),
		];

		$notify_users = $this->notifier->resolve(self::NOTIFICATION_TYPE, $type_data);

		// Zero-method filter (Task 6 hardening, round 2): find_users_for_notification()
		// can return a user_id keyed to an EMPTY method array — every default
		// method opted out in UCP still leaves the key present with value []
		// (phpbb\notification\type\base::check_user_notification_options()'s
		// tail loop only ever appends to $output[$user_id], never removes the
		// key). fire() already no-ops for such an entry (its per-method
		// foreach has nothing to iterate), but mark_reminded() below does not
		// know that — marking them reminded anyway would permanently
		// suppress a real reminder if the user re-enables a method mid-window
		// (occurrence_ts = 0 markers never expire; see mark_reminded()'s
		// reschedule-caveat doc, and even a timed marker survives up to the
		// 60-day prune_reminded_markers() horizon). Attendees are never
		// affected: notification\type\reminder's implicit opt-in override
		// (class docblock) already backfills $notify_users[$attendee_id] with
		// the type's default methods before resolve() returns, so an
		// attendee's entry here is always non-empty; this filter only ever
		// drops a fully opted-out NON-attendee.
		$notify_users = array_filter($notify_users, static function (array $methods): bool {
			return !empty($methods);
		});

		if (empty($notify_users))
		{
			return;
		}

		$already_reminded = $this->already_reminded_user_ids($event_id, $occurrence_key);

		foreach ($already_reminded as $user_id)
		{
			unset($notify_users[$user_id]);
		}

		if (empty($notify_users))
		{
			return;
		}

		// At-most-once guarantee (review decision, round 1): mark the
		// ecal_reminded rows BEFORE firing, not after. Reminders are
		// best-effort — if the process dies between this line and fire()
		// actually queuing/sending, these users simply go un-reminded for
		// this occurrence (recoverable: nothing else depends on it, and a
		// human can always see the event in the calendar). The prior
		// fire-then-mark order risked the opposite failure — a crash after
		// fire() sent the notification but before mark_reminded() recorded
		// it — which would re-send on the very next hourly run. At-most-
		// once is the safer default for a notification, so we accept the
		// (rarer, self-recovering-on-retry-never) risk of an occasional
		// missed reminder over the risk of a duplicate one.
		$user_ids = array_map('intval', array_keys($notify_users));
		$this->mark_reminded($event_id, $occurrence_key, $user_ids);

		$this->notifier->fire(self::NOTIFICATION_TYPE, $type_data, $notify_users);
	}

	/**
	 * Candidate events: a coarse SQL prefilter (avoids scanning the whole
	 * table); recurrence::occurrences() computes the exact answer per
	 * event. A non-recurring event qualifies iff its single start_ts falls
	 * in the window; a recurring one qualifies iff its first possible
	 * occurrence is not already past the window AND it has not fully ended
	 * (recur_until) before now.
	 */
	protected function due_events(int $now, int $window_end): array
	{
		$recur_none = \ecyaz\eventscalendar\service\recurrence::RECUR_NONE;

		$sql = 'SELECT * FROM ' . $this->events_table . '
			WHERE start_ts <= ' . (int) $window_end . '
				AND (
					(recur_type = ' . (int) $recur_none . ' AND start_ts >= ' . (int) $now . ')
					OR (recur_type <> ' . (int) $recur_none . ' AND (recur_until = 0 OR recur_until >= ' . (int) $now . '))
				)';
		$result = $this->db->sql_query($sql);

		$events = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$row['event_id']     = (int) $row['event_id'];
			$row['start_ts']     = (int) $row['start_ts'];
			$row['end_ts']       = (int) $row['end_ts'];
			$row['all_day']      = (bool) $row['all_day'];
			$row['recur_type']   = (int) $row['recur_type'];
			$row['recur_until']  = (int) $row['recur_until'];

			$events[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $events;
	}

	protected function attendee_ids(int $event_id, int $occurrence_key): array
	{
		$sql = 'SELECT user_id FROM ' . $this->attendees_table . '
			WHERE event_id = ' . (int) $event_id . '
				AND occurrence_ts = ' . (int) $occurrence_key;
		$result = $this->db->sql_query($sql);

		$ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	protected function already_reminded_user_ids(int $event_id, int $occurrence_key): array
	{
		$sql = 'SELECT user_id FROM ' . $this->reminded_table . '
			WHERE event_id = ' . (int) $event_id . '
				AND occurrence_ts = ' . (int) $occurrence_key;
		$result = $this->db->sql_query($sql);

		$ids = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $ids;
	}

	/**
	 * Records (event_id, occurrence_key, user_id) as reminded — called
	 * BEFORE notifier->fire() by remind_occurrence() for the at-most-once
	 * guarantee (see that call site's comment).
	 *
	 * Reschedule caveat (review note, round 1): rescheduling a NON-
	 * recurring event (occurrence_key always 0 for those) after its single
	 * marker row already exists does not clear that row, so the rescheduled
	 * occurrence will never be reminded again even though its start_ts
	 * moved. This is accepted as by-design, not a bug: editing a future
	 * event's date/time already fires an 'updated' notice (notification\
	 * type\reminder::REASON_UPDATED, see event_manager) to the same
	 * attendees, which covers the "something about this event changed"
	 * need that a second reminder would otherwise exist for.
	 */
	protected function mark_reminded(int $event_id, int $occurrence_key, array $user_ids): void
	{
		if (empty($user_ids))
		{
			return;
		}

		$now     = time();
		$sql_ary = [];

		foreach (array_unique($user_ids) as $user_id)
		{
			$sql_ary[] = [
				'event_id'      => $event_id,
				'occurrence_ts' => $occurrence_key,
				'user_id'       => (int) $user_id,
				'reminded_ts'   => $now,
			];
		}

		$this->db->sql_multi_insert($this->reminded_table, $sql_ary);
	}

	/**
	 * Marker pruning (Task 6 hardening, round 2): ecal_reminded otherwise
	 * grows unboundedly — one row per (event_id, occurrence_ts, user_id)
	 * ever reminded, with nothing else in the extension ever deleting one
	 * except event_manager::delete() (removed with its event). Prunes only
	 * TIMED markers (occurrence_ts > 0 — recurring/timed occurrences,
	 * identified by their own start) once they are 60+ days in the past;
	 * occurrence_ts = 0 markers (a non-recurring event's single instance)
	 * are deliberately NEVER pruned by age here — they must persist for the
	 * rest of the event's lifetime to preserve the reschedule-no-re-remind
	 * contract documented on mark_reminded() above (a pruned marker would
	 * let a later run's already_reminded_user_ids() miss it and re-remind
	 * on a reschedule, exactly what that contract exists to prevent). Those
	 * rows are instead cleaned up only when their event is deleted (see
	 * service/event_manager.php::delete()).
	 */
	protected function prune_reminded_markers(): void
	{
		$cutoff = time() - (60 * 86400);

		$sql = 'DELETE FROM ' . $this->reminded_table . '
			WHERE occurrence_ts > 0
				AND occurrence_ts < ' . (int) $cutoff;
		$this->db->sql_query($sql);
	}
}
