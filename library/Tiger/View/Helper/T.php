<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_T — translate a semantic key inside a view: `$this->t('cms.page.title')`.
 *
 * The idiomatic way to emit a localized string from a `.phtml`. Views have no `$this->_t()` (that plugin
 * name isn't registered and throws); this helper is the sanctioned replacement, mirroring `Tiger_Form::_t`
 * so a form label and the surrounding view translate through the SAME contract:
 *
 *   - Resolves against the shared `Zend_Translate` built in `_initTranslate` (files cascade + DB overrides).
 *   - **Source-locale fallback:** a key missing in the ACTIVE locale falls back to the source/default
 *     locale (`tiger.i18n.default`, `en`) before giving up — so a module that ships only some locales
 *     (autonomous modules own their own translations, but may not have caught every locale yet) degrades
 *     to English, **never a raw key on screen**. Only a key absent from *every* locale returns verbatim
 *     (greppable — a genuinely undefined key, not a missing translation).
 *   - **Interpolation:** extra args are `vsprintf`'d into the resolved string, so a key can carry `%s`/`%d`
 *     placeholders — `$this->t('cms.page.deleted_n', $count)`.
 *
 * This is what makes a new locale a drop-in: every view string is a key, so a translator fills
 * `languages/<lang>/<module>.php` and nothing in the view changes. See I18N.md.
 *
 *   <h1><?= $this->escape($this->t('cms.page.title')) ?></h1>
 *   <p><?= $this->t('cms.page.count', $n) ?></p>
 *
 * @api
 */
class Tiger_View_Helper_T extends Zend_View_Helper_Abstract
{
    /**
     * Translate a key, optionally interpolating sprintf-style args.
     *
     * @param  string $key      the semantic, owner-prefixed translation key
     * @param  mixed  ...$args   optional values interpolated into the string's %s/%d placeholders
     * @return string           the localized string, or the key itself when untranslated
     */
    public function t($key, ...$args)
    {
        $key  = (string) $key;
        $tr   = $this->_translate();

        if (!$tr) {
            $text = $key;
        } elseif ($tr->isTranslated($key)) {
            $text = $tr->translate($key);                                   // the active locale has it
        } elseif (($fb = self::_fallbackLocale()) !== '' && $tr->isTranslated($key, false, $fb)) {
            $text = $tr->translate($key, $fb);                              // degrade to the source locale (en)
        } else {
            $text = $key;                                                  // genuinely undefined key
        }

        if ($args) {
            $out = @vsprintf($text, $args);
            if ($out !== false) { $text = $out; }   // a mismatched format string never fatals the view
        }
        return $text;
    }

    /**
     * The shared translator, or null when none is registered (early boot / CLI). Resolved per call —
     * the registry lookup is trivial, and NOT caching keeps the locale swappable within a process (tests,
     * a future per-request locale switch) with no stale-translator footgun.
     *
     * @return Zend_Translate|null
     */
    protected function _translate()
    {
        return Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
    }

    /**
     * The source/default locale to fall back to when a key is absent from the active locale — the locale
     * every base language file ships in, so it always has the key. Reads `tiger.i18n.default` (config),
     * defaulting to `en`. Empty string only if config resolution fails hard (then no fallback is attempted).
     *
     * @return string
     */
    protected static function _fallbackLocale()
    {
        try {
            if (Zend_Registry::isRegistered('Zend_Config')) {
                $cfg  = Zend_Registry::get('Zend_Config');
                $i18n = ($cfg->get('tiger') instanceof Zend_Config) ? $cfg->get('tiger')->get('i18n') : null;
                $def  = ($i18n instanceof Zend_Config) ? (string) $i18n->get('default') : '';
                if ($def !== '') { return $def; }
            }
        } catch (Throwable $e) { /* fall through to the hard default */ }
        return 'en';
    }
}
