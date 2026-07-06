<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\controller;

use Symfony\Component\HttpFoundation\Response;

class leaderboard
{
	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\pagination */
	protected $pagination;

	/** @var \ecyaz\treasurehunt\service\stats_repository */
	protected $stats_repo;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/** Number of entries per page for the top-collectors view. */
	const TOP_PER_PAGE = 20;

	/**
	 * @param \phpbb\controller\helper                      $helper
	 * @param \phpbb\language\language                      $language
	 * @param \phpbb\template\template                      $template
	 * @param \phpbb\user                                   $user
	 * @param \phpbb\request\request                        $request
	 * @param \phpbb\pagination                             $pagination
	 * @param \ecyaz\treasurehunt\service\stats_repository  $stats_repo
	 * @param string                                        $root_path
	 * @param string                                        $php_ext
	 */
	public function __construct(
		\phpbb\controller\helper $helper,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\phpbb\request\request $request,
		\phpbb\pagination $pagination,
		\ecyaz\treasurehunt\service\stats_repository $stats_repo,
		$root_path,
		$php_ext
	)
	{
		$this->helper     = $helper;
		$this->language   = $language;
		$this->template   = $template;
		$this->user       = $user;
		$this->request    = $request;
		$this->pagination = $pagination;
		$this->stats_repo = $stats_repo;
		$this->root_path  = $root_path;
		$this->php_ext    = $php_ext;
	}

	/**
	 * Main leaderboard page: three views — top, rare, me.
	 *
	 * @param  string   $view  One of: top, rare, me. Default 'top' via routing.yml.
	 * @return Response
	 */
	public function handle(string $view): Response
	{
		if (!in_array($view, ['top', 'rare', 'me'], true))
		{
			throw new \phpbb\exception\http_exception(404, 'PAGE_NOT_FOUND');
		}

		$this->language->add_lang('common', 'ecyaz/treasurehunt');

		// me view requires login; redirect anonymous visitors to UCP login.
		if ($view === 'me' && $this->user->data['user_id'] == ANONYMOUS)
		{
			redirect(append_sid($this->root_path . 'ucp.' . $this->php_ext, 'mode=login'));
		}

		// Tab URLs — is_amp=false so they are usable as href values without &amp;
		$this->template->assign_vars([
			'VIEW'        => $view,
			'U_VIEW_TOP'  => $this->helper->route('ecyaz_treasurehunt_leaderboard', ['view' => 'top'], false),
			'U_VIEW_RARE' => $this->helper->route('ecyaz_treasurehunt_leaderboard', ['view' => 'rare'], false),
			'U_VIEW_ME'   => $this->helper->route('ecyaz_treasurehunt_leaderboard', ['view' => 'me'], false),
		]);

		switch ($view)
		{
			case 'top':
				$this->render_top();
			break;

			case 'rare':
				$this->render_rare();
			break;

			case 'me':
				$this->render_me();
			break;
		}

		return $this->helper->render(
			'treasurehunt_leaderboard.html',
			$this->language->lang('TREASUREHUNT_LEADERBOARD')
		);
	}

	/**
	 * Populate top-collectors block vars + pagination.
	 */
	protected function render_top()
	{
		$start = max(0, (int) $this->request->variable('start', 0));
		$total = $this->stats_repo->total_players();
		$rows  = $this->stats_repo->top_collectors($start, self::TOP_PER_PAGE);

		if (!function_exists('phpbb_get_user_avatar'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}

		$rank = $start + 1;
		foreach ($rows as $row)
		{
			$this->template->assign_block_vars('top_rows', [
				'RANK'           => $rank++,
				'USERNAME'       => $row['username'],
				'USER_COLOUR'    => $row['user_colour'],
				'AVATAR'         => phpbb_get_user_avatar($row),
				'POINTS'         => (int) $row['points'],
				'TOTAL_FINDS'    => (int) $row['total_finds'],
				'UNIQUE_ITEMS'   => (int) $row['unique_items'],
				'TOP_BADGE_NAME' => (string) $row['top_badge_name'],
				'TOP_BADGE_IMG'  => (string) $row['top_badge_image'],
			]);
		}

		$pagination_url = $this->helper->route(
			'ecyaz_treasurehunt_leaderboard',
			['view' => 'top'],
			false
		);
		$this->pagination->generate_template_pagination(
			$pagination_url,
			'pagination',
			'start',
			$total,
			self::TOP_PER_PAGE,
			$start
		);
		$this->template->assign_var('TOTAL_PLAYERS', $total);
	}

	/**
	 * Populate rarest-finds block vars.
	 */
	protected function render_rare()
	{
		$rows = $this->stats_repo->rarest_finds(50);
		foreach ($rows as $row)
		{
			$this->template->assign_block_vars('rare_rows', [
				'ITEM_NAME'    => $row['item_name'],
				'ITEM_IMAGE'   => $row['item_image'],
				'RARITY'       => (int) $row['rarity'],
				'RARITY_LABEL' => $this->language->lang('TREASUREHUNT_RARITY_' . (int) $row['rarity']),
				'POINTS'       => (int) $row['points'],
				'USERNAME'     => $row['username'],
				'USER_COLOUR'  => $row['user_colour'],
				'FOUND_AT'     => $this->user->format_date((int) $row['found_at']),
			]);
		}
	}

	/**
	 * Populate me-stats template vars.
	 */
	protected function render_me()
	{
		$stats = $this->stats_repo->user_rank((int) $this->user->data['user_id']);

		if (empty($stats))
		{
			$this->template->assign_var('ME_NO_DATA', true);
			return;
		}

		if (!function_exists('phpbb_get_user_avatar'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}

		$progress = ($stats['items_total'] > 0)
			? (int) round(($stats['unique_items'] / $stats['items_total']) * 100)
			: 0;

		$this->template->assign_vars([
			'ME_RANK'         => (int) $stats['rank'],
			'ME_POINTS'       => (int) $stats['points'],
			'ME_TOTAL_FINDS'  => (int) $stats['total_finds'],
			'ME_UNIQUE_ITEMS' => (int) $stats['unique_items'],
			'ME_ITEMS_TOTAL'  => (int) $stats['items_total'],
			'ME_PROGRESS'     => $progress,
			'ME_AVATAR'       => phpbb_get_user_avatar($stats),
		]);
	}
}
