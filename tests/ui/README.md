# tests/ui — functional user tests

AI-driven functional testing for the Tiger UI. Three layers, each answering a different question.

| Run | Answers | Cost |
|---|---|---|
| `php tests/ui/bin/paradigm-scan.php` | does the CODE follow the client/server paradigm? | milliseconds, every view |
| `php tests/ui/bin/auth-probe.php` | does the AJAX/JSON contract behave? | seconds, no browser |
| the `tiger-ui-probe` skill + Playwright MCP | does it WORK for a user? | a real browser |

Browser work is recorded as **scenarios** under `scenarios/` — `auth.json` (sign in, failed sign
in, reset, sign out) and `admin-grids.json` (every admin data grid actually draws). Each scenario is
intent-level with no selectors, and carries a `verified` line naming what the last real run saw.

The static scan is exhaustive but can only see code; the browser proves live behaviour but only
on pages someone wrote a scenario for. Run both — that gap is where the TigerImage studio bugs
lived (three defects that all passed a green PHPUnit suite because nothing ever rendered).

## Dev only

`config.json` carries `never_target`, both probes refuse a production base URL, and the Playwright
MCP server is launched with `--blocked-origins` covering every production host. Negative-path
scenarios use `@example.invalid` identifiers so a run can never trip a real account's lockout.

## Credentials

Copy `credentials.local.json.dist` to `credentials.local.json` (gitignored) and fill in a **dev**
test account. Without it the authenticated scenarios are skipped; everything anonymous still runs.

## Seed first, or a grid assertion means nothing

```
sudo -u apache php tests/ui/bin/seed-dev.php \
    --base=https://tiger-dev.webtigers.com --email=uiprobe-super@tiger.test --apply
```

An **empty grid and a broken grid are the same picture** — that is how three grids shipped serving
HTTP 200 with no rows and no console error (TIGER-390). If the probe account's org owns no content,
every grid looks like those three and the only way to tell them apart is a SQL query, which a
scenario cannot run. The seeder gives that org one page, one article, one menu, one (inactive)
snippet and one subscriber, all labelled `UI Probe` and on the reserved `.invalid` domain.

It is a **dry run** unless you pass `--apply`, it is idempotent, and it refuses to run without
`--base`; a `--base` named in `never_target` is refused, and so is a missing `never_target` list.
That guard is explicit on purpose: an install cannot tell you which site it is — on tiger-dev,
`APPLICATION_ENV` is literally `production`, `tiger.site.url` is unset and `gethostname()` returns
an EC2 internal name — so the operator names the target and the list refuses the forbidden ones.

## Exit codes

Both probes exit non-zero on a real failure, so either drops into a script when you want that.
A check that could not run reports **GATED**, not PASS — a vacuous green is worse than no check.
With reCAPTCHA switched off on the test install (`tiger.recaptcha.enabled = 0`) nothing is gated;
see the probe's own footer.

## Scope — scenarios, by decision

These are **scenarios, run on demand.** There is deliberately no CI job, no promoted-spec
directory and no Playwright suite to maintain: the cost of a regression harness is ongoing and
the value here is finding real defects on real screens, which is what an on-demand pass does.

So a confirmed finding becomes a **Jira ticket** (labelled `functional-user-test` + `ui-probe`),
not a test file. Where a whole CLASS of defect turns out to be statically detectable, the cheap
exhaustive home for it is a check in `paradigm-scan.php` — one scan, every view, milliseconds —
rather than a scenario per screen.
