<?php
namespace ecyaz\liveupdates\tests\event;

class main_listener_test extends \phpbb_test_case
{
	public function test_subscribed_events()
	{
		$events = \ecyaz\liveupdates\event\main_listener::getSubscribedEvents();
		self::assertArrayHasKey('core.user_setup', $events);
		self::assertArrayHasKey('core.page_header_after', $events);
		self::assertEquals('load_language', $events['core.user_setup']);
		self::assertEquals('inject_bootstrap', $events['core.page_header_after']);
	}
}
