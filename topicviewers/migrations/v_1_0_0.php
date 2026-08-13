<?php
/**
 *
 * Topic Viewers. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\topicviewers\migrations;

class v_1_0_0 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return $this->config->offsetExists('topicviewers_enable');
	}

	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_data()
	{
		return [
			['config.add', ['topicviewers_enable', 1]],
			['config.add', ['topicviewers_show_names', 0]],

			// Own category under the Extensions tab → renders as the sidebar section
			// header (depth 1). phpBB only draws a left-hand menu block for a tab when
			// it contains a category that in turn contains a mode, so the mode must
			// live one level below its own category, not directly on the tab.
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TOPICVIEWERS_TITLE',
			]],

			// Settings mode nested under our category → the clickable left-nav link (depth 2).
			['module.add', [
				'acp',
				'ACP_TOPICVIEWERS_TITLE',
				[
					'module_basename'	=> '\ecyaz\topicviewers\acp\main_module',
					'module_langname'	=> 'ACP_TOPICVIEWERS_SETTINGS',
					'module_mode'		=> 'settings',
					'module_auth'		=> 'ext_ecyaz/topicviewers && acl_a_board',
				],
			]],
		];
	}

	public function revert_data()
	{
		return [
			['module.remove', [
				'acp',
				'ACP_TOPICVIEWERS_TITLE',
				'ACP_TOPICVIEWERS_SETTINGS',
			]],
			['module.remove', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_TOPICVIEWERS_TITLE',
			]],
			['config.remove', ['topicviewers_enable']],
			['config.remove', ['topicviewers_show_names']],
		];
	}
}
