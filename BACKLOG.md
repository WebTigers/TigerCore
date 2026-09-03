# Tiger — Backlog

**Tracked in Jira: [TIGER project](https://webtigerscom.atlassian.net/jira/software/projects/TIGER/boards/1).**

The backlog moved out of this file on 2026-09-03. It spanned several repos, which meant the only way
to see the whole picture was to sweep them all by hand — which is how the launch inventory got made,
and not a thing worth repeating.

## What lives where now

| | |
|---|---|
| **Backlog, bugs, planned work** | Jira — the [TIGER project](https://webtigerscom.atlassian.net/jira/software/projects/TIGER/boards/1) |
| **Design of record — the *why*** | stays here, in this repo |
| **Shipped capability** | [FEATURES.md](FEATURES.md) |
| **Changelog** | git history + [CHANGELOG.md](CHANGELOG.md) |

Design docs are deliberately NOT in Jira. A ticket says what to do; these say why it was decided that
way, and they belong next to the code they describe:

[ARCHITECTURE.md](ARCHITECTURE.md) · [ACL.md](ACL.md) · [INSTALL.md](INSTALL.md) ·
[DEPENDENCIES.md](DEPENDENCIES.md) · [THEMES.md](THEMES.md) · [CODE.md](CODE.md) ·
[COMMENTS.md](COMMENTS.md) · [MARKETPLACE.md](MARKETPLACE.md) · [SELLING.md](SELLING.md) ·
[TIGERAGENT.md](TIGERAGENT.md) · [TIGERSKILLS.md](TIGERSKILLS.md) · [TIGERMCP.md](TIGERMCP.md) ·
[WEBSERVICES.md](WEBSERVICES.md) · [ROUTING.md](ROUTING.md) · [ADMIN.md](ADMIN.md)

**When something ships:** close the Jira issue and, if it's a user-facing capability, add it to
FEATURES.md. Don't re-open a markdown backlog here.
