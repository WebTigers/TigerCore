# Tiger — Selling on Tiger (the seller's guide + model)

How a **third-party seller** gets their modules — **free, paid, or both** — to show up in a Tiger
install's **Add Module** screen, and the licensing model underneath it. This is the *seller-facing*
companion to [MARKETPLACE.md](MARKETPLACE.md), which is the *protocol* + the *buyer-side* client. Read
MARKETPLACE.md for the wire details (signatures, the authority contract, the buyer lifecycle); read this
for the **seller journey** and the **build status** of the pieces.

> **Open by design.** Anyone can sell on Tiger — there is no central approval of sellers or listings, no
> WebTigers chokepoint. WebTigers runs *a* marketplace like anyone else can. The core's job is to make a
> buyer's trust decision *informed* (provenance, integrity, consent), never to gatekeep who sells. This
> doc describes that open model; it is **not** about any one vendor's commercial line.

**Status legend:** ✅ built · 🔨 prototype / in progress · ⬜ planned. Part A is the model (settled);
Part B is the concrete build with honest status — the "per-module licensing" work still to do.

---

# Part A — The model

## 0. The one principle

**Free is *reviewable*; paid is *informed trust*.** A free module lives in a public repo the buyer (and a
review bot) can read. A paid module's code isn't public, so the buyer instead trusts the vendor —
grounded in **provenance** (who published it), **integrity** (it arrived signed + unmodified), and
**consent** (a deliberate "add this" gate). Selling is **federated**: a buyer adds the marketplaces they
trust, exactly like adding a package source. See MARKETPLACE.md §0–§1.

## 1. The chain — from your module to a buyer's Add Module

A seller's module reaches a buyer through up to four hops. **Not all are required** — the minimum path
depends on whether the module is free or paid, and whether you run your own storefront:

```
Seller TigerShop  ──►  Seller TigerMarketplace  ──►  WebTigers.com Marketplace  ──►  Tiger "Add Module"
(your store +          [OPTIONAL: your own            (a marketplace source many        (the buyer's Module
 license authority)     catalog/storefront)            installs connect — one of N)      Manager Add screen)
```

| Hop | What it is | Needed when | State |
|---|---|---|---|
| **Seller TigerShop** | your **store**: checkout + the **license authority** that mints keys, signs artifacts, answers `/verify` + `/download` (TigerShop + TigerStripe for money + TigerLicense for keys) | selling a **paid/licensed** module | 🔨 prototype (dev-com: `/shop/authority`, `/checkout`) |
| **Seller TigerMarketplace** *[optional]* | your **own catalog/storefront** — a profiles + listings surface that federates, so a buyer can "Add" *your* marketplace as a source directly | you want your own branded storefront / to aggregate many of your listings | 🔨 phased (see `TigerMarketplace/SCOPES.md`) |
| **WebTigers.com Marketplace** | a central **`live-api` source** that aggregates many sellers' listings (free + paid); most installs connect it by default | you want the widest reach without running your own marketplace | ⬜ not built (runs on a `dev-marketplace.json` stub today) |
| **Tiger "Add Module"** | the buyer's Module Manager Add screen — **aggregates every connected source** (Directory + marketplaces), dedups by priority, badges each listing | always — this is where the buyer installs | ✅ built (`Tiger_Module_Registry` multi-source) |

**The minimum paths:**
- **A single free module** → a public repo + a listing in the **Directory** (`WebTigers/Vendors`, the free
  git-index source every install reads). No store, no marketplace. It appears in Add Module, badged *Free*.
- **A single paid module** → declare `pricing.model = licensed` + run a **license authority** (a small
  TigerShop). You're a *marketplace-of-one* — a buyer can paste your repo URL, or you get listed in a
  marketplace. No full TigerMarketplace needed (MARKETPLACE.md §6a).
- **A catalog of modules / a branded storefront** → run **TigerMarketplace** (optional) and/or get your
  listings into the **WebTigers.com Marketplace**.

## 2. The four ways a module is sold — `module.json` `pricing`

The seller declares how a module is sold in its manifest; **`Tiger_Module_Pricing`** is the one
interpreter, and the installer, the Add screen, and the license checker all read it. ✅ built.

