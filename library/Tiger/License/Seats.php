<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_License_Seats — per-domain TigerPASS seats for an install that serves several domains.
 *
 * One Tiger install can serve many sites (`site_domain`: host → org). A TigerPASS key is bound to ONE site
 * (its apex; `www.` is the same site, and a subdomain is its own site unless the license says otherwise), so
 * each further domain needs a seat of its own — and every seat comes from the SAME license as the install's
 * main key. The main domain is the single payer; the authority links every seat back to that license.
 *
 * STORAGE (option tier, through Tiger_License_Store — no new table). The main key stays exactly where it
 * always was, under {@see Tiger_License_Pass::SLUG}, pinned to its activation domain. Each tenant seat is one
 * more record in the same store under `Tiger_License_Pass::SLUG . '@' . <apex>` — the same record shape the
 * Checker already keeps (key, authority, vendor, public_key, domain = the apex, + its cached verdict). So an
 * install with only the old single record is simply "the main domain covered, nothing else" (backward
 * compatible), and each seat is verified for ITS domain by the ordinary Checker on its normal cached cadence.
 *
 * NAG, NEVER DISABLE. A seat only decides what an admin is TOLD (a coverage column, a notice on that domain's
 * admin). The update gate stays on the main key alone: no seat, a refused claim, or a lapsed seat never blocks
 * an update and never switches anything off. `unknown` (couldn't reach / couldn't trust the authority) counts
 * as covered, exactly like the Checker's assume-current rule.
 *
 * reconcile() claims a seat for every active mapped domain that lacks one; it is idempotent (the authority
 * returns the key already bound to a domain) and never throws into its caller.
 *
 * @api
 * @see Tiger_License_Checker
 * @see Tiger_License_Authority::claim()
 * @see Tiger_Model_SiteDomain
 */
class Tiger_License_Seats
{
    /** The record "slug" separator: `<pass slug>@<apex>`. */
    const SEP = '@';

    /** Coverage states. */
    const MAIN      = 'main';        // the activation domain — the payer's own key
    const COVERED   = 'covered';     // a seat is held (valid, or unknown = assume-current)
    const UNCOVERED = 'uncovered';   // no seat held for this domain
    const LAPSED    = 'lapsed';      // the authority definitively said this seat (or the main key) is not entitled
    const NONE      = 'none';        // no TigerPASS on this install at all — coverage doesn't apply

    /** Longest apex kept verbatim in the record key (the option key column is VARCHAR(191)). */
    const MAX_APEX = 150;

    /** @var callable|null test seam: fn(): array<int,string> — the install's mapped hosts */
    protected static $hostSource = null;

    /** @var array<string,string>|null per-request memo of coverage states (cleared on any write) */
    protected static $memo = null;

    // ---- seams ---------------------------------------------------------------------------

    /**
     * Swap where the mapped hosts come from (tests). Null resets to the `site_domain` table.
     *
     * @param  callable|null $source fn(): array<int,string>
     * @return void
     */
    public static function setHostSource(?callable $source): void
    {
        self::$hostSource = $source;
        self::$memo = null;
    }

    /**
     * Reset the seams + memo — for test isolation.
     *
     * @return void
     */
    public static function _reset(): void
    {
        self::$hostSource = null;
        self::$memo = null;
    }

    // ---- naming --------------------------------------------------------------------------

    /**
     * The licensing apex of a host: lowercased, port + trailing dot stripped, one leading `www.` removed
     * (apex + www are one site). A subdomain stays its own site.
     *
     * @param  string $host a host, possibly with a port or `www.`
     * @return string       the apex, or '' when empty
     */
    public static function apex(string $host): string
    {
        $h = strtolower(trim($host));
        if (($colon = strpos($h, ':')) !== false) { $h = substr($h, 0, $colon); }
        $h = rtrim($h, '.');
        return strpos($h, 'www.') === 0 ? substr($h, 4) : $h;
    }

    /**
     * The store slug a domain's seat record lives under.
     *
     * @param  string $host the domain (any form; normalized to its apex)
     * @return string       `<pass slug>@<apex>` (a long apex is hashed to fit the option key)
     */
    public static function seatSlug(string $host): string
    {
        $apex = self::apex($host);
        if (strlen($apex) > self::MAX_APEX) { $apex = 'sha1-' . sha1($apex); }
        return Tiger_License_Pass::SLUG . self::SEP . $apex;
    }

    // ---- reading -------------------------------------------------------------------------

