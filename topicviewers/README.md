# Topic Viewers

A phpBB 3.3.x extension that shows how many registered users and guests are
currently viewing each topic, displayed next to the board's "Who is online"
list at the bottom of the topic page.

## Features

- Counts the registered users and guests viewing the current topic, derived from
  the active sessions phpBB already tracks (no extra tables).
- ACP setting to enable or disable the display board-wide.
- ACP setting to choose the display style:
  - **Counts only** — e.g. *Viewing this topic: 2 registered users and 3 guests*
  - **Counts and member names** — lists the registered viewers (linked, coloured)
    alongside the guest count.
- Respects "Hide my online status": members who are browsing invisibly are never
  listed or counted.

## Requirements

- phpBB 3.3.0 or higher
- PHP 7.2 or higher

## Download & install

**Easiest — use the zip:** download
[`topicViewers.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/topicViewers.zip) (in the repository root) and
extract it into your board's `ext/` directory. It unpacks to
`ext/ecyaz/topicviewers/`.

**From source instead:** copy the files in this folder into
`ext/ecyaz/topicviewers/` on your board. The path must be exactly
`ext/ecyaz/topicviewers/` — phpBB derives the extension's namespace from it.

Then enable it via *ACP → Customise → Extensions → Topic Viewers* and
purge the cache (*ACP → General → Purge the cache*). Configure it under
*ACP → Extensions → Topic Viewers*.

## How it works

A listener on `core.viewtopic_assign_template_vars_before` queries the
`phpbb_sessions` table for sessions whose stored page is the current topic
(within the board's "view online time" window), splits them into anonymous
(guests) and registered users, and assigns the figures to the template event
`viewtopic_body_online_list_before`. No core files are modified.

The topic a session is viewing is read from `session_page`, which phpBB
populates for default `viewtopic.php?...&t=<id>` URLs. Boards using a URL-rewrite
extension that removes the `t=<id>` parameter from the tracked page may not be
detected.

## License

[GPL-2.0-only](license.txt)