| `model` | Who takes the money | Update-gated? | What the buyer sees in Add Module |
|---|---|---|---|
| **`free`** | nobody | no | *Free* — installs directly (reviewable public repo) |
| **`freemium`** | you, **off-platform** | no | *Free* to install; a "pro upgrade" link out (`pro_url`) |
| **`paid`** | you, **off-platform** | no | a *Buy* link out (`pro_url`); the platform isn't in the transaction |
| **`licensed`** | you, **through the Module Manager** against your `authority` | **yes** (a lapsed license withholds *updates only* — never disables) | *Buy* → checkout popup → key → signed install; update state shown |

```json
"pricing": {
  "model":     "licensed",
  "authority": "https://store.example/shop/authority",
  "vendor":    "acme/TigerVendor"
}
```

`free`/`freemium`/`paid` are declaration-only (no server needed). **`licensed` is the one that needs the
authority + the signed-artifact machinery** — that's the bulk of Part B.

## 3. How free and paid surface in the same Add screen

`Tiger_Module_Registry` reads an **ordered list of sources** and aggregates them into one Add screen
(MARKETPLACE.md §1a): the **Directory** (free, reviewable, git-index) + any **marketplaces** the admin has
connected (free *and* paid, `live-api`). A slug collision resolves by source **priority**; taxonomy is
unioned; a down source is skipped (last-good cache served) so the screen never hard-fails. Each card is
badged by its `pricing.model`, and the UI states the trade honestly: a **Directory** listing guarantees
"code is reviewable"; a **paid** listing relaxes that to "informed trust in the vendor" (MARKETPLACE.md §1,
§8). ✅ aggregation built · 🔨 paid badging + buy-flow UI (Part B).

## 4. Seller journeys (concrete)

### 4a. List a FREE module
1. Put the module in a **public repo** with a `module.json` (`pricing` omitted or `free`) + a `TIGER.md`.
2. Get it into the **Directory** (`WebTigers/Vendors` `index.json` — a serverless, forkable git catalog;
   community/bot-reviewed).
3. It shows up in every install's Add screen, badged *Free*, installable directly. Themes and code modules
   ride the same path ([THEMES.md](THEMES.md), [CODE.md](CODE.md)) — a theme is a module with `type:theme`.

### 4b. Sell a PAID (`licensed`) module
1. Declare `pricing.model = licensed` + your `authority` URL + your `vendor` (`owner/TigerVendor`).
2. Publish a **`[owner]/TigerVendor`** repo — your identity: `api_base`, your **Ed25519 `public_key`**, an
   optional catalog. Buyers pin this on connect (key fingerprint shown).
3. Run a **license authority** (a small **TigerShop** — MARKETPLACE.md §7): on payment, mint a
   **domain-bound key** (idempotently, decoupled from the payment processor); **sign each release once at
   publish**; answer `POST /verify` (signed short-TTL verdict) and `POST /download` (verify the license →
   mint a short-lived **signed** CDN URL — never proxy the bytes, the repo token never leaves you).
4. The buyer **buys** on your hosted checkout (a popup window — not an iframe), gets a key, and
   `installFromAuthority` streams the artifact from the CDN, **verifies the signature before extracting**,
   installs, and remembers the license. Updates re-check `/verify`; a lapsed license **withholds updates
   only** ("renew to update") — nothing is ever disabled.

### 4c. Run your own storefront *[optional]* — TigerMarketplace
A seller with many listings (or who wants a branded catalog) runs **TigerMarketplace**: a profiles +
listings surface that federates, so a buyer can **Add your marketplace as a source** and browse all your
modules in one place. Optional — you can sell without it (4b is a marketplace-of-one). Phased build in
`TigerMarketplace/SCOPES.md`; the factory model (every seller runs their own, keeps their revenue) is the
design intent.

### 4d. Get listed in the WebTigers.com Marketplace
Submit your listing to the **WebTigers marketplace** (a `live-api` source many installs connect by
default) for the widest reach — free listings community-reviewed, paid listings informed-trust. WebTigers
runs this like any other marketplace; it holds no special power over your keys or money (those stay with
your authority + your Stripe).

## 5. Trust, integrity, consent (the seller's obligations)

Everything a buyer needs to trust you is **provenance + integrity + consent**, all in MARKETPLACE.md:
signed artifacts (§4), a pinned public key (§3, silent key change = re-consent), a deliberate connect gate,
and **nag-never-disable** (§5, §8). A conforming **authority contract** is §7. Your obligations as a
seller: sign every release, keep your key stable, bind keys to domains, and never point a kill switch at a
customer's production (the platform won't let you — enforcement is soft by design).

---

# Part B — Build spec (per-module licensing — the work)

