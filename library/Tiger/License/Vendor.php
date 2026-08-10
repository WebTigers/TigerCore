<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_License_Vendor — CONNECT + pin a paid module's vendor (its `[owner]/TigerVendor` repo).
 *
 * The **trust anchor** for the paid line (MARKETPLACE.md §3, §6 CONNECT). A `licensed` module declares
 * `pricing.vendor = "owner/repo"` (its TigerVendor repo) and `pricing.authority` (the store). Before the
 * install can trust anything from that authority the buyer must **pin the vendor's Ed25519 public key** —
 * the key that BOTH the downloaded artifact AND the authority's verdicts are signed with
 * (`Tiger_Crypto_Signature`, `Tiger_License_Checker`). This class fetches the vendor's manifest
 * (`tigervendor.json`), produces a consent payload (owner + key fingerprint), pins the key on confirm, and
 * resolves the pinned key for a licensed install (`Tiger_Module_Installer::installFromAuthority`). A later
 * SILENT public-key change is a takeover signal — `connect()` flags `changed` so the UI re-consents.
 *
 * The `tigervendor.json` contract (published in the `[owner]/TigerVendor` repo root — see MARKETPLACE.md §3):
 *
 *   { "vendor": "acme/TigerVendor",
 *     "api_base": "https://store.acme.com/shop/authority",   // the authority (verify/download)
 *     "public_key": "<base64 Ed25519 public key>",            // pinned; signs artifacts + verdicts
 *     "catalog": "https://store.acme.com/marketplace.json" }  // optional (browse)
 *
 * Pins live in the lazy `option` tier (`Tiger_License_Store`, namespaced `vendor:<owner/repo>`) — the same
 * store the Checker uses — so a tenant/fleet carries its trust decisions with no deploy.
 *
 * @api
 * @see Tiger_License_Checker
 * @see Tiger_Module_Installer
 */
class Tiger_License_Vendor
{
    /** The manifest file at the root of a `[owner]/TigerVendor` repo. */
    const MANIFEST = 'tigervendor.json';

    /** Option-store slug prefix for a pinned vendor (keeps pins clear of module license slugs). */
    const PIN_PREFIX = 'vendor:';

    /** @var Tiger_License_Store|null the pin store (default: the option tier) */
    protected static $store = null;

    /** @var callable|null injected fetch: fn(string $owner, string $repo, string $ref): ?string (raw manifest) */
    protected static $fetch = null;

    // ---- seams (DI / tests) ----------------------------------------------------

    /**
     * Swap the pin store (tests inject an in-memory one). Pass null to reset to the default.
     *
     * @param  Tiger_License_Store|null $store the store, or null to reset
     * @return void
     */
    public static function setStore(?Tiger_License_Store $store): void { self::$store = $store; }

    /**
     * Swap the manifest fetch (tests inject a fake). The callable takes ($owner, $repo, $ref) and returns
     * the raw manifest text, or null when unreachable. Pass null to reset to real GitHub fetch.
     *
     * @param  callable|null $fetch the fetch, or null to reset
     * @return void
     */
    public static function setFetch(?callable $fetch): void { self::$fetch = $fetch; }

    /**
     * Reset all injected seams — for test isolation.
     *
     * @return void
     */
    public static function _reset(): void { self::$store = null; self::$fetch = null; }

    /** The pin store (lazily the option tier). */
    protected static function _store(): Tiger_License_Store
    {
        if (self::$store === null) { self::$store = new Tiger_License_Store_Option(); }
        return self::$store;
    }

    /**
     * Fetch + validate a vendor's `tigervendor.json` manifest.
     *
     * @param  string $vendor the vendor repo as "owner/repo" (e.g. "acme/TigerVendor")
     * @param  string $ref    the git ref to read (default "main")
     * @return array{vendor:string,api_base:string,public_key:string,catalog:?string}|null the manifest, or null when absent/invalid
     */
    public static function manifest(string $vendor, string $ref = 'main'): ?array
    {
        [$owner, $repo] = self::_split($vendor);
        if ($owner === '' || $repo === '') { return null; }

        $raw = (self::$fetch !== null)
            ? (self::$fetch)($owner, $repo, $ref)
            : Tiger_Module_Github::fetchRaw($owner, $repo, $ref, self::MANIFEST);
        if (!is_string($raw) || $raw === '') { return null; }

        try {
            $m = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return null;
        }
        if (!is_array($m)) { return null; }

        $apiBase   = trim((string) ($m['api_base'] ?? ''));
        $publicKey = trim((string) ($m['public_key'] ?? ''));
        // A trust anchor MUST carry an https authority + a real Ed25519 public key, or it's not usable.
        if ($apiBase === '' || !self::_isHttps($apiBase) || !self::_isValidKey($publicKey)) {
            return null;
        }
        return [
            'vendor'     => $owner . '/' . $repo,
            'api_base'   => rtrim($apiBase, '/'),
            'public_key' => $publicKey,
            'catalog'    => (isset($m['catalog']) && (string) $m['catalog'] !== '') ? (string) $m['catalog'] : null,
        ];
    }

