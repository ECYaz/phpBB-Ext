# Changelog

## 1.0.1

Addresses the informational notes from the phpBB.com validation of 1.0.0.

* Console runs are now written to the admin log, one entry with the run's totals, the same way a manual run from the ACP is. The documentation promised this and the ACP and cron paths already did it.
* The console command is now `ecyaz:pmpurge:run` (was `pmpurge:run`), carrying the vendor prefix so no other extension can collide with it.
* The yes/no radio pairs and the automatic purge setting in the ACP form now carry the ids their labels point at, so clicking a label focuses its control. Removed the `placeholder` attribute from the three date fields, which browsers ignore on inputs of type date.
* license.txt now carries the Free Software Foundation's current Franklin Street address in the GPL 2.0 header.

## 1.0.0

First release.

* Purges the private messages of members whose last visit is older than a configurable period, through phpBB's own `delete_pm()` so folder counts, unread counters, notifications and attachments stay consistent.
* Optional message age filter, independent of the member inactivity period.
* Verified against all five databases phpBB supports.
* Both cutoffs accept either a rolling number of days or a fixed calendar date; messages additionally accept a date range with either end left open. Dates are read as UTC midnight, matching core's user pruning.
* Wording throughout makes clear that only messages are deleted and member accounts are never touched.
* Members who never logged in are measured from their registration date rather than treated as infinitely old.
* Undelivered and held messages are never touched; the outbox is opt in, because deleting a sender's unread copy blanks the message text for its recipients.
* Founders skipped by default, plus a configurable list of exempt groups.
* ACP page under Maintenance with a Preview that separates message copies removed from messages removed entirely, and a confirmed, batched Purge now.
* `pmpurge:run` console command with `--dry-run`, `--all` and `--limit`.
* Cron task that walks the member list a batch at a time, using a stored cursor so an exempt member cannot stall progress.
* Every run written to the admin log.
* Automatic purging off and dry run on after installation.
