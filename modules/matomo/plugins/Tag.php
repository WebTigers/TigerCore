<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_Plugin_Tag — emits the self-hosted Matomo (`_paq`) tracking snippet into the head, on public
 * pages, when it's configured AND consent allows it. It appends to the `tigerTracking` head placeholder
 * that the public layout renders high in <head>; the admin/auth layouts don't render it, so admin
 * traffic is never tagged. Fail-open — a broken lookup never breaks the page.
 *
 * Consent: the snippet is emitted only if Tiger_Consent::allows('analytics') — TRUE until the
 * cookie-consent feature sets a mode (so Matomo works standalone), then honors the visitor's choice.
 * (Matomo's own install also honors DoNotTrack + anonymizes IPs server-side.)
 *
 * Affiliate attribution (TIGER-306): if the visitor carries the affiliate cookie (the `/a/<name>`
 * contract — see AFFILIATE_COOKIE), its validated name is pushed as a Matomo visit-scoped custom
 * dimension BEFORE trackPageView, so every visit + conversion is segmentable by affiliate. The cookie
 * is WRITTEN by TigerAffiliates; this plugin only READS it (the name is validated + JSON-encoded before
 * it reaches the page). With no affiliate module installed the dimension is simply never set.
 *
 * @api
 */
class Matomo_Plugin_Tag extends Zend_Controller_Plugin_Abstract
{
    /** Emit-once latch (the tag is identical on every dispatch/forward). */
    private static $_done = false;

    /**
     * The affiliate attribution cookie — the `/a/<name>` contract. TigerAffiliates (TIGER-303) writes it
     * when a visitor arrives on an affiliate link; this plugin reads it into the Matomo custom dimension.
     * Changing this name is a cross-module contract change — keep the two modules in lockstep.
     */
    const AFFILIATE_COOKIE = 'tiger_aff';

    /** A safe affiliate name: a slug of letters/digits/._- up to 64 chars. Anything else is ignored. */
    const AFFILIATE_NAME_RE = '/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/';

    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (self::$_done) {
            return;
        }
        self::$_done = true;

        try {
            if (!self::isConfigured()) {
                return;   // not configured / disabled
            }
            // GDPR gate: load only when consent allows this category.
            if (class_exists('Tiger_Consent') && !Tiger_Consent::allows('analytics')) {
                return;
            }
            // Optionally skip signed-in staff (don't pollute stats with your own team).
            if (self::_excludeSignedIn() && self::_isSignedInStaff()) {
                return;
            }
            self::_view()->placeholder('tigerTracking')->append(self::_snippet());
        } catch (Throwable $e) {
            // fail-open — analytics must never take down a page render
        }
    }

    /** True when Matomo is enabled AND a valid URL + a positive site id are set. (Used by the registry.) */
    public static function isConfigured()
    {
        return self::_boolConfig('matomo.enabled') && self::url() !== '' && self::siteId() > 0;
    }

    /** The normalized Matomo base URL (always trailing-slashed), or '' when disabled/unset/invalid. */
    public static function url()
    {
        $u = trim((string) self::_config('matomo.url', ''));
        if ($u === '' || !preg_match('#^https?://[^\s"\'<>]+$#i', $u)) {
            return '';
        }
        return rtrim($u, '/') . '/';
    }

    /** The configured Matomo site id (idsite) as a positive int, or 0 when unset/invalid. */
    public static function siteId()
    {
        $id = (int) self::_config('matomo.site_id', '0');
        return $id > 0 ? $id : 0;
    }

    /** The Matomo visit-scoped custom-dimension INDEX used for affiliate attribution (default 1). */
    public static function affiliateDimensionId()
    {
        $d = (int) self::_config('matomo.affiliate.dimension_id', '1');
        return $d > 0 ? $d : 0;
    }

    /** The validated affiliate name from the `/a/<name>` contract cookie, or '' when absent/invalid. */
    public static function affiliateName()
    {
        $v = isset($_COOKIE[self::AFFILIATE_COOKIE]) ? trim((string) $_COOKIE[self::AFFILIATE_COOKIE]) : '';
        return ($v !== '' && preg_match(self::AFFILIATE_NAME_RE, $v)) ? $v : '';
    }

    /** The Matomo `_paq` snippet for the (already-validated) URL + site id, with the affiliate dimension. */
    private static function _snippet()
    {
        $u   = self::url();        // validated, trailing-slashed
        $sid = self::siteId();     // positive int
        $jsU = json_encode($u);    // safe JS string literal

        $out  = "\n<!-- Matomo — Tiger Analytics -->\n<script>\n";
        $out .= "var _paq = window._paq = window._paq || [];\n";

        // Affiliate attribution: set the visit-scoped custom dimension BEFORE the pageview, if present.
        $aff = self::affiliateName();
        $dim = self::affiliateDimensionId();
        if ($aff !== '' && $dim > 0) {
            $out .= "_paq.push(['setCustomDimension', " . $dim . ', ' . json_encode($aff) . "]);\n";
        }

        $out .= "_paq.push(['trackPageView']);\n";
        $out .= "_paq.push(['enableLinkTracking']);\n";
        $out .= "(function(){ var u=" . $jsU . ";\n";
        $out .= "_paq.push(['setTrackerUrl', u+'matomo.php']);\n";
        $out .= "_paq.push(['setSiteId', '" . $sid . "']);\n";
        $out .= "var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];\n";
        $out .= "g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);\n";
        $out .= "})();\n</script>\n<!-- End Matomo Code -->\n";
        return $out;
    }

    /** Whether to exclude signed-in staff from tracking (config, default on). */
    private static function _excludeSignedIn()
    {
        return self::_boolConfig('matomo.exclude_signed_in', true);
    }

    /** True if the current visitor is authenticated with a staff (manager+) role. */
    private static function _isSignedInStaff()
    {
        if (!class_exists('Zend_Auth') || !Zend_Auth::getInstance()->hasIdentity()) {
            return false;
        }
        $identity = Zend_Auth::getInstance()->getIdentity();
        $role     = is_object($identity) && isset($identity->role) ? strtolower((string) $identity->role) : '';
        return in_array($role, ['manager', 'supermanager', 'admin', 'superadmin', 'developer'], true);
    }

    /** A Zend_View to reach the placeholder helper (shares the process-wide registry). */
    private static function _view()
    {
        if (Zend_Registry::isRegistered('Zend_View')) {
            $v = Zend_Registry::get('Zend_View');
            if ($v instanceof Zend_View_Interface) {
                return $v;
            }
        }
        return new Zend_View();
    }

    /** Read a `tiger.<dotKey>` config value with a default. */
    private static function _config($dotKey, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return $default;
        }
        $node = Zend_Registry::get('Zend_Config')->get('tiger');
        foreach (explode('.', $dotKey) as $seg) {
            if (!($node instanceof Zend_Config)) { return $default; }
            $node = $node->get($seg);
            if ($node === null) { return $default; }
        }
        return is_scalar($node) ? (string) $node : $default;
    }

    /** Read a boolean-ish `tiger.<dotKey>` config value. */
    private static function _boolConfig($dotKey, $default = false)
    {
        $v = self::_config($dotKey, $default ? '1' : '0');
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }
}
