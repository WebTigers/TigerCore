# Tiger on shared cPanel — the runbook

**The step-by-step an agent follows to get Tiger running on a shared cPanel account, and the short list
of things it must hand back to the human instead.** Written for an AI client driving a real install
(TIGER-88), and equally usable by a person following along.

This is the *runbook*. Two sibling docs cover the other angles, and this one deliberately does not
repeat them:

| Doc | Answers |
|---|---|
| [INSTALL.md](INSTALL.md) | **What a host needs** — PHP, database, web server, filesystem, the preflight |
| [INSTALLER.md](INSTALLER.md) | **How the installer is designed** — the spec and the reasoning behind it |
| **CPANEL.md** (this) | **What you actually do, in order, on a real account** |

Worked example throughout: a **3AO** account (`3AO Unlimited` package, `ea-php81`, WHM at
`host3.3ao.com:2087`, cPanel at `:2083`). Any cPanel host works the same way; 3AO is simply the one
these steps were proven on.

---

## 0. The one rule: know what access each step needs

Steps are gated by **what session or privilege they require**, not by who is at the keyboard. The
distinction matters: an agent driving the user's logged-in cPanel browser session can click through the
UI exactly as a person would, while a headless or API-only client cannot.

| Step needs | Agent with the user's **cPanel session** | Headless / API-only client |
|---|---|---|
| **Nothing** — the installer wizard itself | does it | does it |
| **An authenticated cPanel session** — create the database (§4), add a domain or subdomain (§1), run AutoSSL (§3) | **can do it** | **hand back** with exact instructions |
| **WHM / reseller** — create the account (§2) | hand back, unless the user is a reseller | hand back |

Why the middle row is walled off from the *installer* even though a session can reach it: a user-space
PHP script is denied `CREATE DATABASE`/`CREATE USER` — cPanel owns provisioning and tracks its own
prefixed names (INSTALLER.md §4) — and AutoSSL has no user-space entry point at all.

Two rules hold in every column:

- **Never ask the user to paste a cPanel API token** to route around a missing session. That trades a
  thirty-second click for a security ask.
- **Never fake a result you could not achieve.** If AutoSSL was not run, the site is HTTP — say so
  plainly rather than describing it as secure.

---

## 1. Before you start — what to confirm

Confirm all of these before touching anything. Each has cost a real install when skipped.

- **PHP ≥ 8.1.** cPanel → *MultiPHP Manager*, per-domain. Tiger's floor is `>=8.1`; the installer's
  preflight refuses below it. 3AO's default is `ea-php81`.
- **`mbstring` loaded**, and `zip` or `phar` for extraction. The preflight reports precisely what is short.
- **The domain resolves publicly.** `dig +short A <domain>` must return the server's IP. This does not
  block the install, but it blocks **AutoSSL** (§3) and any inbound webhook. A domain that returns
  `NXDOMAIN` is unregistered; one that returns `SERVFAIL` is registered but its nameservers are not
  answering — different problems, different fixes.
- **You can reach the account's file manager or FTP.** No shell: uploading is how the installer arrives.

### Where is Tiger going?

Not every install owns the account. Tiger may be going in **beside a site that is already running** —
on a subdomain (`app.example.com`) or a second domain in the same cPanel account. Settle it here,
because it decides which hostname gets the certificate (§3), which database is created (§4), and which
directory the installer is uploaded into (§5).

| Case | In cPanel | Docroot |
|---|---|---|
| Tiger **is** the site | nothing to create | `public_html` |
| Tiger on a **subdomain** | *Domains* → **Create A New Domain** | `public_html/app` (proposed) |
| Tiger on **another domain** on the account | same screen | `public_html/<domain>` |

Adding the domain needs an **authenticated cPanel session** — the same column as creating a database.
Accept the document root cPanel proposes unless there is a reason not to.

A subdomain of a domain already on the account needs no DNS work; it is served from the existing zone.
A separate domain must be registered with nameservers already pointing here — run the `dig +short A`
check above against the new name.

Three things bite when installing beside a live site:

