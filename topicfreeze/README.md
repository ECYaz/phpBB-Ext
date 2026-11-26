# Topic Freeze for phpBB 3.3.x

A phpBB extension that gives moderators a middle ground between leaving a topic
open and locking it. A frozen topic stays open for replies, but every reply
from a member waits in the moderation queue until a moderator approves it.

It is meant for discussions that a few members keep derailing. Locking such a
topic stops everybody, and the argument usually restarts in a new topic.
Freezing it lets the useful replies through while the rest never appear.

## What a freeze does

* Replies from members go to the moderation queue. The member sees the usual
  "your post needs approval" message and moderators get the usual queue
  notification.
* Anyone who can approve posts in that forum replies as normal.
* Members cannot edit, delete or bump posts in a frozen topic, the same way
  phpBB treats a locked topic. That includes deleting the attachments of those
  posts from the User Control Panel. Moderators keep their tools.
* Poll voting stays open.
* A notice at the top of the topic, above the quick reply box and on the reply
  form explains that the topic is frozen. Frozen topics carry a snowflake icon
  in the topic list.
* Freezing and unfreezing are written to the moderator log.

Freezing and locking are independent. A topic that is both frozen and locked
behaves as locked.

When a frozen topic is split, the new topic starts unfrozen. When topics are
merged, the destination topic keeps its own state. A moved topic stays frozen
and a copied topic keeps the state of the original.

## Requirements

phpBB 3.3.2 or higher on PHP 7.1.3 or higher.

## Download & install

1. Download [`topicfreeze.zip`](https://github.com/ECYaz/phpBB-Ext/raw/main/topicfreeze.zip)
   and unzip it into your board's `ext/` directory (it unpacks to
   `ext/ecyaz/topicfreeze/`), or copy this folder to `ext/ecyaz/topicfreeze/`.
2. Enable it via *ACP → Customise → Extensions → Topic Freeze*.
3. After enabling (or any change), purge the cache via *ACP → General → Purge
   the cache*.

## Usage

Open a topic and pick *Freeze topic* from the quick moderation tools at the
bottom of the page. Pick *Unfreeze topic* there to lift the freeze.

The actions are offered to anyone holding the moderator permission *Can freeze
and unfreeze topics*. Enabling the extension grants it to the Full Moderator
and Standard Moderator roles. You can change that under *ACP → Permissions*.

## How it works

The extension adds one column, `topic_frozen`, to the topics table and one
moderator permission, `m_topicfreeze`. A listener on
`core.modify_submit_post_data` marks replies to a frozen topic as unapproved,
and phpBB's own approval queue does the rest. No core files are modified.
Deleting the extension's data removes the column and the permission again.

## License

[GPL 2.0 only](license.txt)
