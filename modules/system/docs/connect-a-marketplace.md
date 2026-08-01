<!-- tiger:doc
order:      20
title:      Connect a marketplace
visibility: admin
-->

# Connect a marketplace

The **Add Module** screen browses more than one catalog. Out of the box it reads the WebTigers directory and
the WebTigers marketplace, but you can add **any** marketplace or directory you trust — and manage them all
in one place.

**Add Module → Marketplaces.**

## What you'll see

A table of every source the screen aggregates, with a badge for where each came from:

- **Built-in** — the sources Tiger ships. You can disable or re-order them, but not remove them.
- **Module** — a source a module you installed brought with it. Manage it here, or deactivate the module to
  remove it.
- **Connected** — a marketplace you added yourself. Fully yours: enable, re-order, or remove.

Each row has:

- **Priority** — lower is checked first and wins when two sources list the same module. A marketplace with a
  lower number can *enrich* (add ratings, promotion, a paid catalog to) a listing that also appears in a
  plain directory.
- **On** — a switch to enable or disable the source without removing it.
- **Remove** — deletes a *connected* marketplace (built-in and module sources show a dash — disable them
  instead).

Changes take effect immediately.

## Connect one

Under **Connect a marketplace**:

1. **Name** — what it's called in the list (e.g. *Acme Market*).
2. **Index URL** — the catalog's address. This is a single URL the vendor gives you that returns their Tiger
   catalog. It can be a static file (a **Directory**) or a live endpoint (a **Marketplace**).
3. **Type** — *Marketplace* for a live vendor endpoint, *Directory* for a static `index.json`. If unsure,
   the vendor will tell you; *Marketplace* is the safe default for a hosted store.
4. **Connect.** The new source appears in the table and its modules show up in **Browse** right away.

## Is it safe?

Adding a marketplace is a trust decision, so make it deliberately:

- A source lists modules whose **code runs on your server** once you install them — add only marketplaces
  you trust, the same way you'd trust a package source.
- You're never installing blind: opening a module shows its repository and its `TIGER.md` **before** you
  install, and a **paid** module must arrive **digitally signed** — Tiger verifies it against the vendor's
  key before it's unpacked.
- Removing a marketplace doesn't touch anything you already installed from it — those modules keep running.

## Troubleshooting

- **A connected source shows no modules.** Its endpoint may be unreachable or not returning a valid catalog.
  The screen skips a down source rather than failing — check the URL with the vendor, and use **Refresh
  directory** (the ↻ button on Browse) to re-fetch.
- **Two sources list the same module.** The lower **Priority** wins; adjust the numbers if the wrong one is
  showing.
- **I can't remove a source.** Only *Connected* marketplaces can be removed. Disable a *Built-in* one, or
  deactivate the module that provides a *Module* one.
