# phpBB API Hook

A secure, permission-controlled REST API extension for phpBB 3.3.x. It lets external applications create topics, post replies, and read forum data via HTTP requests authenticated with API keys, while never bypassing the board's own ACL: every request runs with the permissions of the phpBB user account linked to the credential.

---

## Features

- **API key authentication** — `Authorization: Bearer` or `X-API-Key` header.
- **Per-credential user binding** — each key acts as a specific phpBB user; all phpBB permission checks apply.
- **Create content** — create topics and post replies through phpBB's own `submit_post()`.
- **Read content** — list a forum's topics (including global announcements), read a topic's posts, and fetch a single post; post bodies are returned as both rendered HTML and clean BBCode.
- **Search** — keyword search over posts or topics, with optional forum and author filters.
- **Pagination** — `limit`/`offset` on every list and search endpoint (default 25, max 100).
- **Visibility-aware reads** — respects `content_visibility` (soft-deleted/unapproved content stays hidden) and never exposes password-protected forums.
- **Forum allow-list** — optionally restrict a credential to specific forum IDs.
- **IP allow-list** — optionally restrict a credential to specific client IPs.
- **Rate limiting** — configurable requests-per-window cap per credential.
- **Expiration** — credentials can be set to expire on a date.
- **Read-only flag** — prevent a credential from performing write operations.
- **Full ACL enforcement** — the API calls phpBB's own `submit_post()` and `$auth->acl_get()`, so it cannot do anything the linked account could not do in the board UI.
- **Audit logging** — every request is recorded with timestamp, IP, method, route, and outcome.
- **ACP management** — create, edit, enable/disable, and delete credentials from the Administration Control Panel. The token is shown **once** at creation time.
- **HTTPS enforcement** — can be toggled per-site; enabled by default.

---

## Requirements

- phpBB 3.3.x (tested on 3.3.17)
- PHP 7.2 or later
- HTTPS in production (toggle off for local development)

---

## Installation

