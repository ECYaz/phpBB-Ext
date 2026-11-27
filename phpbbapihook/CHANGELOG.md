# Changelog

All notable changes to **phpbbAPIhook** are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [1.2.1] (2026-11-26)

Fixes from the validation review of 1.2.0.

### Security
- A topic title sent in a JSON body was stored without HTML escaping and could
  run script for visitors. JSON values now get the same treatment as form
  values (trimmed, escaped, NFC normalised).
- Posting now follows the board settings and the forum permissions of the
  linked user for BBCode, images, flash, smilies, links and signatures, as the
  posting form does.
- A category or a link forum no longer takes topics (`forum_not_postable`).
- A moderator can no longer reply to a topic that waits for approval or was
  soft deleted (`topic_unapproved`).
- Search now asks for the `u_search` permission and respects the search flood
  interval (`search_flood_control`).
- Search results are checked again when they are loaded, so a cached result no
  longer shows a post that was soft deleted or moved to a forum the user
  cannot read.

### Changed
- Titles, subjects, user names, forum names and `content_bbcode` are returned
  as plain text instead of HTML escaped text. What is read from
  `content_bbcode` can be sent back unchanged.
- Rate limited requests (429) no longer count towards the rate limit.

### Added
- API settings in the ACP: API enabled, Require HTTPS, rate window and the
  number of days the audit log is kept.
- A daily cron task deletes old audit log entries (90 days by default).
- The ACP shows the log of requests that came without a valid token.
- New error codes: `forum_not_postable` (403), `topic_unapproved` (403),
  `unsupported_characters_title` (400), `search_query_too_long` (400),
  `search_flood_control` (429).

### Fixed
- An emoji in a topic title or in a credential name ended in an SQL error on
  MySQL.
- A forum listing now includes global announcements made in other forums the
  user can read.
- Credential names and log routes with an ampersand were escaped twice in the
  ACP, once more on every save.
- An expiration date that cannot be read is refused instead of being saved as
  "never expires".
- A request refused for a known credential (expired, disabled, wrong IP, rate
  limit, banned account) is logged under that credential.
- A search with more words than the board allows answered with an HTML page.

## [1.2.0] (2026-11-26)

Other extensions can now offer their own endpoints through the API.

### Added
- `ecyaz.phpbbapihook.gateway` service: another extension calls
  `handle($extension, $action, $fn)` to run its own endpoint behind the same
  credentials, limits, JSON errors and audit log. The request runs as the linked
  user, so the extension's own permissions apply.
- Event `ecyaz.phpbbapihook.collect_endpoints` to announce endpoints. Extension
  endpoints live under `/api/ext/<vendor>/<package>/`; `handle()` refuses
  anything that was not announced.
- Event `ecyaz.phpbbapihook.request_authenticated` to refuse a request, and
  event `ecyaz.phpbbapihook.response_before` to add fields to a success response
  (core endpoints included).
- Per credential setting **Extension endpoints** in the ACP: the extensions a
  credential may call. Empty for every existing credential, which means none.
- `GET /api/extensions` lists the extension endpoints a credential may call.
- `GET /api/me/permissions` now includes `allowed_extensions`.
- New error codes: `extension_not_allowed` (403), `endpoint_not_registered` (404),
  `endpoint_path_mismatch` (404), `method_not_allowed` (405).

### Changed
- The read only flag is now enforced in one place for every request that is not
  GET or HEAD, so no endpoint (core or third party) can forget it.
- A PHP error inside an endpoint is answered with `internal_error` (500) instead
  of an HTML error page.
- The minimum PHP version is now 7.1.3, the same as phpBB 3.3 itself.

### Fixed
- A request now carries the member flags of the linked user (`is_registered`),
  so core and other extensions no longer see the member as a guest. Posts made
  through the API no longer store a guest name next to the member.
- The help text of the User ID field no longer suggests 0 for guests; a
  credential needs an active member account.

### Database
- New column `ext_access` on `phpbb_apihook_keys` (migration `v_1_2_0`).

## [1.1.0] — 2026-07-02

New read endpoints. No schema or config changes; existing boards receive the
endpoints on update. Fully backward-compatible.

### Added
- `GET /api/forums/{id}/topics` — list topics in a forum, sorted by most-recent
  activity. Respects `content_visibility`, the credential's forum allow-list, and
  the linked user's `f_read` permission. Supports `limit`/`offset` pagination
  (default 25, cap 100). Board-wide global announcements (`topic_type = POST_GLOBAL`,
  `forum_id = 0`) are automatically included in every forum's topic listing, matching
  phpBB's viewforum UI. Results are ordered by type (globals/announcements/stickies
  first), then by most-recent activity.
