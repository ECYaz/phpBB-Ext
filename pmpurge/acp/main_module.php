<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\acp;

class main_module
{
	/** Seconds a single manual run is allowed to spend before handing back to the browser. */
	const RUN_TIME_BUDGET = 8;

	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $config, $request, $template, $language, $phpbb_container, $user;

		$language->add_lang('info_acp_pmpurge', 'ecyaz/pmpurge');

		$this->tpl_name   = 'acp_pmpurge_settings';
		$this->page_title = 'ACP_PMPURGE_SETTINGS';
		$form_key         = 'ecyaz_pmpurge';
		add_form_key($form_key);

		/** @var \ecyaz\pmpurge\purger $purger */
		$purger = $phpbb_container->get('ecyaz.pmpurge.purger');

		$action = $request->variable('action', '');

		if ($request->is_set_post('submit'))
		{
			$this->save_settings($form_key);
		}

		if ($request->is_set_post('preview'))
		{
			$this->preview($form_key, $purger);
		}

		if ($request->is_set_post('purge_now') || $action === 'run')
		{
			$this->run($form_key, $purger);
		}

		$groups = $purger->get_exempt_groups();

		$template->assign_vars([
			'U_ACTION'                    => $this->u_action,
			'PMPURGE_ENABLED'             => (bool) $config['ecyaz_pmpurge_enabled'],
			'PMPURGE_DRY_RUN'             => (bool) $config['ecyaz_pmpurge_dry_run'],
			'PMPURGE_INACTIVE_DAYS'       => (int) $config['ecyaz_pmpurge_inactive_days'],
			'PMPURGE_PM_AGE_DAYS'         => (int) $config['ecyaz_pmpurge_pm_age_days'],
			'S_PMPURGE_INACTIVE_BY_DATE'  => $config['ecyaz_pmpurge_inactive_mode'] === \ecyaz\pmpurge\purger::MODE_DATE,
			'S_PMPURGE_MSG_BY_DATE'       => $config['ecyaz_pmpurge_msg_mode'] === \ecyaz\pmpurge\purger::MODE_DATE,
			'PMPURGE_INACTIVE_BEFORE'     => $this->format_date($config['ecyaz_pmpurge_inactive_before']),
			'PMPURGE_MSG_AFTER'           => $this->format_date($config['ecyaz_pmpurge_msg_after']),
			'PMPURGE_MSG_BEFORE'          => $this->format_date($config['ecyaz_pmpurge_msg_before']),
			'PMPURGE_INCLUDE_OUTBOX'      => (bool) $config['ecyaz_pmpurge_include_outbox'],
			'PMPURGE_INCLUDE_NEVER'       => (bool) $config['ecyaz_pmpurge_include_never_visited'],
			'PMPURGE_SKIP_FOUNDERS'       => (bool) $config['ecyaz_pmpurge_skip_founders'],
			'PMPURGE_EXEMPT_GROUPS'       => implode(', ', $groups),
			'PMPURGE_BATCH_USERS'         => (int) $config['ecyaz_pmpurge_batch_users'],
			'PMPURGE_GC'                  => (int) $config['ecyaz_pmpurge_gc'],
			'PMPURGE_LAST_GC'             => $config['ecyaz_pmpurge_last_gc'] ? $user->format_date((int) $config['ecyaz_pmpurge_last_gc']) : $language->lang('PMPURGE_NEVER'),
		]);
	}

	/**
	 * Store the submitted settings.
	 *
	 * @param string $form_key Form key to validate against
	 * @return void
	 */
	protected function save_settings($form_key)
	{
		global $config, $request, $language, $phpbb_log, $user;

		if (!check_form_key($form_key))
		{
			trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$exempt = [];
		foreach (explode(',', $request->variable('pmpurge_exempt_groups', '')) as $group_id)
		{
			$group_id = (int) trim($group_id);

			if ($group_id > 0)
			{
				$exempt[$group_id] = $group_id;
			}
		}

		$inactive_mode = $this->read_mode('pmpurge_inactive_mode');
		$message_mode  = $this->read_mode('pmpurge_msg_mode');

		$inactive_before = $this->read_date('pmpurge_inactive_before');
		$message_after   = $this->read_date('pmpurge_msg_after');
		$message_before  = $this->read_date('pmpurge_msg_before');

		if ($inactive_mode === \ecyaz\pmpurge\purger::MODE_DATE && !$inactive_before)
		{
			trigger_error($language->lang('PMPURGE_DATE_REQUIRED') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		if ($message_after && $message_before && $message_after >= $message_before)
		{
			trigger_error($language->lang('PMPURGE_DATE_RANGE_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$config->set('ecyaz_pmpurge_enabled', (int) $request->variable('pmpurge_enabled', 0));
		$config->set('ecyaz_pmpurge_dry_run', (int) $request->variable('pmpurge_dry_run', 0));
		$config->set('ecyaz_pmpurge_inactive_mode', $inactive_mode);
		$config->set('ecyaz_pmpurge_inactive_days', max(1, (int) $request->variable('pmpurge_inactive_days', 1095)));
		$config->set('ecyaz_pmpurge_inactive_before', $inactive_before);
		$config->set('ecyaz_pmpurge_msg_mode', $message_mode);
		$config->set('ecyaz_pmpurge_pm_age_days', max(0, (int) $request->variable('pmpurge_pm_age_days', 0)));
		$config->set('ecyaz_pmpurge_msg_after', $message_after);
		$config->set('ecyaz_pmpurge_msg_before', $message_before);
		$config->set('ecyaz_pmpurge_include_outbox', (int) $request->variable('pmpurge_include_outbox', 0));
		$config->set('ecyaz_pmpurge_include_never_visited', (int) $request->variable('pmpurge_include_never_visited', 0));
		$config->set('ecyaz_pmpurge_skip_founders', (int) $request->variable('pmpurge_skip_founders', 0));
		$config->set('ecyaz_pmpurge_exempt_groups', implode(',', $exempt));
		$config->set('ecyaz_pmpurge_batch_users', max(1, (int) $request->variable('pmpurge_batch_users', 25)));
		$config->set('ecyaz_pmpurge_gc', max(60, (int) $request->variable('pmpurge_gc', 86400)));

		// Selection settings changed, so a walk in progress no longer means
		// what it did when it started.
		$config->set('ecyaz_pmpurge_cursor', 0, false);

		$phpbb_log->add('admin', (int) $user->data['user_id'], $user->ip, 'LOG_PMPURGE_SETTINGS', time());

		trigger_error($language->lang('PMPURGE_SAVED') . adm_back_link($this->u_action));
	}

	/**
	 * Render a stored timestamp back into the YYYY-MM-DD the form expects.
	 *
	 * @param int $timestamp Stored UTC timestamp, 0 when unset
	 * @return string
	 */
	protected function format_date($timestamp)
	{
		return (int) $timestamp > 0 ? gmdate('Y-m-d', (int) $timestamp) : '';
	}

	/**
	 * Read a rolling/fixed mode selector, defaulting to rolling days.
	 *
	 * @param string $field Request field to read
	 * @return string
	 */
	protected function read_mode($field)
	{
		global $request;

		return $request->variable($field, '') === \ecyaz\pmpurge\purger::MODE_DATE
			? \ecyaz\pmpurge\purger::MODE_DATE
			: \ecyaz\pmpurge\purger::MODE_DAYS;
	}

	/**
	 * Read a YYYY-MM-DD field and turn it into a UTC midnight timestamp.
	 *
	 * Dates are interpreted in UTC, the way core's own user pruning does it,
	 * so the same setting selects the same messages whatever the viewing
	 * administrator's timezone happens to be.
	 *
	 * @param string $field Request field to read
	 * @return int Timestamp, or 0 when the field is empty or unparseable
	 */
	protected function read_date($field)
	{
		global $request, $language;

		$value = trim($request->variable($field, ''));

		if ($value === '')
		{
			return 0;
		}

		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match))
		{
			trigger_error($language->lang('PMPURGE_DATE_INVALID', $value) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		[, $year, $month, $day] = array_map('intval', $match);

		if (!checkdate($month, $day, $year))
		{
			trigger_error($language->lang('PMPURGE_DATE_INVALID', $value) . adm_back_link($this->u_action), E_USER_WARNING);
		}

		return (int) gmmktime(0, 0, 0, $month, $day, $year);
	}

	/**
	 * Count what a purge would remove and show it, deleting nothing.
	 *
	 * @param string                $form_key Form key to validate against
	 * @param \ecyaz\pmpurge\purger $purger   Purger service
	 * @return void
	 */
	protected function preview($form_key, \ecyaz\pmpurge\purger $purger)
	{
		global $template, $language;

		if (!check_form_key($form_key))
		{
			trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		$stats = $purger->preview();

		$template->assign_vars([
			'S_PMPURGE_PREVIEW'      => true,
			'PMPURGE_PREVIEW_USERS'  => $stats['users'],
			'PMPURGE_PREVIEW_ROWS'   => $stats['rows'],
			'PMPURGE_PREVIEW_MSGS'   => $stats['messages'],
		]);
	}

	/**
	 * Run the purge from the browser, a batch at a time.
	 *
	 * The first request is a form post guarded by a confirmation box; each
	 * continuation is a link carrying a session bound hash, so a purge can
	 * never be started by following a link from somewhere else.
	 *
	 * @param string                $form_key Form key to validate against
	 * @param \ecyaz\pmpurge\purger $purger   Purger service
	 * @return void
	 */
	protected function run($form_key, \ecyaz\pmpurge\purger $purger)
	{
		global $request, $template, $language;

		$continued = $request->variable('action', '') === 'run';
		$dry_run   = (bool) $request->variable('dry', 0);

		if ($continued)
		{
			if (!check_link_hash($request->variable('hash', ''), 'pmpurge_run'))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}
		}
		else if (!confirm_box(true))
		{
			// The confirmation repost carries confirm_box's own session bound
			// token rather than this form's key, so the key is checked here,
			// on the way in, and not again on the way back. A "No" answer also
			// lands here without the key; confirm_box(false) ignores that
			// repost, so it simply falls through to the settings page.
			if (!$request->is_set_post('cancel') && !check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			confirm_box(false, $language->lang('PMPURGE_CONFIRM_RUN'), build_hidden_fields([
				'purge_now' => 1,
				'dry'       => $dry_run ? 1 : 0,
			]));

			return;
		}

		if (!$purger->is_configured())
		{
			trigger_error($language->lang('PMPURGE_NOT_CONFIGURED') . adm_back_link($this->u_action), E_USER_WARNING);
		}

		// A fresh run covers the member list exactly once: from the top, with
		// the wraparound off, so that candidates the batches never remove (a
		// dry run removes none, exempt members are never removed) cannot feed
		// the walk full batches forever.
		if (!$continued)
		{
			$purger->restart();
		}

		$totals = [
			'users'    => max(0, $request->variable('done_users', 0)),
			'rows'     => max(0, $request->variable('done_rows', 0)),
			'messages' => max(0, $request->variable('done_messages', 0)),
		];

		$started  = time();
		$finished = false;

		do
		{
			$stats = $purger->purge(0, $dry_run, false, false);

			foreach (array_keys($totals) as $key)
			{
				$totals[$key] += $stats[$key];
			}

			$finished = $stats['finished'];
		}
		while (!$finished && (time() - $started) < self::RUN_TIME_BUDGET);

		if (!$finished)
		{
			$url = append_sid($this->u_action, [
				'action'        => 'run',
				'dry'           => $dry_run ? 1 : 0,
				'done_users'    => $totals['users'],
				'done_rows'     => $totals['rows'],
				'done_messages' => $totals['messages'],
				'hash'          => generate_link_hash('pmpurge_run'),
			]);

			meta_refresh(1, $url);

			$template->assign_vars([
				'S_PMPURGE_RUNNING'    => true,
				'PMPURGE_RUN_USERS'    => $totals['users'],
				'PMPURGE_RUN_ROWS'     => $totals['rows'],
				'PMPURGE_RUN_MESSAGES' => $totals['messages'],
				'U_PMPURGE_CONTINUE'   => $url,
			]);

			return;
		}

		$purger->log_run($dry_run, $totals);

		$message = $dry_run
			? $language->lang('PMPURGE_DRY_RUN_DONE') . ' ' . $language->lang('PMPURGE_DRY_RUN_TOTALS', $totals['users'], $totals['rows'])
			: $language->lang('PMPURGE_RUN_DONE') . ' ' . $language->lang('PMPURGE_RUN_TOTALS', $totals['users'], $totals['rows'], $totals['messages']);

		trigger_error($message . adm_back_link($this->u_action));
	}
}
