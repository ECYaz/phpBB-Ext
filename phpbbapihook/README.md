# phpbbAPIhook

A secure REST API extension for phpBB 3.3.x with permission control. It lets external applications create topics, post replies, and read forum data via HTTP requests authenticated with API keys, while never bypassing the board's own ACL: every request runs with the permissions of the phpBB user account linked to the credential.

## Features

- **API key authentication**: `Authorization: Bearer` or `X-API-Key` header.
- **User binding per credential**: each key acts as a specific phpBB user; all phpBB permission checks apply.
- **Create content**: create topics and post replies through phpBB's own `submit_post()`.
- **Read content**: list a forum's topics (including global announcements), read a topic's posts, and fetch a single post; post bodies are returned as both rendered HTML and clean BBCode.
- **Search**: keyword search over posts or topics, with optional forum and author filters.
- **Pagination**: `limit`/`offset` on every list and search endpoint (default 25, max 100).
- **Reads that respect visibility**: respects `content_visibility` (soft deleted and unapproved content stays hidden) and never exposes password protected forums.
- **Forum allow list**: optionally restrict a credential to specific forum IDs.
- **IP allow list**: optionally restrict a credential to specific client IPs.
- **Rate limiting**: configurable cap on requests per time window for each credential.
- **Expiration**: credentials can be set to expire on a date.
- **Read only flag**: prevent a credential from performing write operations.
- **Full ACL enforcement**: the API calls phpBB's own `submit_post()` and `$auth->acl_get()`, so it cannot do anything the linked account could not do in the board UI.
- **Audit logging**: every request is recorded with timestamp, IP, method, route, and outcome.
- **ACP management**: create, edit, enable/disable, and delete credentials from the Administration Control Panel. The token is shown **once** at creation time.
- **HTTPS enforcement**: can be toggled for the whole board; enabled by default.
- **Open to other extensions**: any extension can offer its own endpoints through this API, with the same keys, limits and audit log. See [For extension authors](#for-extension-authors).

## Requirements

- phpBB 3.3.x (tested on 3.3.19)
- PHP 7.1.3 or later
- HTTPS in production (toggle off for local development)

## Installation

1. Download [`phpbbapihook.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/phpbbapihook.zip).
2. Unzip it into your board's `ext/` directory so the files end up at
   `ext/ecyaz/phpbbapihook/`. (The archive already contains the
   `ecyaz/phpbbapihook/` folder structure, so you can extract it straight into `ext/`.)
3. In the phpBB Administration Control Panel go to **Customise → Extension Manager**.
4. Find **phpbbAPIhook** and click **Enable**.
5. The extension runs its migration automatically, creating the credentials table and seeding default config.

## ACP Usage

After enabling the extension, go to **Administration Control Panel → Mods → phpBB API Hook → Manage API Keys**.

### Creating a credential

1. Click **Add API Credential**.
2. Fill in:
   - **Credential Name**: a human label (e.g. "Discord bot").
   - **User ID**: the phpBB `user_id` this credential will act as. The account must exist and be active.
   - **Allowed Forum IDs**: list separated by commas of forum IDs, or leave blank for all.
   - **IP Allowlist**: IPs separated by commas or spaces, or leave blank for any.
   - **Rate Limit**: max requests per rate window (default 1 hour); 0 = unlimited.
   - **Expiration Date**: `YYYY-MM-DD`, or blank for no expiry.
   - **Read only**: check to restrict this credential to GET requests only.
   - **Extension endpoints**: the extensions whose endpoints this credential may call, as `vendor/package` names separated by commas, or `*` for all of them. Blank means none.
   - **Enabled**: uncheck to temporarily suspend the credential.
3. Click **Save**. The **API token is displayed once** on the confirmation page, so copy it immediately.

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
| 403         | `read_only_credential`     | Credential is marked read only; write denied.        |
| 403         | `forum_not_allowed`        | Forum not in the credential's allow list.            |
| 403         | `insufficient_permissions` | phpBB ACL denied the action for the linked user.     |
| 403         | `forum_locked`             | Forum is locked; linked user lacks the moderator override (`m_edit`/`m_lock`). |
| 403         | `forum_password_required`  | Forum is password protected; the API cannot supply the password. |
| 429         | `flood_control`            | Posting faster than the board's flood interval allows. |
| 429         | `rate_limit_exceeded`      | Credential exceeded its configured request rate limit. |
| 403         | `topic_locked`             | Topic is locked; linked user lacks `m_lock`.         |
| 403         | `account_unavailable`      | Credential's `user_id` is missing or inactive.       |
| 403         | `account_banned`           | The linked account is banned on the board.           |
| 403         | `credential_disabled`      | Credential has been disabled in ACP.                 |
| 403         | `credential_expired`       | Credential has passed its expiration date.           |
| 403         | `ip_not_allowed`           | Caller's IP is not on the credential's allow list.   |
| 403         | `https_required`           | Board requires HTTPS; request was HTTP.              |
| 400         | `missing_fields`           | Required body field(s) absent.                       |
| 404         | `topic_not_found`          | Topic does not exist or is not visible to the linked user. |
| 404         | `forum_not_found`          | Forum does not exist.                                |
| 404         | `post_not_found`           | Post does not exist or is not visible to the linked user. |
| 400         | `search_query_too_short`   | Query is too short or contains no indexable terms.   |
| 403         | `extension_not_allowed`    | The credential was not given access to this extension. |
| 404         | `endpoint_not_registered`  | The extension never announced this endpoint.         |
| 404         | `endpoint_path_mismatch`   | An extension endpoint was called outside its own path. |
| 405         | `method_not_allowed`       | The HTTP method differs from the announced one.      |
| 500         | `internal_error`           | The endpoint failed. Details are never sent to the client. |
| 503         | `api_disabled`             | Master API switch is off in ACP.                     |
| 503         | `search_unavailable`       | Full text search is disabled on the board, or the search backend failed to initialise. |

### POST /api/topics

Create a new topic.

**Request body** (JSON or form encoded):

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

### POST /api/topics/{topic_id}/reply

Post a reply to an existing topic.

**Path parameter:** `topic_id`, integer ID of the topic to reply to.

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
  "allowed_forums": [],
  "allowed_extensions": ["acme/demo"]
}
```

(`allowed_forums` is an empty array when the credential is allowed all forums.
`allowed_extensions` is an empty array when the credential may not call any
extension endpoint, and `["*"]` when it may call all of them.)

### GET /api/extensions

List the endpoints that other extensions offer through the API. Only extensions
this credential was given access to are listed.

**Example:**

```bash
curl https://example.com/phpBB/app.php/api/extensions \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200):**

```json
{
  "success": true,
  "extensions": [
    {
      "name": "acme/demo",
      "endpoints": [
        {
          "action": "topic.freeze",
          "method": "POST",
          "path": "/api/ext/acme/demo/topics/{topic_id}/freeze",
          "description": "Freeze a topic."
        }
      ]
    }
  ]
}
```

### GET /api/forums/{forum_id}/topics

List topics in a forum, ordered by most recent activity (newest last post first).

**Path parameter:** `forum_id`, integer ID of the forum.

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

### GET /api/topics/{topic_id}/posts

List posts in a topic in chronological order (oldest first). Each post includes
both its rendered HTML and its raw BBCode.

**Path parameter:** `topic_id`, integer ID of the topic.

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

### GET /api/posts/{post_id}

Read a single post by ID. Returns the post, its parent topic summary, and its
forum.

**Path parameter:** `post_id`, integer ID of the post.

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

### GET /api/search

Full text post and topic search using the board's configured search backend.
Only forums the linked user can read and the credential is allowed to access are
searched.

**Query parameters:**

| Parameter  | Type   | Required | Default | Description                                              |
|------------|--------|----------|---------|----------------------------------------------------------|
| `q`        | string | Yes      | none    | Keywords to search for.                                  |
| `type`     | string | No       | `posts` | Result type: `posts` or `topics`.                        |
| `forum_id` | int    | No       | 0       | Restrict results to a single forum (0 = all accessible). |
| `author`   | string | No       | none    | Filter by username prefix (case insensitive).            |
| `limit`    | int    | No       | 25      | Number of results to return (max 100).                   |
| `offset`   | int    | No       | 0       | Number of results to skip.                               |

**Example (post search):**

```bash
curl "https://example.com/phpBB/app.php/api/search?q=hello+world&type=posts&limit=10" \
  -H "X-API-Key: pbapi_abc123..."
```

**Success (200) with `type: "posts"`:**

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

**Success (200) with `type: "topics"`:**

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

`snippet` (post results only) is a plain text excerpt of up to 200 characters
stripped of BBCode tags.

## For extension authors

Your extension can offer its own endpoints through phpbbAPIhook. You write the
endpoint, phpbbAPIhook takes care of the rest: the API key, the IP and rate
limits, the read only flag, the JSON error format and the audit log. The request
runs as the phpBB user linked to the key, so your usual permission checks work,
including permissions that your own extension adds.

A board admin decides per credential which extensions it may call (the
**Extension endpoints** field). A new or existing credential cannot call any
extension endpoint until the admin allows it.

### 1. Add a route under your own path

Every extension endpoint lives under `/api/ext/<vendor>/<package>/`.

```yaml
# config/routing.yml
acme_demo_api_freeze:
    path: /api/ext/acme/demo/topics/{topic_id}/freeze
    methods: [POST]
    requirements: { topic_id: '\d+' }
    defaults: { _controller: acme.demo.controller.api:freeze }
```

### 2. Announce the endpoint

```php
// event/listener.php
public static function getSubscribedEvents()
{
    return ['ecyaz.phpbbapihook.collect_endpoints' => 'collect_endpoints'];
}

public function collect_endpoints($event)
{
    $endpoints = $event['endpoints'];
    $endpoints[] = [
        'extension'   => 'acme/demo',
        'action'      => 'topic.freeze',
        'method'      => 'POST',
        'path'        => '/api/ext/acme/demo/topics/{topic_id}/freeze',
        'description' => 'Freeze a topic.',
    ];
    $event['endpoints'] = $endpoints;
}
```

`handle()` refuses an endpoint that was not announced. Rows are ignored when the
extension is not enabled, when the path is outside your own prefix, or when the
action name contains anything other than lower case letters, digits, dots and
underscores.

### 3. Run the endpoint through the gateway

Ask for the gateway as an optional service, so your extension keeps working
when phpbbAPIhook is not installed.

```yaml
# config/services.yml
services:
    acme.demo.controller.api:
        class: acme\demo\controller\api
        arguments:
            - '@?ecyaz.phpbbapihook.gateway'
            - '@auth'
```

```php
// controller/api.php
public function __construct($gateway, \phpbb\auth\auth $auth)
{
    $this->gateway = $gateway;
    $this->auth    = $auth;
}

public function freeze($topic_id)
{
    if ($this->gateway === null)
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse(['success' => false, 'error' => 'api_unavailable'], 503);
    }

    return $this->gateway->handle('acme/demo', 'topic.freeze', function ($ctx) use ($topic_id) {
        $forum_id = $this->forum_of_topic($topic_id);

        // The forum allow list of the credential.
        $ctx->assert_forum($forum_id);

        // Your own permission, checked for the linked user.
        if (!$this->auth->acl_get('m_acme_freeze', $forum_id))
        {
            $this->gateway->fail('insufficient_permissions', 403);
        }

        $this->freeze_topic($topic_id);

        return ['topic_id' => (int) $topic_id, 'frozen' => true];
    });
}
```

What the gateway gives you:

| Call | What it does |
|------|--------------|
| `$gateway->handle($extension, $action, $fn)` | Authenticates, applies the limits of the credential, runs `$fn` and returns the JSON response. An array returned by `$fn` becomes `{"success": true, ...}`. |
| `$gateway->success($data, $status)` | A success response with another status, for example 201. |
| `$gateway->fail($code, $status)` | Stops with `{"success": false, "error": "<code>"}`. |
| `$ctx->get_user_id()` | The user the request runs as. |
| `$ctx->is_read_only()` | Whether the credential is read only. |
| `$ctx->can_access_forum($forum_id)` | Whether the forum allow list of the credential permits the forum. |
| `$ctx->assert_forum($forum_id)` | The same check, stopping with `forum_not_allowed`. |

What is enforced for you, and what is not:

- A read only credential is refused on every request that is not GET or HEAD, before your code runs. Use GET only for endpoints that change nothing.
- The forum allow list cannot be applied automatically, because only your endpoint knows which forum it touches. Call `$ctx->assert_forum()` whenever your endpoint acts on a forum, a topic or a post.
- Core checks such as `f_read`, post visibility, locked topics and forum passwords are yours to make, exactly as on a normal page of your extension.
- Errors and exceptions thrown inside `$fn` reach the client as `internal_error` only. Do not call `trigger_error()` inside `$fn`, it would print an HTML page.

### Events

| Event | Purpose |
|-------|---------|
| `ecyaz.phpbbapihook.collect_endpoints` | Announce your endpoints (`endpoints`). |
| `ecyaz.phpbbapihook.request_authenticated` | Runs after the credential was accepted and before the endpoint. Set `error` (and `error_status`) to refuse the request. Variables: `extension`, `action`, `method`, `key_id`, `user_id`, `read_only`. |
| `ecyaz.phpbbapihook.response_before` | Runs before a success response is sent. Change `data` to add fields, also to the core endpoints such as `topic.read`. Variables: `extension`, `action`, `key_id`, `user_id`, `status`, `data`. |

For core endpoints `extension` is an empty string and `action` is one of
`topic.create`, `topic.reply`, `topic.read`, `topic.posts`, `forum.list`,
`forum.topics`, `post.read`, `search`, `me.permissions`, `extensions.list`.

## Security Notes

- **HTTPS only by default.** The `phpbbapihook_require_https` config option is `1` on installation. Disable it only in development (`ACP → Mods → phpBB API Hook` or directly in `phpbb_config`).
- **Permissions are never bypassed.** The authenticator calls `$auth->acl($user->data)` with the credential's phpBB account, so every permission check respects the board's role and group assignments.
- **Rate limiting** is tracked in the audit log table. The window size (default 3600 seconds) is stored in `phpbbapihook_rate_window`.
- **Audit log.** Every request writes a row to `phpbb_apihook_log` with the time, IP, method, route, HTTP status, and error code. Viewable per credential in the ACP.
- **Tokens are hashed.** Only a SHA 256 hash of the token is stored. If you lose a token you must delete the credential and create a new one.

## Roadmap / Future Work

The following features are planned but not yet implemented:

- Attachment upload endpoint
- Post and topic editing (`PATCH /api/topics/{id}`, `PATCH /api/posts/{id}`)
- Moderation actions (lock, delete, move, approve)
- Webhook push events (on new post / topic)
- OAuth 2.0 as an alternative auth mechanism

## License

GNU General Public License, version 2 (GPL 2.0 only). See `license.txt`.