    /**
     * CONNECT: fetch the live manifest and compare it to any existing pin — the payload a consent gate shows.
     * `changed` is true when a prior pin exists with a DIFFERENT key (a takeover signal → re-consent).
     *
     * @param  string $vendor "owner/repo"
     * @param  string $ref    git ref (default "main")
     * @return array{vendor:string,api_base:string,public_key:string,catalog:?string,fingerprint:string,pinned:bool,changed:bool}|null
     */
    public static function connect(string $vendor, string $ref = 'main'): ?array
    {
        $m = self::manifest($vendor, $ref);
        if ($m === null) { return null; }
        $prior = self::pinned($m['vendor']);
        return $m + [
            'fingerprint' => self::fingerprint($m['public_key']),
            'pinned'      => $prior !== null,
            'changed'     => $prior !== null && (string) ($prior['public_key'] ?? '') !== $m['public_key'],
        ];
    }

    /**
     * Pin (trust) a vendor's manifest after the buyer consents. Idempotent; overwrites a prior pin —
     * that's the re-consent path once a reviewed key change is accepted.
     *
     * @param  string $vendor   "owner/repo"
     * @param  array  $manifest the manifest from manifest()/connect() (needs api_base + public_key)
     * @return void
     */
    public static function pin(string $vendor, array $manifest): void
    {
        [$owner, $repo] = self::_split($vendor);
        if ($owner === '' || $repo === '') { return; }
        self::_store()->put(self::PIN_PREFIX . $owner . '/' . $repo, [
            'api_base'   => rtrim((string) ($manifest['api_base'] ?? ''), '/'),
            'public_key' => (string) ($manifest['public_key'] ?? ''),
            'catalog'    => $manifest['catalog'] ?? null,
        ]);
    }

    /**
     * The pinned trust record for a vendor, or null when not yet connected.
     *
     * @param  string $vendor "owner/repo"
     * @return array{api_base:string,public_key:string,catalog:?string}|null
     */
    public static function pinned(string $vendor): ?array
    {
        [$owner, $repo] = self::_split($vendor);
        return ($owner === '' || $repo === '') ? null : self::_store()->get(self::PIN_PREFIX . $owner . '/' . $repo);
    }

    /**
     * Drop a vendor's pin (untrust).
     *
     * @param  string $vendor "owner/repo"
     * @return void
     */
    public static function unpin(string $vendor): void
    {
        [$owner, $repo] = self::_split($vendor);
        if ($owner !== '' && $repo !== '') { self::_store()->forget(self::PIN_PREFIX . $owner . '/' . $repo); }
    }

    /**
     * The pinned Ed25519 public key for a vendor (to verify its signed artifacts), or null when unpinned.
     *
     * @param  string $vendor "owner/repo"
     * @return string|null the base64 public key, or null
     */
    public static function publicKey(string $vendor): ?string
    {
        $p = self::pinned($vendor);
        return ($p && (string) ($p['public_key'] ?? '') !== '') ? (string) $p['public_key'] : null;
    }

    /**
     * A short, human-comparable fingerprint of a public key (shown in the consent gate).
     *
     * @param  string $publicKey the base64 Ed25519 public key
     * @return string the fingerprint
     */
    public static function fingerprint(string $publicKey): string
    {
        return Tiger_Crypto_Signature::fingerprint($publicKey);
    }

    // ---- helpers ---------------------------------------------------------------

    /** Split "owner/repo" into sanitized [owner, repo]; ['',''] when malformed. */
    protected static function _split(string $vendor): array
    {
        $parts = explode('/', trim($vendor, " /"));
        if (count($parts) !== 2) { return ['', '']; }
        $owner = preg_replace('/[^A-Za-z0-9._-]/', '', $parts[0]);
        $repo  = preg_replace('/[^A-Za-z0-9._-]/', '', $parts[1]);
        return ($owner !== '' && $repo !== '') ? [$owner, $repo] : ['', ''];
    }

    /** True for an `https://` URL only (a trust anchor is never plain http). */
    protected static function _isHttps(string $url): bool
    {
        return stripos($url, 'https://') === 0;
    }

    /** A valid Ed25519 public key is 32 raw bytes, base64-encoded (standard or url-safe). */
    protected static function _isValidKey(string $b64): bool
    {
        if ($b64 === '') { return false; }
        $raw = base64_decode(strtr($b64, '-_', '+/'), true);
        return $raw !== false && strlen($raw) === 32;
    }
}
