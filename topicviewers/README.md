# Topic Viewers for phpBB 3.3.x

A phpBB extension that shows how many registered users and guests are currently
viewing each topic, next to the board's "Who is online" list at the bottom of
the topic page.

The counts come from the active sessions phpBB already tracks, so no extra
tables are needed. Two ACP settings control the display: one switch turns the
feature on or off for the whole board, and a display style choice shows either
the counts alone (for example *Viewing this topic: 2 registered users and 3
guests*) or the counts together with the names of the registered viewers,
linked and coloured. Members who hide their online status are never listed or
counted.

## Requirements

phpBB 3.3.0 or higher on PHP 7.1.3 or higher.

## Download & install

1. Download [`topicviewers.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/topicviewers.zip)
   and unzip it into your board's `ext/` directory (it unpacks to
   `ext/ecyaz/topicviewers/`), or copy this folder to `ext/ecyaz/topicviewers/`.
2. Enable it via *ACP → Customise → Extensions → Topic Viewers*.
3. After enabling (or any change), purge the cache via *ACP → General → Purge
   the cache*.
4. Configure it under *ACP → Extensions → Topic Viewers*.

## How it works

A listener on `core.viewtopic_assign_template_vars_before` queries the
`phpbb_sessions` table for sessions whose stored page is the current topic,
within the board's "view online time" window, splits them into guests and
registered users, and assigns the figures to the template event
`viewtopic_body_online_list_before`. No core files are modified.

The topic a session is viewing is read from `session_page`. Both default
`viewtopic.php?...&t=<id>` URLs and post permalinks (`viewtopic.php?p=<id>`, as
produced by unread, last post and search result links) are recognised; post ids
are resolved to the topic with one additional query. Boards using a URL rewrite
extension that removes both parameters from the tracked page may not detect
viewers.

## License

GPL 2.0 only (see `license.txt`).
