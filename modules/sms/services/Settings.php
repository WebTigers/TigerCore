<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_Service_Settings — save the SMS provider + credentials, and send a test message.
 *
 * Secrets (an adapter field flagged `secret`) are encrypted with Tiger_Crypto and stored as `<key>_enc`;
 * a blank secret on save KEEPS the current one (so the screen never shows or wipes a stored token).
 * `test` sends through the live Tiger_Sms facade so the operator can prove the config end to end.
 */
class Sms_Service_Settings extends Tiger_Service_Service
{
    /**
     * Save the SMS settings: provider, default sender, and the selected provider's own fields.
     *
     * @param  array $params {provider, from, <adapter field keys…>}
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $provider = strtolower(trim((string) ($params['provider'] ?? '')));
        if ($provider !== '' && !in_array($provider, Tiger_Sms::providers(), true)) {
            $this->_error('sms.settings.error.provider'); return;
        }

        try {
            $this->_transaction(function () use ($params, $provider) {
                $cfg   = new Tiger_Model_Config();
                $scope = Tiger_Model_Config::SCOPE_GLOBAL;
                $cfg->set($scope, '', 'tiger.sms.provider', $provider);
                if ($provider === '') { return; }
                $adapterClass = 'Tiger_Sms_Adapter_' . ucfirst($provider);
                $fields = class_exists($adapterClass) ? (new $adapterClass())->fields() : [];
                foreach ($fields as $f) {
                    $key = (string) $f['key'];
                    $val = (string) ($params[$key] ?? '');
                    $base = 'tiger.sms.adapters.' . $provider . '.' . $key;
                    if (!empty($f['secret'])) {
                        if ($val === '') { continue; }                    // blank keeps the stored secret
                        if (!Tiger_Crypto::isConfigured()) { throw new RuntimeException('encryption key not configured'); }
                        $cfg->set($scope, '', $base . '_enc', Tiger_Crypto::encrypt($val));
                    } else {
                        $cfg->set($scope, '', $base, $val);
                    }
                }
            });
            $this->_success(['configured' => Tiger_Sms::isConfigured()], 'sms.settings.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Send a test message through the configured provider.
     *
     * @param  array $params {to, message?}
     * @return void
     */
    public function test(array $params): void
    {
        if (!$this->_isAllowed('test')) { $this->_error('core.api.error.not_allowed'); return; }
        $to  = (string) ($params['to'] ?? '');
        $msg = trim((string) ($params['message'] ?? '')) ?: 'Tiger SMS test message.';
        if ($to === '') { $this->_error('sms.settings.error.to'); return; }

        $res = Tiger_Sms::send($to, $msg);
        if (!empty($res['ok'])) {
            $this->_success(['id' => $res['id'] ?? null, 'to' => $res['to'] ?? $to], 'sms.settings.test_sent');
            return;
        }
        $this->_error(APPLICATION_ENV !== 'production'
            ? ('test failed: ' . (string) ($res['error'] ?? 'unknown'))
            : 'sms.settings.error.test');
    }
}
