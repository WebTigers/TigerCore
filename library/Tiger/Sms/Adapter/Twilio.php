<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Twilio — the Twilio SMS adapter (the industry-standard API).
 *
 * Sends via Twilio's REST API — a Basic-auth POST to the Messages resource, no SDK required. Config
 * (`tiger.sms.adapters.twilio.*`): `account_sid`, `auth_token` (stored `auth_token_enc`, encrypted),
 * and either a `from` number OR a `messaging_service_sid` (Twilio's number pool / Copilot). A message
 * prefers `messaging_service_sid` when set, else the `from`/default sender.
 *
 * @api
 */
class Tiger_Sms_Adapter_Twilio extends Tiger_Sms_Adapter_Abstract
{
    const API = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function label(): string { return 'Twilio'; }

    public function fields(): array
    {
        return [
            ['key' => 'account_sid',           'label' => 'Account SID',            'type' => 'text',   'placeholder' => 'AC…'],
            ['key' => 'auth_token',            'label' => 'Auth Token',             'type' => 'secret', 'secret' => true],
            ['key' => 'from',                  'label' => 'From number (E.164)',    'type' => 'text',   'placeholder' => '+15551234567'],
            ['key' => 'messaging_service_sid', 'label' => 'Messaging Service SID (optional)', 'type' => 'text', 'placeholder' => 'MG…'],
        ];
    }

    public function isConfigured(): bool
    {
        $hasSender = $this->defaultFrom() !== '' || (string) $this->_cfg('messaging_service_sid', '') !== '';
        return (string) $this->_cfg('account_sid', '') !== '' && $this->_secret('auth_token') !== '' && $hasSender;
    }

    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        $sid   = (string) $this->_cfg('account_sid', '');
        $token = $this->_secret('auth_token');
        if ($sid === '' || $token === '') { return ['ok' => false, 'error' => 'Twilio is not configured (account_sid / auth_token)']; }

        $params = ['To' => $to, 'Body' => $message];
        $mss    = (string) ($opts['messaging_service_sid'] ?? $this->_cfg('messaging_service_sid', ''));
        if ($mss !== '') { $params['MessagingServiceSid'] = $mss; }
        elseif ($from !== '') { $params['From'] = $from; }
        else { return ['ok' => false, 'error' => 'Twilio has no sender (set a From number or a Messaging Service SID)']; }

        [$status, $body] = $this->_http('POST', sprintf(self::API, rawurlencode($sid)),
            ['Authorization: Basic ' . base64_encode($sid . ':' . $token), 'Content-Type: application/x-www-form-urlencoded'],
            http_build_query($params));

        $json = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['sid'])) {
            return ['ok' => true, 'id' => (string) $json['sid']];
        }
        $err = is_array($json) ? (string) ($json['message'] ?? $json['error_message'] ?? '') : '';
        return ['ok' => false, 'error' => 'Twilio error (HTTP ' . $status . ')' . ($err !== '' ? ': ' . $err : '')];
    }
}
