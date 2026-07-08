# Post Length Reminder — phpBB 3.3.x

A phpBB extension that warns a user before they submit a post that is shorter
than a configurable length. When the post is too short, a confirmation dialog
appears — e.g. *"This post is really short. Do you want to expand on it before
posting?"* — letting the user **post anyway** or **go back and add more**.

The **Preview** and **Save draft** buttons are never blocked, and empty posts
are left to phpBB's own "you must enter a message" validation. Works on the full
posting editor — new topic, reply, quote, edit, and private message compose.

## Settings (ACP → Customise → Extensions → Post Length Reminder)

- **Minimum post length** — the character count below which the reminder fires.
  Set to `0` to turn the reminder off.
- **Reminder message** — the text shown in the confirmation dialog. Leave blank
  to use the built-in (translatable) default.

> **Note:** this is a client-side reminder. A user with browser dev tools or with
> JavaScript disabled can still submit a short post — it nudges the normal
> posting flow rather than hard-blocking it.

## Download & install

**Easiest — use the zip:** download
[`postLengthReminder.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/postLengthReminder.zip) (in the repository root) and
extract it into your board's `ext/` directory. It unpacks to
`ext/ecyaz/postlengthreminder/`.

**From source instead:** copy the files in this folder into
`ext/ecyaz/postlengthreminder/` on your board. The path must be exactly
`ext/ecyaz/postlengthreminder/` — phpBB derives the extension's namespace from it.

Then enable it via *ACP → Customise → Extensions → Post Length Reminder* and
purge the cache (*ACP → General → Purge the cache*).

## License

GPL-2.0-only (see `license.txt`).
