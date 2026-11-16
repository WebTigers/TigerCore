<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_I18n_Overrides — the DB override tier of i18n, folded per ORG (TIGER-511).
 *
 * Translation overrides (`translation` rows, Tiger_Model_Translation) layer on top of the shipped
 * language files in two tiers: the install-wide GLOBAL tier, then ONE org's tier on top (org wins). An
 * org therefore sees its own wording where it set one, and inherits the install-wide wording (else the
 * file) everywhere else — never another org's.
 *
 * Which org a request folds:
 *   - the BOOTSTRAP folds the SITE org (`Tiger_Model_Org::siteOrgId` — the tenant the request host maps
 *     to, else the install's own org). Site-facing strings are what the PUBLIC site shows every visitor,
 *     so a member of org B browsing site A reads A's wording, not their own org's. (The config fold uses
 *     the signed-in user's org first; strings deliberately do not, because the translator is built once,
 *     before routing, and most of what it renders is the site.)
 *   - an ADMIN screen (Tiger_Controller_Admin_Action) re-folds to the CALLER's org when it differs, so the
 *     admin chrome reads in the org tier the Translations screen edits. On a single-site install the two
 *     are the same org and nothing is re-folded.
 *
 * @api
 * @since 1.60.0
 * @see Tiger_Model_Translation the DB tier this reads
 * @see Tiger_I18n_Catalog      the file tier underneath
 */
class Tiger_I18n_Overrides
{
    /** @var string|null the org whose tier is folded into the registered translator (null = none yet) */
    private static $_folded = null;

    /** @var string[] the locales the registered translator was built with */
    private static $_locales = [];

    /**
     * The effective overrides for a locale and org: the global tier with the org's tier on top. ''
     * reads the global tier only. Fail-soft: [] with no DB / no `translation` table.
     *
     * @api
     * @param  string $locale language-only locale (en, es)
     * @param  string $orgId  the org whose tier to fold on top ('' = global only)
     * @return array<string,string> key => string
     */
    public static function forLocale($locale, $orgId)
    {
        try {
            $model = new Tiger_Model_Translation();
            $map   = $model->getForLocale($locale, Tiger_Model_Translation::SCOPE_GLOBAL);
            if ((string) $orgId !== '') {
                $map = array_merge($map, $model->getForLocale($locale, Tiger_Model_Translation::SCOPE_ORG, (string) $orgId));
            }
            return $map;
        } catch (Throwable $e) {
            return [];   // no DB / no `translation` table yet — files only
        }
    }

    /**
     * The org the request translator folds at bootstrap: the SITE org — the tenant the request host maps
     * to, else the install's own org. Deliberately NOT the signed-in visitor's org (see the class
     * docblock): a page of site A reads A's wording whoever is looking at it.
     *
     * @api
     * @return string the org id, or '' before any org exists (a pre-install install: global tier only)
     */
    public static function requestOrgId()
    {
        return class_exists('Tiger_Model_Org') ? (string) Tiger_Model_Org::siteOrgId() : '';
    }

    /**
     * The org an ADMIN screen folds: the signed-in caller's org (the tier the Translations screen edits),
     * else the site org — Tiger_Model_Config::currentOrgId(), the same org an admin settings screen saves for.
     *
     * @api
     * @return string
     */
    public static function adminOrgId()
    {
        return (string) Tiger_Model_Config::currentOrgId();
    }

    /**
     * Fold the override tiers for one locale into a translator (called per locale by the bootstrap,
     * after that locale's files). Records the org so refold() knows what is folded.
     *
     * @param  Zend_Translate $translate the request translator
     * @param  string         $locale    the locale just loaded from files
     * @param  string         $orgId     the org whose tier to fold
     * @return void
     */
    public static function fold(Zend_Translate $translate, $locale, $orgId)
    {
        $overrides = self::forLocale($locale, $orgId);
        if ($overrides) {
            $translate->addTranslation(['content' => $overrides, 'locale' => $locale]);
        }
        self::$_folded = (string) $orgId;
        if (!in_array((string) $locale, self::$_locales, true)) {
            self::$_locales[] = (string) $locale;
        }
    }

    /**
     * The org whose tier the registered translator currently carries (null before the bootstrap fold).
     *
     * @api
     * @return string|null
     */
    public static function foldedOrgId()
    {
        return self::$_folded;
    }

    /**
     * Re-fold the registered translator for another org: each locale is rebuilt from its files, then
     * the global tier, then that org's tier — so no string of the previously folded org survives. A
     * no-op when that org is already folded (the single-site case) or no translator is registered.
     *
     * @api
     * @param  string $orgId the org to fold
     * @return bool   true when the translator was rebuilt
     */
    public static function refold($orgId)
    {
        $orgId = (string) $orgId;
        if (self::$_folded === null || self::$_folded === $orgId || !Zend_Registry::isRegistered('Zend_Translate')) {
            return false;
        }
        $translate = Zend_Registry::get('Zend_Translate');
        foreach (self::$_locales as $locale) {
            $data = [];
            foreach (Tiger_I18n_Catalog::files($locale) as $file) {
                $part = include $file;   // each file returns [key => string]
                if (is_array($part)) { $data = array_merge($data, $part); }
            }
            $data = array_merge($data, self::forLocale($locale, $orgId));
            $translate->addTranslation(['content' => $data, 'locale' => $locale, 'clear' => true]);
        }
        self::$_folded = $orgId;
        return true;
    }

    /**
     * Forget what was folded (tests; a fresh bootstrap).
     *
     * @return void
     */
    public static function reset()
    {
        self::$_folded  = null;
        self::$_locales = [];
    }
}
