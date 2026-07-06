<?php
/**
 * @copyright (c) 2026 ECYaz
 * @license GPL-2.0-only
 */

namespace ecyaz\treasurehunt\manager;

use ecyaz\treasurehunt\repository\badge_repository;

class badge_manager
{
	/** @var badge_repository */
	protected $badge_repository;

	public function __construct(badge_repository $badge_repository)
	{
		$this->badge_repository = $badge_repository;
	}

	/**
	 * Evaluate and award any newly-earned badges for the user after a collect.
	 *
	 * $stats shape (as passed by collect_manager):
	 *   'points'        => int   — user's total points AFTER this collect
	 *   'total_finds'   => int   — total finds AFTER this collect
	 *   'unique_items'  => int   — distinct items found AFTER this collect
	 *   'rarities_owned'=> int[] — DISTINCT rarity values the user has ever found (after this collect)
	 *   'items_total'   => int   — count of ALL enabled items in the catalog
	 *
	 * @param int   $user_id
	 * @param array $stats
	 * @return array Newly-awarded badges: [['name'=>string,'image'=>string], ...]
	 */
	public function evaluate(int $user_id, array $stats): array
	{
		$owned_ids = $this->badge_repository->user_badge_ids($user_id);
		$awarded   = [];

		// --- Points (milestone threshold) badges ---
		foreach ($this->badge_repository->enabled_point_badges() as $badge)
		{
			if (in_array((int) $badge['badge_id'], $owned_ids, true))
			{
				continue;
			}

			if ($stats['points'] >= (int) $badge['condition_value'])
			{
				$this->badge_repository->award($user_id, (int) $badge['badge_id']);
				$awarded[] = ['name' => $badge['badge_name'], 'image' => $badge['badge_image']];
			}
		}

		// --- Feat badges ---
		foreach ($this->badge_repository->enabled_feat_badges() as $badge)
		{
			if (in_array((int) $badge['badge_id'], $owned_ids, true))
			{
				continue;
			}

			if ($this->check_feat((string) $badge['condition_value'], $stats))
			{
				$this->badge_repository->award($user_id, (int) $badge['badge_id']);
				$awarded[] = ['name' => $badge['badge_name'], 'image' => $badge['badge_image']];
			}
		}

		return $awarded;
	}

	/**
	 * Evaluate a single feat condition against the given stats.
	 * Protected so the unit test can subclass and call it directly.
	 *
	 * @param string $feat_key
	 * @param array  $stats
	 * @return bool
	 */
	protected function check_feat(string $feat_key, array $stats): bool
	{
		$rarities_owned = isset($stats['rarities_owned']) ? (array) $stats['rarities_owned'] : [];

		switch ($feat_key)
		{
			case 'first_find':
				return (int) $stats['total_finds'] >= 1;

			case 'first_rare':
				// "first rare" = first find of rarity >= 3 (Rare, Epic, Legendary)
				foreach ($rarities_owned as $r)
				{
					if ((int) $r >= \ecyaz\treasurehunt\ext::RARITY_RARE)
					{
						return true;
					}
				}
				return false;

			case 'first_legendary':
				return in_array(\ecyaz\treasurehunt\ext::RARITY_LEGENDARY, array_map('intval', $rarities_owned), true);

			case 'unique_10':
				return (int) $stats['unique_items'] >= 10;

			case 'unique_50':
				return (int) $stats['unique_items'] >= 50;

			case 'unique_100':
				return (int) $stats['unique_items'] >= 100;

			case 'finds_100':
				return (int) $stats['total_finds'] >= 100;

			case 'finds_500':
				return (int) $stats['total_finds'] >= 500;

			case 'finds_1000':
				return (int) $stats['total_finds'] >= 1000;

			case 'one_each_rarity':
				// Must own a find of every rarity 1..5
				$int_rarities = array_map('intval', $rarities_owned);
				return count(array_intersect([1, 2, 3, 4, 5], $int_rarities)) === 5;

			case 'completionist':
				// Must have found every enabled item
				$items_total = (int) $stats['items_total'];
				return $items_total > 0 && (int) $stats['unique_items'] >= $items_total;

			default:
				return false;
		}
	}
}
