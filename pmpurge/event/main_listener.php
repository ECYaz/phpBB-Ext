<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes the extension's language available board wide.
 *
 * Without this the admin log renders LOG_PMPURGE_RUN as its own key, because
 * core has no reason to know an extension's language file exists.
 */
class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
		];
	}

	/**
	 * Append the extension's common language file to the set core loads.
	 *
	 * @param \phpbb\event\data $event Event object
	 * @return void
	 */
	public function load_language($event)
	{
		$lang_set_ext   = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'ecyaz/pmpurge',
			'lang_set' => 'common',
		];

		$event['lang_set_ext'] = $lang_set_ext;
	}
}
