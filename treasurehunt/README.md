# Treasure Hunt

A gamified collect-a-thon for phpBB 3.3.x. Treasures spawn as members browse and post;
collect them for points, climb the leaderboard, and earn badges. No core files modified.

---

## Features

- **Server-authoritative spawn engine** — drop odds, item selection, and collect validation
  all happen server-side; clients receive only what the server authorised. Faked collects
  gain zero points.
- **Weighted-random item catalog** — admin-defined items with rarity tiers (Common → Legendary),
  point values, and drop weights; rare drops are the hook.
- **Three spawn styles** — **Modal** (default, treasure pops centre-screen), **Icon** (hidden
  treasure to spot and click), **Hybrid** (spot the icon, modal reveals the reward). Admin-
  selectable via ACP.
- **Per-member cooldown** — rate-limiting prevents farming; the cooldown duration is ACP-tunable.
- **Leaderboard** — public page with Top Collectors (ranked by points), Rarest Finds (recent
  Epic/Legendary drops), and Your Stats (rank, points, collection progress).
- **Badge engine** — two badge types: point-milestone tiers (points ≥ threshold) and 11
  collection feats (first find, first rare, first legendary, unique-10/50/100, finds-100/500/1000,
  one-of-every-rarity, completionist).
- **Profile & postbit rendering** — badges (and points/rank) displayed on member profiles and
  in the compact postbit beside every post; admin-configurable postbit cap.
- **ACP module** — full settings page, item catalog CRUD, badge catalog CRUD; ships with seed
  data (one item per rarity + example badges) so the extension is playable on first enable.
- **Forum scope & group control** — spawn can be scoped to specific forums and groups.
- **No core edits** — zero modifications to phpBB core files.

---

## Requirements

- phpBB 3.3.x (tested on 3.3.17)
- PHP 7.2 or later

---

## Installation

1. Download [`treasurehunt.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/treasurehunt.zip).
2. Unzip it into your board's `ext/` directory — the archive already contains the folder
   structure, so the path becomes:
   ```
   phpBB/ext/ecyaz/treasurehunt/
   ```
3. In the phpBB ACP go to **Customise → Extension Manager**.
4. Find **Treasure Hunt** and click **Enable**.
5. Migrations run automatically: six tables are created, config keys registered, the play
   permission added, and seed items/badges inserted. The extension is immediately playable.

---

## ACP Settings

Go to **ACP → Extensions → Treasure Hunt → Settings**.

| Setting | Default | Description |
|---|---|---|
| **Enable Treasure Hunt** | Yes | Master switch. Off = no spawns, no collects. |
| **Drop rate** | 50 (= 5%) | Chance per eligible page load of spawning a treasure, in tenths of a percent (1–1000). |
| **Cooldown** | 300 s | Seconds between spawns for the same member. |
| **Spawn style** | Modal | How the treasure is presented: `modal`, `icon`, or `hybrid`. |
| **Spawn expiry** | 60 s | Seconds before an uncollected spawn token expires. |
| **Forum scope** | All | Restrict spawns to specific forums (comma-joined IDs), or `all`. |
| **Play groups** | All | Restrict the play permission to specific groups, or `all`. |
| **Postbit badge cap** | 3 | Max badges shown beside each post. |

---

## Third-party

*(List any bundled vendor assets here with name, URL, and license — e.g. item images.)*
If none bundled beyond the seed images authored by ECYaz, this section may be omitted.

---

## License

GNU General Public License, version 2 (GPL-2.0-only). See `license.txt`.
