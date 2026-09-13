# AGENTS.md — working on the `message` module

> **Step 0 — check core before you build.** Before writing any mechanism, grep
> [`../../CAPABILITIES.md`](../../CAPABILITIES.md) — a generated, CI-checked index of every `Tiger_*` class and module.
> Core probably already has it. TigerImage once reimplemented `Tiger_View_Helper_I18n` by hand because this
> hop was skipped, and lost a property core had designed in. **Assume a capability exists until you have
> grepped the index and confirmed it doesn't.**

Instructions for an AI assistant (or a new contributor) working on Tiger's **message** module. For platform
conventions read the root **AGENTS.md**. This file is the module-specific layer.

> In-app messaging (TIGER-114). **App → admins** is the primary surface and is always on: a module that
> needs to tell an operator something calls `Tiger_Message::toAdmins()` and it lands in every admin's inbox
> with the header bell lit. **Person → person** is the optional surface and is OFF until an operator sets
> `tiger.message.user_to_user = 1` — most sites are not a social network and should not silently become one.

## The rules — enforced in the SERVICE, never only in a screen

`/api` is the same surface an agent drives, so a rule a screen enforces alone is not a rule.

- **Org-scoped.** Every recipient must be an active member of the caller's org. Another org's member is
  "unknown", not "forbidden" — existence is private too.
- **Who may compose.** Admin-or-higher always; anyone else only when `user_to_user` is on. This is a
  question about the ROLE — use `Tiger_Message::isAtLeastAdmin($identity->role)`, **not `_isAdmin()`**,
  which asks "is this role allowed on this service" and every signed-in user is (that is how they read
  their inbox). Getting this wrong let plain users send with the flag off; a test now pins it.
- **Blocks are silent.** Applied per recipient at send time; the sender's call reports success even if
  every recipient dropped it. Telling them turns a mute into a confrontation.
- **System messages ignore blocks.** An operator must not be able to make the platform unable to reach them.
- **Cannot block admin-or-higher — in THIS org.** Role lives on `org_user`, not `user`; ask the
  membership. Seniority comes from the live ACL chain (`Zend_Acl::inheritsRole`), not a hard-coded list.
- **Read / archive / delete act on the caller's COPY.** The message row and other recipients' copies are
  never touched. Deleting is a soft-delete on `message_recipient`.
- **Only a recipient or the sender may read a message.** Same org is not enough; an admin cannot read a
  message not addressed to them. (Decision taken in v1: private channel, not a moderated one. If
  moderation is wanted it is an explicit feature, not a side effect.)

## Where things live

- `library/Tiger/Message.php` — the facade other modules call (`toAdmins`, `toUser`, `isUserToUserEnabled`,
  `isAtLeastAdmin`). Fail-soft: a message is never the reason a backup job throws.
- `library/Tiger/Model/Message*.php` — three tables (migrations 0047–0049). One body row per message;
  one `message_recipient` row per person (read/archive/delete state); `message_block` per (org, user,
  blocked). Named finders only — a service never builds a `$db` predicate.
- `services/Message.php` — the `/api` surface. `controllers/IndexController.php` extends
  `Tiger_Controller_Account_Action` (the **/account** surface: every member has an inbox).
- `configs/navigation-account.ini` — the account-menu item (zero code). `Bootstrap.php` — the header
  bell, registered with a `badge` callable (see below).

## The header bell

`Tiger_Admin_Header::register([... 'badge' => fn () => $count])`. The badge is a **callable**, resolved at
render, after the ACL filter, for the signed-in user. It is one indexed `COUNT(*)` on
`message_recipient (user_id, read_at, deleted)` — cheap enough for every admin page. `badgeCount()` is
fail-soft: a throwing callable renders no badge, never a broken header. The old hardcoded demo bell
(fake count of 3, invented alerts) is gone; this replaced it.

## Conventions that bit

- Strings reach the JS through core's `$this->i18n([...])` + `Tiger.t()` — aliases, never full keys.
- `activeSelect()` hardcodes the unaliased table name in its `deleted` filter. A finder that aliases the
  table (`from(['m' => …])`) must use `select()` and write `m.deleted = 0` itself.
- `username` is nullable; display names are `COALESCE(u.username, u.email)`.
- Seven locales (en/es/pt/hi/de/fr/tlh); `tlh` is English fallback, as the other core modules ship it.

## Tests

`tests/Integration/Message/MessageServiceTest` — 20 tests, every rule above, against a real schema.
Mutation-tested: ten policy mutations (block ignored, block not silent, admin blockable, tenant check
dropped, anyone-may-read, delete-hits-the-row, flag ignored, toAdmins-reaches-everyone, manager-is-admin,
open-doesn't-mark-read) all fail. `tests/Unit/Admin/HeaderBadgeTest` covers the badge.
