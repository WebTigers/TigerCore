<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_Form_Settings — the SMS sender identity, provider and provider credentials (TIGER-528).
 *
 * save writes provider SECRETS into the config tier, so it is exactly the write a cross-site page must
 * not be able to trigger. Each adapter declares its own fields (`fields()`), so the form discovers them
 * the same way Sms_Service_Settings::save does — `Tiger_Sms_Adapter_<Provider>` for every registered
 * provider — and declares the union (a secret field as a password element). Every field is optional and
 * filter-free: which ones apply depends on the chosen provider, and a blank secret means "keep the
 * stored one".
 *
 * @api
 */
class Sms_Form_Settings extends Sms_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        $out  = ['brand' => $this->_field('brand'), 'provider' => $this->_field('provider')];
        foreach (Tiger_Sms::providers() as $provider) {
            $class = 'Tiger_Sms_Adapter_' . ucfirst((string) $provider);
            if (!class_exists($class)) { continue; }
            foreach ((new $class())->fields() as $f) {
                $key = (string) ($f['key'] ?? '');
                if ($key === '' || isset($out[$key])) { continue; }
                $out[$key] = $this->_field($key, !empty($f['secret']) ? 'password' : 'text');
            }
        }
        return array_values($out);
    }
}
