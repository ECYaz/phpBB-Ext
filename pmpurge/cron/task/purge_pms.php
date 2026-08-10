<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\cron\task;

/**
 * Purges one batch of inactive members' private messages per run.
 *
 * Keeping the batch small is deliberate: cron in phpBB runs inside a normal
 * page request, so a run has to finish quickly. The purger's cursor makes sure
 * successive runs walk the whole member list instead of retrying one window.
 */
class purge_pms extends \phpbb\cron\task\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \ecyaz\pmpurge\purger */
	protected $purger;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\config\config   $config Config object
	 * @param \ecyaz\pmpurge\purger  $purger Purger service
	 */
	public function __construct(\phpbb\config\config $config, \ecyaz\pmpurge\purger $purger)
	{
		$this->config = $config;
		$this->purger = $purger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function run()
	{
		$this->purger->purge();

		$this->config->set('ecyaz_pmpurge_last_gc', time(), false);
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_runnable()
	{
		return $this->purger->is_enabled();
	}

	/**
	 * {@inheritdoc}
	 */
	public function should_run()
	{
		return $this->config['ecyaz_pmpurge_last_gc'] < time() - (int) $this->config['ecyaz_pmpurge_gc'];
	}
}
