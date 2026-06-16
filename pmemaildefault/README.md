# PM Email Default (`ecyaz/pmemaildefault`)

A phpBB **3.3.x** extension that makes **email notifications for incoming private messages ON by default** — for both new and existing users.

Out of the box, phpBB only enables the in-board (notification list) method for new PMs; email is opt-in and most members never turn it on. This extension flips that default so members are emailed when they receive a PM, while still letting each user switch it off in their UCP.

## Features

- New users are subscribed to PM email notifications automatically at registration.
- Existing users are switched on by a one-time migration when the extension is enabled.
- In-board (notification list) notifications are unaffected — they remain on as before.
- No phpBB core files are modified; everything is delivered through the extension system.

## Requirements

- phpBB 3.3.x
- PHP 7.2 or newer

## Installation

1. Download [`pmemaildefault.zip`](https://github.com/ECYaz/phpBB-Extensions/raw/main/pmemaildefault.zip).
2. Unzip it into your board's `ext/` directory so the files end up at
   `ext/ecyaz/pmemaildefault/`. (The archive already contains the `ecyaz/pmemaildefault/`
   folder structure, so you can extract it straight into `ext/`.)
3. In the ACP, go to **Customise → Manage extensions**.
4. Click **Enable** next to **PM Email Default** and confirm.

Enabling runs a one-time migration that turns PM email on for every existing user.

## How it works

- A listener on the core `core.user_add_modify_notifications_data` event adds a
  `notification.type.pm` / `notification.method.email` subscription for each new user as
  they are created.
- A one-time migration writes the same `notify = 1` subscription for every existing user.

The send-time notification logic then emails the recipient about new PMs, exactly as it
already does for users who opted in manually.

## Notes

- The migration enables PM email for **all** existing users, including any who had
  previously turned it off. After it runs, the extension never changes the setting again,
  so a user who later disables PM email in their UCP keeps it disabled — their choice is
  respected.
- Uninstalling (purging) the extension does not restore each user's previous on/off state,
  because that state is overwritten when the extension is enabled.

## Uninstall

In the ACP under **Customise → Manage extensions**, click **Disable** to switch the
behaviour off, or **Delete data** to remove the extension's data entirely.

## License

[GPL-2.0-only](license.txt)

## Author

ECYaz — <https://github.com/ECYaz/phpBB-Extensions>
