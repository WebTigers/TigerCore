<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations admin — search, edit, and override every translation string per locale.
 *
 * Thin per ADMIN.md: it renders the shell (the locale selector + the DataTables grid + the edit
 * modal) and hands the data work to Translations_Service_Translation over /api. The admin layout
 * comes from the base (Tiger_Controller_Admin_Action).
 */
class Translations_IndexController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();   // base sets layout('admin')
    }

    /** The Translations screen: a target-locale selector + a searchable key/value grid. */
    public function indexAction()
    {
        $svc = new Translations_Service_Translation();
        // Reuse the service's own resolution so the view and the /api feed agree on the locale set.
        $supported = $this->_supportedLocales();
        $default   = $this->_defaultLocale($supported);

        $this->view->title     = 'Translations — Tiger Admin';
        $this->view->locales   = $supported;
        $this->view->localeMap = $this->_localeNames($supported);
        $this->view->default   = $default;
        $this->view->target    = $this->_firstNonDefault($supported, $default);
    }

    /** Supported language-only locales (LocalePrefix's resolved set, else config). */
    protected function _supportedLocales()
    {
        if (defined('SUPPORTED_LANGS') && is_array(SUPPORTED_LANGS) && SUPPORTED_LANGS) {
            return array_values(SUPPORTED_LANGS);
        }
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $i18n = ($cfg && $cfg->get('tiger') && $cfg->tiger->get('i18n')) ? $cfg->tiger->i18n : null;
        $list = $i18n ? array_values(array_filter(array_map('trim', explode(',', (string) $i18n->get('locales'))))) : [];
        return $list ?: ['en'];
    }

    protected function _defaultLocale(array $supported)
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $i18n = ($cfg && $cfg->get('tiger') && $cfg->tiger->get('i18n')) ? $cfg->tiger->i18n : null;
        $d = $i18n ? (string) $i18n->get('default') : '';
        return ($d !== '' && in_array($d, $supported, true)) ? $d : $supported[0];
    }

    protected function _firstNonDefault(array $supported, $default)
    {
        foreach ($supported as $code) { if ($code !== $default) { return $code; } }
        return $default;
    }

    /** Language display names for the selector (fallback = the code). */
    protected function _localeNames(array $supported)
    {
        $names = [
            'en' => 'English',  'es' => 'Español', 'pt' => 'Português', 'de' => 'Deutsch',
            'fr' => 'Français', 'hi' => 'हिन्दी',   'it' => 'Italiano', 'nl' => 'Nederlands',
            'ja' => '日本語',    'zh' => '中文',
        ];
        $out = [];
        foreach ($supported as $code) { $out[$code] = $names[$code] ?? strtoupper($code); }
        return $out;
    }
}
