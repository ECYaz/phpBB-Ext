<?php
/**
 *
 * PM Email Default. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\pmemaildefault\migrations;

class m1_pm_email_default extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_data()
	{
		return [
			['custom', [[$this, 'enable_pm_email_for_existing_users']]],
		];
	}

	/**
	 * Force the PM/email notification subscription ON for every existing real user.
	 *
	 * The actual work lives in \ecyaz\pmemaildefault\helper\pm_email, shared with
	 * the ACP module. Migrations have no service container, so the helper is
	 * instantiated directly from the migration's own db and table prefix.
	 */
	public function enable_pm_email_for_existing_users()
	{
		$helper = new \ecyaz\pmemaildefault\helper\pm_email(
			$this->db,
			$this->table_prefix . 'users',
			$this->table_prefix . 'user_notifications'
		);
		$helper->apply_to_existing_users(1);
	}
}
