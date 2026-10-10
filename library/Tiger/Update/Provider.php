<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Update_Provider — a module may own HOW it detects + applies its own updates.
 *
 * Most modules are updated by the platform's built-in channels: GitHub (a public or private repo, via
 * {@see Tiger_Update_Checker::modules()} + {@see Tiger_Module_Installer::installFromUrl()}), Packagist
 * (core), or the no-shell release-ZIP swap. But a module may be distributed some other way — behind a
 * vendor's own authority, a signed artifact server, an internal feed — where the box neither holds the
 * credential to read the source directly nor should. Rather than teach the core Updates screen about any
 * particular such scheme (which would hard-wire a private mechanism into open-source core), this is a
 * NEUTRAL seam: a module registers a provider for its own slug and the Updates screen treats it like any
 * other — same descriptor on detection, same result on apply. Where the bytes actually come from is the
 * provider's business and never appears here.
 *
 * Registration mirrors {@see Tiger_Audience::register()} / {@see Tiger_Search::register()}: call it from
 * the module's Bootstrap `_init*`, so a provider exists exactly while its module is active and vanishes
 * when the module is deactivated (in-memory, re-declared each request — nothing to clean up).
 *
 * THE CONTRACT a provider must honor so the Module Manager stays uniform:
 *
 *   check(string $slug, string $installed): ?array
 *       Detection, no side effects. Return null when there's nothing to say (unreachable / not managed by
 *       this provider right now — fail-safe, never flags an update on error). Otherwise return:
 *         ['latest' => '1.4.0',            // REQUIRED — the newest available version
 *          'installed' => '1.3.0',         // optional — overrides the row's version (e.g. read from disk)
 *          'repository' => 'org/repo',     // optional — for the "what's in this update" changelog link
 *          'ref' => 'v1.4.0']              // optional — the release ref
 *       Core builds the update descriptor from this via the SAME {@see Tiger_Update_Checker} machinery,
 *       so a provider row is indistinguishable from a GitHub one except `method` = 'provider'.
 *
 *   apply(string $slug, array $descriptor): array
 *       Perform the update (download, verify, install — the provider's responsibility). Return the SAME
 *       shape _applyOne yields for a module:
 *         ['ok' => true, 'version' => '1.4.0', 'log' => [['step'=>..,'ok'=>..,'detail'=>..], ...]]
 *       A provider that installs through {@see Tiger_Module_Installer::installFromTarball()} (with its
 *       signature material) gets the platform's verify-before-extract gate for free.
 *
 * @api
 * @see Tiger_Update_Checker
 */
class Tiger_Update_Provider
{
    /** @var array<string,array> slug => ['id'=>string,'check'=>callable,'apply'=>callable,'label'=>string] */
    protected static $providers = [];

    /**
     * Register (or replace) the update provider for a module slug. Call from the module's Bootstrap.
     *
     * @param  string $slug the module slug this provider owns
     * @param  array  $spec ['id'=>string provider id, 'check'=>callable, 'apply'=>callable, 'label'?=>string]
     * @return void
     */
    public static function register(string $slug, array $spec): void
    {
        $slug = trim($slug);
        if ($slug === '' || !is_callable($spec['check'] ?? null) || !is_callable($spec['apply'] ?? null)) {
            return;   // a provider that can't both check and apply is no provider — ignore it
        }
        self::$providers[$slug] = [
            'id'    => (string) ($spec['id'] ?? $slug),
            'label' => (string) ($spec['label'] ?? ''),
            'check' => $spec['check'],
            'apply' => $spec['apply'],
        ];
    }

    /** Drop a provider (rarely needed — deactivating the module already removes it). Mostly a test seam. */
    public static function unregister(string $slug): void
    {
        unset(self::$providers[trim($slug)]);
    }

    /** True when a module slug has a registered update provider. */
    public static function has(string $slug): bool
    {
        return isset(self::$providers[trim($slug)]);
    }

    /** The provider spec for a slug, or null. */
    public static function get(string $slug): ?array
    {
        return self::$providers[trim($slug)] ?? null;
    }

    /** All registered providers, slug => spec. */
    public static function all(): array
    {
        return self::$providers;
    }

    /**
     * Run a provider's detection — fail-safe. Returns the provider's info array, or null on nothing-to-say
     * or any throw (a broken provider must never break the Updates screen or flag a phantom update).
     *
     * @param  string $slug      the module slug
     * @param  string $installed the version recorded for the install
     * @return array|null {latest, installed?, repository?, ref?}
     */
    public static function check(string $slug, string $installed): ?array
    {
        $p = self::get($slug);
        if (!$p) { return null; }
        try {
            $info = ($p['check'])($slug, $installed);
            return is_array($info) && isset($info['latest']) && (string) $info['latest'] !== '' ? $info : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Run a provider's apply. Returns the provider's result, or a failure result on a throw (so apply()
     * can never bubble an exception into System_Service_Updates).
     *
     * @param  string $slug       the module slug
     * @param  array  $descriptor the update descriptor being applied
     * @return array {ok, version?, log?}
     */
    public static function apply(string $slug, array $descriptor): array
    {
        $p = self::get($slug);
        if (!$p) { return ['ok' => false, 'log' => [['step' => 'provider', 'ok' => false, 'detail' => 'no provider']]]; }
        try {
            $r = ($p['apply'])($slug, $descriptor);
            return is_array($r) ? $r : ['ok' => false, 'log' => [['step' => 'provider', 'ok' => false, 'detail' => 'bad provider result']]];
        } catch (Throwable $e) {
            return ['ok' => false, 'log' => [['step' => 'error', 'ok' => false, 'detail' => $e->getMessage()]]];
        }
    }

    /** Reset the registry — test isolation. */
    public static function _reset(): void
    {
        self::$providers = [];
    }
}
