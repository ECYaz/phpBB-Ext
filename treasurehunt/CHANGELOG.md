# Changelog

## 0.1.0

- Initial release: gamified collect-a-thon for phpBB 3.3.x.
- Server-authoritative spawn engine with weighted-random item drops and per-member cooldown.
- Three admin-selectable spawn styles: modal (default), hidden-icon, and hybrid.
- AJAX collect controller with CSRF and one-time token validation; silent failure on
  expired, replayed, or faked collects.
- Leaderboard with Top Collectors (by points), Rarest Finds (Epic/Legendary feed), and
  Your Stats (rank, points, collection progress).
- Badge engine: point-milestone tiers and all 11 collection feat conditions.
- Badge rendering on the full member profile and in the post-bit (admin-capped).
- Full ACP: settings, item catalog CRUD, badge catalog CRUD.
- Seed data: one item per rarity tier, sample badge per condition type; playable immediately.
- Full i18n: all user-facing strings in `language/en/`; JS strings via bootstrap config.
- Clean uninstall: all 6 tables dropped, config keys removed, permission reverted.
- No phpBB core files modified.
