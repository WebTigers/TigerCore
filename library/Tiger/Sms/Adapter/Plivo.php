<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Plivo — the Plivo SMS adapter.
 *
 * Sends via Plivo's REST API — a Basic-auth JSON POST to the account's Message resource, no SDK
 * required. Config (`tiger.sms.adapters.plivo.*`): `auth_id`, `auth_token` (stored `auth_token_enc`,
 * encrypted), and either a `from` source number OR a `powerpack_uuid` (a Plivo PowerPack number pool).
 * A message prefers `powerpack_uuid` when set, else the `from`/default sender.
 *
 * Inbound (two-way) is supported through Plivo's V3 request signature: point the number's Message URL at
 * `/sms/inbound/plivo` (POST), and verifyInbound validates the `X-Plivo-Signature-V3` header (HMAC-SHA256
 * of the request URL + nonce) in constant time. A missing token/nonce/signature fails closed.
 *
 * @api
 */
class Tiger_Sms_Adapter_Plivo extends Tiger_Sms_Adapter_Abstract
{
    const API = 'https://api.plivo.com/v1/Account/%s/Message/';

    public function label(): string { return 'Plivo'; }

    public function fields(): array
    {
        return [
            ['key' => 'auth_id',        'label' => 'Auth ID',                 'type' => 'text',   'placeholder' => 'MAXXXXXXXXXXXXXXXXXX'],
            ['key' => 'auth_token',     'label' => 'Auth token',              'type' => 'secret', 'secret' => true],
            ['key' => 'from',           'label' => 'From number (E.164)',     'type' => 'text',   'placeholder' => '+15551234567'],
            ['key' => 'powerpack_uuid', 'label' => 'PowerPack UUID (optional, instead of From)', 'type' => 'text'],
        ];
    }

    public function isConfigured(): bool
    {
        $hasSender = $this->defaultFrom() !== '' || (string) $this->_cfg('powerpack_uuid', '') !== '';
        return (string) $this->_cfg('auth_id', '') !== '' && $this->_secret('auth_token') !== '' && $hasSender;
    }

    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        $id    = (string) $this->_cfg('auth_id', '');
        $token = $this->_secret('auth_token');
        if ($id === '' || $token === '') { return ['ok' => false, 'error' => 'Plivo is not configured (auth_id / auth_token)']; }

        $payload = ['dst' => ltrim($to, '+'), 'text' => $message];
        $pp      = (string) ($opts['powerpack_uuid'] ?? $this->_cfg('powerpack_uuid', ''));
        if ($pp !== '')        { $payload['powerpack_uuid'] = $pp; }
        elseif ($from !== '')  { $payload['src'] = ltrim($from, '+'); }
        else                   { return ['ok' => false, 'error' => 'Plivo has no sender (set a From number or a PowerPack UUID)']; }

        [$status, $body] = $this->_http('POST', sprintf(self::API, rawurlencode($id)),
            ['Authorization: Basic ' . base64_encode($id . ':' . $token), 'Content-Type: application/json'],
            json_encode($payload));

        $json = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($json) && !empty($json['message_uuid'][0])) {
            return ['ok' => true, 'id' => (string) $json['message_uuid'][0]];
        }
        $err = is_array($json) ? (string) ($json['error'] ?? '') : '';
        return ['ok' => false, 'error' => 'Plivo error (HTTP ' . $status . ')' . ($err !== '' ? ': ' . $err : '')];
    }

    // ----- inbound (two-way) ------------------------------------------------------------------------

    /**
     * Validate Plivo's `X-Plivo-Signature-V3`: base64(HMAC-SHA256(auth_token, requestUrl + nonce)),
     * compared in constant time (the header may carry several comma-separated signatures — any match
     * passes). A missing token, nonce or signature fails closed.
     */
    public function verifyInbound(string $url, array $post, array $headers): bool
    {
        $token = $this->_secret('auth_token');
        $nonce = (string) ($headers['X-Plivo-Signature-V3-Nonce'] ?? $headers['x-plivo-signature-v3-nonce'] ?? '');
        $sigs  = (string) ($headers['X-Plivo-Signature-V3'] ?? $headers['x-plivo-signature-v3'] ?? '');
        if ($token === '' || $nonce === '' || $sigs === '') { return false; }

        $expected = base64_encode(hash_hmac('sha256', $url . $nonce, $token, true));
        foreach (explode(',', $sigs) as $sig) {
            if (hash_equals($expected, trim($sig))) { return true; }
        }
        return false;
    }

    /** Normalize a Plivo inbound webhook (null for a non-message payload). */
    public function parseInbound(array $post): ?array
    {
        if (!isset($post['Text']) && !isset($post['From'])) { return null; }
        $media = [];
        for ($i = 0, $n = (int) ($post['MediaCount'] ?? 0); $i < $n; $i++) {
            if (!empty($post['Media' . $i])) { $media[] = (string) $post['Media' . $i]; }
        }
        return [
            'provider'   => 'plivo',
            'message_id' => (string) ($post['MessageUUID'] ?? ''),
            'from'       => '+' . ltrim((string) ($post['From'] ?? ''), '+'),
            'to'         => '+' . ltrim((string) ($post['To'] ?? ''), '+'),
            'body'       => (string) ($post['Text'] ?? ''),
            'media'      => $media,
            'raw'        => $post,
        ];
    }
}
