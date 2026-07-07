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
 * Outbox: the pending-work queue between event CRUD and the (future, Task 7)
 * Google Calendar sync worker. This class only manages the queue's state
 * machine — nothing here talks to Google.
 *
 * No-op rule (binding, see plan Global Constraints): when ecal_gcal_enable is
 * 0, enqueue_upsert()/enqueue_delete() write NO outbox row. They still
 * destroy the ICS cache key unconditionally — a forward hook for Task 7's
 * ICS feed cache (cheap no-op today since nothing caches under that key yet).
 *
 * Backoff schedule (binding): attempts -> delay 0s, 5m, 25m, 2h, 10h; dead
 * (no further retries) once attempts >= 6. backoff_delay()/is_dead() are
 * pure/static so the state machine is unit-testable without a DB — see
 * tests/service/outbox_test.php.
 */
class outbox
{
	const ACTION_UPSERT = 0;
	const ACTION_DELETE = 1;

	const DEAD_AFTER_ATTEMPTS = 6;

	/** attempts (1-based, i.e. count of failures so far) => retry delay in seconds */
	const BACKOFF_SCHEDULE = [
		1 => 0,
		2 => 300,
		3 => 1500,
		4 => 7200,
		5 => 36000,
	];

	const ICS_CACHE_KEY = '_ecal_ics';

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var string */
	protected $outbox_table;

	public function __construct(
		\phpbb\db\driver\driver_interface $db,
		\phpbb\config\config $config,
		\phpbb\cache\driver\driver_interface $cache,
		$table_prefix
	)
	{
		$this->db           = $db;
		$this->config       = $config;
		$this->cache        = $cache;
		$this->outbox_table = $table_prefix . 'ecal_outbox';
	}

	/**
	 * Queues a create/update push. Dedupes: any existing pending row for this
	 * event (upsert or delete) is replaced by a single fresh upsert due now.
	 */
	public function enqueue_upsert(int $event_id): void
	{
		$this->cache->destroy(self::ICS_CACHE_KEY);

		if (empty($this->config['ecal_gcal_enable']))
		{
			return;
		}

		$this->dedupe($event_id);

		$row = [
			'event_id'      => $event_id,
			'action'        => self::ACTION_UPSERT,
			'gcal_id'       => '',
			'attempts'      => 0,
			'next_retry_ts' => time(),
			'last_error'    => '',
		];

		$this->db->sql_query('INSERT INTO ' . $this->outbox_table . ' ' . $this->db->sql_build_array('INSERT', $row));
	}

	/**
	 * Queues a delete push. $gcal_id is a snapshot taken before the event row
	 * itself is deleted — the worker needs it to identify the remote event
	 * once the local row is gone.
	 */
	public function enqueue_delete(int $event_id, string $gcal_id): void
	{
		$this->cache->destroy(self::ICS_CACHE_KEY);

		if (empty($this->config['ecal_gcal_enable']))
		{
			return;
		}

		$this->dedupe($event_id);

		if ($gcal_id === '')
		{
			// Never synced to Google (no remote counterpart exists) -- nothing to
			// delete there, so no delete row is queued. dedupe() above still ran,
			// so any stale pending row for this event (e.g. an unsent upsert) is
			// cleared either way.
			return;
		}

		$row = [
			'event_id'      => $event_id,
			'action'        => self::ACTION_DELETE,
			'gcal_id'       => $gcal_id,
			'attempts'      => 0,
			'next_retry_ts' => time(),
			'last_error'    => '',
		];

		$this->db->sql_query('INSERT INTO ' . $this->outbox_table . ' ' . $this->db->sql_build_array('INSERT', $row));
	}

