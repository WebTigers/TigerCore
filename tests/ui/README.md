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

Both probes exit non-zero on a real failure, so they drop straight into CI or a cron.
A check that could not run (e.g. reCAPTCHA refused a tokenless client) reports **GATED**, not
PASS — a vacuous green is worse than no check.

## Promotion

A confirmed finding becomes a spec in `regression/` so it cannot come back silently.
