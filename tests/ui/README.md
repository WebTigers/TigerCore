# tests/ui — functional user tests

AI-driven functional testing for the Tiger UI. Three layers, each answering a different question.

| Run | Answers | Cost |
|---|---|---|
| `php tests/ui/bin/paradigm-scan.php` | does the CODE follow the client/server paradigm? | milliseconds, every view |
| `php tests/ui/bin/auth-probe.php` | does the AJAX/JSON contract behave? | seconds, no browser |
| the `tiger-ui-probe` skill + Playwright MCP | does it WORK for a user? | a real browser |

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