- `GET /api/topics/{id}/posts` — list posts in a topic in chronological order.
  Each post carries rendered `content_html` (HTML) and `content_bbcode`
  (raw BBCode) from the new `content_renderer` service. Pagination as above.
- `GET /api/posts/{id}` — read a single post. Returns the post object, its
  parent topic summary, and its forum. Uses `content_renderer` for both rendered
  forms.
- `GET /api/search` — full-text search. Accepts `q` (keywords), `type`
  (`posts` or `topics`; default `posts`), optional `forum_id` filter, optional
  `author` prefix filter, and pagination. Only searches forums the linked user
  can read and the credential is allowed to access.
- `content_renderer` service: renders a stored post row into `content_html` and
  `content_bbcode`; shared by topics, posts, and search controllers.
- New error codes: `forum_not_found` (404), `post_not_found` (404),
  `search_unavailable` (503), `search_query_too_short` (400).

### Security
- All read endpoints enforce password-protected forums: `GET /api/forums/{id}/topics`,
  `/topics/{id}/posts`, and `/posts/{id}` return `forum_password_required` (403), and
  `/search` excludes password-protected forums from results. The API never exposes content
  from a forum the linked account has not unlocked — consistent with the existing write
  paths and `GET /api/topics/{id}`.
- Search results are strictly scoped to forums the linked user can both read (`f_read`)
  and search (`f_search`) and that the credential's allow-list permits; newly created or
  otherwise unlisted forums are excluded by default (fail-closed).
- All read endpoints apply `content_visibility`, so soft-deleted and unapproved
  topics/posts are hidden from users without the moderator approval permission.

### Fixed
- Pagination is consistent across all list endpoints: an out-of-range `offset` returns an
  empty page rather than the last page, and results carry a stable ordering tiebreaker to
  prevent duplicate/skipped rows across page boundaries.

## [1.0.1] — 2026-06-22

Security hardening from a full audit. No API or schema changes.

### Security
- The linked account's ban status is now enforced: a banned user can no longer
  act through the API (`account_banned`, 403), just as they cannot log in.
- Topic visibility is enforced on read and reply: soft-deleted or unapproved
  topics that a non-moderator may not see are reported as `topic_not_found`
  (404) instead of leaking their metadata or accepting replies.
- The ACP enable/disable toggle now requires a CSRF link hash, closing a
  cross-site request forgery hole on a state-changing GET link.
- Corrupt forum allow-lists now fail closed (deny every forum) instead of
  silently granting access to all forums.
- All admin-rendered, attacker-influenceable values (credential/user names, and
  the request method/route/detail in the audit log) are HTML-escaped in the ACP
  templates, preventing stored XSS. The audit log now records the real request
  path instead of duplicating the action name.

### Tests
- Added functional tests for each of the above (banned user, hidden topic,
  CSRF-protected toggle, fail-closed allow-list, ACP output escaping).

## [1.0.0] — 2026-06-17

Initial release.

### Features
- Secure REST API for phpBB 3.3.x with token authentication
  (`Authorization: Bearer` or `X-API-Key`).
- ACP credential management: create keys (token shown once), bind each key to a
  phpBB user account, set forum allow-lists, IP allow-lists, rate limits,
  expiry dates, read-only and enabled flags, and view a per-credential audit log.
- Endpoints: `POST /api/topics`, `POST /api/topics/{id}/reply`,
  `GET /api/topics/{id}`, `GET /api/forums`, `GET /api/me/permissions`.
- Every action runs with the linked account's phpBB permissions via
  `$auth->acl()`; content is created through phpBB's own `submit_post()`.
- Full request audit logging; HTTPS enforced by default.

### Security
- Enforces phpBB forum/topic lock status (locked forum requires `m_edit` for new
  topics / `m_lock` for replies) and refuses password-protected forums, so the
  API can never post where the linked account could not.
- API tokens are stored only as SHA-256 hashes; CSRF form keys protect all ACP
  write actions.

### Tested
- 17 functional tests (authentication, per-user ACL enforcement, forum-lock
  enforcement, forum/credential restrictions, rate limiting, expiry, ACP page
  load). Passes phpBB CodeSniffer and the Extension Pre-Validator (EPV).

### Not yet implemented (roadmap)
- Attachments, post/topic editing, topic locking and moderation actions,
  webhooks, OAuth2, and SSO — see the README roadmap.
