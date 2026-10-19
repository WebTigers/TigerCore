<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Telnyx — the Telnyx SMS adapter.
 *
 * Sends via Telnyx's v2 REST API — a Bearer-auth JSON POST to `/v2/messages`, no SDK required. Config
 * (`tiger.sms.adapters.telnyx.*`): `api_key` (stored `api_key_enc`, encrypted) and either a `from`
 * (an E.164 number or an alphanumeric sender ID) OR a `messaging_profile_id` (a Telnyx number pool).
 * Both may be set — the profile selects the pool, the `from` pins a specific number within it.
 *
 * Inbound (two-way) is NOT wired here: Telnyx POSTs a JSON body (not form fields) and signs it with
 * Ed25519 (`telnyx-signature-ed25519` over `timestamp|rawBody`), which needs the raw request body the
 * form-based inbound controller doesn't pass. So it inherits the base refuse-by-default (verifyInbound
 * → false) until that raw-body/Ed25519 path is built — the same follow-up as the SNS adapter; Twilio,
 * Vonage and Plivo inbound work through the form webhook today.
 *
 * @api
 */
class Tiger_Sms_Adapter_Telnyx extends Tiger_Sms_Adapter_Abstract
{
    const API = 'https://api.telnyx.com/v2/messages';

    public function label(): string { return 'Telnyx'; }

    public function fields(): array
    {
        return [
            ['key' => 'api_key',              'label' => 'API key',             'type' => 'secret', 'secret' => true],
            ['key' => 'from',                 'label' => 'From (number or sender ID)', 'type' => 'text', 'placeholder' => '+15551234567'],
            ['key' => 'messaging_profile_id', 'label' => 'Messaging Profile ID (optional, a number pool)', 'type' => 'text'],
        ];
    }

    public function isConfigured(): bool
    {
        $hasSender = $this->defaultFrom() !== '' || (string) $this->_cfg('messaging_profile_id', '') !== '';
        return $this->_secret('api_key') !== '' && $hasSender;
    }

    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        $key = $this->_secret('api_key');
        if ($key === '') { return ['ok' => false, 'error' => 'Telnyx is not configured (api_key)']; }

        $payload = ['to' => $to, 'text' => $message];
        $profile = (string) ($opts['messaging_profile_id'] ?? $this->_cfg('messaging_profile_id', ''));
        if ($from !== '')    { $payload['from'] = $from; }
        if ($profile !== '') { $payload['messaging_profile_id'] = $profile; }
        if ($from === '' && $profile === '') {
            return ['ok' => false, 'error' => 'Telnyx has no sender (set a From number or a Messaging Profile ID)'];
        }

        [$status, $body] = $this->_http('POST', self::API,
            ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
            json_encode($payload));

        $json = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['data']['id'])) {
            return ['ok' => true, 'id' => (string) $json['data']['id']];
        }
        $err = '';
        if (is_array($json) && isset($json['errors'][0]) && is_array($json['errors'][0])) {
            $err = trim((string) ($json['errors'][0]['title'] ?? '') . ' ' . (string) ($json['errors'][0]['detail'] ?? ''));
        }
        return ['ok' => false, 'error' => 'Telnyx error (HTTP ' . $status . ')' . ($err !== '' ? ': ' . $err : '')];
    }
}
