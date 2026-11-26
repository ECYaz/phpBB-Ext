<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\migrations;

/**
 * 1.2.0: per credential access to endpoints offered by other extensions.
 * The column is empty for every existing credential, which means no access.
 */
class v_1_2_0 extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\ecyaz\phpbbapihook\migrations\acp_module'];
	}

	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'apihook_keys', 'ext_access');
	}

	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'apihook_keys' => [
					'ext_access' => ['TEXT', ''],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'apihook_keys' => [
					'ext_access',
				],
			],
		];
	}
}