1. Download [`phpbbapihook.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/phpbbapihook.zip).
2. Unzip it into your board's `ext/` directory so the files end up at
   `ext/ecyaz/phpbbapihook/`. (The archive already contains the
   `ecyaz/phpbbapihook/` folder structure, so you can extract it straight into `ext/`.)
3. In the phpBB Administration Control Panel go to **Customise → Extension Manager**.
4. Find **phpbbAPIhook** and click **Enable**.
5. The extension runs its migration automatically, creating the credentials table and seeding default config.

---

## ACP Usage

After enabling the extension, go to **Administration Control Panel → Mods → phpBB API Hook → Manage API Keys**.

### Creating a credential

1. Click **Add API Credential**.
2. Fill in:
   - **Credential Name** — a human label (e.g. "Discord bot").
   - **User ID** — the phpBB `user_id` this credential will act as. The account must exist and be active.
   - **Allowed Forum IDs** — comma-separated list of forum IDs, or leave blank for all.
   - **IP Allowlist** — comma/space-separated IPs, or leave blank for any.
   - **Rate Limit** — max requests per rate window (default 1 hour); 0 = unlimited.
   - **Expiration Date** — `YYYY-MM-DD`, or blank for no expiry.
   - **Read-only** — check to restrict this credential to GET requests only.
   - **Enabled** — uncheck to temporarily suspend the credential.
3. Click **Save**. The **API token is displayed once** on the confirmation page — copy it immediately.

---

## Authentication

Include the token in every request using one of:

```
Authorization: Bearer <token>
```
```
X-API-Key: <token>
```

> **Note:** some server setups (Apache/FastCGI/CGI) strip the `Authorization`
> header before PHP sees it. If `Authorization: Bearer` does not work on your
> host, use the `X-API-Key` header instead (it is always available), or configure
> your server to pass `Authorization` through (e.g. the Apache rewrite rule
> `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]`).

---

## Endpoint Reference

All endpoints are under `app.php/api/` and return `application/json`.

### Error envelope

Every error response uses the same shape:

```json
{"success": false, "error": "<code>"}
```

| HTTP status | `error` code               | Meaning                                              |
|-------------|----------------------------|------------------------------------------------------|
| 401         | `missing_token`            | No token was supplied.                               |
| 401         | `invalid_token`            | Token not found / hash mismatch.                     |
| 403         | `read_only_credential`     | Credential is marked read-only; write denied.        |
| 403         | `forum_not_allowed`        | Forum not in the credential's allow-list.            |
| 403         | `insufficient_permissions` | phpBB ACL denied the action for the linked user.     |
| 403         | `forum_locked`             | Forum is locked; linked user lacks the moderator override (`m_edit`/`m_lock`). |
| 403         | `forum_password_required`  | Forum is password-protected; the API cannot supply the password. |
| 429         | `flood_control`            | Posting faster than the board's flood interval allows. |
| 429         | `rate_limit_exceeded`      | Credential exceeded its configured request rate limit. |
| 403         | `topic_locked`             | Topic is locked; linked user lacks `m_lock`.         |
| 403         | `account_unavailable`      | Credential's `user_id` is missing or inactive.       |
| 403         | `account_banned`           | The linked account is banned on the board.           |
| 403         | `credential_disabled`      | Credential has been disabled in ACP.                 |
| 403         | `credential_expired`       | Credential has passed its expiration date.           |
| 403         | `ip_not_allowed`           | Caller's IP is not on the credential's allow-list.   |
| 403         | `https_required`           | Board requires HTTPS; request was HTTP.              |
| 400         | `missing_fields`           | Required body field(s) absent.                       |
| 404         | `topic_not_found`          | Topic does not exist or is not visible to the linked user. |
| 404         | `forum_not_found`          | Forum does not exist.                                |
| 404         | `post_not_found`           | Post does not exist or is not visible to the linked user. |
| 429         | `rate_limit_exceeded`      | Credential has exceeded its rate limit.              |
| 429         | `flood_control`            | phpBB flood-control interval not yet elapsed.        |
| 400         | `search_query_too_short`   | Query is too short or contains no indexable terms.   |
| 503         | `api_disabled`             | Master API switch is off in ACP.                     |
| 503         | `search_unavailable`       | Full-text search is disabled on the board, or the search backend failed to initialise. |

---

### POST /api/topics

Create a new topic.

**Request body** (JSON or form-encoded):

| Field      | Type   | Required | Description                                      |
|------------|--------|----------|--------------------------------------------------|
| `forum_id` | int    | Yes      | ID of the forum to post in.                      |
| `title`    | string | Yes      | Topic subject.                                   |
| `content`  | string | Yes      | Post body (BBCode supported).                    |
| `type`     | string | No       | `normal` (default), `sticky`, or `announcement`. |

**Example:**

```bash
curl -X POST https://example.com/phpBB/app.php/api/topics \
  -H "Authorization: Bearer pbapi_abc123..." \
  -H "Content-Type: application/json" \
  -d '{"forum_id": 2, "title": "Hello from the API", "content": "This is the post body."}'
```

**Success (201):**

```json
{
  "success": true,
  "topic_id": 42,
  "post_id": 101,
  "url": "https://example.com/phpBB/viewtopic.php?t=42"
}
```

---

### POST /api/topics/{topic_id}/reply

Post a reply to an existing topic.

**Path parameter:** `topic_id` — integer ID of the topic to reply to.

**Request body:**

| Field     | Type   | Required | Description           |
|-----------|--------|----------|-----------------------|
| `content` | string | Yes      | Reply body (BBCode).  |

**Example:**

```bash
curl -X POST https://example.com/phpBB/app.php/api/topics/42/reply \
  -H "Authorization: Bearer pbapi_abc123..." \
  -H "Content-Type: application/json" \
  -d '{"content": "This is my reply."}'
