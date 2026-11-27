<?php
/**
 *
 * phpbbAPIhook. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026 ECYaz
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace ecyaz\phpbbapihook\cron;

/**
 * Deletes old rows of the audit log once a day.
 *
 * Rows inside the rate limit window are always kept, because the rate limit
 * is counted from them.
 */
class prune_log extends \phpbb\cron\task\base
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \ecyaz\phpbbapihook\api\logger */
	protected $logger;

	public function __construct(\phpbb\config\config $config, \ecyaz\phpbbapihook\api\logger $logger)
	{
		$this->config = $config;
		$this->logger = $logger;
	}

	public function run()
	{
		$keep = max((int) $this->config['phpbbapihook_log_days'] * 86400, (int) $this->config['phpbbapihook_rate_window']);

		$this->logger->prune(time() - $keep);
		$this->config->set('phpbbapihook_log_gc_last', time(), false);
	}

	public function is_runnable()
	{
		return (int) $this->config['phpbbapihook_log_days'] > 0;
	}

	public function should_run()
	{
		return (int) $this->config['phpbbapihook_log_gc_last'] < time() - 86400;
	}
}
