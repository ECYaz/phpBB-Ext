<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 *
 * @group functional
 */

namespace ecyaz\liveupdates\tests\functional;

class ecyaz_liveupdates_poll_test extends \phpbb_functional_test_case
{
	protected static function setup_extensions()
	{
		return ['ecyaz/liveupdates'];
	}

	public function test_poll_returns_json_when_enabled()
	{
		$this->login();
		$crawler = self::request('GET', 'app.php/liveupdates/poll', [], false);
		$content = self::$client->getResponse()->getContent();
		$data = json_decode($content, true);

		$this->assertIsArray($data);
		$this->assertArrayHasKey('enabled', $data);
		$this->assertTrue($data['enabled']);
		$this->assertArrayHasKey('deltas', $data);
	}

	public function test_topic_delta_counts_new_posts()
	{
		$this->login();
		// Forum 2 / "Your first forum" exists in the default install; post a reply via the helper.
		$post = $this->create_topic(2, 'LU topic', 'first post body');
		$reply = $this->create_post(2, $post['topic_id'], 'LU topic', 'second post body');

		$crawler = self::request('GET', 'app.php/liveupdates/poll?topic_id=' . (int) $post['topic_id']
			. '&forum_id=2&last_post_id=' . (int) $post['post_id'], [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);

		$this->assertArrayHasKey('topic', $data['deltas']);
		$this->assertSame(1, $data['deltas']['topic']['count']);
		$this->assertSame((int) $reply['post_id'], $data['deltas']['topic']['last_post_id']);
	}

	public function test_topic_delta_zero_for_unreadable_forum()
	{
		// A non-admin user must not receive deltas for a forum they cannot read.
		$this->login();
		$crawler = self::request('GET', 'app.php/liveupdates/poll?topic_id=999999&forum_id=999999&last_post_id=0', [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertSame(0, $data['deltas']['topic']['count']);
	}

	public function test_topic_delta_authorizes_against_topic_forum_not_request()
	{
		// IDOR guard: forum_id from the request must be ignored; auth derives from the topic's real forum.
		$this->login();
		$post  = $this->create_topic(2, 'LU idor topic', 'first');
		$reply = $this->create_post(2, $post['topic_id'], 'LU idor topic', 'second');

		// Pass a mismatched/unreadable forum_id; the real topic lives in forum 2 which admin can read.
		$crawler = self::request('GET', 'app.php/liveupdates/poll?topic_id=' . (int) $post['topic_id']
			. '&forum_id=999999&last_post_id=' . (int) $post['post_id'], [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);

		// With the IDOR fixed, forum_id is derived from the topic, so the count is correct (1), not 0.
		$this->assertSame(1, $data['deltas']['topic']['count']);
		$this->assertSame((int) $reply['post_id'], $data['deltas']['topic']['last_post_id']);
	}

	public function test_notify_delta_present_for_logged_in_user()
	{
		$this->login();
		$crawler = self::request('GET', 'app.php/liveupdates/poll', [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('notify', $data['deltas']);
		$this->assertArrayHasKey('unread', $data['deltas']['notify']);
		$this->assertIsInt($data['deltas']['notify']['unread']);
	}

	public function test_index_delta_counts_recent_topics()
	{
		$this->login();
		$this->create_topic(2, 'LU index topic', 'body for index delta');
		// since=1 ⇒ effectively "everything"; there is at least one topic.
		$crawler = self::request('GET', 'app.php/liveupdates/poll?since=1', [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('index', $data['deltas']);
		$this->assertGreaterThanOrEqual(1, $data['deltas']['index']['count']);
	}

	public function test_min_interval_throttle_returns_429_on_rapid_poll()
	{
		$this->login();
		// First poll primes the per-session min-interval cache.
		self::request('GET', 'app.php/liveupdates/poll', [], false);
		$first = self::$client->getResponse()->getStatus();
		$this->assertSame(200, $first);
		// Immediate second poll (within min_interval) must be throttled.
		self::request('GET', 'app.php/liveupdates/poll', [], false);
		$this->assertSame(429, self::$client->getResponse()->getStatus());
	}

	public function test_pm_delta_present_for_logged_in_user()
	{
		$this->login();
		self::request('GET', 'app.php/liveupdates/poll', [], false);
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('pm', $data['deltas']);
		$this->assertIsInt($data['deltas']['pm']['unread']);
	}

	public function test_stats_delta_present_with_flag()
	{
		$this->login();
		self::request('GET', 'app.php/liveupdates/poll?stats=1', [], false);
		$this->assertSame(200, self::$client->getResponse()->getStatus());
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('stats', $data['deltas']);
		$this->assertArrayHasKey('posts', $data['deltas']['stats']);
		$this->assertArrayHasKey('topics', $data['deltas']['stats']);
		$this->assertArrayHasKey('members', $data['deltas']['stats']);
		$this->assertArrayHasKey('newest', $data['deltas']['stats']);
		$this->assertIsInt($data['deltas']['stats']['posts']);
	}

	public function test_stats_delta_absent_without_flag()
	{
		$this->login();
		self::request('GET', 'app.php/liveupdates/poll', [], false);
		$this->assertSame(200, self::$client->getResponse()->getStatus());
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayNotHasKey('stats', $data['deltas']);
	}

	public function test_online_delta_present_with_flag()
	{
		$this->login();
		self::request('GET', 'app.php/liveupdates/poll?online=1', [], false);
		$this->assertSame(200, self::$client->getResponse()->getStatus());
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayHasKey('online', $data['deltas']);
		$this->assertIsInt($data['deltas']['online']['online']);
	}

	public function test_online_delta_absent_without_flag()
	{
		$this->login();
		self::request('GET', 'app.php/liveupdates/poll', [], false);
		$this->assertSame(200, self::$client->getResponse()->getStatus());
		$data = json_decode(self::$client->getResponse()->getContent(), true);
		$this->assertArrayNotHasKey('online', $data['deltas']);
	}
}
