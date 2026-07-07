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
 * Thin wrapper around phpbb\notification\manager's low-level primitives
 * (get_item_type_class() / get_method_class()) that deliberately bypasses
 * notification_manager::add_notifications() / add_notifications_for_users().
 *
 * Dedupe decision (binding, VERIFY findings recorded in task-6-report.md):
 * add_notifications_for_users() (phpbb/notification/manager.php ~334-386)
 * unconditionally re-filters the notify_users list it was given by calling,
 * for every subscription method, get_notified_users($notification_type_id,
 * ['item_id' => $item_id]) — scoped to item_id ONLY. It never passes
 * item_parent_id, even though both phpbb\notification\method\board::
 * get_notified_users() and ...\method\email::get_notified_users() DO honour
 * an item_parent_id key when present in $options (confirmed by reading
 * both). Our reminder type's item_id is the event_id, constant across every
 * occurrence of a recurring event; item_parent_id is the occurrence_ts. If
 * we called add_notifications()/add_notifications_for_users(), a user who
 * already has ANY notification row for this event_id (e.g. from last
 * week's occurrence) would be silently dropped from notify_users for THIS
 * occurrence too — breaking "each occurrence reminds exactly once", not
 * "each event reminds exactly once ever".
 *
 * We therefore drive the manager's filter-free building blocks ourselves,
 * replicating exactly what add_notifications_for_users() does (build the
 * insert array per user, queue it against each requested method, load the
 * users, flush each method's queue) minus that one incorrect filter.
 * "Already reminded" is instead decided entirely by the caller: cron/
 * reminders.php's own ecal_reminded marker table (grain: event_id +
 * occurrence_ts + user_id, see migrations/install_notifications.php) for
 * reminders, and — by design — no dedupe at all for edit/cancel notices
 * (event_manager fires exactly one notice per update()/delete() call).
 */
class notifier
{
	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\user_loader */
	protected $user_loader;

	public function __construct(
		\phpbb\notification\manager $notification_manager,
		\phpbb\user_loader $user_loader
	)
	{
		$this->notification_manager = $notification_manager;
		$this->user_loader          = $user_loader;
	}

	/**
	 * Resolves the notify_users array ([user_id => [method_name, ...]]) for
	 * $type_name via that type's find_users_for_notification(), same as
	 * notification_manager::add_notifications() does internally — just
	 * without also firing the notifications.
	 */
	public function resolve(string $type_name, array $type_data, array $options = []): array
	{
		// No $data argument: that parameter is set_initial_data()'s payload
		// for hydrating an EXISTING row (e.g. re-loading a notification
		// fetched from the DB) — passing $type_data there would make it
		// leak into $this->data verbatim (bypassing create_insert_array()'s
		// controlled shape) the moment create_insert_array() ever ran on
		// this same instance. Only create_insert_array($type_data, ...)
		// (called from fire()) is meant to consume $type_data.
		$notification = $this->notification_manager->get_item_type_class($type_name);
		$notify_users = $notification->find_users_for_notification($type_data, $options);

		unset($notify_users[ANONYMOUS]);

		return $notify_users;
	}

	/**
	 * Inserts + queues + sends $type_name notifications for exactly
	 * $notify_users (already resolved and, if the caller cares, already
	 * deduped) — see class docblock for why this does not go through
	 * notification_manager::add_notifications_for_users().
	 *
	 * @param string $type_name
	 * @param array  $type_data
	 * @param array  $notify_users [user_id => [method_name, ...]]
	 * @return int[] user_ids actually notified (insertion order)
	 */
	public function fire(string $type_name, array $type_data, array $notify_users): array
	{
		unset($notify_users[ANONYMOUS]);

		if (empty($notify_users))
		{
			return [];
		}

		$pre_create_notification = $this->notification_manager->get_item_type_class($type_name);
		$pre_create_data         = $pre_create_notification->pre_create_insert_array($type_data, $notify_users);
		unset($pre_create_notification);

		$notification_methods = [];
		$user_ids              = [];

		foreach ($notify_users as $user_id => $methods)
		{
			$notification = $this->notification_manager->get_item_type_class($type_name);
			$notification->user_id = (int) $user_id;
			$notification->create_insert_array($type_data, $pre_create_data);

			$user_ids = array_merge($user_ids, $notification->users_to_query());

			foreach ($methods as $method_name)
			{
				if (!isset($notification_methods[$method_name]))
				{
					$notification_methods[$method_name] = $this->notification_manager->get_method_class($method_name);
				}

				$notification_methods[$method_name]->add_to_queue($notification);
			}
		}

		$this->user_loader->load_users(array_unique($user_ids));

		foreach ($notification_methods as $method)
		{
			$method->notify();
		}

		return array_map('intval', array_keys($notify_users));
	}

	/**
	 * Convenience for callers that do not need to intervene between
	 * resolving recipients and firing (event_manager's edit/cancel
	 * notices — no dedupe layer of their own).
	 */
	public function notify(string $type_name, array $type_data, array $options = []): array
	{
		$notify_users = $this->resolve($type_name, $type_data, $options);

		if (empty($notify_users))
		{
			return [];
		}

		return $this->fire($type_name, $type_data, $notify_users);
	}
}
