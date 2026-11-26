<?php
/**
 *
 * Topic Freeze. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 *
 */

namespace ecyaz\topicfreeze\migrations;

/**
 * Adds the frozen flag to the topics table and the moderator permission that
 * allows freezing and unfreezing topics.
 */
class v_1_0_0 extends \phpbb\db\migration\migration
{
	/**
	 * {@inheritDoc}
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_column_exists($this->table_prefix . 'topics', 'topic_frozen');
	}

	/**
	 * {@inheritDoc}
	 */
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v33x\v332'];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_schema()
	{
		return [
			'add_columns' => [
				$this->table_prefix . 'topics' => [
					'topic_frozen' => ['BOOL', 0],
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function revert_schema()
	{
		return [
			'drop_columns' => [
				$this->table_prefix . 'topics' => ['topic_frozen'],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_data()
	{
		return [
			// Moderator permissions exist both globally and per forum.
			['permission.add', ['m_topicfreeze', true]],
			['permission.add', ['m_topicfreeze', false]],

			['if', [
				['permission.role_exists', ['ROLE_MOD_FULL']],
				['permission.permission_set', ['ROLE_MOD_FULL', 'm_topicfreeze']],
			]],
			['if', [
				['permission.role_exists', ['ROLE_MOD_STANDARD']],
				['permission.permission_set', ['ROLE_MOD_STANDARD', 'm_topicfreeze']],
			]],
		];
	}
}
