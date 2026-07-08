# Force Post Preview — phpBB 3.3.x

A phpBB extension that keeps the **Submit** button greyed-out and unclickable on
the posting screen until the user has **previewed** their post. After a preview,
Submit becomes available; if the post is edited again, a fresh preview is
required. The **Preview** and **Save draft** buttons always stay usable.

Works on the full posting editor — new topic, reply, quote, edit, and private
message compose.

**Quick reply is covered too.** Because the quick-reply box has no inline
preview, its **Submit** button is locked and the **Full Editor & Preview**
button stays enabled — so the only way forward is the full editor, where the
preview gate above applies.

> **Note:** this is a client-side UI gate. A user with browser dev tools, or with
> JavaScript disabled, can still submit without previewing — phpBB has no reliable
> way to enforce "was previewed" server-side, since a preview is a separate,
> stateless request. This extension nudges the normal posting flow; it is not a
> security control.

## Download & install

**Easiest — use the zip:** download
[`forcePostPreview.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/forcePostPreview.zip) (in the repository root) and
extract it into your board's `ext/` directory. It unpacks to
`ext/ecyaz/forcepostpreview/`.

**From source instead:** copy the files in this folder into
`ext/ecyaz/forcepostpreview/` on your board. The path must be exactly
`ext/ecyaz/forcepostpreview/` — phpBB derives the extension's namespace from it.

Then enable it via *ACP → Customise → Extensions → Force Post Preview*. After
enabling (or any change), purge the cache via *ACP → General → Purge the cache*.

## License

GPL-2.0-only (see `license.txt`).
