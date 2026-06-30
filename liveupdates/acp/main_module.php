<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\liveupdates\acp;

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	public function main($id, $mode)
	{
		global $config, $request, $template, $language, $phpbb_log, $user;

		$language->add_lang('info_acp_liveupdates', 'ecyaz/liveupdates');

		$this->tpl_name   = 'acp_liveupdates_settings';
		$this->page_title = 'ACP_LIVEUPDATES_SETTINGS';
		$form_key         = 'ecyaz_liveupdates';
		add_form_key($form_key);

		$postures = ['balanced', 'snappy', 'conservative'];

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
				return;
			}

			$posture = $request->variable('lu_posture', 'balanced');
			if (!in_array($posture, $postures, true))
			{
				$posture = 'balanced';
			}

			$config->set('ecyaz_liveupdates_enabled', (int) $request->variable('lu_enabled', 0));
			$config->set('ecyaz_liveupdates_posture', $posture);
			$config->set('ecyaz_liveupdates_interval_override', abs((int) $request->variable('lu_interval_override', 0)));
			$config->set('ecyaz_liveupdates_guest_enabled', (int) $request->variable('lu_guest_enabled', 0));
			$config->set('ecyaz_liveupdates_guest_interval', max(1, (int) $request->variable('lu_guest_interval', 30)));
			$config->set('ecyaz_liveupdates_min_interval', max(1, (int) $request->variable('lu_min_interval', 3)));
			$config->set('ecyaz_liveupdates_surface_topic', (int) $request->variable('lu_surface_topic', 0));
			$config->set('ecyaz_liveupdates_surface_notify', (int) $request->variable('lu_surface_notify', 0));
			$config->set('ecyaz_liveupdates_surface_index', (int) $request->variable('lu_surface_index', 0));
			$config->set('ecyaz_liveupdates_surface_pm', (int) $request->variable('lu_surface_pm', 0));
			$config->set('ecyaz_liveupdates_surface_online', (int) $request->variable('lu_surface_online', 0));
			$config->set('ecyaz_liveupdates_surface_stats', (int) $request->variable('lu_surface_stats', 0));

			$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_LIVEUPDATES_SETTINGS', time());
			trigger_error($language->lang('LIVEUPDATES_SAVED') . adm_back_link($this->u_action));
		}

		foreach ($postures as $key)
		{
			$template->assign_block_vars('lu_postures', [
				'VALUE'    => $key,
				'NAME'     => $language->lang('LIVEUPDATES_POSTURE_' . strtoupper($key)),
				'SELECTED' => ((string) $config['ecyaz_liveupdates_posture'] === $key),
			]);
		}

		$template->assign_vars([
			'U_ACTION'                => $this->u_action,
			'LU_ENABLED'              => (bool) $config['ecyaz_liveupdates_enabled'],
			'LU_INTERVAL_OVERRIDE'    => (int) $config['ecyaz_liveupdates_interval_override'],
			'LU_GUEST_ENABLED'        => (bool) $config['ecyaz_liveupdates_guest_enabled'],
			'LU_GUEST_INTERVAL'       => (int) $config['ecyaz_liveupdates_guest_interval'],
			'LU_MIN_INTERVAL'         => (int) $config['ecyaz_liveupdates_min_interval'],
			'LU_SURFACE_TOPIC'        => (bool) $config['ecyaz_liveupdates_surface_topic'],
			'LU_SURFACE_NOTIFY'       => (bool) $config['ecyaz_liveupdates_surface_notify'],
			'LU_SURFACE_INDEX'        => (bool) $config['ecyaz_liveupdates_surface_index'],
			'LU_SURFACE_PM'           => (bool) $config['ecyaz_liveupdates_surface_pm'],
			'LU_SURFACE_ONLINE'       => (bool) $config['ecyaz_liveupdates_surface_online'],
			'LU_SURFACE_STATS'        => (bool) $config['ecyaz_liveupdates_surface_stats'],
		]);
	}
}
