# Changelog

## 0.2.0
- Added live surfaces: private-message counter, who's-online count, and board statistics (each ACP-toggleable; index-only surfaces gated server-side to their page).

## 0.1.0
- Initial release: adaptive AJAX poller; live topic posts (hybrid append/banner), live notification counter, live index/forum updates; full ACP configuration; permission-filtered, DBAL-only poll endpoint; pure progressive enhancement.
- Session-write note: poll endpoint is counts-only; server-enforced minimum interval (default 3s) keeps session-row updates within normal request rates.