The taxonomy + the buyer half are built; the **seller/store half and the aggregation UI** are the "biggie."

## Built ✅
- **Pricing taxonomy** — `Tiger_Module_Pricing` (`free`/`freemium`/`paid`/`licensed`; `assertValid` on a
  `licensed` manifest).
- **Multi-source registry + Add-screen aggregation** — `Tiger_Module_Registry` / `Tiger_Module_Source`
  (Directory `git-index` + marketplace `live-api`, priority dedup, per-source cache, taxonomy union).
- **Buyer client** — `Tiger_License_Checker` (verify/`gate()`/`remember()`, `option`-tier store),
  `Tiger_Crypto_Signature` (Ed25519 sign/verify/`verifyFile`/`fingerprint`), `Tiger_License_Authority`
  (client for `/download`), `Tiger_Module_Installer::installFromAuthority` (verify-before-extract),
  `Tiger_Update_Checker` (nag-never-disable update gate).
- **The authority contract** — the protocol an authority must speak (MARKETPLACE.md §7).
- **Module dependency *detection*** — `Tiger_Module_Dependency` (`configs/dependency.ini` `[requires]
  modules[]`) + `missingReport` surfaced in the Modules admin.

## Prototype / in progress 🔨
- **Seller TigerShop authority** — dev-com serves a real `/shop/authority` + `/checkout`; the buyer
  (tiger-dev) points at them. Needs **productionizing** (real store, not the dev prototype) and hardening
  to the full §7 contract (idempotent key minting, publish-time signing, domain rebinding).
- **Paid/licensed UI in Add Module** — the screen aggregates sources; the **buy flow** (checkout popup →
  key → `installFromAuthority`), the **paid/licensed badges**, and the **update-state** surfacing need
  finishing/verification.
- **TigerMarketplace [optional] layer** — Phase-1 catalog done; rendering/taxonomy/federation phases open
  (`TigerMarketplace/SCOPES.md`).

## Planned ⬜
1. **WebTigers.com Marketplace as a real `live-api` source** — replace the `dev-marketplace.json` stub with
   a live catalog endpoint that serves the `{modules, taxonomy}` shape, enriched (ratings, downloads, a
   paid catalog). This is the "central marketplace" hop in §1.
2. **`requires.module` in the registry schema + a premium marker** (`WebTigers/Vendors`
   `schema/registry.v1.json`) — so a listing can declare a required module and mark itself premium, and the
   Add screen can show/resolve it.
3. **Installer resolver hook** — dependency *detection* exists; the install-time **resolve/offer** of a
   required module (fetch + install the dep during a marketplace install) is the gap.
4. **Seller onboarding docs** — turn Part A into public **TigerDocs** pages once the flow is real ("list a
   free module", "sell a paid module", "run your store").

## Recommended build order
1. Finish the **buyer-visible loop** first (it's closest): paid/licensed **badges + buy flow + update
   state** in the Add screen, against the existing dev-com prototype authority → a seller can list and a
   buyer can buy+install+update end to end.
2. **Productionize a seller store** (TigerShop authority to the full §7 contract) so 4b is real, not a
   prototype.
3. **Registry `requires.module` + premium marker + the resolver hook** (#2/#3 above) — unblocks
   dependency-carrying paid modules.
4. **WebTigers.com Marketplace live source** (#1) — the reach hop; do last since a marketplace-of-one
   (4b) already works without it.
5. **Seller docs** (#4) once 1–2 are shippable.

---

## Rejected alternatives (so we don't relitigate)
| Rejected | Why | Chosen |
|---|---|---|
| A central registry / approval of sellers | a chokepoint; couples the ecosystem to one party | open federation — add the marketplaces you trust (MARKETPLACE.md §0) |
| Force every seller through a WebTigers store | kills the "run your own, keep your money" model | seller runs their own authority + Stripe; WebTigers is *a* marketplace |
| Make `licensed` the only paid path | over-heavy for "here's a Buy link" | keep `paid`/`freemium` (off-platform) alongside `licensed` (through the Manager) |
| Disable a module on a lapsed license | a kill switch at a customer's prod | **nag, never disable** — withhold *updates* only |

## See also
[MARKETPLACE.md](MARKETPLACE.md) (protocol + buyer client) · [THEMES.md](THEMES.md) (themes are modules) ·
[CODE.md](CODE.md) (code modules) · `TigerMarketplace/SCOPES.md` (the optional storefront layer).