- **Two URLs.** The docroot sits *inside* `public_html`, so `app.example.com` is also reachable at
  `example.com/app/` — a real path, not a redirect. Set the site URL to the hostname you intend.
- **The parent `.htaccess` sits above your docroot.** A WordPress or Tiger neighbour ends its
  `public_html/.htaccess` with a front-controller catch-all (`RewriteRule . /index.php [L]`) and your
  directory is underneath it. It fails in the cruellest way: `/` is a real file and looks perfect,
  while every *route* falls through to the other site. Test a deep route, not the home page.
- **Database names collide.** cPanel prefixes by account, so `cpuser_tiger` may already be the running
  site's. Read the list first; pick `cpuser_tigerapp` or similar. Same for the database user.

**Never modify the existing site to make room** — not its `.htaccess`, not its files, not its document
root. If Tiger cannot go in cleanly beside it, say so and let the human decide.

---

## 2. The account (WHM — usually the human)

Skip if the account exists.

WHM → *Create a New Account*: domain, username, package, contact email. Or, equivalently:

```
whmapi1 createacct username=<user> domain=<domain> plan="3AO Unlimited" \
        password=<generated> contactemail=<email>
```

**On a host with DNS clustering** — 3AO clusters host3 with `ns1`/`ns2.3ao.com` — creating the account
also pushes the zone to the cluster nameservers, and the domain begins resolving on its own. If the
domain was previously answering `SERVFAIL`, this is usually the fix: the delegation was fine and the
zone simply did not exist anywhere.

---

## 3. Get HTTPS working **before** you install

Do this before the database and the wizard — not at the end.

**The wizard asks the user to choose an admin password, and on a plain HTTP site that password crosses
the network in the clear.** AutoSSL needs only DNS, not Tiger, so there is no reason to install first.

**Prerequisite:** the domain must already resolve (§1). AutoSSL validates ownership over the public
hostname and cannot issue otherwise — and when it fails later, the user will reasonably conclude the
*install* is broken rather than DNS.

cPanel → **SSL/TLS Status** → tick the domain and `www` → **Run AutoSSL**. Give it a minute, reload,
and confirm a valid certificate.

**On a fresh account, tick the wildcard entry too.** If the list shows `*.<domain>`, include it in the
same run. A certificate covering only the bare domain and `www` leaves every other subdomain — the one
you add next week, and the service names cPanel creates itself — on an expired or mismatched cert,
which the browser reports as a security warning rather than as a missing certificate. Tick everything
the page lists; there is no cost to covering a name and a real cost to missing one.

If the wildcard is listed but AutoSSL will not issue for it, that is expected on some providers —
HTTP validation cannot prove control of a wildcard, so it needs DNS-based validation. The bare domain
and `www` are what the install needs; note it and move on.

- **With a cPanel session:** do it.
- **Without one:** hand the user those exact steps and wait. It is worth the pause.

Do **not** substitute a self-signed certificate or edit the SSL store. If HTTPS genuinely cannot be had
— DNS is not ready and the user wants to proceed anyway — that is their call to make knowingly: tell
them the admin password will travel in the clear and to change it once TLS is up.

## 4. The database (cPanel UI — session required)

cPanel → *MySQL® Databases*:

1. **Create a database** — cPanel prefixes it, e.g. `cpuser_tiger`.
2. **Create a user** with a strong password — also prefixed, e.g. `cpuser_tiger`.
3. **Add the user to the database** with **ALL PRIVILEGES**.

Note the three values; the installer asks for them.

> **The password must not contain a double quote (`"`).** The installer writes `local.ini` as INI, and a
> `"` breaks the quoting. It refuses such a password rather than writing a broken config — but choosing
> one without is faster than discovering the refusal.

The granted user has full rights **on that database**, so the installer creating tables works fine. Only
database and user *creation* is walled off.

---

## 5. Fetch and place the installer (agent)

Take the current release from **TigerInstall**, not a raw file from a branch:

```
https://github.com/WebTigers/TigerInstall/releases/latest
  → tiger-install.zip        (and its .sha256)
```

Unzip and upload `tiger-install.php` into **the document root settled in §1** — `public_html` for a
plain install, or the subdomain/second-domain docroot when going in beside an existing site. File
Manager's upload, or FTP.

