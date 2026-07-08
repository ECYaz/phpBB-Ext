# Changelog

## 1.0.1 - 2026-07-08

### Changed

- The "Calendar" link in the header navigation bar now shows a calendar icon (Font Awesome `fa-calendar`), matching prosilver's icon convention.

## 1.0.0 - 2026-07-07

First stable release.

### Added

- Board-index and full-page calendar: month grid, year overview, day view, and free-text event search with a date-range filter.
- Event CRUD (`u_ecal_post`) with title/description (BBCode), start/end, all-day toggle, colour tag, and recurrence (none/daily/weekly/monthly/annually with an optional "repeat until" date); moderators/admins (`m_ecal_manage`) can edit or delete any event.
- Optional auto-posted discussion topic per event, created in a configurable forum.
- Per-occurrence RSVP (`u_ecal_attend`) with an attendee list, so a single recurring event tracks attendance separately for each date.
- Member birthdays surfaced on the calendar alongside events (no data of their own — read from existing profile fields).
- ACP-managed board-wide special dates (holidays, anniversaries, etc.), one-off or annually recurring, with their own colour tag.
- Notifications: attendees get a reminder a configurable number of days before an event they're attending, plus update/cancellation notices; delivered through phpBB's normal notification methods (board + email), with a dedicated email template.
- iCalendar (ICS, RFC 5545) feed of all events and special dates for subscribing from an external calendar client — public or secret-key-gated, with a regenerate-key control and a 15-minute server-side cache.
- One-way Google Calendar sync: events are queued and pushed to a Google Calendar via a service-account key, processed by the board cron with retry/backoff and a manual sync-queue in the ACP (retry/discard per entry), plus "Test connection" and "Resync all" actions and an in-ACP setup walkthrough.
- Full ACP: general settings (board-index display mode, upcoming-events count, reminder lead time, discussion forum, birthdays toggle, ICS feed), Google sync settings, and special-dates management.
- Five permissions (`u_ecal_view`, `u_ecal_post`, `u_ecal_attend`, `m_ecal_manage`, `a_ecal_manage`) wired into phpBB's standard user/mod/admin roles by default.
- Complete English and French language packs, including ACP, permissions, and email templates.
- 178 PHPUnit tests (unit + functional) covering recurrence expansion, RSVP, notifications, the ICS feed, and Google sync, plus clean install/purge migrations and a Docker-based dev harness.
