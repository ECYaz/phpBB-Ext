<?php
/**
 *
 * Topic Viewers. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\topicviewers\acp;

class main_module
{
	/** @var string */
	public $u_action;

	/** @var string */
	public $tpl_name;

	/** @var string */
	public $page_title;

	/**
	 * Render and process the Topic Viewers settings page.
	 *
	 * @param int		$id		Module id
	 * @param string	$mode	Module mode
	 * @return void
	 */
	public function main($id, $mode)
	{
		global $config, $request, $template, $user;

		$user->add_lang_ext('ecyaz/topicviewers', 'acp/topicviewers');

		$this->tpl_name = 'acp_topicviewers';
		$this->page_title = 'ACP_TOPICVIEWERS_SETTINGS';

		$form_key = 'acp_topicviewers';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error($user->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$config->set('topicviewers_enable', $request->variable('topicviewers_enable', 0));
			$config->set('topicviewers_show_names', $request->variable('topicviewers_show_names', 0));

			trigger_error($user->lang('ACP_TOPICVIEWERS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'S_TOPICVIEWERS_ENABLE'		=> (bool) $config['topicviewers_enable'],
			'S_TOPICVIEWERS_SHOW_NAMES'	=> (int) $config['topicviewers_show_names'],
			'U_ACTION'					=> $this->u_action,
		]);
	}
}
