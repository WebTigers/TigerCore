# Tiger — Comments, Ratings & Reviews

How a Tiger install attaches **discussion and ratings to anything** — a CMS page, a blog article, a
marketplace listing, a shop product, a user profile — through one core module that is **off by
default**. Read this before building the comment store, the subject registry, or a star widget. For
the platform *why* read [ARCHITECTURE.md](ARCHITECTURE.md); for the admin-screen template read
[ADMIN.md](ADMIN.md); for the `/api` contract read [WEBSERVICES.md](WEBSERVICES.md); for how a
marketplace consumes the aggregate read `TigerMarketplace/docs/design/reputation.md`.

> **Status: design of record — NOT built.** This records the decisions and their rationale so we
> don't relitigate them or drift when the code lands. Everything below is target behaviour.

---

## 0. The one principle

**A review IS a comment with a rating.** Not two features, not two tables, not two moderation
queues — one primitive with an optional score, attached to a subject.

Everything follows from that:

| What a user calls it | What it actually is |
|---|---|
| A blog comment | a comment, `rating = null` |
| A product review | a comment, `rating = 4` |
| A vendor's reply to a review | a comment, `parent_id` set, `rating = null` |
| A star rating with no words | a comment, `body = ''`, `rating = 5` |

One store, one moderation queue, one spam path, one admin screen, one `/api` service. If you find
yourself adding a `review` table, stop and re-read this section.

---

## 1. The shape — core module, off by default

- **`modules/comment`** (first-party, BSD-3, ships in tiger-core) — the feature: the `/api` service,
  the moderation admin, the shortcodes, the views.
- **`Tiger_Comment_*`** (library) — the substrate: the subject registry, the aggregate maths, the
  star renderer. Engine in the library, feature in a module — the same split as the CMS
  (ARCHITECTURE §3a).
- **Disabled by default** (`tiger.comment.enabled`, default `0`), like `/mcp`. Comments are a spam
  magnet and a standing moderation obligation; a brochure site should not get an open POST endpoint
  it never asked for. Turning it on is a deliberate admin act.

---

## 2. Attach to anything — the subject registry

The load-bearing abstraction. Core must never know what a "shop product" is, yet must render
*"Reviews of **Blue Widget**"* with a working link, gate who may post, and know whether stars even
apply. So a module **registers a subject provider**, exactly like `Tiger_Search` and
`Tiger_Audience` do for their surfaces:

```php
Tiger_Comment::registerSubject([
    'key'        => 'shop.product',          // the stored subject_type
    'label'      => 'Product',
    'resolve'    => [Shop_Service_Product::class, 'commentSubject'],  // id => ['title','url','exists']
    'resource'   => 'Shop_IndexController',  // ACL resource gating who may READ the thread
    'ratings'    => true,                    // may a comment here carry a star rating?
    'threading'  => 1,                       // max reply depth (0 = flat)
    'may_review' => [Shop_Service_Product::class, 'hasPurchased'],    // optional entitlement gate (§7)
]);
```

What the registry buys, and why free-string subject types are not enough:

- **Rendering** — a title and a URL for the moderation queue and for "your review of X".
- **Authorization** — the subject's own ACL resource decides who may read the thread; a comment
  never becomes a side channel to content someone can't see.
- **Capability** — `ratings` is per-subject. A blog post takes comments without stars; a product
  takes both. Core doesn't guess.
- **Orphans** — `resolve()` reports `exists`, so a cleanup job can find comments whose subject was
  deleted. Core does **not** cascade-delete on a module's behalf; it can't know the intent.

**Built-in providers** ship for `page` (CMS) and `blog.post`. Everything else is a module opting in.

---

## 3. Data model

Two tables. Standard columns throughout (ARCHITECTURE §7a).

### `comment` — the one primitive

