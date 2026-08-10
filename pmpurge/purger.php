<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge;

/**
 * Selects the private messages of long-inactive members and hands them to
 * phpBB's own delete_pm() so that every side table stays consistent.
 *
 * Nothing in here writes to the private message tables directly: delete_pm()
 * owns the bookkeeping (privmsgs_to rows, folder counts, unread counters,
 * notifications, attachments, and the orphaned privmsgs row itself).
 */
class purger
{
	/** Cutoffs are measured backwards from now. */
	const MODE_DAYS = 'days';

	/** Cutoffs are fixed calendar dates. */
	const MODE_DATE = 'date';

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log_interface */
	protected $log;

	/** @var \phpbb\user */
	protected $user;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** @var bool */
	protected $functions_loaded = false;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config              $config    Config object
	 * @param \phpbb\db\driver\driver_interface $db        Database driver
	 * @param \phpbb\log\log_interface          $log       Log object
	 * @param \phpbb\user                       $user      User object
	 * @param string                            $root_path phpBB root path
	 * @param string                            $php_ext   php file extension
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\log\log_interface $log, \phpbb\user $user, $root_path, $php_ext)
	{
		$this->config    = $config;
		$this->db        = $db;
		$this->log       = $log;
		$this->user      = $user;
		$this->root_path = $root_path;
		$this->php_ext   = $php_ext;
	}

	/**
	 * Whether the purger has enough configuration to select anything at all.
	 *
	 * @return bool
	 */
	public function is_configured()
	{
		return $this->get_user_cutoff() > 0;
	}

	/**
	 * Whether automatic (cron) purging is switched on.
	 *
	 * @return bool
	 */
	public function is_enabled()
	{
		return (bool) $this->config['ecyaz_pmpurge_enabled'] && $this->is_configured();
	}

	/**
	 * Whether the purger is in dry run mode, in which nothing is deleted.
	 *
	 * @return bool
	 */
	public function is_dry_run()
	{
		return (bool) $this->config['ecyaz_pmpurge_dry_run'];
	}

	/**
	 * Timestamp before which a member counts as inactive, or 0 when no
	 * inactivity period has been set at all.
	 *
	 * @return int
	 */
	public function get_user_cutoff()
	{
		if ($this->config['ecyaz_pmpurge_inactive_mode'] === self::MODE_DATE)
		{
			return (int) $this->config['ecyaz_pmpurge_inactive_before'];
		}

		$days = (int) $this->config['ecyaz_pmpurge_inactive_days'];

		return $days > 0 ? time() - ($days * 86400) : 0;
	}

	/**
	 * Timestamp before which a message counts as old, or 0 when the selection
	 * has no upper bound on message age.
	 *
	 * @return int
	 */
	public function get_message_cutoff()
	{
		if ($this->config['ecyaz_pmpurge_msg_mode'] === self::MODE_DATE)
		{
			return (int) $this->config['ecyaz_pmpurge_msg_before'];
		}

		$days = (int) $this->config['ecyaz_pmpurge_pm_age_days'];

		return $days > 0 ? time() - ($days * 86400) : 0;
	}

	/**
	 * Timestamp after which a message is old enough to keep, or 0 when the
	 * selection has no lower bound.
	 *
	 * Only the fixed date mode has a lower bound: "older than N days" is open
	 * ended backwards by definition.
	 *
	 * @return int
	 */
	public function get_message_floor()
	{
		return $this->config['ecyaz_pmpurge_msg_mode'] === self::MODE_DATE
			? (int) $this->config['ecyaz_pmpurge_msg_after']
			: 0;
	}

	/**
	 * Count what a purge would remove, without removing anything.
	 *
	 * `messages` is the number of rows that would disappear from the privmsgs
	 * table, i.e. messages whose every remaining copy is inside the selection.
	 * A message still held by somebody who is not being purged keeps its body,
	 * so `rows` is always the larger and less interesting number.
	 *
	 * @return array Array with keys users, rows and messages
	 */
	public function preview()
	{
		if (!$this->is_configured())
		{
			return ['users' => 0, 'rows' => 0, 'messages' => 0];
		}

		$sql = 'SELECT COUNT(DISTINCT pt.user_id) AS num_users, COUNT(*) AS num_rows
			FROM ' . $this->from_sql() . '
			WHERE ' . $this->filter_sql();
		$result = $this->db->sql_query($sql);
		$row    = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return [
			'users'    => $row ? (int) $row['num_users'] : 0,
			'rows'     => $row ? (int) $row['num_rows'] : 0,
			'messages' => $this->count_orphaned_messages(),
		];
	}

	/**
	 * Purge the private messages of up to $max_users inactive members.
	 *
	 * Members are walked in user id order from a stored cursor, so a member the
	 * batch cannot act on (an exempt one, say) still moves the cursor along and
	 * the next run makes progress instead of retrying the same window forever.
	 *
	 * @param int  $max_users Members to process, 0 for the configured batch size
	 * @param bool $dry_run   Override the configured dry run setting
	 * @param bool $log_run   Whether to write an entry to the admin log
	 * @return array Array with keys users, rows, messages, dry_run and finished
	 */
	public function purge($max_users = 0, $dry_run = null, $log_run = true)
	{
		$dry_run = $dry_run === null ? $this->is_dry_run() : (bool) $dry_run;
		$stats   = ['users' => 0, 'rows' => 0, 'messages' => 0, 'dry_run' => $dry_run, 'finished' => true];

		if (!$this->is_configured())
		{
			return $stats;
		}

		$max_users  = (int) $max_users > 0 ? (int) $max_users : (int) $this->config['ecyaz_pmpurge_batch_users'];
		$cursor     = (int) $this->config['ecyaz_pmpurge_cursor'];
		$candidates = $this->fetch_candidate_users($max_users, $cursor);

		// End of the member list reached: start over from the beginning.
		if (empty($candidates) && $cursor > 0)
		{
			$cursor     = 0;
			$candidates = $this->fetch_candidate_users($max_users, $cursor);
		}

		if (empty($candidates))
		{
			$this->config->set('ecyaz_pmpurge_cursor', 0);

			return $stats;
		}

		$stats['finished'] = count($candidates) < $max_users;
		$this->config->set('ecyaz_pmpurge_cursor', $stats['finished'] ? 0 : (int) end($candidates));

		$user_ids = $this->remove_exempt_users($candidates);

		if (empty($user_ids))
		{
			return $stats;
		}

		$messages_before = $dry_run ? 0 : $this->count_messages();

		// delete_pm() adjusts the private message counters of the *session*
		// user in memory as a side effect. Snapshot them so an admin running a
		// purge does not see their own counters skewed for the rest of the
		// request, and so a CLI run does not touch an uninitialised user->data.
		$new_privmsg    = isset($this->user->data['user_new_privmsg']) ? $this->user->data['user_new_privmsg'] : 0;
		$unread_privmsg = isset($this->user->data['user_unread_privmsg']) ? $this->user->data['user_unread_privmsg'] : 0;

		$this->user->data['user_new_privmsg']    = $new_privmsg;
		$this->user->data['user_unread_privmsg'] = $unread_privmsg;

		if (!$dry_run)
		{
			$this->load_functions();
		}

		foreach ($user_ids as $user_id)
		{
			$folders = $this->fetch_user_messages($user_id);

			if (empty($folders))
			{
				continue;
			}

			$stats['users']++;

			foreach ($folders as $folder_id => $msg_ids)
			{
				$stats['rows'] += count($msg_ids);

				if (!$dry_run)
				{
					delete_pm($user_id, $msg_ids, (int) $folder_id);
				}
			}
		}

		$this->user->data['user_new_privmsg']    = $new_privmsg;
		$this->user->data['user_unread_privmsg'] = $unread_privmsg;

		if (!$dry_run)
		{
			$stats['messages'] = max(0, $messages_before - $this->count_messages());
		}

		if ($log_run && $stats['users'])
		{
			$this->log->add('admin', (int) $this->user->data['user_id'], $this->user->ip, $dry_run ? 'LOG_PMPURGE_DRY_RUN' : 'LOG_PMPURGE_RUN', time(), [
				$stats['users'],
				$stats['rows'],
				$stats['messages'],
			]);
		}

		return $stats;
	}

	/**
	 * Members holding at least one purgeable message, in user id order.
	 *
	 * This query is the one that goes through sql_query_limit(), so its WHERE
	 * clause must stay free of subqueries: the MSSQL driver injects TOP into
	 * every SELECT it finds in the statement, nested ones included. Exempt
	 * groups are therefore applied afterwards, in remove_exempt_users().
	 *
	 * @param int $limit          Maximum number of members to return
	 * @param int $after_user_id  Only consider members above this id
	 * @return int[]
	 */
	protected function fetch_candidate_users($limit, $after_user_id = 0)
	{
		$where = $this->filter_sql(false);

		if ($after_user_id > 0)
		{
			$where .= ' AND pt.user_id > ' . (int) $after_user_id;
		}

		$sql = $this->db->sql_build_query('SELECT_DISTINCT', [
			'SELECT'    => 'pt.user_id',
			'FROM'      => [PRIVMSGS_TO_TABLE => 'pt'],
			'LEFT_JOIN' => $this->left_join_sql(),
			'WHERE'     => $where,
			'ORDER_BY'  => 'pt.user_id ASC',
		]);
		$result = $this->db->sql_query_limit($sql, (int) $limit);

		$user_ids = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_ids[] = (int) $row['user_id'];
		}
		$this->db->sql_freeresult($result);

		return $user_ids;
	}

	/**
	 * Drop the members of exempt groups from a batch of candidates.
	 *
	 * @param int[] $user_ids Candidate member ids
	 * @return int[]
	 */
	protected function remove_exempt_users(array $user_ids)
	{
		$groups = $this->get_exempt_groups();

		if (empty($groups) || empty($user_ids))
		{
			return $user_ids;
		}

		$sql = 'SELECT DISTINCT user_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE ' . $this->db->sql_in_set('group_id', $groups) . '
				AND ' . $this->db->sql_in_set('user_id', $user_ids);
		$result = $this->db->sql_query($sql);

		$exempt = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$exempt[(int) $row['user_id']] = true;
		}
		$this->db->sql_freeresult($result);

		if (empty($exempt))
		{
			return $user_ids;
		}

		return array_values(array_filter($user_ids, function ($user_id) use ($exempt)
		{
			return !isset($exempt[$user_id]);
		}));
	}

	/**
	 * Purgeable message ids of one member, grouped by the folder they sit in.
	 *
	 * delete_pm() works on one folder at a time because the bookkeeping it does
	 * (folder counts, and the outbox special case) is per folder.
	 *
	 * @param int $user_id Member to collect messages for
	 * @return array Array of folder_id => msg_id[]
	 */
	protected function fetch_user_messages($user_id)
	{
		$sql = $this->db->sql_build_query('SELECT', [
			'SELECT'    => 'pt.folder_id, pt.msg_id',
			'FROM'      => [PRIVMSGS_TO_TABLE => 'pt'],
			'LEFT_JOIN' => $this->left_join_sql(),
			'WHERE'     => $this->db->sql_in_set('pt.user_id', [(int) $user_id]) . ' AND ' . $this->filter_sql(false),
		]);
		$result = $this->db->sql_query($sql);

		$folders = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$folders[(int) $row['folder_id']][] = (int) $row['msg_id'];
		}
		$this->db->sql_freeresult($result);

		return $folders;
	}

	/**
	 * Number of messages whose every copy is inside the selection, and which
	 * would therefore lose their row in the privmsgs table.
	 *
	 * @return int
	 */
	protected function count_orphaned_messages()
	{
		$sql = 'SELECT COUNT(*) AS num_messages
			FROM (
				SELECT pt.msg_id
				FROM ' . $this->from_sql() . '
				GROUP BY pt.msg_id
				HAVING COUNT(*) = SUM(CASE WHEN ' . $this->filter_sql() . ' THEN 1 ELSE 0 END)
			) orphans';
		$result = $this->db->sql_query($sql);
		$num    = (int) $this->db->sql_fetchfield('num_messages', false, $result);
		$this->db->sql_freeresult($result);

		return $num;
	}

	/**
	 * Current number of rows in the privmsgs table.
	 *
	 * @return int
	 */
	protected function count_messages()
	{
		$sql    = 'SELECT COUNT(*) AS num_messages FROM ' . PRIVMSGS_TABLE;
		$result = $this->db->sql_query($sql);
		$num    = (int) $this->db->sql_fetchfield('num_messages', false, $result);
		$this->db->sql_freeresult($result);

		return $num;
	}

	/**
	 * FROM clause joining privmsgs_to to its member and its message.
	 *
	 * The joins are outer so that a row whose member or message row went
	 * missing on an old board is still counted, and simply never matches the
	 * filter.
	 *
	 * @return string
	 */
	protected function from_sql()
	{
		$sql = PRIVMSGS_TO_TABLE . ' pt
			LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = pt.user_id)
			LEFT JOIN ' . PRIVMSGS_TABLE . ' p ON (p.msg_id = pt.msg_id)';

		$exempt_groups = $this->get_exempt_groups();

		if (!empty($exempt_groups))
		{
			// Exempt membership is reached through a join rather than a
			// subquery in the WHERE clause, because the orphan count tests it
			// inside SUM(CASE ...) and MSSQL rejects a subquery within an
			// aggregate. The inner DISTINCT matters: a member in two exempt
			// groups would otherwise duplicate rows and break the COUNT the
			// orphan query compares against.
			$sql .= '
			LEFT JOIN (SELECT DISTINCT user_id FROM ' . USER_GROUP_TABLE . '
				WHERE ' . $this->db->sql_in_set('group_id', $exempt_groups) . ') ex ON (ex.user_id = pt.user_id)';
		}

		return $sql;
	}

	/**
	 * The same joins in the array form sql_build_query() expects.
	 *
	 * @return array
	 */
	protected function left_join_sql()
	{
		return [
			['FROM' => [USERS_TABLE => 'u'], 'ON' => 'u.user_id = pt.user_id'],
			['FROM' => [PRIVMSGS_TABLE => 'p'], 'ON' => 'p.msg_id = pt.msg_id'],
		];
	}

	/**
	 * Predicate matching exactly the privmsgs_to rows that may be purged.
	 *
	 * Used both as a WHERE clause and inside a CASE expression, so it must not
	 * depend on anything outside the joined row.
	 *
	 * @param bool $with_exempt_groups Test exempt membership, which requires
	 *                                 the join from_sql() adds. False for the
	 *                                 queries built with sql_build_query().
	 * @return string
	 */
	protected function filter_sql($with_exempt_groups = true)
	{
		$where = [];

		// Undelivered and held messages are core's business, never ours.
		$where[] = 'pt.folder_id <> ' . PRIVMSGS_NO_BOX;
		$where[] = 'pt.folder_id <> ' . PRIVMSGS_HOLD_BOX;

		// Deleting a sender's outbox copy blanks the message body for the
		// recipients who have not read it yet, so it is opt in.
		if (!$this->config['ecyaz_pmpurge_include_outbox'])
		{
			$where[] = 'pt.folder_id <> ' . PRIVMSGS_OUTBOX;
		}

		$where[] = 'u.user_id <> ' . ANONYMOUS;
		$where[] = 'u.user_type <> ' . USER_IGNORE;

		if ($this->config['ecyaz_pmpurge_skip_founders'])
		{
			$where[] = 'u.user_type <> ' . USER_FOUNDER;
		}

		$cutoff   = $this->get_user_cutoff();
		$inactive = ['(u.user_lastvisit > 0 AND u.user_lastvisit < ' . $cutoff . ')'];

		if ($this->config['ecyaz_pmpurge_include_never_visited'])
		{
			// A member who never logged in has no last visit to measure, so
			// fall back to the registration date rather than treating a zero
			// timestamp as infinitely old.
			$inactive[] = '(u.user_lastvisit = 0 AND u.user_regdate > 0 AND u.user_regdate < ' . $cutoff . ')';
		}

		$where[] = '(' . implode(' OR ', $inactive) . ')';

		$message_cutoff = $this->get_message_cutoff();
		if ($message_cutoff)
		{
			$where[] = 'p.message_time < ' . $message_cutoff;
		}

		$message_floor = $this->get_message_floor();
		if ($message_floor)
		{
			$where[] = 'p.message_time >= ' . $message_floor;
		}

		if ($with_exempt_groups && !empty($this->get_exempt_groups()))
		{
			$where[] = 'ex.user_id IS NULL';
		}

		return '(' . implode(' AND ', $where) . ')';
	}

	/**
	 * Group ids whose members are never purged.
	 *
	 * @return int[]
	 */
	public function get_exempt_groups()
	{
		$raw = trim((string) $this->config['ecyaz_pmpurge_exempt_groups']);

		if ($raw === '')
		{
			return [];
		}

		$groups = [];
		foreach (explode(',', $raw) as $group_id)
		{
			$group_id = (int) trim($group_id);

			if ($group_id > 0)
			{
				$groups[$group_id] = $group_id;
			}
		}

		return array_values($groups);
	}

	/**
	 * Pull in the core private message functions delete_pm() lives in.
	 *
	 * @return void
	 */
	protected function load_functions()
	{
		if ($this->functions_loaded || function_exists('delete_pm'))
		{
			$this->functions_loaded = true;

			return;
		}

		include_once($this->root_path . 'includes/functions_privmsgs.' . $this->php_ext);
		$this->functions_loaded = true;
	}
}
