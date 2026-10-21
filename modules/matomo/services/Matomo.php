<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_Service_Matomo — the /api service behind the Matomo settings screen. Validates the URL +
 * site id + affiliate dimension, then writes the Matomo config (enabled + url + site_id +
 * exclude-signed-in + affiliate.dimension_id) to the config tier (live-override, no deploy). GLOBAL
 * scope for now — the single site; a future multi-site module flips it to per-org (isolated in
 * `_scope()`, same pattern as the Analytics module).
 *
 * @api
 */
class Matomo_Service_Matomo extends Tiger_Service_Service
{
    /**
     * Save the Matomo settings.
     *
     * @param  array $params the posted form values (+ enabled / exclude_signed_in switches)
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) {
            $this->_error('core.api.error.not_allowed');
            return;
        }

        $form = new Matomo_Form_Settings();
        if (!$form->isValid($params)) {
            $this->_formErrors($form);
            return;
        }

        $url = rtrim(trim((string) $form->getValue('matomo_url')), '/');
        if ($url !== '') {
            $url .= '/';
        }
        $siteId = (string) (int) $form->getValue('matomo_site_id');
        $dim    = (int) $form->getValue('matomo_affiliate_dimension_id');
        $dim    = $dim > 0 ? $dim : 1;   // a visit-scoped dimension index is always >= 1

        try {
            $this->_transaction(function () use ($params, $url, $siteId, $dim) {
                $cfg = new Tiger_Model_Config();
                [$scope, $scopeId] = $this->_scope();
                $cfg->set($scope, $scopeId, 'tiger.matomo.enabled',                 !empty($params['enabled']) ? '1' : '0');
                $cfg->set($scope, $scopeId, 'tiger.matomo.url',                     $url);
                $cfg->set($scope, $scopeId, 'tiger.matomo.site_id',                 $siteId);
                $cfg->set($scope, $scopeId, 'tiger.matomo.exclude_signed_in',       !empty($params['exclude_signed_in']) ? '1' : '0');
                $cfg->set($scope, $scopeId, 'tiger.matomo.affiliate.dimension_id',  (string) $dim);
            });
            $this->_success([], 'matomo.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The config scope for Matomo writes. GLOBAL today; a multi-site module overrides to per-org.
     *
     * @return array [scope, scopeId]
     */
    protected function _scope()
    {
        return [Tiger_Model_Config::SCOPE_GLOBAL, ''];
    }
}
