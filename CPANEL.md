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

## 0. The one rule: know which half is yours

Almost everything is automatable. Three things are not, and the difference is not arbitrary — each is
walled off by cPanel itself, not by convention.

| Task | Who | Why it cannot be otherwise |
|---|---|---|
| Create the cPanel **account** | **human** (WHM) | Reseller/root privilege. An account cannot create itself. |
| Create the **MySQL database + user + grant** | **human** (cPanel → *MySQL® Databases*) | A user-space PHP script is denied `CREATE DATABASE`/`CREATE USER`; cPanel owns provisioning and prefixes names as `cpuser_name`. See INSTALLER.md §4. |
| Run **AutoSSL** | **human** (cPanel → *SSL/TLS Status*) | Needs an authenticated cPanel session. There is no user-space path, and it is **not the agent's lane** — see §6. |
| Everything else | **agent / installer** | Download, extract, place code above docroot, write secrets, migrate, create the owner, self-delete |

**Do not try to work around these three.** An agent that asks a user to paste a cPanel API token has
traded a 30-second click for a security ask, and it will be wrong more often than it is right.

---

## 1. Before you start — what to confirm

Confirm all of these before touching anything. Each has cost a real install when skipped.

- **PHP ≥ 8.1.** cPanel → *MultiPHP Manager*, per-domain. Tiger's floor is `>=8.1`; the installer's
  preflight refuses below it. 3AO's default is `ea-php81`.
- **`mbstring` loaded**, and `zip` or `phar` for extraction. The preflight reports precisely what is short.
- **The domain resolves publicly.** `dig +short A <domain>` must return the server's IP. This does not
  block the install, but it blocks **AutoSSL** (§6) and any inbound webhook. A domain that returns
  `NXDOMAIN` is unregistered; one that returns `SERVFAIL` is registered but its nameservers are not
  answering — different problems, different fixes.
- **You can reach the account's file manager or FTP.** No shell: uploading is how the installer arrives.

---

## 2. The account (human, WHM)

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

## 3. The database (human, cPanel UI)

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

## 4. Fetch and place the installer (agent)

Take the current release from **TigerInstall**, not a raw file from a branch:

```
https://github.com/WebTigers/TigerInstall/releases/latest
  → tiger-install.zip        (and its .sha256)
```

Unzip and upload `tiger-install.php` into the domain's document root (`public_html`, or the addon
domain's docroot). File Manager's upload, or FTP.

Then open it in a browser: `https://<domain>/tiger-install.php`

> The installer **deletes itself on success**, so there is exactly one run per upload. A failed install
> that needs a retry means re-uploading the file. This is deliberate — no lingering `install.php`.

---

## 5. The wizard (agent drives, five screens)

| Screen | What it does | What it needs |
|---|---|---|
| **Requirements** | Preflight: PHP version, extensions, writability | nothing — read the verdict |
| **Location** | Detects the docroot and proposes an app dir **above** it | confirm or correct the path |
| **Download** | Fetches the vendored release ZIP, verifies its checksum, extracts | a version, or accept latest |
| **Database** | Tests the connection, writes `local.ini`, migrates | the three values from §3 |
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

## 6. AutoSSL — **the human's job, in the cPanel UI**

**This is not the agent's lane.** AutoSSL needs an authenticated cPanel session; there is no user-space
path to it, and an agent should stop here and hand back a clear instruction.

Tell the user, in these words or close to them:

> Log in to cPanel → **SSL/TLS Status** → tick the domain (and `www`) → **Run AutoSSL**.
> It takes a minute or two. Reload the page; the domain should show a valid certificate.

**Before sending them, confirm the prerequisite** — AutoSSL validates ownership over the public
hostname, so it cannot issue until DNS resolves:

```
dig +short A <domain>     # must return the server's IP
```

If that is empty, AutoSSL will fail and the user will reasonably think the install is broken. Fix DNS
first, then send them to the UI.

**Do not** attempt to substitute a self-signed certificate, edit the SSL store, or promise HTTPS the
install does not have. Report the state honestly: the site works over HTTP and needs one click in the
UI for HTTPS.

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
| Download step fails / checksum refused | release missing its `.sha256`, or a partial fetch | retry; if a release genuinely ships no checksum, download the ZIP yourself and use the manual upload |
| Site renders unstyled | assets neither linked nor copied | confirm core ≥ 1.5.2; re-publish assets from the admin |
| AutoSSL fails | domain does not resolve publicly | fix DNS first (§6), then re-run from the UI |
| Installer page 404s after a failure | it self-deleted on a *partial* success | re-upload `tiger-install.php` and run again |

---

## 9. What the agent reports back

End with something the human can act on, not a log dump:

- the site URL and whether it currently serves over HTTP or HTTPS
- the admin URL and the account created (never the password in plain text if it can be avoided)
- **the AutoSSL instruction** (§6), if HTTPS is not yet active
- anything left undone, said plainly — an unfinished step reported honestly costs far less than one
  discovered later

---

*This runbook records what actually works on a real shared-cPanel account. If a step changes, update it
here in the same change — a runbook that has drifted from reality is worse than none.*