	/**
	 * Rows due for processing now, oldest-due first, excluding dead rows
	 * (attempts >= DEAD_AFTER_ATTEMPTS never come back regardless of
	 * next_retry_ts).
	 */
	public function claim_due(int $limit): array
	{
		$sql = 'SELECT * FROM ' . $this->outbox_table . '
			WHERE next_retry_ts <= ' . time() . '
				AND attempts < ' . self::DEAD_AFTER_ATTEMPTS . '
			ORDER BY next_retry_ts ASC';
		$result = $this->db->sql_query_limit($sql, max(0, $limit));

		$rows = [];

		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = [
				'outbox_id'     => (int) $row['outbox_id'],
				'event_id'      => (int) $row['event_id'],
				'action'        => (int) $row['action'],
				'gcal_id'       => (string) $row['gcal_id'],
				'attempts'      => (int) $row['attempts'],
				'next_retry_ts' => (int) $row['next_retry_ts'],
				'last_error'    => (string) $row['last_error'],
			];
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	/**
	 * Successful push: the row's job is done, remove it from the queue.
	 */
	public function mark_ok(int $outbox_id): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->outbox_table . ' WHERE outbox_id = ' . (int) $outbox_id);
	}

	/**
	 * Failed push: bump attempts, schedule the next retry per the backoff
	 * schedule, and record the (truncated) error for ACP display. No-op if
	 * the row is already gone (e.g. raced with a concurrent mark_ok()).
	 */
	public function mark_failed(int $outbox_id, string $error): void
	{
		$sql    = 'SELECT attempts FROM ' . $this->outbox_table . ' WHERE outbox_id = ' . (int) $outbox_id;
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if (!$row)
		{
			return;
		}

		$attempts = ((int) $row['attempts']) + 1;

		$update = [
			'attempts'      => $attempts,
			'next_retry_ts' => time() + self::backoff_delay($attempts),
			'last_error'    => utf8_substr($error, 0, 255),
		];

		$this->db->sql_query('UPDATE ' . $this->outbox_table . '
			SET ' . $this->db->sql_build_array('UPDATE', $update) . '
			WHERE outbox_id = ' . (int) $outbox_id);
	}

	/**
	 * Pure backoff-schedule lookup — attempts is 1-based (the count of
	 * failures so far, i.e. the value mark_failed() is about to store).
	 * Static and DB-less on purpose: the state machine's timing rules are
	 * unit-tested directly against this method.
	 */
	public static function backoff_delay(int $attempts): int
	{
		if ($attempts <= 0)
		{
			return 0;
		}

		$schedule = self::BACKOFF_SCHEDULE;

		if (isset($schedule[$attempts]))
		{
			return $schedule[$attempts];
		}

		// Beyond the defined schedule the row is already dead (see is_dead());
		// the delay value is moot since claim_due() excludes it either way.
		return end($schedule);
	}

	/**
	 * Pure dead-letter check — see class docblock.
	 */
	public static function is_dead(int $attempts): bool
	{
		return $attempts >= self::DEAD_AFTER_ATTEMPTS;
	}

	/**
	 * Removes any existing pending row(s) for this event before a fresh
	 * enqueue — an event save (or delete) only ever needs its latest state
	 * pushed, never a backlog of stale upserts/deletes for the same event.
	 *
	 * Deliberately unconditional: this deletes ALL rows for $event_id
	 * regardless of their state — including a row that is mid-retry
	 * (attempts > 0, next_retry_ts in the future) or already dead-lettered
	 * (attempts >= DEAD_AFTER_ATTEMPTS). Latest-state-wins is the whole
	 * point (see class docblock's "No-op rule"/backoff notes): a fresh
	 * enqueue always supersedes whatever stale job was previously queued for
	 * this event, dead or not. A consequence is that a worker holding a
	 * claim_due() row for this event_id can have that row vanish out from
	 * under it if a save/delete races the in-flight push: mark_ok()'s
	 * DELETE is naturally a no-op on a missing row, and mark_failed()
	 * explicitly guards on the row being gone (see its "No-op if the row is
	 * already gone" comment above). Callers of claim_due() must tolerate
	 * rows disappearing between claim and mark_*() for this reason.
	 */
	protected function dedupe(int $event_id): void
	{
		$this->db->sql_query('DELETE FROM ' . $this->outbox_table . ' WHERE event_id = ' . (int) $event_id);
	}
}
