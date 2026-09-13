<!-- tiger:doc
title: Messages
order: 45
visibility: admin
-->

# Messages

Your inbox, under **My Account → Messages**. Two kinds of message arrive there:

- **From Tiger itself** — the platform telling you something an operator should know: a backup did not
  complete, an update is waiting, a payment webhook is misbehaving. These always reach every
  administrator and cannot be blocked.
- **From people in your organization** — if the site has turned that on.

The bell in the header shows how many are unread.

## Sending

Administrators can always message members of their organization. For anyone else to send, an operator
sets `tiger.message.user_to_user = 1`; it is off by default, because most sites are not a social network
and should not silently become one.

Start typing a name in **To** to add a recipient. Only active members of your own organization appear.

## Your inbox is yours

**Archive** files a message out of the inbox; it still counts as unread until you open it. **Delete**
removes *your* copy only — other recipients keep theirs. Opening a message marks it read.

## Blocking

**Block sender** stops that person's messages reaching you. They are not told, and their messages
appear to them to have been sent. You can see and undo your blocks under **Blocked**.

You cannot block an administrator, and messages from Tiger itself cannot be blocked.
