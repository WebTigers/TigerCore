<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translation — live translation overrides (the DB tier of i18n; see migration 0013).
 *
 * File translations (core/app/module languages/<lang>/*.php) are the base; rows
 * here OVERRIDE or ADD strings at REQUEST time with no deploy — the same idea as
 * the `config` table for config. Read by Tiger_Application_Bootstrap::_initTranslate
 * and layered on top of the files (last wins). Scopes: global (platform-wide) and
 * org (per-tenant, reserved for later). Keys are owner-prefixed semantic keys
 * (core.*, app.*, <module>.*); locale is language-only (en, es).
 *
 * @api
 */
class Tiger_Model_Translation extends Tiger_Model_Table
{
    protected $_name    = 'translation';
    protected $_primary = 'translation_id';

    const SCOPE_GLOBAL = 'global';
    const SCOPE_ORG    = 'org';

    /**
     * Overrides for a locale + scope as a key => value map, ready to hand to
     * Zend_Translate::addTranslation.
     *
     * @param  string $locale  language-only locale (en, es)
     * @param  string $scope   SCOPE_GLOBAL | SCOPE_ORG
     * @param  string $scopeId scope discriminator (org id for SCOPE_ORG, '' otherwise)
     * @return array<string,string>
     */
    public function getForLocale($locale, $scope = self::SCOPE_GLOBAL, $scopeId = '')
    {
        $rows = $this->fetchAll(
            $this->activeSelect()
                ->where('locale = ?', (string) $locale)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row->translation_key] = $row->translation_value;
        }
        return $map;
    }

    /**
     * A single override value, or null.
     *
     * @param  string $locale  language-only locale (en, es)
     * @param  string $scope   SCOPE_GLOBAL | SCOPE_ORG
     * @param  string $scopeId scope discriminator (org id for SCOPE_ORG, '' otherwise)
     * @param  string $key     owner-prefixed semantic translation key
     * @return string|null the override value, or null if none
     */
    public function get($locale, $scope, $scopeId, $key)
    {
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('locale = ?', (string) $locale)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
                ->where('translation_key = ?', $key)
        );
        return $row ? $row->translation_value : null;
    }

    /**
     * Upsert one override. Returns the translation_id.
     *
     * @param  string $locale  language-only locale (en, es)
     * @param  string $scope   SCOPE_GLOBAL | SCOPE_ORG
     * @param  string $scopeId scope discriminator (org id for SCOPE_ORG, '' otherwise)
     * @param  string $key     owner-prefixed semantic translation key
     * @param  string $value   the override text
     * @return string the translation_id (existing on update, generated on insert)
     */
    public function set($locale, $scope, $scopeId, $key, $value)
    {
        $existing = $this->fetchRow(
            $this->activeSelect()
                ->where('locale = ?', (string) $locale)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
                ->where('translation_key = ?', $key)
        );
        if ($existing) {
            $this->update(
                ['translation_value' => $value],
                $this->getAdapter()->quoteInto('translation_id = ?', $existing->translation_id)
            );
            return $existing->translation_id;
        }
        return $this->insert([
            'locale'            => (string) $locale,
            'scope'             => $scope,
            'scope_id'          => (string) $scopeId,
            'translation_key'   => $key,
            'translation_value' => $value,
        ]);
    }
}
