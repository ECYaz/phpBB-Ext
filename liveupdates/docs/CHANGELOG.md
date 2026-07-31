# Changelog

## 0.2.1
- Fixed: the stats and who's-online refresh wrote values into `<strong>` elements by position, corrupting the display (and stats injected by other extensions) on boards where any extension adds content to those blocks. The values updated are now wrapped in extension-owned spans and replaced with complete server-rendered localized strings.
- Fixed: the who's-online refresh updated only the total while the registered/hidden/guest breakdown went stale; the whole sentence is now refreshed together, so the numbers always agree.

## 0.2.0
- Added live surfaces: private-message counter, who's-online count, and board statistics (each ACP-toggleable; index-only surfaces gated server-side to their page).

## 0.1.0
- Initial release: adaptive AJAX poller; live topic posts (hybrid append/banner), live notification counter, live index/forum updates; full ACP configuration; permission-filtered, DBAL-only poll endpoint; pure progressive enhancement.
- Session-write note: poll endpoint is counts-only; server-enforced minimum interval (default 3s) keeps session-row updates within normal request rates.