```

**Success (201):**

```json
{
  "success": true,
  "topic_id": 42,
  "post_id": 102,
  "url": "https://example.com/phpBB/viewtopic.php?t=42#p102"
}
```

---

### GET /api/topics/{topic_id}

Read a topic's metadata.

**Example:**

```bash
curl https://example.com/phpBB/app.php/api/topics/42 \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "topic": {
    "topic_id": 42,
    "forum_id": 2,
    "title": "Hello from the API",
    "poster_id": 5,
    "post_count": 3,
    "views": 17,
    "time": 1750000000,
    "locked": false,
    "url": "https://example.com/phpBB/viewtopic.php?t=42"
  }
}
```

---

### GET /api/forums

List forums visible to the credential's user.

**Example:**

```bash
curl https://example.com/phpBB/app.php/api/forums \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "forums": [
    {
      "forum_id": 2,
      "parent_id": 1,
      "name": "Your first forum",
      "type": 1,
      "can_read": true,
      "can_post": true,
      "can_reply": true
    }
  ]
}
```

---

### GET /api/me/permissions

Return identity and access information for the current credential.

**Example:**

```bash
curl https://example.com/phpBB/app.php/api/me/permissions \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "user_id": 5,
  "username": "mybot",
  "read_only": false,
  "is_founder": false,
  "allowed_forums": []
}
```

(`allowed_forums` is an empty array when the credential is allowed all forums.)

---

### GET /api/forums/{forum_id}/topics

List topics in a forum, ordered by most-recent activity (newest last-post first).

**Path parameter:** `forum_id` — integer ID of the forum.

**Query parameters:**

| Parameter | Type | Default | Description                          |
|-----------|------|---------|--------------------------------------|
| `limit`   | int  | 25      | Number of topics to return (max 100).|
| `offset`  | int  | 0       | Number of topics to skip.            |

**Example:**

```bash
curl "https://example.com/phpBB/app.php/api/forums/2/topics?limit=10&offset=0" \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "forum": {
    "forum_id": 2,
    "name": "Your first forum"
  },
  "pagination": {
    "limit": 10,
    "offset": 0,
    "total": 42,
    "count": 10
  },
  "topics": [
    {
      "topic_id": 7,
      "forum_id": 2,
      "title": "Latest discussion",
      "type": "normal",
      "poster_id": 5,
      "poster": "Alice",
      "replies": 3,
      "views": 100,
      "first_post_time": 1750000000,
      "last_post_time": 1750100000,
      "last_poster_id": 6,
      "last_poster": "Bob",
      "locked": false
    }
  ]
}
```

`type` is one of `normal`, `sticky`, `announcement`, or `global`.

---

### GET /api/topics/{topic_id}/posts

List posts in a topic in chronological order (oldest first). Each post includes
both its rendered HTML and its raw BBCode.

**Path parameter:** `topic_id` — integer ID of the topic.

**Query parameters:**

| Parameter | Type | Default | Description                        |
|-----------|------|---------|------------------------------------|
| `limit`   | int  | 25      | Number of posts to return (max 100).|
| `offset`  | int  | 0       | Number of posts to skip.           |

**Example:**

```bash
curl "https://example.com/phpBB/app.php/api/topics/42/posts?limit=25&offset=0" \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "topic": {
    "topic_id": 42,
    "forum_id": 2,
    "title": "Hello from the API",
    "locked": false
  },
  "pagination": {
    "limit": 25,
    "offset": 0,
    "total": 3,
    "count": 3
  },
  "posts": [
    {
      "post_id": 101,
      "poster_id": 5,
      "poster": "Alice",
      "post_time": 1750000000,
      "edit_count": 0,
      "edit_time": 0,
      "subject": "Hello from the API",
      "content_html": "<p>This is the post body.</p>",
      "content_bbcode": "This is the post body."
    }
  ]
}
```

---

### GET /api/posts/{post_id}

Read a single post by ID. Returns the post, its parent topic summary, and its
forum.

**Path parameter:** `post_id` — integer ID of the post.

**Example:**

```bash
curl https://example.com/phpBB/app.php/api/posts/101 \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "post": {
    "post_id": 101,
    "poster_id": 5,
    "poster": "Alice",
    "post_time": 1750000000,
    "edit_count": 0,
    "edit_time": 0,
    "subject": "Hello from the API",
    "content_html": "<p>This is the post body.</p>",
    "content_bbcode": "This is the post body."
  },
  "topic": {
    "topic_id": 42,
    "forum_id": 2,
    "title": "Hello from the API",
    "locked": false
  },
  "forum": {
    "forum_id": 2,
    "name": "Your first forum"
  }
}
```

---

### GET /api/search

Full-text post and topic search using the board's configured search backend.
Only forums the linked user can read and the credential is allowed to access are
searched.

**Query parameters:**

| Parameter  | Type   | Required | Default | Description                                              |
|------------|--------|----------|---------|----------------------------------------------------------|
| `q`        | string | Yes      | —       | Keywords to search for.                                  |
| `type`     | string | No       | `posts` | Result type: `posts` or `topics`.                        |
| `forum_id` | int    | No       | 0       | Restrict results to a single forum (0 = all accessible). |
| `author`   | string | No       | —       | Filter by username prefix (case-insensitive).            |
| `limit`    | int    | No       | 25      | Number of results to return (max 100).                   |
| `offset`   | int    | No       | 0       | Number of results to skip.                               |

**Example (post search):**

```bash
curl "https://example.com/phpBB/app.php/api/search?q=hello+world&type=posts&limit=10" \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200) — `type: "posts"`:**

