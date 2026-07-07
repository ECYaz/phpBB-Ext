<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\notification;

/**
 * Reminder / updated / cancelled notice for a single calendar event
 * occurrence — one phpBB notification TYPE covers all three, distinguished
 * by the 'reason' key stored in notification_data (VERIFY get_title()
 * override below).
 *
 * Identity (binding, task-6 brief): item_id = event_id. item_parent_id is
 * LOGICALLY occurrence_ts (the same 0-for-non-recurring sentinel as
 * ecal_attendees.occurrence_ts, controller/event.php's RSVP identity rule)
 * but is stored as intdiv(occurrence_ts, 86400) — days since epoch, not raw
 * seconds (VERIFY, discovered by the functional test below failing with
 * "Out of range value for column 'item_parent_id'"): phpbb_notifications.
 * item_parent_id is `mediumint unsigned` (max 16,777,215), which cannot
 * hold a raw UNIX timestamp (~1.7 billion today). Day-truncating is safe —
 * collision-free — because recurrence.php's minimum occurrence spacing is
 * one full calendar day (RECUR_DAILY), so no two occurrences of the SAME
 * event (same item_id) ever truncate to the same day-index; 0 stays 0
 * either way. This encoding is a storage-layer adaptation only: the
 * ecal_reminded marker table (migrations/install_notifications.php),
 * which is the sole authority for "already reminded", stores the full
 * occurrence_ts and is unaffected.
 * get_item_parent_id() below returns the encoded value.
 *
 * Recipients (find_users_for_notification, VERIFY required override):
 *  - reason = 'reminder': every user who has NOT disabled this notification
 *    type in UCP (check_user_notification_options(false, ...), the same
 *    "opted in unless disabled" pattern core uses for e.g.
 *    admin_activate_user) — PLUS, unconditionally, every attendee of the
 *    occurrence.
 *  - Implicit opt-in (binding review decision, task-6-report.md "Fix round
 *    1"): RSVPing to an occurrence IS opting in to that occurrence's
 *    reminder. "Attendees ALWAYS get reminded" therefore overrides UCP —
 *    an attendee who disabled this notification type entirely in UCP
 *    (check_user_notification_options() then reports an empty or absent
 *    method array for them) still gets the reminder, delivered via the
 *    type's DEFAULT notification methods (notification_manager::
 *    get_default_methods(), board notification at minimum) instead of
 *    being silently skipped. An attendee who still has real methods
 *    enabled keeps their actual preference untouched — this is an
 *    additive floor, not a replacement. Non-attendee users are pure
 *    preference-based: no override applies to them.
 *  - 'opted_in_users' (optional type_data key, perf fix): cron/reminders.php
 *    resolves the full non-attendee-specific opt-in audience ONCE per cron
 *    run and injects that same cached array here for every occurrence via
 *    this key, instead of find_users_for_notification() re-running
 *    check_user_notification_options(false, ...)'s all-users scan per
 *    occurrence (was O(occurrences x users), see service/notifier.php's
 *    resolve() caller in cron/reminders.php). The local copy taken from it
 *    is never mutated in place, so the cache stays valid for the next
 *    occurrence.
 *  - reason = 'updated'/'cancelled': ONLY the occurrence's attendees
 *    (type_data['attendee_ids'], passed in by event_manager) — an edit/
 *    cancel notice is not broadcast to the general opt-in audience, only to
 *    people who actually RSVP'd.
 *
 * This class is NEVER fired via notification_manager::add_notifications() /
 * add_notifications_for_users() — see service/notifier.php's class docblock
 * for why (the manager's automatic "already notified" filter is scoped to
 * item_id only, which would wrongly suppress a later occurrence's reminder
 * to a user who already received an earlier occurrence's reminder for the
 * same event). find_users_for_notification() is still implemented to the
 * full interface contract (it is called directly by
 * service/notifier.php::resolve()) and remains correct/reusable should a
 * future caller go through the manager's public API for a non-recurring
 * notice.
 */
class reminder extends \phpbb\notification\type\base
{
	const TYPE_NAME = 'ecyaz.eventscalendar.notification.type.reminder';

	const REASON_REMINDER  = 'reminder';
	const REASON_UPDATED   = 'updated';
	const REASON_CANCELLED = 'cancelled';

	/** @var \phpbb\controller\helper */
	protected $helper;

	public function set_helper(\phpbb\controller\helper $helper): void
	{
		$this->helper = $helper;
	}

