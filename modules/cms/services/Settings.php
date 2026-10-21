<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Service_Settings — /api service for the site/CMS Settings screen.
 *
 * Validates Cms_Form_Settings, then writes the values to the DB `config` table
 * (scope=global) via Tiger_Model_Config — the live-override tier of the config
 * cascade, so a change takes effect on the next request with no deploy. Storage is
 * the config table; there is no separate settings/option table (config-discipline).
 * ACL: admin+ (modules/cms/configs/acl.ini).
 *
 * @api
 */
class Cms_Service_Settings extends Tiger_Service_Service
{
    /**
     * Validate the Settings form and persist the values to the config table.
     *
     * @param  array $params the posted Settings form values
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Cms_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            $g   = Tiger_Model_Config::SCOPE_GLOBAL;
            $cfg->set($g, '', 'tiger.site.name', trim((string) $v['site_name']));

            // The stored value is a CMS page_id, a PATH ("/marketplace"), a theme page
            // ("@theme:<key>[:<slug>]"), or '' for the built-in landing — whatever the combobox (or a
            // free-typed path) put in the field. IndexController resolves it; the form validated its shape.
            $cfg->set($g, '', 'tiger.site.home_page', trim((string) $v['home_page']));

            // The flag is stored POSITIVELY (tiger.site.generator_meta, shipped = 1 in configs/core.ini);
            // the checkbox asks the opposite question, so this is the ONE place the two are reconciled.
            // An unchecked box doesn't submit at all, so cms.settings.js sends an explicit "0" —
            // otherwise un-ticking "hide" could never turn the tag back on.
            $cfg->set($g, '', 'tiger.site.generator_meta', !empty($v['hide_generator']) ? '0' : '1');

            $this->_success([], 'cms.settings.saved', '/cms/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
