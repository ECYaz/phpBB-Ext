<?php
namespace ecyaz\pmemaildefault\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.user_add_modify_notifications_data' => 'add_pm_email_subscription',
		];
	}

	/**
	 * Add an email subscription for the "private message" notification type to the
	 * default notifications inserted when a new user is created.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function add_pm_email_subscription($event)
	{
		$notifications_data = $event['notifications_data'];

		$notifications_data[] = [
			'item_type'	=> 'notification.type.pm',
			'method'	=> 'notification.method.email',
		];

		$event['notifications_data'] = $notifications_data;
	}
}
