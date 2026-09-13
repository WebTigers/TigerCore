<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_LegalLinks — the footer's privacy/terms links, without anyone having to know
 * a config key exists (TIGER-119).
 *
 * The links used to render ONLY from `tiger.footer.privacy_url` / `.terms_url`. That is correct for
 * a customer install with no such pages — and it is also how webtigers.com ran for seven weeks with
 * a published privacy policy that nothing linked to. The pages and the config were separate steps,
 * one got done and the other didn't, and an unset config is indistinguishable from "this site
 * deliberately shows no legal links": no error, no warning, nothing a test can catch. It surfaced
 * only because Google's OAuth verification requires the policy to be linked from the home page.
 *
 * So: publish a page at `privacy`, get a Privacy link. Create nothing, get nothing — which is the
 * behaviour a customer install needs. The explicit config still wins for a site whose legal pages
 * live on another domain or under different slugs.
 *
 *   $links = $this->legalLinks();      // ['privacy' => '/privacy'|null, 'terms' => '/terms'|null]
 *
 * @api
 */
class Tiger_View_Helper_LegalLinks extends Zend_View_Helper_Abstract
{
    /** Config key prefix; `<prefix>.<name>_url` is the explicit override. */
    const CFG_PREFIX = 'footer';

    /** Slugs looked for when there is no explicit override, keyed by link name. */
    const SLUGS = ['privacy' => 'privacy', 'terms' => 'terms'];

    /** @var array<string,string|null>|null resolved links, memoized per request */
    protected static $_memo = null;

    /**
     * @return array<string,string|null> ['privacy' => url|null, 'terms' => url|null]
     */
    public function legalLinks()
    {
        if (self::$_memo !== null) {
            return self::$_memo;
        }
        $out = [];
        foreach (self::SLUGS as $name => $slug) {
            $out[$name] = $this->_config($name . '_url') ?? $this->_published($slug);
        }
        return self::$_memo = $out;
    }

    /** Forget the memo — tests, and anything that changes config mid-request. */
    public static function reset()
    {
        self::$_memo = null;
    }

    /** The explicit `tiger.footer.<key>` override, or null when unset/blank. */
    protected function _config($key)
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return null; }
        $node = Zend_Registry::get('Zend_Config')->get('tiger');
        foreach ([self::CFG_PREFIX, $key] as $seg) {
            if (!($node instanceof Zend_Config)) { return null; }
            $node = $node->get($seg);
        }
        return (is_string($node) && trim($node) !== '') ? trim($node) : null;
    }

    /**
     * `/<slug>` when a live page resolves there, else null.
     *
     * Goes through Tiger_Model_Page::resolveBySlug(), which already decides what "live" means —
     * published, schedule arrived, right locale, right tenant. Re-deciding that here is how a footer
     * ends up linking a draft.
     *
     * FAIL-SOFT. A footer must never be the reason a page 500s: no database, no `page` table, or a
     * half-built install all mean "no link", not an exception.
     */
    protected function _published($slug)
    {
        try {
            if (!class_exists('Tiger_Model_Page') || !Zend_Db_Table_Abstract::getDefaultAdapter()) {
                return null;
            }
            $row = (new Tiger_Model_Page())->resolveBySlug(
                $slug,
                $this->_locale(),
                $this->_orgId(),
                Tiger_Model_Page::TYPE_PAGE
            );
            return $row ? '/' . $slug : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** The request locale, for the page model's locale cascade. */
    protected function _locale()
    {
        try {
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                return (string) Zend_Registry::get('Zend_Locale');
            }
        } catch (Throwable $e) {
            // fall through
        }
        return '';
    }

    /** The tenant scope, so a per-org legal page wins over the global one. */
    protected function _orgId()
    {
        try {
            $identity = Zend_Auth::getInstance()->getIdentity();
            return (string) ($identity->org_id ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }
}
