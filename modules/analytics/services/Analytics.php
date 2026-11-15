<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Service_Analytics — the /api service behind the Analytics settings screen. Validates the
 * GA4 Measurement ID, then writes the analytics config (enabled + id + exclude-signed-in) to the
 * config tier (live-override, no deploy) at the caller's ORG (TIGER-511) — `_scope()`; the install-wide
 * value stays the fallback. The public tag (Analytics_Plugin_Tag) reads the SITE org's value.
 *
 * @api
 */
class Analytics_Service_Analytics extends Tiger_Service_Service
{
    /**
     * Save the analytics settings.
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

        $form = new Analytics_Form_Settings();
        if (!$form->isValid($params)) {
            $this->_formErrors($form);
            return;
        }
        $id = trim((string) $form->getValue('ga4_measurement_id'));

        try {
            $this->_transaction(function () use ($params, $id) {
                $cfg = new Tiger_Model_Config();
                [$scope, $scopeId] = $this->_scope();
                $cfg->set($scope, $scopeId, 'tiger.analytics.enabled',            !empty($params['enabled']) ? '1' : '0');
                $cfg->set($scope, $scopeId, 'tiger.analytics.ga4.measurement_id',  $id);
                $cfg->set($scope, $scopeId, 'tiger.analytics.exclude_signed_in',   !empty($params['exclude_signed_in']) ? '1' : '0');

                // Reporting: the connect mode (broker | byo), the GA4 property id, and — for BYO — the
                // Google OAuth client creds (secret encrypted; blank secret keeps the current one).
                // The Connect flow reads these afterward. Broker mode needs no client creds locally.
                if (class_exists('Tiger_Google_Analytics')) {
                    Tiger_Google_Analytics::saveMode((string) ($params['oauth_mode'] ?? ''), $scopeId);
                    Tiger_Google_Analytics::saveOauthConfig(
                        (string) ($params['oauth_client_id']     ?? ''),
                        (string) ($params['oauth_client_secret'] ?? ''),
                        (string) ($params['property_id']         ?? ''),
                        $scopeId
                    );
                }
            });
            $this->_success([], 'analytics.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The config scope for analytics writes: the caller's org (TIGER-511). Global only on an install
     * with no org at all.
     *
     * @return array [scope, scopeId]
     */
    protected function _scope()
    {
        return Tiger_Model_Config::settingScope();
    }
}