    /**
     * The install's main TigerPASS record, or null when none is held.
     *
     * @return array|null
     */
    public static function main(): ?array
    {
        $r = Tiger_License_Checker::get(Tiger_License_Pass::SLUG);
        return (is_array($r) && (string) ($r['key'] ?? '') !== '') ? $r : null;
    }

    /**
     * The main key's activation apex ('' when no TigerPASS, or it has not pinned a domain yet).
     *
     * @return string
     */
    public static function mainDomain(): string
    {
        $m = self::main();
        return $m ? self::apex((string) ($m['domain'] ?? '')) : '';
    }

    /**
     * The install's served apex domains, deduplicated (apex + www are one), excluding the main domain.
     *
     * @return array<int,string>
     */
    public static function domains(): array
    {
        $main = self::mainDomain();
        $out  = [];
        foreach (self::_hosts() as $h) {
            $a = self::apex((string) $h);
            if ($a === '' || $a === $main) { continue; }
            $out[$a] = true;
        }
        $list = array_keys($out);
        sort($list);
        return $list;
    }

    /**
     * The stored seat record for a domain, or null.
     *
     * @param  string $host the domain
     * @return array|null
     */
    public static function seat(string $host): ?array
    {
        $r = Tiger_License_Checker::get(self::seatSlug($host));
        return (is_array($r) && (string) ($r['key'] ?? '') !== '') ? $r : null;
    }

    /**
     * A domain's coverage state from CACHED data only (no network): main | covered | uncovered | lapsed, or
     * `none` when the install holds no TigerPASS.
     *
     * @param  string $host the domain
     * @return string one of the state constants
     */
    public static function stateFor(string $host): string
    {
        $apex = self::apex($host);
        if (self::$memo !== null && isset(self::$memo[$apex])) { return self::$memo[$apex]; }

        $main = self::main();
        if (!$main) { return self::NONE; }

        if ($apex === self::mainDomain()) {
            $st = (string) Tiger_License_Checker::status(Tiger_License_Pass::SLUG)['state'];
            $state = $st === Tiger_License_Checker::LAPSED ? self::LAPSED : self::MAIN;
        } elseif (!self::seat($apex)) {
            $state = self::UNCOVERED;
        } else {
            $st = (string) Tiger_License_Checker::status(self::seatSlug($apex))['state'];
            $state = $st === Tiger_License_Checker::LAPSED ? self::LAPSED : self::COVERED;
        }
        self::$memo = (self::$memo ?? []) + [$apex => $state];
        return $state;
    }

    /**
     * Coverage across the whole install (no network): each domain's state plus "N of M covered", where M is
     * the main domain + every other served apex. `enabled` is false when there's no TigerPASS at all.
     *
     * @return array{enabled:bool,main:string,domains:array<string,string>,covered:int,total:int}
     */
    public static function coverage(): array
    {
        $empty = ['enabled' => false, 'main' => '', 'domains' => [], 'covered' => 0, 'total' => 0];
        try {
            if (!self::main()) { return $empty; }
            $main    = self::mainDomain();
            $domains = [];
            if ($main !== '') { $domains[$main] = self::stateFor($main); }
            foreach (self::domains() as $d) { $domains[$d] = self::stateFor($d); }
            $covered = count(array_filter($domains, static fn($s) => $s === self::MAIN || $s === self::COVERED));
            return ['enabled' => true, 'main' => $main, 'domains' => $domains, 'covered' => $covered, 'total' => count($domains)];
        } catch (Throwable $e) {
            return $empty;
        }
    }

