<?php
/**
 *
 * This file is part of the phpBB Events Calendar extension.
 *
 * @copyright (c) ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\eventscalendar\acp;

class main_module
{
	/**
	 * Special-dates form constants (Task 10). Deliberately NOT reused from
	 * controller/event.php's own COLOR_COUNT/RECUR_ANNUAL/RECUR_NONE (a
	 * front-end controller class this ACP module has no DI path to and no
	 * other reason to depend on) -- these mirror the SAME values (recur_type
	 * is a shared ecal_events column, color is the shared 0-7 palette), kept
	 * here as this class's own small, self-contained copy.
	 */
	const SPECIAL_COLOR_COUNT   = 8;
	const SPECIAL_RECUR_NONE    = 0;
	const SPECIAL_RECUR_ANNUAL  = 4;

	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $config, $request, $template, $user, $db, $phpbb_container;

		$user->add_lang_ext('ecyaz/eventscalendar', 'acp/ecal');

		if ($mode === 'special')
		{
			$this->special_mode($config, $request, $template, $user, $db, $phpbb_container);

			return;
		}

		if ($mode === 'google')
		{
			$this->google_mode($config, $request, $template, $user, $db, $phpbb_container);

			return;
		}

		$this->tpl_name   = 'acp_ecal_settings';
		$this->page_title = 'ACP_ECAL_SETTINGS';

		$form_key = 'ecyaz_ecal';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$ecal_index_upcoming_count = max(1, min(50, $request->variable('ecal_index_upcoming_count', 5)));
			$ecal_reminder_days        = max(0, min(30, $request->variable('ecal_reminder_days', 3)));

			$ecal_ics_enable = $request->variable('ecal_ics_enable', false);

			$config->set('ecal_index_display', $request->variable('ecal_index_display', 0));
			$config->set('ecal_index_upcoming_count', $ecal_index_upcoming_count);
			$config->set('ecal_reminder_days', $ecal_reminder_days);
			$config->set('ecal_birthdays_enable', $request->variable('ecal_birthdays_enable', false));
			$config->set('ecal_topic_forum_id', $request->variable('ecal_topic_forum_id', 0));
			$config->set('ecal_ics_enable', $ecal_ics_enable);
			$config->set('ecal_ics_public', $request->variable('ecal_ics_public', false));

			// M7 fix: enabling the feed with no token ever set (the shipped
			// '' default, see migrations/install_config.php) would otherwise
			// require a SEPARATE "Regenerate" click before a private feed
			// URL exists at all -- auto-generate one here, the same
			// bin2hex(random_bytes(16)) the dedicated regenerate_token
			// branch below already uses, so enabling the feed always leaves
			// it immediately usable.
			if ($ecal_ics_enable && (string) $config['ecal_ics_token'] === '')
			{
				$config->set('ecal_ics_token', bin2hex(random_bytes(16)));
			}

			trigger_error($user->lang('ACP_ECAL_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		// Own POST branch, own <form> (see acp_ecal_settings.html): a
		// dedicated "Regenerate" button that must NOT also require every
		// other settings field to be present in the same submit, mirroring
		// google_mode()'s separate test_connection/resync_all forms below.
		// Regenerating immediately invalidates every previously-issued feed
		// URL (task-10 brief) — no extra invalidation step is needed here:
		// feed.php compares the GET `key` against the freshly-read
		// ecal_ics_token config value on every request, so the old token
		// simply stops matching the moment this config->set() below commits.
		if ($request->is_set_post('regenerate_token'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$config->set('ecal_ics_token', bin2hex(random_bytes(16)));

			trigger_error($user->lang('ACP_ECAL_ICS_TOKEN_REGENERATED') . adm_back_link($this->u_action));
		}

		$ecal_topic_forum_id = (int) $config['ecal_topic_forum_id'];

		$s_ecal_topic_forum_options = '<option value="0"' . (($ecal_topic_forum_id === 0) ? ' selected="selected"' : '') . '>' . $user->lang('ACP_ECAL_TOPIC_FORUM_NONE') . '</option>';
		$s_ecal_topic_forum_options .= make_forum_select($ecal_topic_forum_id ?: false);

		/** @var \phpbb\controller\helper $helper */
		$helper = $phpbb_container->get('controller.helper');

		// ecal_ics_token is always bin2hex() output (or the shipped '' default,
		// see migrations/install_config.php) -- safe ASCII hex, never through
		// $request->variable(), assigned raw like every other plain config
		// value elsewhere in this codebase (no HTML-meaningful characters are
		// ever possible in it, so there is nothing for an `|e` sink to do).
		$ecal_ics_token = (string) $config['ecal_ics_token'];

		// Session id deliberately suppressed ($session_id = '', not false --
		// see service/ics.php's own U_EVENT-building comment/task-7 regression
		// fix): this URL is meant to be copy-pasted into an external calendar
		// client, never carrying the acting admin's live session.
		$ecal_ics_feed_url = $helper->route(
			'ecyaz_eventscalendar_ics',
			['key' => $ecal_ics_token],
			false,
			false,
			\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL
		);

		$template->assign_vars([
			'ECAL_INDEX_DISPLAY'         => (int) $config['ecal_index_display'],
			'ECAL_INDEX_UPCOMING_COUNT'  => (int) $config['ecal_index_upcoming_count'],
			'ECAL_REMINDER_DAYS'         => (int) $config['ecal_reminder_days'],
			'S_ECAL_BIRTHDAYS_ENABLE'    => (bool) $config['ecal_birthdays_enable'],
			'S_ECAL_TOPIC_FORUM_OPTIONS' => $s_ecal_topic_forum_options,
			'S_ECAL_ICS_ENABLE'          => (bool) $config['ecal_ics_enable'],
			'S_ECAL_ICS_PUBLIC'          => (bool) $config['ecal_ics_public'],
			'ECAL_ICS_TOKEN'             => $ecal_ics_token,
			'ECAL_ICS_FEED_URL'          => $ecal_ics_feed_url,
			'U_ACTION'                   => $this->u_action,
		]);
	}

	// ------------------------------------------------------------------
	// Google sync mode (Task 9)
	// ------------------------------------------------------------------

	/**
	 * ACP google mode: enable toggle + calendar id + write-only service-
	 * account JSON key, a Test connection probe, the outbox queue (pending +
	 * dead rows) with per-row retry/discard, and a Resync all action. Every
	 * POST branch below checks the SAME page-wide form key ('ecyaz_ecal',
	 * added once via add_form_key()) -- phpBB's check_form_key() validates
	 * against the session/time-bound token, not a per-form secret, so one
	 * token legitimately covers the several independent <form>s this page
	 * renders (settings, test-connection, resync, per-row queue actions).
	 *
	 * No constructor DI here: ACP modules are instantiated bare (`new
	 * $module_basename()`) by phpBB's p_master, so services beyond the
	 * always-global $config/$request/$template/$user/$db are fetched from
	 * $phpbb_container by id -- the same pattern phpBB core's own ACP
	 * modules use (e.g. includes/acp/acp_board.php).
	 */
	protected function google_mode($config, $request, $template, $user, $db, $phpbb_container): void
	{
		$this->tpl_name   = 'acp_ecal_google';
		$this->page_title = 'ACP_ECAL_GOOGLE';

		/** @var \phpbb\config\db_text $config_text */
		$config_text = $phpbb_container->get('config_text');

		/** @var \ecyaz\eventscalendar\service\outbox $outbox */
		$outbox = $phpbb_container->get('ecyaz.eventscalendar.outbox');

		/** @var \ecyaz\eventscalendar\service\gcal_client $gcal_client */
		$gcal_client = $phpbb_container->get('ecyaz.eventscalendar.gcal_client');

		$table_prefix = $phpbb_container->getParameter('core.table_prefix');
		$events_table = $table_prefix . 'ecal_events';
		$outbox_table = $table_prefix . 'ecal_outbox';

		$form_key = 'ecyaz_ecal';
		add_form_key($form_key);

		$test_result = null;

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			// The calendar id is a plain config string like every other
			// text field in this codebase: $request->variable()'s
			// unconditional htmlspecialchars() (see \phpbb\request\
			// type_cast_helper::set_var() -- true regardless of the
			// $multibyte flag) is exactly right for it, since it is later
			// template-assigned raw into value="{ECAL_GCAL_CALENDAR_ID}"
			// (see assign_google_template_vars() below) -- real Google
			// Calendar ids never contain &/</>/", so there is nothing here
			// for htmlspecialchars() to legitimately corrupt.
			$config->set('ecal_gcal_enable', $request->variable('ecal_gcal_enable', 0));
			$config->set('ecal_gcal_calendar_id', $request->variable('ecal_gcal_calendar_id', '', true));

			// The service-account JSON key is the ONE field on this page
			// that must survive byte-for-byte: its '"'/'<'/'>' characters
			// are structural JSON syntax, and $request->variable()'s
			// htmlspecialchars() would corrupt them so json_decode() in
			// gcal_auth::load_service_account() breaks. It is never
			// template-assigned back (write-only, see below), so there is
			// no XSS sink to protect here -- htmlspecialchars_decode()
			// with the matching ENT_COMPAT flag exactly inverts
			// $request->variable()'s encoding step.
			//
			// Write-only: an empty textarea submit means "leave the stored
			// key alone" (the page never echoes the key back, so an empty
			// submit is the normal case on every settings save that isn't
			// specifically replacing the key) -- only a non-empty paste
			// overwrites config_text.
			$sa_json = htmlspecialchars_decode($request->variable('ecal_gcal_sa_json', '', true), ENT_COMPAT);

			if (trim($sa_json) !== '')
			{
				$config_text->set('ecal_gcal_sa_json', $sa_json);
			}

			trigger_error($user->lang('ACP_ECAL_GOOGLE_SAVED') . adm_back_link($this->u_action));
		}

		if ($request->is_set_post('test_connection'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			try
			{
				$summary     = $gcal_client->test_connection();
				$test_result = ['success' => true, 'message' => $user->lang('ACP_ECAL_GOOGLE_TEST_OK', $summary)];
			}
			catch (\Throwable $e)
			{
				// Never fatals the page (brief, binding): every failure mode
				// here -- unconfigured/malformed SA JSON, an unparseable
				// private key, Google rejecting the assertion, a bad/missing
				// calendar id -- is a \RuntimeException with an admin-
				// actionable message (see gcal_auth/gcal_client class
				// docblocks), so it is safe to surface verbatim.
				$test_result = ['success' => false, 'message' => $e->getMessage()];
			}
		}

		if ($request->is_set_post('resync_all'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$this->resync_all($db, $outbox, $events_table);

			trigger_error($user->lang('ACP_ECAL_GOOGLE_RESYNC_DONE') . adm_back_link($this->u_action));
		}

		$retry_ids = array_keys($request->variable('retry', [0 => 0]));

		if (!empty($retry_ids))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$this->retry_row($db, $outbox_table, (int) $retry_ids[0]);

			trigger_error($user->lang('ACP_ECAL_GOOGLE_QUEUE_UPDATED') . adm_back_link($this->u_action));
		}

		$discard_ids = array_keys($request->variable('discard', [0 => 0]));

		if (!empty($discard_ids))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$this->discard_row($db, $outbox_table, (int) $discard_ids[0]);

			trigger_error($user->lang('ACP_ECAL_GOOGLE_QUEUE_UPDATED') . adm_back_link($this->u_action));
		}

		$this->assign_google_template_vars($template, $user, $config, $config_text, $test_result);
		$this->assign_queue_rows($db, $template, $user, $outbox_table, $events_table);
	}

	/**
	 * Enqueues an upsert (service/outbox.php::enqueue_upsert()) for every
	 * event that is either still recurring (recur_type > 0, no natural end
	 * to stop pushing) or has not fully ended yet (end_ts >= now) -- past,
	 * non-recurring events are never re-synced by this action since Google
	 * already either has or lacks their (immutable, over) occurrence.
	 */
	protected function resync_all($db, \ecyaz\eventscalendar\service\outbox $outbox, string $events_table): void
	{
		$sql = 'SELECT event_id FROM ' . $events_table . '
			WHERE recur_type > 0
				OR end_ts >= ' . time();
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$outbox->enqueue_upsert((int) $row['event_id']);
		}
		$db->sql_freeresult($result);
	}

	protected function retry_row($db, string $outbox_table, int $outbox_id): void
	{
		if ($outbox_id <= 0)
		{
			return;
		}

		$db->sql_query('UPDATE ' . $outbox_table . '
			SET attempts = 0, next_retry_ts = 0
			WHERE outbox_id = ' . (int) $outbox_id);
	}

	protected function discard_row($db, string $outbox_table, int $outbox_id): void
	{
		if ($outbox_id <= 0)
		{
			return;
		}

		$db->sql_query('DELETE FROM ' . $outbox_table . ' WHERE outbox_id = ' . (int) $outbox_id);
	}

	protected function assign_google_template_vars($template, $user, $config, \phpbb\config\db_text $config_text, ?array $test_result): void
	{
		$raw_sa_json = (string) $config_text->get('ecal_gcal_sa_json');
		$sa_configured = trim($raw_sa_json) !== '';
		$sa_client_email = '';

		if ($sa_configured)
		{
			$decoded = json_decode($raw_sa_json, true);

			if (is_array($decoded) && !empty($decoded['client_email']))
			{
				$sa_client_email = (string) $decoded['client_email'];
			}
		}

		// Twig autoescape is OFF in this codebase (the framework relies on
		// escape-at-input via $request->variable() for normal form fields
		// instead) -- but none of the three values flagged below ever
		// passed through $request->variable(): $sa_client_email is parsed
		// out of the write-only, htmlspecialchars_decode()d service-account
		// JSON (see google_mode() above), and ECAL_GCAL_STATUS/
		// ECAL_TEST_MESSAGE carry \Throwable::getMessage() text that embeds
		// raw Google/proxy HTTP response bodies (see gcal_client.php's
		// error-message docblocks). They are assigned RAW here -- escaping
		// happens at the actual render sink instead, via an explicit Twig
		// `|e` filter on each of the three in acp_ecal_google.html (see
		// that template's comments), rather than a PHP-side
		// htmlspecialchars() call here: phpBB's Extension Pre-Validator
		// hard-errors on ANY direct htmlspecialchars() call anywhere in an
		// extension's PHP, by design (the framework's own convention is
		// escape-at-input, not ad hoc PHP-side output escaping), so the
		// one deliberate opt-out this page needs from that convention is
		// expressed in the template layer instead, using the escape
		// mechanism Twig already exposes for exactly this. The underlying
		// config/config_text/exception-message values themselves stay raw
		// for every other (non-HTML) consumer.
		$template->assign_vars([
			'S_ECAL_GCAL_ENABLE'     => (bool) $config['ecal_gcal_enable'],
			'ECAL_GCAL_CALENDAR_ID'  => (string) $config['ecal_gcal_calendar_id'],
			'ECAL_GCAL_STATUS'       => (string) $config['ecal_gcal_status'],
			'S_ECAL_SA_CONFIGURED'   => $sa_configured,
			'ECAL_SA_CLIENT_EMAIL'   => $sa_client_email,
			'S_ECAL_TEST_RESULT'     => ($test_result !== null),
			'S_ECAL_TEST_SUCCESS'    => ($test_result !== null && $test_result['success']),
			'ECAL_TEST_MESSAGE'      => ($test_result !== null) ? (string) $test_result['message'] : '',
			'U_ACTION'               => $this->u_action,
		]);
	}

	protected function assign_queue_rows($db, $template, $user, string $outbox_table, string $events_table): void
	{
		$sql = 'SELECT o.outbox_id, o.event_id, o.action, o.attempts, o.next_retry_ts, o.last_error, e.title
			FROM ' . $outbox_table . ' o
			LEFT JOIN ' . $events_table . ' e ON (e.event_id = o.event_id)
			ORDER BY o.next_retry_ts ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$attempts = (int) $row['attempts'];
			$action   = (int) $row['action'];

			$template->assign_block_vars('ecal_gcal_queue', [
				'OUTBOX_ID'   => (int) $row['outbox_id'],
				'EVENT_TITLE' => ($row['title'] !== null) ? (string) $row['title'] : $user->lang('ACP_ECAL_GOOGLE_QUEUE_DELETED_EVENT'),
				'L_ACTION'    => ($action === \ecyaz\eventscalendar\service\outbox::ACTION_DELETE) ? $user->lang('ACP_ECAL_GOOGLE_ACTION_DELETE') : $user->lang('ACP_ECAL_GOOGLE_ACTION_UPSERT'),
				'ATTEMPTS'    => $attempts,
				'NEXT_RETRY'  => $user->format_date((int) $row['next_retry_ts']),
				// last_error is outbox::mark_failed()'s stored copy of a
				// caught \Throwable::getMessage() (see gcal_sync.php's
				// run()) -- same raw-HTTP-body-embedding hazard as
				// ECAL_GCAL_STATUS/ECAL_TEST_MESSAGE above (see
				// assign_google_template_vars()'s comment for why this is
				// assigned raw and escaped via a template-side `|e` filter
				// instead of htmlspecialchars() here); the DB column
				// itself stays raw.
				'LAST_ERROR'  => (string) $row['last_error'],
				'S_DEAD'      => \ecyaz\eventscalendar\service\outbox::is_dead($attempts),
			]);
		}
		$db->sql_freeresult($result);
	}

	// ------------------------------------------------------------------
	// Special dates mode (Task 10)
	// ------------------------------------------------------------------

	/**
	 * ACP special mode: list + single add/edit form + delete for
	 * event_type = 1 ("special date") rows (spec §12) -- board holidays and
	 * similar fixed annual/one-off markers, distinct from ordinary
	 * event_type = 0 user events. ALL writes go through
	 * ecyaz.eventscalendar.event_manager (create()/update()/delete()) so the
	 * SAME outbox-enqueue + ICS-cache-invalidation side effects fire as for
	 * every other event write (see event_manager.php's class docblock,
	 * revised for this task).
	 *
	 * Special dates are always: all_day = 1, poster_id = the acting admin,
	 * recur_type 0 (one-off) or RECUR_ANNUAL (annual toggle), no
	 * description, no discussion topic (post_topic is simply never set in
	 * the $data this builds, so event_manager::create()'s
	 * `!empty($data['post_topic'])` gate is never true here), and end_ts is
	 * the SAME board-local calendar day as start_ts (start-of-day + 86399,
	 * mirroring controller/event.php::parse_date_only()'s $end_of_day
	 * convention for an all-day event with no explicit end date) -- i.e.
	 * always a single-day span, never multi-day.
	 *
	 * The 'action'/'id' request vars double as both GET query params (the
	 * list's Edit/Delete links) and the add/edit form's own hidden
	 * event_id field -- $request->variable() reads either transport
	 * transparently, so one pair of names covers both.
	 */
	protected function special_mode($config, $request, $template, $user, $db, $phpbb_container): void
	{
		$this->tpl_name   = 'acp_ecal_special';
		$this->page_title = 'ACP_ECAL_SPECIAL';

		// ECAL_TITLE/ECAL_COLOR (form field labels) and ECAL_ERR_* (shared
		// validation messages) are defined in the front-end 'common' lang
		// file, not 'acp/ecal' -- reused here rather than duplicated, same
		// strings a board member sees on the normal event form.
		$user->add_lang_ext('ecyaz/eventscalendar', 'common');

		/** @var \ecyaz\eventscalendar\service\event_manager $event_manager */
		$event_manager = $phpbb_container->get('ecyaz.eventscalendar.event_manager');

		$table_prefix = $phpbb_container->getParameter('core.table_prefix');
		$events_table = $table_prefix . 'ecal_events';

		$form_key = 'ecyaz_ecal';
		add_form_key($form_key);

		$action   = $request->variable('action', '');
		$event_id = $request->variable('event_id', 0);

		if ($action === 'delete' && $event_id > 0)
		{
			$this->handle_special_delete($event_manager, $user, $event_id);
		}

		$errors = [];

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$data = $this->collect_special_form_data($config, $request, $errors);

			if (empty($errors))
			{
				if ($event_id > 0)
				{
					// Reject an event_id that does not identify an existing
					// special date -- e.g. a tampered hidden field pointing at
					// an ordinary user event -- rather than silently letting
					// update() rewrite its title/date. event_type itself is
					// immutable (update() never touches that column, see
					// event_manager.php), so this check is the only guard
					// against this page editing the wrong row.
					$existing = $event_manager->get($event_id);

					if ($existing === null || (int) $existing['event_type'] !== \ecyaz\eventscalendar\service\event_manager::EVENT_TYPE_SPECIAL)
					{
						trigger_error('ECAL_EVENT_NOT_FOUND', E_USER_ERROR);
					}

					$event_manager->update($event_id, $data);
				}
				else
				{
					$data['poster_id']  = (int) $user->data['user_id'];
					$data['event_type'] = \ecyaz\eventscalendar\service\event_manager::EVENT_TYPE_SPECIAL;
					$event_manager->create($data);
				}

				trigger_error($user->lang('ACP_ECAL_SPECIAL_SAVED') . adm_back_link($this->u_action));
			}
		}

		$edit_event = null;

		if ($action === 'edit' && $event_id > 0)
		{
			$edit_event = $event_manager->get($event_id);

			if ($edit_event === null || (int) $edit_event['event_type'] !== \ecyaz\eventscalendar\service\event_manager::EVENT_TYPE_SPECIAL)
			{
				$edit_event = null;
				$event_id   = 0;
			}
		}
		else
		{
			$event_id = 0;
		}

		$this->assign_special_form_vars($config, $template, $user, $request, $errors, $edit_event, $event_id);
		$this->assign_special_list_rows($config, $db, $template, $events_table);

		$template->assign_vars([
			'U_ACTION' => $this->u_action,
		]);
	}

	/**
	 * confirm_box() itself both exits (redirect() after a successful delete
	 * is unreachable-by-design) and re-renders/exits on the "show the
	 * confirm page" branch -- see controller/event.php::delete()'s class
	 * docblock for the same core function's documented behaviour, mirrored
	 * here for the ACP context (confirm_box() detects
	 * IN_ADMIN + session_admin itself and calls adm_page_header() instead of
	 * the front-end page_header(), so no ACP-specific branching is needed
	 * here).
	 */
	protected function handle_special_delete(\ecyaz\eventscalendar\service\event_manager $event_manager, $user, int $event_id): void
	{
		$event = $event_manager->get($event_id);

		if ($event === null || (int) $event['event_type'] !== \ecyaz\eventscalendar\service\event_manager::EVENT_TYPE_SPECIAL)
		{
			trigger_error('ECAL_EVENT_NOT_FOUND', E_USER_ERROR);
		}

		if (confirm_box(true))
		{
			$event_manager->delete($event_id);

			trigger_error($user->lang('ACP_ECAL_SPECIAL_DELETED') . adm_back_link($this->u_action));
		}

		// title comes straight from ecal_events (escaped-at-input via
		// $request->variable(..., true) on the original ACP save, see
		// collect_special_form_data()) -- safe to interpolate into the
		// confirm message the same way controller/event.php::delete() does
		// for ordinary events.
		confirm_box(
			false,
			$user->lang('ACP_ECAL_SPECIAL_DELETE_CONFIRM', $event['title']),
			build_hidden_fields(['action' => 'delete', 'event_id' => $event_id])
		);
	}

	/**
	 * Mirrors controller/event.php::parse_date_only()'s round-trip check
	 * exactly ($end_of_day is never needed here -- see special_mode()'s
	 * docblock, a special date is always exactly one board-local calendar
	 * day) -- duplicated rather than reused because that method lives on a
	 * different class (controller/event.php's \ecyaz\eventscalendar\
	 * controller\event, never constructed here: ACP modules are instantiated
	 * bare by p_master, so there is no DI path to reach that controller
	 * instance from here, see google_mode()'s class docblock for the same
	 * "no constructor DI" constraint).
	 */
	protected function parse_special_date(\DateTimeZone $tz, string $date, array &$errors, string $error_key): ?int
	{
		$date = trim($date);

		if ($date === '')
		{
			$errors[] = $error_key;

			return null;
		}

		$dt = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);

		if (!$dt || $dt->format('Y-m-d') !== $date)
		{
			$errors[] = $error_key;

			return null;
		}

		return $dt->setTime(0, 0, 0)->getTimestamp();
	}

	protected function board_timezone($config): \DateTimeZone
	{
		$tz = (string) $config['board_timezone'];

		return new \DateTimeZone($tz !== '' ? $tz : 'UTC');
	}

	/**
	 * Reads and validates the submitted add/edit form into a
	 * create()/update()-ready $data array (spec §12: title required <= 255,
	 * date required/valid, annual toggle, color 0-7), appending any
	 * validation failures (as lang keys) to $errors by reference.
	 */
	protected function collect_special_form_data($config, $request, array &$errors): array
	{
		$title  = $request->variable('title', '', true);
		$date   = $request->variable('event_date', '');
		$annual = $request->variable('annual', false);
		$color  = $request->variable('color', 0);

		if ($title === '')
		{
			$errors[] = 'ECAL_ERR_TITLE_REQUIRED';
		}
		else if (utf8_strlen($title) > 255)
		{
			$errors[] = 'ECAL_ERR_TITLE_TOO_LONG';
		}

		$start_ts = $this->parse_special_date($this->board_timezone($config), $date, $errors, 'ECAL_ERR_START_INVALID');

		$color = ($color >= 0 && $color < self::SPECIAL_COLOR_COUNT) ? $color : 0;

		return [
			'title'       => $title,
			'description' => '',
			'start_ts'    => $start_ts ?? 0,
			// Single board-local calendar day, always (see special_mode()'s
			// docblock) -- start-of-day + 86399, the same "inclusive of its
			// whole day" end-of-day convention parse_date_only() uses
			// elsewhere in this codebase.
			'end_ts'      => ($start_ts !== null) ? ($start_ts + 86399) : 0,
			'all_day'     => true,
			'recur_type'  => $annual ? self::SPECIAL_RECUR_ANNUAL : self::SPECIAL_RECUR_NONE,
			'recur_until' => 0,
			'color'       => $color,
		];
	}

	/**
	 * Prefill values for the add/edit form: after a failed validation POST,
	 * echoes back exactly what was submitted; otherwise reflects $edit_event
	 * (edit) or blank defaults (add) -- mirrors controller/event.php::
	 * form_prefill()'s same three-way shape.
	 */
	protected function special_form_prefill($config, $request, ?array $edit_event): array
	{
		if ($request->is_set_post('submit'))
		{
			return [
				'title'      => $request->variable('title', '', true),
				'event_date' => $request->variable('event_date', ''),
				'annual'     => $request->variable('annual', false),
				'color'      => $request->variable('color', 0),
			];
		}

		if ($edit_event !== null)
		{
			$tz = $this->board_timezone($config);

			return [
				'title'      => $edit_event['title'],
				'event_date' => (new \DateTimeImmutable('@' . $edit_event['start_ts']))->setTimezone($tz)->format('Y-m-d'),
				'annual'     => ((int) $edit_event['recur_type'] === self::SPECIAL_RECUR_ANNUAL),
				'color'      => $edit_event['color'],
			];
		}

		return [
			'title'      => '',
			'event_date' => '',
			'annual'     => false,
			'color'      => 0,
		];
	}

	protected function assign_special_form_vars($config, $template, $user, $request, array $errors, ?array $edit_event, int $event_id): void
	{
		$prefill = $this->special_form_prefill($config, $request, $edit_event);

		$error_text = '';

		foreach ($errors as $key)
		{
			$error_text .= ($error_text !== '' ? '<br />' : '') . $user->lang($key);
		}

		for ($c = 0; $c < self::SPECIAL_COLOR_COUNT; $c++)
		{
			$template->assign_block_vars('color_options', [
				'VALUE'      => $c,
				'LABEL'      => $user->lang('ECAL_COLOR_' . $c),
				'S_SELECTED' => ($c === (int) $prefill['color']),
			]);
		}

		$template->assign_vars([
			'ERROR'      => $error_text,
			'S_EDIT'     => ($event_id > 0),
			'EVENT_ID'   => $event_id,
			'TITLE'      => $prefill['title'],
			'EVENT_DATE' => $prefill['event_date'],
			'S_ANNUAL'   => (bool) $prefill['annual'],
		]);
	}

	/**
	 * Fixed 8-colour hex palette, kept in sync BY HAND with
	 * styles/prosilver/theme/eventscalendar.css's .ecal-c0..ecal-c7
	 * rules -- the ACP does not load the front-end theme stylesheet, so the
	 * list's colour swatch is rendered via an inline style instead (see
	 * assign_special_list_rows()).
	 */
	const COLOR_HEX = [
		0 => '#1A5FB4',
		1 => '#2E7D32',
		2 => '#B91C1C',
		3 => '#6A1B9A',
		4 => '#8A5A00',
		5 => '#00695C',
		6 => '#AD1457',
		7 => '#37474F',
	];

	protected function assign_special_list_rows($config, $db, $template, string $events_table): void
	{
		$tz = $this->board_timezone($config);

		$sql = 'SELECT event_id, title, start_ts, recur_type, color
			FROM ' . $events_table . '
			WHERE event_type = ' . \ecyaz\eventscalendar\service\event_manager::EVENT_TYPE_SPECIAL . '
			ORDER BY start_ts ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$event_id = (int) $row['event_id'];
			$color    = (int) $row['color'];

			// title: escaped-at-input via $request->variable(..., true) on
			// save (see collect_special_form_data()) -- rendered raw here,
			// same convention as every other event title in this codebase
			// (e.g. controller/calendar.php's TITLE, service/calendar_view.php's
			// chip data).
			$template->assign_block_vars('ecal_special', [
				'EVENT_ID'  => $event_id,
				'TITLE'     => (string) $row['title'],
				'DATE'      => (new \DateTimeImmutable('@' . (int) $row['start_ts']))->setTimezone($tz)->format('Y-m-d'),
				'S_ANNUAL'  => ((int) $row['recur_type'] === self::SPECIAL_RECUR_ANNUAL),
				'COLOR_HEX' => self::COLOR_HEX[$color] ?? self::COLOR_HEX[0],
				'U_EDIT'    => $this->u_action . '&amp;action=edit&amp;event_id=' . $event_id,
				'U_DELETE'  => $this->u_action . '&amp;action=delete&amp;event_id=' . $event_id,
			]);
		}
		$db->sql_freeresult($result);
	}
}
