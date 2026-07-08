# eventscalendar — phpBB Events Calendar Extension

A full-featured events calendar for phpBB 3.3.x: month/year/day views on the board index or a full page, RSVP per occurrence, recurring events, member birthdays, ACP-managed special dates, email/board reminders, an iCalendar (ICS) subscription feed, and one-way push to a Google Calendar via a service account.

---

## Feature tour

- **Calendar views** — month grid (with event bars/chips), year overview, day view, and a free-text search with an optional date-range filter. Configurable board-index widget (off / upcoming-events list / mini calendar / both).
- **Events** — title, BBCode description, start/end date-time, all-day toggle, colour tag, and recurrence (none, daily, weekly, monthly, annually — with an optional "repeat until" date). Owners can edit/delete their own events; moderators/admins can edit or delete any event.
- **Discussion topics** — optionally auto-posts a topic in a configurable forum when an event is created, so members can discuss it inline.
- **RSVP** — members can attend/un-attend individual *occurrences* of an event (a weekly meetup tracks attendance separately for each week), with a visible attendee list.
- **Birthdays** — member birthdays already on file are shown alongside events; no separate data of their own.
- **Special dates** — board-wide holidays, anniversaries, and similar fixed markers, managed from the ACP, one-off or annually recurring, with their own colour tag.
- **Reminders & notifications** — attendees get a phpBB notification (and email, if enabled) a configurable number of days before an event they're attending, plus update and cancellation notices.
- **iCalendar (ICS) feed** — subscribe to the whole calendar (events + special dates) from any external calendar app, public or secret-key-gated, cached for 15 minutes server-side.
- **Google Calendar sync** — one-way push of event creates/edits/deletes to a Google Calendar via a service account, processed in the background by the board cron with retry/backoff, a manual sync queue, "Test connection," and "Resync all."

---

## Requirements

- phpBB 3.3.x (tested on 3.3.17)
- PHP 7.2 or later
- (Optional) a Google Cloud project with the Calendar API enabled, for Google Calendar sync

---

## Installation