| Column | Notes |
|---|---|
| `comment_id` | UUID v7 PK (time-ordered — a thread reads in creation order off the index) |
| `org_id` | tenancy |
| `subject_type` / `subject_id` | the polymorphic key. `subject_id` is `VARCHAR(191)`, not a UUID column — it has to hold a UUID, a TID, a slug or an integer id depending on the module |
| `parent_id` | reply threading; null = top level |
| `depth` | denormalized so a query can cap depth without recursion |
| `user_id` | null for a guest comment (when allowed) |
| `author_name` / `author_email` | guest identity only; a signed-in comment reads its identity live so a renamed user isn't stale |
| `body` | the text. May be empty when `rating` is set — a star-only rating is legitimate |
| `rating` | `TINYINT` 1–5, **nullable**. Null = a plain comment. This one nullable column is the whole review/comment distinction (§0) |
| `verified` | the entitlement flag (§7) |
| `status` | `pending` \| `approved` \| `spam` \| `rejected` |
| `ip` / `user_agent` | rate-limiting + abuse forensics |

Index on `(subject_type, subject_id, status, created_at)` — that is the thread query.

### `comment_aggregate` — denormalized, because a card cannot average N rows

One row per subject: `comment_count`, `rating_count`, `rating_avg` `DECIMAL(3,2)`, and
`star_1`…`star_5` for the histogram bars. Recomputed inside the transaction that approves, edits,
un-approves or deletes a comment.

**This is not premature optimization.** A marketplace grid renders 60 cards; without the aggregate
that is 60 `AVG()` queries, or a join that defeats the source-merge. The marketplace overlay
(`mkt_listing_meta`) already consumes exactly this shape.

---

## 4. Ratings — 5 stars, half-star visuals

- **Input is whole stars, 1–5.** Stored as `TINYINT`.
- **Display is half-star precision.** An average of `4.3` renders as 4 full stars + 1 half
  (`round($avg * 2) / 2`). This is what everyone means by "5 stars with half stars" — halves come
  from *averaging*, not from a half-star picker.
- **Why not half-star input:** it doubles the scale to 1–10 for no measured gain in signal, and
  every half-star UI is fiddly on touch. If it's ever wanted, store `TINYINT` 1–10 and divide by 2
  at render — a migration and a widget change, no schema redesign. Recorded so the door stays open.
- **The widget is pure markup + CSS** (Font Awesome `fa-star` / `fa-star-half-stroke` /
  `fa-star:regular`) — no build step, per the zero-build pillar.
- **Accessible**: the star row is `role="img"` with `aria-label="4.3 out of 5 stars"`, and the
  numeric average is always present as text next to it. Stars alone are not an accessible rating.

---

## 5. Surfaces

Following the registry-not-hooks convention (BACKLOG "Extension model"), everything is declarative:

- **View helpers** — `$this->stars($avg)`, `$this->commentThread($type, $id)`, so a theme is never
  forced through the shortcode path.
- **Shortcodes** — `[comments subject="page:42"]`, `[stars subject="shop.product:abc"]`,
  `[rating_summary …]` (average + histogram), `[review_form …]`. Registered on the existing
  `Tiger_Cms_Renderer` shortcode registry.
- **`/api`** — `comment/comment/{list,post,edit,delete,moderate}`, validate → transaction, ACL-gated
  per §2. The client is AJAX like every other Tiger surface; a comment form is not a page POST.
- **Admin** — a moderation queue (DataTables, server-side) with bulk approve/spam/delete, built per
  [ADMIN.md](ADMIN.md).

---

## 6. Moderation & abuse — the part that decides whether this is usable

An open comment endpoint is the most-attacked surface a CMS has. Non-negotiables:

- **`status` pipeline** with the default configurable: approve-first (`pending`) or post-first
  (`approved`), per install and overridable per subject type.
- **Sign-in required by default.** Guest commenting is a config opt-in, and when on it needs a name
  + email.
- **Rate limits** per user, per IP, per subject — the substrate the `login` audit log already
  models.
- **Honeypot + a time-trap** (a form rendered and submitted in under a second is a bot).
- **One rating per user per subject**, editable in place. A user cannot stack five reviews.
- **No self-review** — the subject's provider decides ownership; a vendor cannot review their own
  listing.
