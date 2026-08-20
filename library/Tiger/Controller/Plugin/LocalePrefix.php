<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Controller_Plugin_LocalePrefix — semantic /xx/ locale URLs + language resolution.
 *
 * Makes an optional canonical /xx/ prefix work for EVERY route without editing any
 * of them: it resolves the request's language and strips a leading supported-
 * language segment so the existing routes match the rest.
 *
 *   /es/pricing          -> routes "/pricing"          (Spanish; cookie set to es)
 *   /es/billing/invoice  -> routes "/billing/invoice"
 *   /es                  -> routes "/"                 (home, Spanish)
 *   /pricing             -> routes "/pricing"          (language from cookie/browser)
 *
 * Resolution precedence (first match wins), then the choice is persisted to the
 * `locale` cookie so it sticks (matches AskLevi):
 *   1. URL prefix  /xx/            explicit for THIS navigation; the hard source of truth
 *   2. `locale` cookie             the last EXPLICIT selection (UI switcher or a prior /xx/ URL)
 *   3. signed-in user's locale     user.locale — a personalized FALLBACK across devices; a SUGGESTION,
 *                                  never a mandate: it can't override an explicit URL/cookie choice
 *   4. browser Accept-Language     the first-visit default
 *   5. configured default          tiger.i18n.default, else the first supported language
 *
 * Languages are LANGUAGE-ONLY (en, es) per Tiger convention. Only a code in the
 * supported list is treated as a prefix, so a content slug like "no"/"it" is never
 * mistaken for a locale. Defines LANG and registers Zend_Locale for the request.
 * The header language switcher (tiger.prefs.js) writes the same `locale` cookie +
 * localStorage client-side, so the two mechanisms agree.
 *
 * @api
 */
class Tiger_Controller_Plugin_LocalePrefix extends Zend_Controller_Plugin_Abstract
{
    /** @var string[] supported language codes (language-only) */
    protected $_supported;

    /** @var string fallback language */
    protected $_default;

    /**
     * Configure the supported languages and the fallback language.
     *
     * @param  string[] $supported supported language codes (language-only)
     * @param  string   $default   fallback language when none resolves
     * @return void
     */
    public function __construct(array $supported = ['en'], string $default = 'en')
    {
        $this->_supported = $supported ?: ['en'];
        $this->_default   = in_array($default, $this->_supported, true) ? $default : $this->_supported[0];
    }

    /**
     * Resolve the request language and strip a leading /xx/ prefix so the routes match.
     *
     * @param  Zend_Controller_Request_Abstract $request the current request
     * @return void
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        if (!$request instanceof Zend_Controller_Request_Http) {
            return;
        }

        $lang = null;

        // 1. URL prefix /xx/ (only a SUPPORTED language) — strip it so routes match.
        $path = $request->getPathInfo();
        if (preg_match('#^/([a-z]{2})(?=/|$)(.*)$#', $path, $m) && in_array($m[1], $this->_supported, true)) {
            $lang = $m[1];
            $request->setPathInfo($m[2] !== '' ? $m[2] : '/');
        }

        // 2. cookie — the last EXPLICIT language selection (the header switcher writes it; a prior /xx/
        //    visit does too). An explicit choice on THIS device wins over a stored account preference —
        //    picking a language in the UI must actually take effect, even when signed in.
        if ($lang === null && isset($_COOKIE['locale']) && in_array($_COOKIE['locale'], $this->_supported, true)) {
            $lang = $_COOKIE['locale'];
        }

        // 3. the signed-in user's stored locale — a personalized FALLBACK: their language follows them
        //    across devices ONLY when they haven't explicitly chosen one here (no /xx/ URL, no cookie).
        //    It's a suggestion, never a mandate — it can't override the explicit choices above.
        if ($lang === null) {
            $lang = $this->_fromUser();
        }

        // 4. browser Accept-Language, then 5. the configured default.
        if ($lang === null) {
            $lang = $this->_fromBrowser() ?? $this->_default;
        }

        // Persist server-side so the choice sticks next request (the UI switcher
        // also sets this cookie + localStorage client-side).
        if (!headers_sent()) {
            setcookie('locale', $lang, [
                'expires'  => time() + 31536000,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['locale'] = $lang;   // visible to the rest of THIS request too

        defined('LANG') || define('LANG', $lang);
        // Expose the supported set so /api services can honor a payload `locale`
        // (Tiger_Service_Service checks SUPPORTED_LANGS).
        defined('SUPPORTED_LANGS') || define('SUPPORTED_LANGS', $this->_supported);
        try { Zend_Registry::set('Zend_Locale', new Zend_Locale($lang)); } catch (Throwable $e) {}

        // Point the shared translator (built in _initTranslate with all locales
        // loaded) at the resolved language for this request.
        if (Zend_Registry::isRegistered('Zend_Translate')) {
            try { Zend_Registry::get('Zend_Translate')->setLocale($lang); } catch (Throwable $e) {}
        }
    }

    /**
     * The signed-in user's stored locale (`user.locale`) if it's a supported language, else null.
     *
     * A personalized FALLBACK, resolved only after the URL + cookie: it fills in a signed-in user's
     * language when they haven't explicitly chosen one here, never overriding an explicit choice. Fully
     * guarded + graceful: no identity (guest), no row, or an unsupported/blank value all yield null,
     * and the DB lookup can never break routing. It's one indexed PK read per authenticated dynamic
     * request — cheap; if it ever matters, cache the locale on the auth identity at login instead.
     *
     * @return string|null
     */
    protected function _fromUser()
    {
        try {
            $auth = Zend_Auth::getInstance();
            if (!$auth->hasIdentity()) {
                return null;
            }
            $id     = $auth->getIdentity();
            $userId = is_object($id) ? ($id->user_id ?? null) : (is_array($id) ? ($id['user_id'] ?? null) : null);
            if (empty($userId)) {
                return null;
            }
            return (new Tiger_Model_User())->preferredLocale((string) $userId, $this->_supported);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** First supported language from the browser's Accept-Language header, or null. */
    protected function _fromBrowser()
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if ($code !== '' && in_array($code, $this->_supported, true)) {
                return $code;
            }
        }
        return null;
    }
}
