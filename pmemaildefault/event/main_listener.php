<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;

	/**
	 * @param \phpbb\config\config $config
	 */
	public function __construct(\phpbb\config\config $config)
	{
		$this->config = $config;
	}

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
	 * Only runs while the admin has the default switched ON
	 * (config 'pmemaildefault_enabled'). When it is OFF we add nothing, so the new
	 * user keeps phpBB's stock default of no PM email subscription.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function add_pm_email_subscription($event)
	{
		if (empty($this->config['pmemaildefault_enabled']))
		{
			return;
		}

		$notifications_data = $event['notifications_data'];

		$notifications_data[] = [
			'item_type'	=> 'notification.type.pm',
			'method'	=> 'notification.method.email',
		];

		$event['notifications_data'] = $notifications_data;
	}
}
