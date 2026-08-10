# Changelog

## 1.0.0

First release.

- Purges the private messages of members whose last visit is older than a configurable period, through phpBB's own `delete_pm()` so folder counts, unread counters, notifications and attachments stay consistent.
- Optional message age filter, independent of the member inactivity period.
- Verified against all five databases phpBB supports.
- Both cutoffs accept either a rolling number of days or a fixed calendar date; messages additionally accept a date range with either end left open. Dates are read as UTC midnight, matching core's user pruning.
- Wording throughout makes clear that only messages are deleted and member accounts are never touched.
- Members who never logged in are measured from their registration date rather than treated as infinitely old.
- Undelivered and held messages are never touched; the outbox is opt in, because deleting a sender's unread copy blanks the message text for its recipients.
- Founders skipped by default, plus a configurable list of exempt groups.
- ACP page under Maintenance with a Preview that separates message copies removed from messages removed entirely, and a confirmed, batched Purge now.
- `pmpurge:run` console command with `--dry-run`, `--all` and `--limit`.
- Cron task that walks the member list a batch at a time, using a stored cursor so an exempt member cannot stall progress.
- Every run written to the admin log.
- Automatic purging off and dry run on after installation.