- **A pluggable spam check** (`Tiger_Comment_Spam` registry) so an Akismet-style module can slot in.
  Core ships the cheap heuristics, not a service integration.

---

## 7. The verified reviewer — Tiger's actual differentiator

Stars are commodity. What Tiger has that WordPress structurally does not is an **entitlement
oracle**: for a licensed module the authority knows who bought it; for a shop product the order
does; for a membership the grant does. So a provider may declare `may_review`, and a comment that
passes it is stamped `verified = 1`.

That supports the only claim in this space anyone actually values:

> *"Every review here is from someone who bought it."*

The gate is per-subject and optional — a blog post has no entitlement to check. Build the flag and
the provider hook with v1 even if only the shop uses it at first; retrofitting a trust flag onto
existing rows is the hard version.

---

## 8. How a marketplace uses it

`TigerMarketplace/docs/design/reputation.md` decides that the **origin marketplace owns the reviews**
for a listing. This module is *how* a marketplace hosts them: it stores the thread, computes the
aggregate, and the marketplace publishes `rating_avg` / `rating_count` / `comment_count` into
`mkt_listing_meta`, which already travels to a buyer's Module Manager through the feed.

Nothing about that contract changes. This module fills numbers that are currently always zero.

---

## 9. Rejected alternatives (so we don't relitigate)

| Rejected | Why | Chosen |
|---|---|---|
| Separate `review` and `comment` tables | two moderation queues, two spam paths, two admin screens, for one primitive | one `comment` table + a nullable `rating` (§0) |
| A `TigerReviews` add-on module | comments are a WP-parity **platform gap**; the free platform should have them, and a marketplace shouldn't be the only way to get them | a core module, off by default (§1) |
| Free-string `subject_type` with no registry | core can't render a title, can't ACL-gate, can't find orphans, can't know if stars apply | a subject provider registry (§2) |
| Computing averages on read | 60 cards = 60 aggregate queries | a denormalized `comment_aggregate` (§3) |
| Half-star input | doubles the scale for no signal; fiddly on touch | whole-star input, half-star *display* (§4) |
| On by default | hands every install an attacked endpoint and a moderation duty it didn't ask for | `tiger.comment.enabled`, default off (§1) |
| Cascade-deleting comments when a subject dies | core can't know a module's intent | providers report `exists`; a cleanup job reconciles (§2) |
| Bundling an Akismet integration | a paid third-party service in the free core | cheap heuristics + a spam-check registry (§6) |

---

## 10. Build order

1. **Substrate + store** — `Tiger_Comment` (subject registry) + the two tables + `Tiger_Model_Comment`
   / `_CommentAggregate`, with the aggregate recompute inside the write transaction.
2. **`/api` + moderation admin** — post/list/moderate, the status pipeline, rate limits, honeypot.
   At this point it is usable headlessly.
3. **Rendering** — the star widget (half-star display, accessible), view helpers, the four
   shortcodes, and the built-in `page` + `blog.post` providers.
4. **Verified reviewer** — the `may_review` provider hook + the `verified` flag + its badge.
5. **Marketplace wiring** — a `marketplace.listing` provider and the aggregate → `mkt_listing_meta`
   publish (§8).
6. **Later** — the spam-check registry's first real implementation, email notification on reply,
   comment subscriptions, import from WordPress (`wp_comments` maps almost 1:1).

---

## 11. Open questions

- **Editing window.** Let an author edit their own comment forever, or for N minutes? (Forever is
  friendlier; it also lets a 1-star review be quietly rewritten after a refund — a real pattern.)
- **Does an edited review re-enter moderation?** Leaning yes for the body, no for the rating.
- **Threading depth** for plain comments — flat, one level, or N? (Registry field exists; the
  default is the question.)
- **Do we surface `comment_count` separately from `rating_count`?** They differ whenever ratings are
  optional, and conflating them overstates engagement.
- **Guest ratings.** Sign-in-required is the default; is a guest *rating* (no body) ever acceptable,
  or is that just an open ballot box?

---

*This document records decisions and their rationale. If you change a decision, update the "why"
here in the same change.*