1. Download [`eventscalendar.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/eventscalendar.zip) (or copy the `eventscalendar/` source directory).
2. Extract/copy it into your board's `ext/` directory so the files end up at `ext/ecyaz/eventscalendar/`.
3. In the phpBB Administration Control Panel go to **Customise → Manage extensions**.
4. Find **Events Calendar** and click **Enable**. The extension's migrations run automatically, creating the calendar tables, seeding default configuration, the ACP module, notification type, and permissions.
5. Grant permissions to the relevant groups (see below) — none of the user-facing permissions are granted to anyone by default beyond the standard roles listed in the table.

---

## Permissions

| Permission | Grants | Default role |
|---|---|---|
| `u_ecal_view` | View the events calendar (board index widget and full calendar page) | `ROLE_USER_STANDARD` |
| `u_ecal_post` | Create events; edit/delete **own** events | `ROLE_USER_STANDARD` |
| `u_ecal_attend` | RSVP (attend/un-attend) event occurrences | `ROLE_USER_STANDARD` |
| `m_ecal_manage` | Edit/delete **any** event, regardless of owner | `ROLE_MOD_STANDARD` |
| `a_ecal_manage` | Access the Events Calendar ACP module (settings, Google sync, special dates) | `ROLE_ADMIN_STANDARD` |

Manage these under **ACP → Permissions → Permission roles/settings**, category **Events Calendar**. Because they're wired into phpBB's standard user/moderator/admin roles, most boards need no manual grants — adjust per-group or per-forum only if you want a different shape (e.g. a "read-only" group with `u_ecal_view` but not `u_ecal_post`).

---

## ACP guide

The ACP module lives at **ACP → Extensions → Events Calendar** (auth-gated by `a_ecal_manage`) and has three tabs:

### Settings

- **Board index display** — Off / upcoming-events list / mini calendar / both.
- **Upcoming events count** — how many events the upcoming-events list shows.
- **Reminder lead time (days)** — how many days before an event attendees are reminded; `0` disables reminders.
- **Discussion forum** — which forum auto-posted event topics go into; `0` disables topic creation.
- **Show member birthdays** — toggle birthdays on the calendar.
- **Calendar feed (ICS)** — enable/disable the feed, toggle public vs. secret-key access, and regenerate the secret key (instantly invalidates every previously issued private feed URL). The feed URL itself is shown here once the feed is enabled.

### Google sync

Configuration and controls for pushing events to a Google Calendar — see the [Google service-account walkthrough](#google-service-account-walkthrough-sync-to-a-google-calendar) below for the full setup. This tab also shows:

- **Last sync result** — the outcome of the most recent sync attempt.
- **Test connection** — verifies the service-account key and calendar ID by fetching the calendar's details from Google, without changing any data.
- **Resync all** — queues every current/future event (and every still-recurring event) to be pushed again; use after first configuring sync or after changing the target calendar.
- **Sync queue** — every pending push, with event, action (create/update or delete), attempt count, next retry time, and last error; each row can be retried immediately or discarded.

### Special dates

Add, edit, and delete board-wide special dates (holidays, anniversaries, etc.), each with a title, date, optional "repeats annually" flag, and colour tag. These appear on the calendar for every member with `u_ecal_view`, regardless of who created them — they are not tied to any user account and are not editable from the front end.

---

## Google service-account walkthrough (sync to a Google Calendar)

Google Calendar sync pushes events to a calendar you own; it does not read anything back. It uses a **service account** (a machine identity), not your personal Google login, so the board never needs your Google password or an OAuth consent flow.

1. **Create a project and a service account.** In the [Google Cloud Console](https://console.cloud.google.com/), create (or pick) a project, then enable the **Google Calendar API** for it (APIs & Services → Library). Under APIs & Services → Credentials, create a new **Service Account**.
2. **Create a JSON key.** On the service account's page, go to the **Keys** tab → **Add key** → **Create new key** → **JSON**, and download the file. Open it and copy its full contents.
3. **Paste the key into the ACP.** ACP → Events Calendar → Google sync → **Service account JSON key** — paste the whole JSON file contents. The stored key is write-only: once saved it's never shown again (the field just indicates "Configured ✓" or "Not configured"); leave it empty on future saves to keep the current key, or paste a new one to replace it.
4. **Share the target calendar with the service account.** In Google Calendar, open the settings of the calendar you want events pushed to → **Share with specific people** → add the service account's email address (the `client_email` field in the JSON key, looks like `something@your-project.iam.gserviceaccount.com`) → set its permission to **"Make changes to events."**
5. **Copy the Calendar ID.** Still in that calendar's settings, copy its **Calendar ID** (under "Integrate calendar," looks like `abc123@group.calendar.google.com`, or your own address for a primary calendar) and paste it into the ACP's **Calendar ID** field.
6. **Save, then test.** Save the settings, then click **Test connection** — it should report the calendar's name back. If it fails, double-check the key was pasted in full and that the calendar is shared with the exact `client_email` from the key.
7. **Enable sync and (if needed) resync.** Turn on **Enable Google Calendar sync** and save. New event creates/edits/deletes are queued automatically from then on and pushed by the board's cron. If you already have existing events you want mirrored, click **Resync all** to queue them too.

The board cron processes the sync queue in the background with retry/backoff on failures (see the **Sync queue** panel above to retry or discard individual entries by hand).

### Members: subscribing to the shared calendar

Once events are flowing into the Google Calendar, any member you've shared *that* calendar with (View permission is enough) can add it to their own Google Calendar via **Other calendars → Subscribe to calendar** and pasting its Calendar ID or public URL. This is entirely a Google-side share, separate from phpBB permissions — members don't need any `ecal_*` permission to subscribe to a calendar you've shared with them directly in Google.

---

## ICS calendar feed

Independent of Google sync, the extension can serve its own iCalendar (ICS, RFC 5545) feed of every event and special date, for subscribing from any calendar client (Google Calendar, Apple Calendar, Outlook, Thunderbird, etc.).

1. In the ACP, enable **Calendar feed (ICS)** under **Settings**.
2. Choose the access mode:
   - **Public feed** — the feed URL works for anyone who has it, no key required. Use this only if the calendar's contents are not sensitive.
   - **Private feed** (public toggle off) — the feed URL must include a `?key=` query parameter matching the secret key shown in the ACP. Rotate it any time with **Regenerate secret key**, which immediately invalidates every previously issued URL.
3. Copy the **Feed URL** shown in the ACP (e.g. `https://yourboard.example/app.php/calendar/feed.ics` for a public feed, or the same URL with `?key=<secret>` appended for a private one).
4. In your calendar app, use its "subscribe by URL" / "add calendar by URL" option and paste the feed URL. Most apps refresh subscribed feeds automatically every few hours; the board itself caches the generated feed for 15 minutes.

Recurring events are emitted as a single `VEVENT` with an `RRULE` — the subscribing calendar app expands the occurrences itself, exactly as it does for any other iCalendar feed.

---

## Notifications & reminders

When **Reminder lead time (days)** is set above `0`, any member attending an event's occurrence gets a phpBB notification that many days beforehand (delivered through whichever notification methods — board, email, etc. — that member has enabled for the "Upcoming calendar events" notification type, found under their own **Board preferences → Edit notification options**). Attendees are also notified when an event they're attending is updated or cancelled. Reminders and update/cancellation notices are processed by the board's cron, and email delivery uses the bundled `ecal_reminder` email template (available in English and French).

---

## License

GPL-2.0-only — see `license.txt`.
