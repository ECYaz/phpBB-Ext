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
 * Google Calendar sync worker: drains service/outbox.php's queue into
 * service/gcal_client.php's push_upsert()/push_delete() calls (Task 9).
 *
 * should_run() gates on three cheap checks (config enabled, a due-row COUNT
 * -- not a full claim, and a minimum 5-minute gap since the last run via
 * ecal_gcal_status's sibling config key ecal_gcal_last_run) so an idle board
 * with gcal sync on doesn't hammer the outbox table every cron tick.
 *
 * run() claims up to BATCH_LIMIT due rows and processes each independently:
 * a throw from any single row (gcal_auth/gcal_client's documented
 * \RuntimeException on bad JSON/key/Google error, or an empty
 * ecal_gcal_calendar_id) is caught right there and turned into
 * outbox::mark_failed() for THAT row only -- one bad row (or a board-wide
 * config mistake, which throws identically for every row in the batch) never
 * aborts the rest of the batch. The batch's outcome is summarized into
 * ecal_gcal_status for the ACP page (Task 9's acp/main_module.php google
 * mode) to display.
 *
 * Outbox row vanishing mid-claim (service/outbox.php's dedupe() docblock:
 * a fresh enqueue_upsert()/enqueue_delete() unconditionally deletes ALL rows
 * for an event_id, including one this run() call already claimed) is
 * tolerated for free -- mark_ok()'s DELETE and mark_failed()'s guarded
 * UPDATE are both no-ops on a missing outbox_id, so no special handling is
 * needed here beyond what outbox.php already documents.
 *
 * Event row vanishing between enqueue and this run() (event deleted after
 * its upsert was queued, before the worker got to it) is handled explicitly:
 * load_normalized_event() returns null and the job is dropped via mark_ok()
 * rather than pushing a stale/nonexistent event to Google.
 *
 * Test strategy decision (task-9 brief, VERIFY): run()'s push path goes
 * through gcal_client -> gcal_auth -> a real Google token-endpoint HTTP
 * request, which is not something a functional test can trigger without
 * live network egress (undesirable/non-deterministic in CI). should_run()'s
 * gating logic and the ACP surfaces (page render, settings save, resync,
 * queue actions) ARE covered functionally (tests/functional/acp_google_test.
 * php). run()'s orchestration (claim -> per-row dispatch -> mark_ok/
 * mark_failed -> status summary) is instead covered by a plain UNIT test
 * (tests/cron/gcal_sync_test.php) that subclasses this class, overriding
 * the three DB/network-touching seams (due_count(), load_normalized_event(),
 * write_gcal_id()) with canned in-memory data, and passes anonymous-class
 * stubs for the outbox and gcal_client collaborators -- no real DB, no real
 * HTTP, ever. See that test file's class docblock for why
 * load_normalized_event() is deliberately ONE method (DB fetch + BBCode-to-
 * HTML rendering + absolute URL, mirroring service/ics.php's prepare_row())
 * rather than two: generate_text_for_display() depends on
 * globals ($phpbb_container, $phpbb_dispatcher, ...) that a plain
 * \phpbb_test_case does not bootstrap, so the unit test overrides this
 * single seam wholesale instead of exercising BBCode rendering.
 */
class gcal_sync extends \phpbb\cron\task\base
{
	/** claim_due() batch size per run() call (task-9 brief). */
	const BATCH_LIMIT = 25;

	/** Minimum seconds between two run()s, mirrors cron\reminders' hourly gate at a shorter interval (task-9 brief: 5 minutes). */
	const MIN_RUN_GAP_SECONDS = 300;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \ecyaz\eventscalendar\service\outbox */
	protected $outbox;

	/** @var \ecyaz\eventscalendar\service\gcal_client */
	protected $gcal_client;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $events_table;

	/** @var string */
	protected $outbox_table;

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db,
		\ecyaz\eventscalendar\service\outbox $outbox,
		\ecyaz\eventscalendar\service\gcal_client $gcal_client,
		\phpbb\controller\helper $helper,
		$table_prefix
	)
	{
		$this->config       = $config;
		$this->db           = $db;
		$this->outbox       = $outbox;
		$this->gcal_client  = $gcal_client;
		$this->helper       = $helper;
		$this->events_table = $table_prefix . 'ecal_events';
		$this->outbox_table = $table_prefix . 'ecal_outbox';
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_run()
	{
		if (empty($this->config['ecal_gcal_enable']))
		{
			return false;
		}

		if ((time() - (int) $this->config['ecal_gcal_last_run']) < self::MIN_RUN_GAP_SECONDS)
		{
			return false;
		}

		return $this->due_count() > 0;
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$this->config->set('ecal_gcal_last_run', time());

		$rows = $this->outbox->claim_due(self::BATCH_LIMIT);

		$pushed     = 0;
		$failed     = 0;
		$last_error = '';

		foreach ($rows as $row)
		{
			try
			{
				if ((int) $row['action'] === \ecyaz\eventscalendar\service\outbox::ACTION_DELETE)
				{
					$this->gcal_client->push_delete($row['gcal_id']);
					$this->outbox->mark_ok($row['outbox_id']);
					$pushed++;

					continue;
				}

				$normalized = $this->load_normalized_event($row['event_id']);

				if ($normalized === null)
				{
					// Event vanished since it was enqueued -- nothing left to
					// push, drop the job (see class docblock).
					$this->outbox->mark_ok($row['outbox_id']);

					continue;
				}

				$gcal_id = $this->gcal_client->push_upsert($normalized);

				$this->write_gcal_id($row['event_id'], $gcal_id);
				$this->outbox->mark_ok($row['outbox_id']);
				$pushed++;
			}
			catch (\Throwable $e)
			{
				$failed++;
				$last_error = $e->getMessage();
				$this->outbox->mark_failed($row['outbox_id'], $last_error);
			}
		}

		$this->update_status($pushed, $failed, $last_error);
	}

	// ------------------------------------------------------------------
	// Internal helpers (protected, deliberately overridable seams for
	// tests/cron/gcal_sync_test.php -- see class docblock)
	// ------------------------------------------------------------------

	/**
	 * Cheap COUNT of rows claim_due() would currently pick up -- same WHERE
	 * clause as service/outbox.php::claim_due(), duplicated here rather than
	 * added to outbox.php (out of this task's file surface) since it is only
	 * ever used for should_run()'s gate, never to actually process rows.
	 */
	protected function due_count(): int
	{
		$sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->outbox_table . '
			WHERE next_retry_ts <= ' . time() . '
				AND attempts < ' . \ecyaz\eventscalendar\service\outbox::DEAD_AFTER_ATTEMPTS;
		$result = $this->db->sql_query($sql);
		$count  = (int) $this->db->sql_fetchfield('cnt');
		$this->db->sql_freeresult($result);

		return $count;
	}

	/**
	 * Loads $event_id and normalizes it into gcal_client::push_upsert()'s
	 * required row shape (start_ts, end_ts, all_day, recur_type, recur_until,
	 * title, url, description -- url already absolute with the session id
	 * suppressed) in one step, mirroring service/ics.php's prepare_row().
	 * Also carries 'gcal_id' (not part of build_payload()'s required-keys
	 * contract, but read by push_upsert() itself to decide insert vs.
	 * update). Returns null when the event no longer exists.
	 *
	 * 'description' is the BBCode-rendered HTML straight out of
	 * generate_text_for_display() -- NOT pre-converted to plain text here.
	 * gcal_client::build_payload() is the sole HTML -> plain-text converter
	 * (text_util::html_to_plain_text(), see that class's docblock); an
	 * earlier revision of this method also ran the conversion here, which
	 * meant build_payload() ran strip_tags() a SECOND time over already-
	 * stripped text and silently ate any literal "<"/">" the plain text
	 * happened to contain (e.g. "revenue < $100"). Single-conversion is now
	 * enforced by contract: exactly one BBCode-render step here, exactly
	 * one HTML->plain-text step inside build_payload().
	 */
	protected function load_normalized_event(int $event_id): ?array
	{
		$sql    = 'SELECT * FROM ' . $this->events_table . ' WHERE event_id = ' . (int) $event_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return null;
		}

		$this->ensure_content_functions();

		$description_html = generate_text_for_display(
			(string) $row['description'],
			(string) $row['bbcode_uid'],
			(string) $row['bbcode_bitfield'],
			(int) $row['bbcode_options']
		);

		// '' (not false) explicitly suppresses the session id -- see
		// service/ics.php::prepare_row()'s "VERIFY / decision" comment for
		// why `false` is the wrong value here (append_sid() treats false as
		// "use the current global session", leaking a live sid into a value
		// that gets stored in ecal_events.gcal_id / pushed to Google and
		// potentially re-read/re-pushed far later than the request that
		// triggered this run()).
		$url = $this->helper->route(
			'ecyaz_eventscalendar_event',
			['event_id' => $event_id],
			false,
			'',
			\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
		);

		return [
			'event_id'    => $event_id,
			'gcal_id'     => (string) $row['gcal_id'],
			'title'       => (string) $row['title'],
			'description' => $description_html,
			'url'         => $url,
			'start_ts'    => (int) $row['start_ts'],
			'end_ts'      => (int) $row['end_ts'],
			'all_day'     => !empty($row['all_day']),
			'recur_type'  => (int) $row['recur_type'],
			'recur_until' => (int) $row['recur_until'],
		];
	}

	protected function write_gcal_id(int $event_id, string $gcal_id): void
	{
		$this->db->sql_query('UPDATE ' . $this->events_table . '
			SET gcal_id = \'' . $this->db->sql_escape($gcal_id) . '\'
			WHERE event_id = ' . (int) $event_id);
	}

	/**
	 * ecal_gcal_status summary for the ACP page: 'ok <UTC time> / N pushed'
	 * on a clean batch, else the last row's error message (truncated to the
	 * same 255-octet budget outbox::mark_failed() already applies to
	 * last_error, since this is stored via the plain config table too).
	 */
	protected function update_status(int $pushed, int $failed, string $last_error): void
	{
		if ($failed > 0 && $last_error !== '')
		{
			$status = utf8_substr($last_error, 0, 255);
		}
		else
		{
			$status = 'ok ' . gmdate('Y-m-d H:i:s') . ' UTC / ' . $pushed . ' pushed';
		}

		$this->config->set('ecal_gcal_status', $status);
	}

	/** Mirrors service/ics.php::ensure_content_functions(). */
	protected function ensure_content_functions(): void
	{
		if (!function_exists('generate_text_for_display'))
		{
			global $phpbb_root_path, $phpEx;
			include $phpbb_root_path . 'includes/functions_content.' . $phpEx;
		}
	}
}
