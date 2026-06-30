# Live Updates for phpBB

Makes a phpBB 3.3 board feel live without a page refresh:

- **Topics** — new replies append automatically when you are at the bottom, or show a "new replies" banner otherwise.
- **Notifications** — the header counter updates itself while you browse.
- **Index / forum** — a banner flags new or updated topics.
- **Private messages** — the header PM counter updates live.
- **Who is online** — the live online count on the board index refreshes automatically (the per-user list refreshes on the next page load).
- **Board statistics** — total posts, topics, members, and newest member on the index update live.

All driven by a single adaptive AJAX poller (one request per interval, backs off when the tab is hidden or idle). Fully configurable in **ACP → Live Updates → Settings** (polling posture, custom interval, guest polling, per-surface toggles for all surfaces, server-enforced minimum interval).

Pure progressive enhancement: with JavaScript off or the extension disabled, the board behaves exactly as stock phpBB. No core files are modified.

## Requirements
- phpBB 3.3.0+ (< 4.0)
- PHP 7.2+

## Install
1. Copy to `ext/ecyaz/liveupdates/`.
2. ACP → Customise → Manage extensions → enable **Live Updates**.

## License
GPL-2.0-only.
