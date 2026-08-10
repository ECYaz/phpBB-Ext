<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\pmpurge\console\command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Runs the purge from the command line.
 *
 * The first clear-out of a board that has been collecting messages for twenty
 * years is the wrong thing to run through a browser: this is the entry point
 * that has no request timeout over it.
 */
class purge extends \phpbb\console\command\command
{
	/** @var \phpbb\language\language */
	protected $language;

	/** @var \ecyaz\pmpurge\purger */
	protected $purger;

	/**
	 * Constructor.
	 *
	 * @param \phpbb\user               $user     User object
	 * @param \phpbb\language\language  $language Language object
	 * @param \ecyaz\pmpurge\purger     $purger   Purger service
	 */
	public function __construct(\phpbb\user $user, \phpbb\language\language $language, \ecyaz\pmpurge\purger $purger)
	{
		$this->language = $language;
		$this->purger   = $purger;

		$this->language->add_lang('cli', 'ecyaz/pmpurge');

		parent::__construct($user);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		$this
			->setName('pmpurge:run')
			->setDescription($this->language->lang('CLI_DESCRIPTION_PMPURGE_RUN'))
			->addOption('dry-run', null, InputOption::VALUE_NONE, $this->language->lang('CLI_DESCRIPTION_PMPURGE_DRY_RUN'))
			->addOption('all', 'a', InputOption::VALUE_NONE, $this->language->lang('CLI_DESCRIPTION_PMPURGE_ALL'))
			->addOption('limit', null, InputOption::VALUE_REQUIRED, $this->language->lang('CLI_DESCRIPTION_PMPURGE_LIMIT'), 0)
		;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$io = new SymfonyStyle($input, $output);

		if (!$this->purger->is_configured())
		{
			$io->error($this->language->lang('CLI_PMPURGE_NOT_CONFIGURED'));

			return 1;
		}

		$dry_run = (bool) $input->getOption('dry-run') || $this->purger->is_dry_run();
		$limit   = (int) $input->getOption('limit');

		$totals = ['users' => 0, 'rows' => 0, 'messages' => 0];

		do
		{
			$stats = $this->purger->purge($limit, $dry_run, false);

			foreach (array_keys($totals) as $key)
			{
				$totals[$key] += $stats[$key];
			}

			if ($input->getOption('all') && !$stats['finished'])
			{
				$io->writeln($this->language->lang('CLI_PMPURGE_PROGRESS', $totals['users'], $totals['rows']), OutputInterface::VERBOSITY_VERBOSE);
			}
		}
		while ($input->getOption('all') && !$stats['finished']);

		if ($dry_run)
		{
			$io->note($this->language->lang('CLI_PMPURGE_DRY_RUN_NOTICE'));
			$io->success($this->language->lang('CLI_PMPURGE_SUMMARY_DRY', $totals['users'], $totals['rows']));
		}
		else
		{
			$io->success($this->language->lang('CLI_PMPURGE_SUMMARY', $totals['users'], $totals['rows'], $totals['messages']));
		}

		return 0;
	}
}