Getting this wrong is quiet: dropping the installer in `public_html` when Tiger was meant to live on
`app.example.com` installs it over the *existing* site's document root.

Then open it in a browser: `https://<domain>/tiger-install.php`

> The installer **deletes itself once the owner is created** — and only then. Every error path stops
> before that, so after a failed step the file is still there and **retrying needs no re-upload**.
> Provisioning has been idempotent since installer 1.0.3: a retry preserves existing secrets rather than
> regenerating them. Fix what the error names and submit the step again.

---

## 6. The wizard (agent drives, five screens)

| Screen | What it does | What it needs |
|---|---|---|
| **Requirements** | Preflight: PHP version, extensions, writability | nothing — read the verdict |
| **Location** | Detects the docroot and proposes an app dir **above** it | confirm or correct the path |
| **Download** | Fetches the vendored release ZIP, verifies its checksum, extracts | a version, or accept latest |
| **Database** | Tests the connection, writes `local.ini`, migrates | the three values from §4 |
| **Admin** | Creates the founding org + owner | email, password, org name |

Then it finishes, reports what it did, and unlinks itself.

**What the agent should verify rather than assume:** the finish screen reports success, `https://<domain>/`
serves the Tiger front page, and `https://<domain>/auth/login` accepts the owner credentials. An HTTP 200
on the home page is *not* sufficient evidence — a shell with unstyled or missing assets also returns 200.
Check that a referenced CSS/JS asset actually loads.

Since **1.0.3** the installer refuses a release that ships no `.sha256`, and refuses to proceed on a
checksum mismatch — a download it cannot verify is an abort, not a warning. Provisioning is also
idempotent from 1.0.3: a retry preserves existing secrets rather than regenerating them.

---

## 7. After the install

- **Assets on a host where `symlink()` is disabled.** Many shared hosts disable it. Tiger detects this
  and **copies** published assets instead of linking (core 1.5.2+), and re-publishes them on update.
  If a site renders unstyled, this is the first thing to check — not a theme problem.
- **Mail works with no SMTP configuration** on a normal cPanel account; the local MTA is used. Verified
  on a real 3AO account. Configure SMTP only if the host blocks local mail.
- **`local.ini` lives above the docroot** at `0600`, holding the DB credentials plus the minted
  `tiger.crypto.key` and `tiger.security.pepper`. **Never regenerate those on an existing install** —
  encrypted settings become unreadable and pepper-dependent credentials stop verifying.
- **Updates** run from the admin. On a no-shell host Tiger swaps a pre-built vendored release ZIP; there
  is no Composer step.

---

## 8. When it goes wrong

| Symptom | Cause | Fix |
|---|---|---|
| Preflight refuses on PHP version | domain set to an older PHP | *MultiPHP Manager* → set ≥ 8.1 |
| "Tiger already appears to be installed" | a configured `local.ini` in the target | intended guard — choose another app dir, or clear the old install deliberately |
| Checksum missing or mismatched | release-side problem, or a truncated download | retry once; if a published release genuinely ships no `.sha256`, **report it** — do not bypass. Manual upload is the human's deliberate trust decision about a ZIP they already hold, never a workaround |
| Site renders unstyled | assets neither linked nor copied | confirm core ≥ 1.5.2; re-publish assets from the admin |
| AutoSSL fails | domain does not resolve publicly | fix DNS first (§1), then re-run from the UI |
| Installer 404s **after the owner was created** | it self-deleted, as designed | the install finished — verify the site (§below) rather than re-uploading |

---

## 9. What the agent reports back

End with something the human can act on, not a log dump:

- the site URL and whether it currently serves over HTTP or HTTPS
- the admin URL and the account created (never the password in plain text if it can be avoided)
- **the AutoSSL instruction** (§3), if HTTPS is not yet active
- anything left undone, said plainly — an unfinished step reported honestly costs far less than one
  discovered later

---

*This runbook records what actually works on a real shared-cPanel account. If a step changes, update it
here in the same change — a runbook that has drifted from reality is worse than none.*