    /**
     * Should an admin on this host be told the domain has no valid seat? True only for a MAPPED tenant domain
     * (not the main one) on an install that holds TigerPASS, whose seat is missing or lapsed. Cheap: option
     * reads only, never the network — it backs a notice drawn on every admin page.
     *
     * @param  string $host the request host
     * @return bool
     */
    public static function needsSeat(string $host): bool
    {
        try {
            $apex = self::apex($host);
            if ($apex === '' || !self::main() || $apex === self::mainDomain()) { return false; }
            if (!in_array($apex, self::domains(), true)) { return false; }   // an unmapped alias isn't a tenant
            $state = self::stateFor($apex);
            return $state === self::UNCOVERED || $state === self::LAPSED;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Where the buyer adds a seat: `tiger.pass.manage_url` when set, else the main key's authority origin +
     * `/license` (the reference authority's buyer dashboard). '' when neither is known.
     *
     * @return string
     */
    public static function manageUrl(): string
    {
        $cfg = Tiger_License_Pass::config('manage_url');
        if ($cfg !== '') { return $cfg; }
        $m = self::main();
        $authority = $m ? (string) ($m['authority'] ?? '') : Tiger_License_Pass::authority();
        $p = parse_url($authority);
        if (!is_array($p) || empty($p['host']) || !in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true)) {
            return '';
        }
        return strtolower((string) $p['scheme']) . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . '/license';
    }

    // ---- writing -------------------------------------------------------------------------

    /**
     * Claim a seat, with the main TigerPASS key, for every served domain that lacks one (or whose seat
     * definitively lapsed). Skips the main domain, www/apex duplicates and domains already seated. A refusal
     * (`no_seats`, …) or an untrustworthy reply stores nothing — the domain just stays uncovered. Idempotent;
     * NEVER throws into the caller.
     *
     * @return array{claimed:array<int,string>,refused:array<string,string>,unknown:array<int,string>,skipped:string}
     */
    public static function reconcile(): array
    {
        $out = ['claimed' => [], 'refused' => [], 'unknown' => [], 'skipped' => ''];
        try {
            $main = self::main();
            if (!$main)                                   { $out['skipped'] = 'no_pass';   return $out; }
            if ((string) ($main['authority'] ?? '') === '') { $out['skipped'] = 'no_authority'; return $out; }
            // The main key pins its domain on its first check; until then we can't tell it from a tenant.
            if (self::mainDomain() === '')                 { $out['skipped'] = 'no_main_domain'; return $out; }

            foreach (self::domains() as $apex) {
                $have = self::seat($apex);
                if ($have) {
                    $st = (string) Tiger_License_Checker::status(self::seatSlug($apex))['state'];
                    if ($st !== Tiger_License_Checker::LAPSED) { continue; }   // seated — nothing to do
                }
                try {
                    $res = Tiger_License_Authority::claim(
                        (string) $main['authority'],
                        (string) $main['key'],
                        $apex,
                        (string) ($main['public_key'] ?? '')
                    );
                } catch (Throwable $e) {
                    $res = null;
                }
                if ($res === null)  { $out['unknown'][] = $apex; continue; }
                if (!$res['ok'])    { $out['refused'][$apex] = (string) ($res['reason'] ?? 'unknown'); continue; }

                Tiger_License_Checker::remember(self::seatSlug($apex), [
                    'key'        => (string) $res['key'],
                    'authority'  => (string) $main['authority'],
                    'vendor'     => (string) ($main['vendor'] ?? Tiger_License_Pass::VENDOR),
                    'public_key' => (string) ($main['public_key'] ?? ''),
                    'domain'     => $apex,
                ]);
                $out['claimed'][] = $apex;
            }
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('license.seats.reconcile_failed', ['error' => $e->getMessage()]); }
        }
        self::$memo = null;
        return $out;
    }

    /**
     * Verify every held seat for ITS OWN domain through the ordinary Checker (cached; a fresh verdict costs
     * no network). Informational only — nothing here gates an update. Never throws.
     *
     * @param  int $now unix time override (0 = time()); for tests
     * @return array<string,string> apex => verdict state
     */
    public static function verifyAll(int $now = 0): array
    {
        $out = [];
        try {
            foreach (self::domains() as $apex) {
                if (!self::seat($apex)) { continue; }
                $out[$apex] = (string) Tiger_License_Checker::verify(self::seatSlug($apex), ['domain' => $apex], $now)['state'];
            }
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) { Tiger_Log::warn('license.seats.verify_failed', ['error' => $e->getMessage()]); }
        }
        self::$memo = null;
        return $out;
    }

    /**
     * The scheduled job body: claim what's missing, then refresh each seat's verdict. Never throws.
     *
     * @return array{reconcile:array,verified:array<string,string>}
     */
    public static function run(): array
    {
        return ['reconcile' => self::reconcile(), 'verified' => self::verifyAll()];
    }

    // ---- internals -----------------------------------------------------------------------

    /** The install's mapped hosts: the injected source, else the active `site_domain` rows. */
    protected static function _hosts(): array
    {
        if (self::$hostSource !== null) {
            return (array) (self::$hostSource)();
        }
        try {
            return (new Tiger_Model_SiteDomain())->activeHosts();
        } catch (Throwable $e) {
            return [];   // no DB / table yet — no tenant domains to speak of
        }
    }
}
