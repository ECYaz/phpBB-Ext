# Profile Side Switcher — phpBB 3.3.x

A phpBB extension that lets each user choose which side the post-profile column
appears on in topics — left or right.

This is a maintained fork of the original **Profile Side Switcher** extension by
Татьяна5 and LavIgor, modernized for **phpBB 3.3.x** (tested on 3.3.17) and packaged
as **`ecyaz/profileSwitcher`**. Changes from the original 3.1 release:

- `composer.json` updated (PHP ≥ 7.1.3, soft-require phpBB `>=3.3.0,<4.0.0@dev`, SPDX `GPL-2.0-only`).
- `config/services.yml` quoted-argument syntax (required by phpBB 3.3's Symfony).
- PHP 8.2 compatibility (null/undefined-index guards) and a Twig-3-safe stylesheet include.
- **New admin setting** (ACP → Customise → Extensions → *Profile Side Switcher*):
  choose the online-ribbon style for the left-side layout — **Right (original)** or
  **Left (mirrored)**. Defaults to *Right*. The ribbon text follows the board's language.

Original authors: **Татьяна5** and **LavIgor**. Licensed under **GPL-2.0** (see `license.txt`).

## Download & install

**Easiest — use the zip:** download
[`profileSwitcher.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/profileSwitcher.zip)
(in the repository root) and extract it into your board's `ext/` directory. It
unpacks to `ext/ecyaz/profileSwitcher/` automatically.

**From source instead:** copy the files in this folder into
`ext/ecyaz/profileSwitcher/` on your board. The path must be exactly
`ext/ecyaz/profileSwitcher/` — phpBB derives the extension's namespace from it,
so it won't load anywhere else.

Then enable it via *ACP → Customise → Extensions → Profile Side Switcher*.
After enabling (or after any change), purge the cache via
*ACP → General → Purge the cache*.
