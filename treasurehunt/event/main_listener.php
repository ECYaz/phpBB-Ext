<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var \phpbb\config\config */
	protected $config;
	/** @var \phpbb\language\language */
	protected $language;
	/** @var \phpbb\template\template */
	protected $template;
	/** @var \phpbb\controller\helper */
	protected $helper;
	/** @var \phpbb\user */
	protected $user;
	/** @var \phpbb\request\request_interface */
	protected $request;
	/** @var \phpbb\path_helper */
	protected $path_helper;
	/** @var \ecyaz\treasurehunt\service\spawn_manager */
	protected $spawn_manager;

	/** @var \ecyaz\treasurehunt\repository\badge_repository */
	protected $badge_repository;

	/** @var \ecyaz\treasurehunt\service\stats_repository */
	protected $stats_repository;

	/** @var array Badge data keyed by user_id, populated in postbit_load_badges(). */
	protected $postbit_badge_cache = [];

	public function __construct(
		\phpbb\config\config $config,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\controller\helper $helper,
		\phpbb\user $user,
		\phpbb\request\request_interface $request,
		\phpbb\path_helper $path_helper,
		\ecyaz\treasurehunt\service\spawn_manager $spawn_manager,
		\ecyaz\treasurehunt\repository\badge_repository $badge_repository,
		\ecyaz\treasurehunt\service\stats_repository $stats_repository
	)
	{
		$this->config            = $config;
		$this->language          = $language;
		$this->template          = $template;
		$this->helper            = $helper;
		$this->user              = $user;
		$this->request           = $request;
		$this->path_helper       = $path_helper;
		$this->spawn_manager     = $spawn_manager;
		$this->badge_repository  = $badge_repository;
		$this->stats_repository  = $stats_repository;
	}

	static public function getSubscribedEvents()
	{
		return [
			'core.user_setup'        => 'load_language_on_setup',
			'core.permissions'       => 'add_permissions',
			'core.page_header'       => 'inject_nav_url',
			'core.page_header_after' => 'inject_bootstrap',
			'core.memberlist_modify_view_profile_template_vars' => 'profile_view',
			'core.viewtopic_modify_post_data' => 'postbit_load_badges',
			'core.viewtopic_modify_post_row'  => 'postbit_inject_post_row',
		];
	}

	public function load_language_on_setup($event)
	{
		$lang_set_ext   = $event['lang_set_ext'];
		$lang_set_ext[] = [
			'ext_name' => 'ecyaz/treasurehunt',
			'lang_set' => 'common',
		];
		$event['lang_set_ext'] = $lang_set_ext;
	}

	public function add_permissions($event)
	{
		$permissions                        = $event['permissions'];
		$permissions['u_treasurehunt_play'] = [
			'lang' => 'ACL_U_TREASUREHUNT_PLAY',
			'cat'  => 'misc',
		];
		$event['permissions'] = $permissions;
	}

	/**
	 * Assign the leaderboard URL to the global template so the nav link
	 * event file can render it on every page.
	 *
	 * @param \phpbb\event\data $event
	 */
	public function inject_nav_url($event)
	{
		$this->template->assign_var(
			'U_TREASUREHUNT_LEADERBOARD',
			$this->helper->route('ecyaz_treasurehunt_leaderboard', [], false)
		);
	}

	public function inject_bootstrap($event)
	{
		if (!(int) $this->config['treasurehunt_enable'])
		{
			$this->template->assign_var('S_TREASUREHUNT_ENABLED', false);
			return;
		}

		$forum_id = (int) $this->request->variable('f', 0);
		$user_id  = (int) $this->user->data['user_id'];

		$bootstrap = [
			'enabled'    => true,
			'style'      => (string) $this->config['treasurehunt_spawn_style'],
			'collectUrl' => $this->helper->route('ecyaz_treasurehunt_collect', [], false),
			'csrf'       => generate_link_hash('treasurehunt_collect'),
			'expiry'     => (int) $this->config['treasurehunt_spawn_expiry'],
			'strings'    => [
				'found'         => $this->language->lang('TREASUREHUNT_FOUND'),
				'collect'       => $this->language->lang('TREASUREHUNT_COLLECT'),
				'points'        => $this->language->lang('TREASUREHUNT_POINTS_SUFFIX'),
				'badgeUnlocked' => $this->language->lang('TREASUREHUNT_BADGE_UNLOCKED'),
			],
		];

		// Server-authoritative spawn decision. Only members (not guests) roll;
		// maybe_spawn itself re-checks permission / scope / cooldown.
		// Guard: a spawn engine failure must NEVER break page rendering.
		$spawn = null;

		if ($user_id != ANONYMOUS)
		{
			try
			{
				$spawn = $this->spawn_manager->maybe_spawn($user_id, $forum_id);
			}
			catch (\Throwable $e)
			{
				// Absorb silently — spawn failure must not break the board
			}
		}

		if ($spawn !== null)
		{
			$item               = $spawn['item'];
			$bootstrap['spawn'] = [
				'itemName'  => (string) $item['item_name'],
				'itemImage' => $this->image_url((string) $item['item_image']),
				'points'    => (int) $item['points'],
				'rarity'    => (int) $item['rarity'],
				'token'     => $spawn['token'],
			];
		}

		$this->template->assign_vars([
			'S_TREASUREHUNT_ENABLED' => true,
			'TREASUREHUNT_CONFIG'    => json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
		]);
	}

	/**
	 * Inject Treasure Hunt data into a member's profile view.
	 *
	 * Event: core.memberlist_modify_view_profile_template_vars (3.2.6-RC1)
	 *
	 * @param \phpbb\event\data $event  Keys: user_id (int), template_ary (array)
	 */
	public function profile_view($event)
	{
		if (empty($this->config['treasurehunt_enable']))
		{
			return;
		}

		$user_id = (int) $event['user_id'];
		if ($user_id <= 0)
		{
			return;
		}

		// user_rank() returns [], rank, points, unique_items, items_total or [] if no row
		$rank_data = $this->stats_repository->user_rank($user_id);

		// Users who have never collected anything have no aggregate row; show nothing
		$points       = isset($rank_data['points'])      ? (int) $rank_data['points']      : 0;
		$unique_items = isset($rank_data['unique_items']) ? (int) $rank_data['unique_items'] : 0;
		$rank         = isset($rank_data['rank'])         ? (int) $rank_data['rank']         : 0;
		$items_total  = isset($rank_data['items_total'])  ? (int) $rank_data['items_total']  : 0;

		if ($points <= 0 && $unique_items <= 0)
		{
			// No participation yet — do not render the block
			return;
		}

		// Badges for this user
		$badges = $this->badge_repository->user_badges($user_id);
		foreach ($badges as $badge)
		{
			$this->template->assign_block_vars('th_profile_badges', [
				'NAME'  => (string) $badge['badge_name'],
				'IMAGE' => $this->image_url((string) $badge['badge_image']),
			]);
		}

		$template_ary = $event['template_ary'];
		// Cap displayed progress so deleted-catalog items don't push unique_items > items_total
		$display_found    = ($items_total > 0) ? min($unique_items, $items_total) : 0;
		$progress_percent = ($items_total > 0) ? (int) min(100, round(($display_found / $items_total) * 100)) : 0;

		$template_ary['TREASUREHUNT_PROFILE_POINTS']    = $points;
		$template_ary['TREASUREHUNT_PROFILE_RANK']      = $rank;
		$template_ary['TREASUREHUNT_PROGRESS_FOUND']    = $display_found;
		$template_ary['TREASUREHUNT_PROGRESS_TOTAL']    = $items_total;
		$template_ary['TREASUREHUNT_PROGRESS_PERCENT']  = $progress_percent;
		$template_ary['S_TREASUREHUNT_PROFILE']         = true;
		$event['template_ary'] = $template_ary;
	}

	/**
	 * Batch-load badge data for all posters on the current topic page.
	 * Stores results in $this->postbit_badge_cache keyed by user_id.
	 *
	 * Event: core.viewtopic_modify_post_data (3.1.0-RC3)
	 * @var array user_cache  Keyed by poster user_id; populated by viewtopic.php.
	 */
	public function postbit_load_badges($event)
	{
		if (empty($this->config['treasurehunt_enable']))
		{
			return;
		}

		$cap      = max(1, (int) $this->config['treasurehunt_postbit_cap']);
		$user_ids = array_keys((array) $event['user_cache']);

		if (empty($user_ids))
		{
			return;
		}

		$all = $this->badge_repository->user_badges_bulk($user_ids);

		// Apply cap per user.
		foreach ($all as $uid => $badges)
		{
			$this->postbit_badge_cache[$uid] = array_slice($badges, 0, $cap);
		}
	}

	/**
	 * Inject pre-loaded badge HTML into the post_row template data.
	 *
	 * Event: core.viewtopic_modify_post_row (3.1.0-a1)
	 * @var array post_row   Template variables for this post.
	 * @var int   poster_id
	 */
	public function postbit_inject_post_row($event)
	{
		if (empty($this->config['treasurehunt_enable']))
		{
			return;
		}

		$poster_id = (int) $event['poster_id'];
		$badges    = isset($this->postbit_badge_cache[$poster_id])
			? $this->postbit_badge_cache[$poster_id]
			: [];

		if (empty($badges))
		{
			return;
		}

		// Pre-render as an HTML fragment; using | raw in the Twig template event.
		// This is the safest postbit pattern in phpBB 3.3 — avoids nested block
		// timing issues inside the post loop.
		$html = '';
		foreach ($badges as $badge)
		{
			$img  = utf8_htmlspecialchars($this->image_url((string) $badge['badge_image']));
			$name = utf8_htmlspecialchars($badge['badge_name']);
			$html .= '<img src="' . $img . '" alt="' . $name . '" title="' . $name . '" class="th-badge-icon" />';
		}

		$post_row                           = $event['post_row'];
		$post_row['TREASUREHUNT_POSTBIT']   = $html;
		$post_row['S_TREASUREHUNT_POSTBIT'] = true;
		$event['post_row']                  = $post_row;
	}

	/**
	 * Resolve an item image to a browser URL.
	 *
	 * Absolute URLs (containing "://") pass through unchanged. A plain filename
	 * or relative path is prefixed with the extension's shipped image directory
	 * relative to the board web root.
	 *
	 * @param string $image Stored item_image value
	 * @return string Browser-usable URL
	 */
	protected function image_url(string $image): string
	{
		if ($image === '' || strpos($image, '://') !== false)
		{
			return $image;
		}

		return $this->path_helper->get_web_root_path()
			. 'ext/ecyaz/treasurehunt/styles/all/theme/images/treasurehunt/'
			. ltrim($image, '/');
	}
}
