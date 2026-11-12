<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_License_Pass — which license a licensed module installs and updates under, on this install.
 *
 * A `licensed` module only ever reaches an install through its authority (verify -> signed download ->
 * verify-before-extract -> install). This class answers the one question that path needs: with WHICH key.
 *
 *   1. For a module on the TigerPASS shelf, the install's ONE TigerPASS key (stored under {@see SLUG} by the
 *      Module Manager's "Activate TigerPASS"). TigerPASS is a subscription, not a per-module license: one
 *      key covers every PASS module.
 *   2. Otherwise the module's own license record (`tiger.license.<slug>`) — a per-vendor paid module bought
 *      on its own, or a PASS module on an install with no TigerPASS activated.
 *   3. Otherwise none — the caller refuses. A licensed module is never fetched unsigned.
 *
 * A module is on the TigerPASS shelf when its manifest says `pricing.plan = "tigerpass"`, or its
 * `pricing.authority` is this install's configured pass authority (`tiger.pass.authority`). The key is read
 * in-process and handed straight to the authority; nothing here prints or returns it to a client.
 *
 * @api
 * @see Tiger_License_Checker
 * @see Tiger_Module_Installer::installFromAuthority()
 */
class Tiger_License_Pass
{
    /** The reserved license "slug" the install's single TigerPASS key is stored under. */
    const SLUG = '__tigerpass__';

    /** The `owner/repo` trust anchor for WebTigers-published TigerPASS modules. */
    const VENDOR = 'WebTigers/TigerVendor';

    /**
     * The license to install or update a module under: its own record, else the TigerPASS key for a PASS
     * module, else null.
     *
     * @param  string $slug     the module slug
     * @param  array  $manifest the module's manifest (module.json)
     * @return array{key:string,authority:string,vendor:string,public_key:string,via:string}|null the license, or null
     */
    public static function licenseFor(string $slug, array $manifest): ?array
    {
        if (!Tiger_Module_Pricing::isLicensed($manifest)) {
            return null;
        }

        // A PASS module resolves to the install's TigerPASS key FIRST: installFromAuthority also records the
        // key under the module's slug, and that copy must never outlive a renewed or replaced TigerPASS key.
        if (self::covers($manifest)) {
            $pass = Tiger_License_Checker::get(self::SLUG);
            if (self::_usable($pass)) {
                return self::_shape($pass, 'pass');
            }
        }

        $own = Tiger_License_Checker::get($slug);
        return self::_usable($own) ? self::_shape($own, 'module') : null;
    }

    /**
     * Whether a licensed module is on the TigerPASS shelf (so the install's TigerPASS key covers it).
     *
     * @param  array $manifest the module's manifest or registry listing
     * @return bool
     */
    public static function covers(array $manifest): bool
    {
        if (!Tiger_Module_Pricing::isLicensed($manifest)) {
            return false;
        }
        $pricing = (isset($manifest['pricing']) && is_array($manifest['pricing'])) ? $manifest['pricing'] : [];
        if (strtolower((string) ($pricing['plan'] ?? '')) === 'tigerpass') {
            return true;
        }
        $pass = self::authority();
        return $pass !== '' && rtrim((string) Tiger_Module_Pricing::of($manifest)['authority'], '/') === rtrim($pass, '/');
    }

    /**
     * The configured TigerPASS authority URL (`tiger.pass.authority`), or '' when unset.
     *
     * @return string
     */
    public static function authority(): string
    {
        return self::config('authority');
    }

    /**
     * Read a `tiger.pass.<key>` value from the resolved config, or '' when absent.
     *
     * @param  string $key the setting under tiger.pass
     * @return string
     */
    public static function config(string $key): string
    {
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t    = $cfg ? $cfg->get('tiger') : null;
        $pass = $t ? $t->get('pass') : null;
        return $pass ? (string) $pass->get($key) : '';
    }

    /**
     * This install's domain, for a domain-bound license: the request host, else the host of `tiger.site.url`
     * (an update run from the CLI has no request). '' when neither is known.
     *
     * @return string
     */
    public static function installDomain(): string
    {
        $host = '';
        if (class_exists('Zend_Controller_Front')) {
            $req  = Zend_Controller_Front::getInstance()->getRequest();
            $host = $req ? (string) $req->getHttpHost() : '';
        }
        if ($host === '') {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        }
        if ($host === '') {
            $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
            $site = ($cfg && $cfg->get('tiger') && $cfg->tiger->get('site')) ? (string) $cfg->tiger->site->get('url') : '';
            $host = (string) parse_url($site, PHP_URL_HOST);
        }
        return strtolower((string) preg_replace('/:\d+$/', '', $host));
    }

    /** A stored record that can actually drive an authority install (key + authority + pinned public key). */
    private static function _usable(?array $r): bool
    {
        return is_array($r) && (string) ($r['key'] ?? '') !== '' && (string) ($r['authority'] ?? '') !== ''
            && (string) ($r['public_key'] ?? '') !== '';
    }

    /** Normalize a stored record to the shape installFromAuthority needs. */
    private static function _shape(array $r, string $via): array
    {
        return [
            'key'        => (string) $r['key'],
            'authority'  => (string) $r['authority'],
            'vendor'     => (string) ($r['vendor'] ?? self::VENDOR),
            'public_key' => (string) $r['public_key'],
            'via'        => $via,
        ];
    }
}
