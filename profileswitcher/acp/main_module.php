<?php
/**
*
* @package profileSwitcher
* @copyright (c) 2014 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace ecyaz\profileSwitcher\acp;

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
		global $config, $request, $template, $user;

		$user->add_lang_ext('ecyaz/profileSwitcher', 'acp_profile_side_switcher');

		$this->tpl_name   = 'acp_profile_side_switcher';
		$this->page_title = 'ACP_PROFILE_SIDE_SWITCHER';

		$form_key = 'acp_profile_side_switcher';
		add_form_key($form_key);

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID', E_USER_ERROR);
			}

			$config->set('pss_ribbon_left', $request->variable('pss_ribbon_left', 0));

			trigger_error($user->lang('ACP_PSS_SETTINGS_SAVED') . adm_back_link($this->u_action));
		}

		$template->assign_vars([
			'PSS_RIBBON_LEFT'	=> (int) $config['pss_ribbon_left'],
			'U_ACTION'			=> $this->u_action,
		]);
	}
}
