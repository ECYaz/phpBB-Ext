# Old PMs Purger

Purges the private messages of members who have not visited for a long time, on the board's cron, from the ACP, or from the command line.

Built for phpBB **3.3.x**.

## Why it needs care

phpBB stores a private message body once in `phpbb_privmsgs`, however many people it was sent to, and one row per participant in `phpbb_privmsgs_to`. The body can only go when the last participant's copy goes. Deleting rows by hand also leaves folder counts, unread counters, notifications and attachments behind.

So this extension does not write to those tables at all. It selects the copies that match your settings and hands them to phpBB's own `delete_pm()`, which owns all of that bookkeeping, including deleting attachment files through the attachment manager.

The practical consequence is worth knowing before you run it: **purging one side of a conversation frees a row, not the message text.** You only reclaim real space where every participant in a message is inside the selection. The Preview screen reports both numbers separately for exactly this reason.

## What it does

- Selects members whose last visit is older than a configurable period.
- Optionally restricts to messages older than their own configurable age.
- Optionally includes members who never logged in, measured from their registration date rather than treating an empty last visit as infinitely old.
- Never touches undelivered or held messages: a message the recipient has not yet received stays.
- Leaves the outbox alone by default (see below).
- Skips founders, and any groups you list.
- Writes every run to the admin log.

## Install

1. Download [`pmpurge.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/pmpurge.zip) and unzip it into your board's `ext/` directory (it unpacks to `ext/ecyaz/pmpurge/`), or copy this folder to `ext/ecyaz/pmpurge/`.
2. ACP → Customise → Manage extensions → enable **Old PMs Purger**.
3. ACP → Maintenance → **Old PMs Purger**.

Requires `a_userdel` (the same permission as core's Prune users).

## Using it

Automatic purging is **off** and dry run is **on** after installation. That is deliberate: look at a Preview before you let it delete anything.

- **Preview** counts what your settings select and changes nothing.
- **Purge now** works through the whole member list in batches, continuing across page loads.
- On a board with years of messages, prefer the command line, which has no request timeout over it:

```
php bin/phpbbcli.php pmpurge:run --dry-run --all
php bin/phpbbcli.php pmpurge:run --all
```

`--limit=N` overrides the members-per-batch setting for one run.

Once you are happy with the numbers, set **Purge automatically** to Yes and **Dry run** to No, and the board's cron will keep it tidy a batch at a time.

## The outbox setting

The outbox holds sent messages nobody has read yet. When a sender deletes such a message, phpBB blanks its text for the recipients as well. Including the outbox in a purge therefore risks emptying a message an active member still has waiting, so it is off by default. Turn it on only if you understand that trade.

## Settings

Each of the two cutoffs can be expressed either way:

- **A rolling number of days** — "members inactive for 1095 days", "messages older than 365 days". Keeps meaning the same thing as time passes, which is what you want for the cron.
- **A fixed calendar date** — "members who last visited before 2020-06-01", "messages sent between 2005-01-01 and 2015-12-31". Right for a one-off sweep. Either end of the message range may be left empty for an open bound.

Dates are read as UTC midnight, the way core's own user pruning reads them, so the same setting selects the same messages regardless of the administrator's timezone. A fixed date does not move, so a cron left on one will slowly stop matching anything new.

| Setting | Default | Notes |
|---|---|---|
| Members inactive for | 1095 days | Three years. Or a "last visited before" date. |
| Which of their messages | 0 days | 0 purges every message a selected member holds. Or a date range. |
| Include members who never logged in | Yes | Measured from registration date. |
| Never purge founders | Yes | |
| Never purge these groups | *(empty)* | Comma separated group IDs. |
| Include the outbox | No | See above. |
| Purge automatically | No | Cron. |
| Dry run | Yes | Report only. |
| Run at most once every | 86400 s | |
| Members per run | 25 | |

## Testing

The extension ships unit/database and functional tests. With the phpBB Docker harness:

```
./.phpbb-harness/harness sniff
./.phpbb-harness/harness epv
./.phpbb-harness/harness test
```

The database tests cover the selection rules against a seeded board, including the message that survives because one participant is still active, the undelivered copy that is never touched, custom folder counts, unread counters, attachment deletion, exempt groups, and batching.

## Licence

GPL-2.0-only. See `license.txt`.
