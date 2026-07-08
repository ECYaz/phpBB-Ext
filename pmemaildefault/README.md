# PM Email Default (`ecyaz/pmemaildefault`)

A phpBB **3.3.x** extension that lets you set **email notifications for incoming private messages on or off by default** — for both new and existing users — from a single switch in the ACP.

Out of the box, phpBB only enables the in-board (notification list) method for new PMs; email is opt-in and most members never turn it on. This extension lets an admin flip that default in either direction so members are (or are not) emailed when they receive a PM, while still letting each user change their own choice in their UCP.

## Features

- An ACP toggle sets the PM email default **On** or **Off** for all users.
- New users are subscribed to (or left off) PM email notifications automatically at registration, following the toggle.
- Saving the toggle applies the chosen state immediately to every existing member.
- In-board (notification list) notifications are unaffected — they remain on as before.
- No phpBB core files are modified; everything is delivered through the extension system.

## Requirements

- phpBB 3.3.x
- PHP 7.2 or newer

## Installation

1. Download [`pmemaildefault.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/pmemaildefault.zip).
2. Unzip it into your board's `ext/` directory so the files end up at
   `ext/ecyaz/pmemaildefault/`. (The archive already contains the `ecyaz/pmemaildefault/`
   folder structure, so you can extract it straight into `ext/`.)
3. In the ACP, go to **Customise → Manage extensions**.
4. Click **Enable** next to **PM Email Default** and confirm.

Enabling defaults to **On** and turns PM email on for every existing user (the same behaviour as earlier versions).

## Usage

After enabling, go to **ACP → Extensions → PM Email Default settings**. Choose:

- **On for all users** — new members are subscribed to PM email, and every existing
  member is switched on.
- **Off for all users** — new members are not subscribed, and every existing member is
  switched off.

The setting controls the default for newly registered users, and **saving the form
applies the chosen state to every existing member immediately**. Members can still change
their own choice afterwards in their UCP — until the next time you save this form, which
overrides it again.

## How it works

- A listener on the core `core.user_add_modify_notifications_data` event adds a
  `notification.type.pm` / `notification.method.email` subscription for each new user as
  they are created — but only while the ACP toggle is **On**. When it is **Off** the
  listener adds nothing, so the new user keeps phpBB's stock default of no PM email.
- The ACP page writes the same subscription (`notify = 1` when On, `notify = 0` when Off)
  for every existing user each time it is saved.
- A one-time migration applies the default (On) to existing users when the extension is
  first enabled.

The send-time notification logic then emails the recipient about new PMs, exactly as it
already does for users who opted in manually.

## Notes

- Saving the ACP form overwrites every member's current PM email choice with the selected
  state — including members who had set it themselves. Between saves, each member's own
  UCP choice is respected.
- Uninstalling (purging) the extension does not restore each user's previous on/off state,
  because that state is overwritten while the extension is in use.

## Uninstall

In the ACP under **Customise → Manage extensions**, click **Disable** to switch the
behaviour off, or **Delete data** to remove the extension's data entirely (this also
removes the ACP settings page and the stored toggle value).

## License

[GPL-2.0-only](license.txt)

## Author

ECYaz — <https://github.com/ECYaz/phpBB-Extensions>
