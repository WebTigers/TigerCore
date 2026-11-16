<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo_Service_Matomo — the /api service behind the Matomo setup (hosted on the Analytics screen's
 * "Matomo" provider panel since 1.61). Validates the URL + site id + token + affiliate dimension, then
 * writes the Matomo config (enabled + url + site_id + exclude-signed-in + affiliate.dimension_id, and
 * the encrypted `token_auth_enc` when one is sent) to the config tier (live-override, no deploy) at the
 * caller's ORG (TIGER-511) — `_scope()`; the install-wide value stays the fallback for the plain
 * settings, never for the token. The public tag (Matomo_Plugin_Tag) reads the SITE org's value.
 *
 * @api
 */
class Matomo_Service_Matomo extends Tiger_Service_Service
{
    /**
     * Save the Matomo settings.
     *
     * @param  array $params the posted form values (+ enabled / exclude_signed_in switches)
     * @apiRequest Matomo_Form_Settings
     * @apiParam boolean enabled Turn Matomo tracking on.
     * @apiParam boolean exclude_signed_in Do not track signed-in staff.
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

        // The Reporting API token: write-only, encrypted at rest, at the caller's ORG only (no install
        // fallback for another org — Tiger_Analytics_Adapter_Matomo reads it org-level). Blank keeps the
        // stored token. With no crypto key it is refused rather than stored in the clear.
        $token = trim((string) $form->getValue('matomo_token_auth'));
        if ($token !== '' && !(class_exists('Tiger_Crypto') && Tiger_Crypto::isConfigured())) {
            $this->_error('matomo.error.no_crypto');
            return;
        }

        try {
            $this->_transaction(function () use ($params, $url, $siteId, $dim, $token) {
                $cfg = new Tiger_Model_Config();
                [$scope, $scopeId] = $this->_scope();
                if ($token !== '') {
                    $cfg->set($scope, $scopeId, Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, (string) Tiger_Crypto::encrypt($token));
                }
                $cfg->set($scope, $scopeId, 'tiger.matomo.enabled',                 !empty($params['enabled']) ? '1' : '0');
                $cfg->set($scope, $scopeId, 'tiger.matomo.url',                     $url);
                $cfg->set($scope, $scopeId, 'tiger.matomo.site_id',                 $siteId);
                $cfg->set($scope, $scopeId, 'tiger.matomo.exclude_signed_in',       !empty($params['exclude_signed_in']) ? '1' : '0');
                $cfg->set($scope, $scopeId, 'tiger.matomo.affiliate.dimension_id',  (string) $dim);
            });
            // Report whether a token is now held — never the token itself.
            [, $orgId] = $this->_scope();
            $this->_success(['has_token' => (new Tiger_Analytics_Adapter_Matomo())->hasToken((string) $orgId)], 'matomo.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The config scope for Matomo writes: the caller's org (TIGER-511). Global only on an install
     * with no org at all.
     *
     * @return array [scope, scopeId]
     */
    protected function _scope()
    {
        return Tiger_Model_Config::settingScope();
    }
}