	/**
	 * {@inheritdoc}
	 */
	static public $notification_option = [
		'lang'	=> 'NOTIFICATION_TYPE_ECAL_REMINDER',
		'group'	=> 'NOTIFICATION_GROUP_ECAL',
	];

	/**
	 * {@inheritdoc}
	 */
	public function get_type()
	{
		return self::TYPE_NAME;
	}

	/**
	 * {@inheritdoc}
	 */
	static public function get_item_id($type_data)
	{
		return (int) $type_data['event_id'];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Days-since-epoch, not the raw occurrence_ts — see class docblock's
	 * "Identity" section for why (mediumint unsigned column width).
	 */
	static public function get_item_parent_id($type_data)
	{
		return intdiv((int) ($type_data['occurrence_ts'] ?? 0), 86400);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available()
	{
		return $this->auth->acl_get('u_ecal_view');
	}

	/**
	 * {@inheritdoc}
	 */
	public function find_users_for_notification($type_data, $options = [])
	{
		$options = array_merge([
			'ignore_users' => [],
		], $options);

		$attendee_ids = array_map('intval', (array) ($type_data['attendee_ids'] ?? []));

		if (($type_data['reason'] ?? self::REASON_REMINDER) !== self::REASON_REMINDER)
		{
			// Edit/cancel notices only ever go to the occurrence's attendees.
			return $this->check_user_notification_options($attendee_ids, $options);
		}

		// Reminders: everyone who has not disabled "Upcoming calendar
		// events" in UCP (board method default-on, email opt-in — the
		// standard core opt-out model, see phpbb\notification\type\
		// admin_activate_user's identical use of check_user_notification_
		// options(false, ...)). If the caller already resolved this SAME
		// full-audience result this cron run (see class docblock's
		// 'opted_in_users' entry), reuse it instead of re-scanning every
		// board user for every occurrence. Taken as a local copy — never
		// written back into $type_data — so the caller's cache is untouched.
		$notify_users = is_array($type_data['opted_in_users'] ?? null)
			? $type_data['opted_in_users']
			: $this->check_user_notification_options(false, $options);

		// Implicit opt-in override (binding decision, see class docblock):
		// an attendee ALWAYS receives the reminder. If UCP left them with no
		// enabled method for this type, fall back to the type's default
		// notification methods rather than skipping them.
		if (!empty($attendee_ids))
		{
			$default_methods = $this->notification_manager->get_default_methods();

			foreach ($attendee_ids as $user_id)
			{
				if (isset($options['ignore_users'][$user_id]))
				{
					continue;
				}

				if (empty($notify_users[$user_id]))
				{
					$notify_users[$user_id] = $default_methods;
				}
			}
		}

		return $notify_users;
	}

	/**
	 * {@inheritdoc}
	 */
	public function users_to_query()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_load_special()
	{
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_style_class()
	{
		return 'ecal_reminder';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title()
	{
		$title = (string) $this->get_data('title');

		switch ($this->get_data('reason'))
		{
			case self::REASON_UPDATED:
				return $this->language->lang('NOTIFICATION_ECAL_UPDATED', $title);

			case self::REASON_CANCELLED:
				return $this->language->lang('NOTIFICATION_ECAL_CANCELLED', $title);

			default:
				return $this->language->lang('NOTIFICATION_ECAL_REMINDER', $title);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_url()
	{
		return $this->helper->route('ecyaz_eventscalendar_event', ['event_id' => $this->item_id]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template()
	{
		return '@ecyaz_eventscalendar/ecal_reminder';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_email_template_variables()
	{
		return [
			'REASON'      => (string) $this->get_data('reason'),
			'EVENT_TITLE' => html_entity_decode((string) $this->get_data('title'), ENT_COMPAT),
			'EVENT_WHEN'  => $this->user->format_date((int) $this->get_data('occurrence_at')),
			'U_EVENT'     => $this->helper->route(
				'ecyaz_eventscalendar_event',
				['event_id' => $this->item_id],
				false,
				false,
				\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
			),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function create_insert_array($type_data, $pre_create_data = [])
	{
		$this->set_data('reason', (string) ($type_data['reason'] ?? self::REASON_REMINDER));
		$this->set_data('title', (string) ($type_data['title'] ?? ''));
		$this->set_data('occurrence_at', (int) ($type_data['occurrence_at'] ?? $type_data['occurrence_ts'] ?? 0));

		parent::create_insert_array($type_data, $pre_create_data);
	}
}
