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
 *   - **Fail-soft:** an untranslated key returns the key verbatim (never a fatal, never a blank) — so a
 *     missing translation is visible and greppable, not an empty page.
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
        $text = ($tr && $tr->isTranslated($key)) ? $tr->translate($key) : $key;

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
}
