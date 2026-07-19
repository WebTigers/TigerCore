<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Service_Settings — the /api service behind the TigerAgent settings screen.
 *
 * Writes the agent config to the eager `config` tier (live-override, no deploy): the on/off
 * switch, the provider + model, and the BYO API key. The key is encrypted with Tiger_Crypto
 * (`tiger.agent.api_key_enc`) and NEVER round-trips to the browser — a blank key field on
 * save keeps the stored one (the same masked-secret convention as the analytics OAuth creds).
 * BYO is deliberate: the org connects its own AI account, so WebTigers never pays an LLM bill
 * (TIGERAGENT.md §9).
 *
 * @api
 */
class Agent_Service_Settings extends Tiger_Service_Service
{
    /**
     * Save the agent settings.
     *
     * @param  array $params enabled, provider, model, api_key (blank = keep current)
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Agent_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $provider = (string) $form->getValue('provider');
        if (!array_key_exists($provider, Tiger_Agent_Provider_Factory::options())) {
            $provider = 'anthropic';
        }
        $model = trim((string) $form->getValue('model'));
        $key   = trim((string) $form->getValue('api_key'));

        try {
            $this->_transaction(function () use ($params, $provider, $model, $key) {
                $cfg = new Tiger_Model_Config();
                $scope   = Tiger_Model_Config::SCOPE_GLOBAL;
                $scopeId = '';

                $modeMax = (string) $form->getValue('mode_max');
                if (!isset(Tiger_Agent::MODES[$modeMax])) { $modeMax = 'auto'; }

                $cfg->set($scope, $scopeId, Tiger_Agent::CFG_ENABLED,  !empty($params['enabled']) ? '1' : '0');
                $cfg->set($scope, $scopeId, Tiger_Agent::CFG_PROVIDER, $provider);
                $cfg->set($scope, $scopeId, Tiger_Agent::CFG_MODEL,    $model);
                $cfg->set($scope, $scopeId, Tiger_Agent::CFG_MODE_MAX, $modeMax);

                // Encrypt + store a NEW key only; a blank field preserves the current secret.
                if ($key !== '') {
                    if (!Tiger_Crypto::isConfigured()) {
                        throw new RuntimeException('Cannot store the key — encryption is not configured (tiger.crypto.key).');
                    }
                    $cfg->set($scope, $scopeId, Tiger_Agent::CFG_KEY_ENC, Tiger_Crypto::encrypt($key));
                }
            });
            $this->_success(['connected' => Tiger_Agent::isConnected()], 'agent.settings.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
