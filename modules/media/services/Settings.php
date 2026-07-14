<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Service_Settings — /api service for the Media Library settings screen.
 *
 * Validates Media_Form_Settings, then writes the filename-obfuscation choices to the `config`
 * table via Tiger_Model_Config — scoped to the acting ORG (else global for a single-tenant
 * install), so each tenant controls its own naming with no deploy. Config store only, no
 * separate settings table (config-discipline). ACL: admin+ (configs/acl.ini).
 *
 * @api
 */
class Media_Service_Settings extends Tiger_Service_Service
{
    /**
     * Validate the settings form and persist the obfuscation flags (per visibility, org-scoped).
     *
     * @param  array $params the posted settings form values
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Media_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            list($scope, $sid) = Tiger_Model_Media::settingScope((string) ($this->_org_id ?? ''));
            $cfg->set($scope, $sid, Tiger_Model_Media::CFG_OBFUSCATE . 'public',  ((string) $v['obfuscate_public']  === '1') ? '1' : '0');
            $cfg->set($scope, $sid, Tiger_Model_Media::CFG_OBFUSCATE . 'private', ((string) $v['obfuscate_private'] === '1') ? '1' : '0');

            $this->_success([], 'media.settings.saved', '/media/admin/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