```json
{
  "success": true,
  "type": "posts",
  "query": "hello world",
  "pagination": {
    "limit": 10,
    "offset": 0,
    "total": 5,
    "count": 5
  },
  "results": [
    {
      "post_id": 101,
      "topic_id": 42,
      "forum_id": 2,
      "poster": "Alice",
      "post_time": 1750000000,
      "snippet": "This is the post body."
    }
  ]
}
```

**Success (200) — `type: "topics"`:**

```json
{
  "success": true,
  "type": "topics",
  "query": "hello world",
  "pagination": {
    "limit": 10,
    "offset": 0,
    "total": 2,
    "count": 2
  },
  "results": [
    {
      "topic_id": 42,
      "forum_id": 2,
      "title": "Hello from the API",
      "poster": "Alice",
      "last_post_time": 1750100000
    }
  ]
}
```

`snippet` (post results only) is a plain-text excerpt of up to 200 characters
stripped of BBCode tags.

---

## Security Notes

- **HTTPS only by default.** The `phpbbapihook_require_https` config option is `1` on installation. Disable it only in development (`ACP → Mods → phpBB API Hook` or directly in `phpbb_config`).
- **Permissions are never bypassed.** The authenticator calls `$auth->acl($user->data)` with the credential's phpBB account, so every permission check respects the board's role and group assignments.
- **Rate limiting** is tracked in the audit log table. The window size (default 3600 seconds) is stored in `phpbbapihook_rate_window`.
- **Audit log.** Every request writes a row to `phpbb_apihook_log` with the time, IP, method, route, HTTP status, and error code. Viewable per-credential in ACP.
- **Tokens are hashed.** Only a SHA-256 hash of the token is stored. If you lose a token you must delete the credential and create a new one.

---

## Roadmap / Future Work

The following features are planned but not yet implemented:

- Attachment upload endpoint
- Post and topic editing (`PATCH /api/topics/{id}`, `PATCH /api/posts/{id}`)
- Moderation actions (lock, delete, move, approve)
- Webhook push events (on new post / topic)
- OAuth 2.0 as an alternative auth mechanism

---

## License

GNU General Public License, version 2 (GPL-2.0-only). See `license.txt`.
