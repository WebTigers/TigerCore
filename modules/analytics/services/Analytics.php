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
     * @apiRequest Analytics_Form_Settings
     * @apiParam boolean enabled Turn the analytics tracking tag on.
     * @apiParam boolean exclude_signed_in Skip tracking for signed-in visitors.
     * @apiParam string(broker|byo) oauth_mode How reporting connects to Google (anything else saves as broker).
     * @apiParam string oauth_client_id The Google OAuth client id (bring-your-own mode).
     * @apiParam string oauth_client_secret The Google OAuth client secret; blank keeps the current one.
     * @apiParam string property_id The GA4 property id reports are read from.
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

                // The Measurement Protocol API secret for server-side events (Tiger_Analytics::track) —
                // encrypted, org-level; a blank field keeps the stored one (it is never sent back to the page).
                $mp = trim((string) ($params['mp_api_secret'] ?? ''));
                if ($mp !== '' && class_exists('Tiger_Crypto') && Tiger_Crypto::isConfigured()) {
                    $cfg->set($scope, $scopeId, Tiger_Analytics_Adapter_Ga::CFG_API_SECRET, (string) Tiger_Crypto::encrypt($mp));
                }

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
     * Choose the org's analytics REPORTING provider (`provider` = a Tiger_Analytics provider key, or ''
     * for automatic). Writes only `tiger.analytics.provider` at the caller's org — the other provider's
     * settings are never touched, and tracking tags are unaffected (each has its own enabled flag).
     *
     * @param  array $params `provider` + the screen's `_csrf`
     * @apiRequest Analytics_Form_Provider
     * @return void
     */
    public function saveProvider(array $params): void
    {
        if (!$this->_isAllowed('saveProvider')) {
            $this->_error('core.api.error.not_allowed');
            return;
        }
        $form = new Analytics_Form_Provider();
        if (!$form->isValid($params)) {
            $this->_formErrors($form);
            return;
        }
        $provider = (string) $form->getValue('provider');
        [, $orgId] = $this->_scope();
        try {
            if (!Tiger_Analytics::saveProvider($provider, $orgId)) {
                $this->_error('analytics.provider.invalid');
                return;
            }
            $this->_success(Tiger_Analytics::overview((string) $orgId), 'analytics.provider.saved');
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
