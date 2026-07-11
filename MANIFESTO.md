# The Abstraction Tax

### Why the web got slow — and PHP didn't

The web got slow. Not because the computers got slower — they got ~100× faster. Not because the
languages got slower — they got faster too. The web got slow because **frameworks got fat**, and
somewhere around 2006 the whole industry quietly agreed not to notice.

## The trade nobody voted on

Modern web frameworks made a deal on your behalf: trade **runtime simplicity** for
**developer-convenience abstraction**. It sounded free. It wasn't. You pay it on every single
request:

- **Dependency-injection containers** that assemble a graph of the entire application… to render one page.
- **ORMs** that hydrate rich objects, fire lazy queries, and materialize rows you never read.
- **Middleware pipelines** ten layers deep, each one wrapping the last.
- **Reflection and metaprogramming** resolving at runtime what a compiler resolves once.
- **Kernels, providers, bootstrappers** — a cathedral of indirection between the request arriving and *your* code running.

None of it is free. It's just **invisible** — until someone puts a stopwatch on it. Then the
"negligible" overhead turns out to be most of your latency.

## The tax is real, and it's measurable

We measured it. A Tiger page — real routing, real work, not a "hello world" — renders server-side
in **~10 ms**, flat across the median, the 95th, and the 99th percentile. The heavyweight stacks —
Laravel, Django + ORM, Next.js SSR — spend **several times longer** generating the equivalent page,
and most of that time isn't *your* code. It's the plumbing, running before your code gets a turn.

*(Honest footnote, because we're not liars: the 10 ms is measured on a modest box, warm, PHP 8 +
OPcache. The multipliers are typical per-request generation times for comparable pages against
framework defaults — not a controlled head-to-head. Run your own. A claim you can reproduce is the
only kind worth making.)*

## It's not the language. It's the complexity.

Here's the part that turns a flame war into a fact: **this was never about PHP vs. Python vs. Node.**
It's lean vs. fat.

Look at the fast option in *every* ecosystem:

- The fast Python is **FastAPI** — lean.
- The fast Node is **bare Express** — lean.
- The fast Ruby, the fast Java, the fast anything — always the thin one.

And the instant any of them bolts on the batteries-included framework — Django, NestJS, the full
Rails — the tax reappears. The lesson is universal:

> **Simplicity beats abstraction at runtime. In every language.**

Python isn't slow; Django's architecture is expensive. Node isn't slow; Nest's is. The languages
are fine. The plumbing is the problem.

## The "outdated" architecture was the correct one

Which brings us to the punchline the industry will hate: the model everyone left for dead was
**right all along.**

Tiger runs on TigerZF — Zend Framework 1, modernized for PHP 8. And it's fast for boring,
unglamorous, *architectural* reasons:

- **Shared-nothing, process-per-request.** No long-lived state to bloat, no cross-request leaks.
  OPcache makes the "boot" nearly free.
- **A thin MVC.** A service you *call* — `Module_Service_Thing::method($params)` — not a container
  that assembles the universe to go find it.
- **No build step.** No transpile, no bundle, no cold start. `composer install` and it runs.
- **Extend by adding, not by wrapping.** Modules plug in; they don't swaddle the kernel in another
  layer of indirection.

ZF1 didn't *get* fast. **It never got fat.** It sat out the complexity arms race, and when the dust
settled, the "grandpa" framework was the one still doing 10 ms while everyone else shipped multiples
of that — and calling it progress.

## The bet

Tiger's bet is the same one PHP quietly made and never bragged about: **do less per request, and the
request is fast.** Give developers a full multi-tenant SaaS substrate — auth, orgs, roles, theming, a
clean `/api` — *without* charging a runtime tax for the privilege. All the plumbing, none of the
plumbing's plumbing.

The web doesn't have to be slow. It chose to be.

You don't have to.

---

*The Tiger performance thesis. The numbers are reproducible — that's the whole point. See
[ARCHITECTURE.md](ARCHITECTURE.md) for how the platform is built, and [FEATURES.md](FEATURES.md) for
what it does.*
