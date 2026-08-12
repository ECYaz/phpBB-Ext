<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\acp;

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
		global $config, $request, $template, $user, $phpbb_container;

		$user->add_lang_ext('ecyaz/pmemaildefault', 'acp/pmemaildefault');

		$this->tpl_name   = 'acp_pmemaildefault';
		$this->page_title = 'ACP_PMEMAILDEFAULT_SETTINGS';

		$form_key = 'acp_pmemaildefault';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$enabled = $request->variable('pmemaildefault_enabled', 0) ? 1 : 0;

			$config->set('pmemaildefault_enabled', $enabled);

			// Apply the chosen default to every existing member straight away.
			$phpbb_container->get('ecyaz.pmemaildefault.helper')->apply_to_existing_users($enabled);

			trigger_error($user->lang('ACP_PMEMAILDEFAULT_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'PMEMAILDEFAULT_ENABLED'	=> !empty($config['pmemaildefault_enabled']),
			'U_ACTION'					=> $this->u_action,
		]);
	}
}
