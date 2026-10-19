<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Vonage — the Vonage (formerly Nexmo) SMS adapter.
 *
 * Sends via Vonage's classic SMS API — a form POST to `rest.nexmo.com/sms/json`, credentials in the
 * body (no SDK required). Config (`tiger.sms.adapters.vonage.*`): `api_key`, `api_secret` (stored
 * `api_secret_enc`, encrypted), and a `from` (an E.164 number OR an alphanumeric sender ID where the
 * destination country allows one). Non-GSM text is sent as `type=unicode` automatically.
 *
 * Inbound (two-way) is supported through Vonage's SIGNED webhooks: set a Signature Secret in the Vonage
 * dashboard (+ the matching signature method), put it in `signature_secret`, and point the number's
 * inbound webhook at `/sms/inbound/vonage` set to HTTP POST. verifyInbound validates the `sig` the
 * platform appends; with no secret configured it inherits the base refuse-by-default, so an unsigned
 * request can never be spoofed into the inbound stream.
 *
 * @api
 */
class Tiger_Sms_Adapter_Vonage extends Tiger_Sms_Adapter_Abstract
{
    const API = 'https://rest.nexmo.com/sms/json';

    public function label(): string { return 'Vonage'; }

    public function fields(): array
    {
        return [
            ['key' => 'api_key',          'label' => 'API key',                 'type' => 'text',   'placeholder' => 'abcd1234'],
            ['key' => 'api_secret',       'label' => 'API secret',              'type' => 'secret', 'secret' => true],
            ['key' => 'from',             'label' => 'From (number or sender ID)', 'type' => 'text', 'placeholder' => '+15551234567 or MyBrand'],
            ['key' => 'signature_secret', 'label' => 'Signature secret (optional, for inbound)', 'type' => 'secret', 'secret' => true],
            ['key' => 'signature_method', 'label' => 'Signature method (inbound: md5hash / sha1 / sha256 / sha512)', 'type' => 'text', 'placeholder' => 'sha256'],
        ];
    }

    public function isConfigured(): bool
    {
        return (string) $this->_cfg('api_key', '') !== ''
            && $this->_secret('api_secret') !== ''
            && $this->defaultFrom() !== '';
    }

    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        $key    = (string) $this->_cfg('api_key', '');
        $secret = $this->_secret('api_secret');
        if ($key === '' || $secret === '') { return ['ok' => false, 'error' => 'Vonage is not configured (api_key / api_secret)']; }
        if ($from === '') { return ['ok' => false, 'error' => 'Vonage has no sender (set a From number or sender ID)']; }

        $params = [
            'api_key'    => $key,
            'api_secret' => $secret,
            'from'       => $from,
            'to'         => ltrim($to, '+'),   // Vonage wants the digits, no leading +
            'text'       => $message,
        ];
        // Non-GSM characters require the unicode type, or they're mangled.
        if (preg_match('/[^\x00-\x7F]/', $message)) { $params['type'] = 'unicode'; }

        [$status, $body] = $this->_http('POST', self::API,
            ['Content-Type: application/x-www-form-urlencoded'], http_build_query($params));

        $json = json_decode($body, true);
        $msg  = (is_array($json) && isset($json['messages'][0]) && is_array($json['messages'][0])) ? $json['messages'][0] : null;
        if ($status >= 200 && $status < 300 && $msg !== null && (string) ($msg['status'] ?? '') === '0') {
            return ['ok' => true, 'id' => (string) ($msg['message-id'] ?? '')];
        }
        $err = $msg !== null ? trim((string) ($msg['status'] ?? '') . ' ' . (string) ($msg['error-text'] ?? '')) : '';
        return ['ok' => false, 'error' => 'Vonage error (HTTP ' . $status . ')' . ($err !== '' ? ': ' . $err : '')];
    }

    // ----- inbound (two-way) ------------------------------------------------------------------------

    /**
     * Validate Vonage's `sig`: sort the params (minus `sig`), build `&key=value` with `&`/`=` in values
     * replaced by `_`, then MD5 (`md5hash`) or HMAC (sha1/sha256/sha512) with the signature secret, and
     * compare case-insensitively in constant time. A missing secret or signature fails closed.
     */
    public function verifyInbound(string $url, array $post, array $headers): bool
    {
        $secret = $this->_secret('signature_secret');
        $sig    = (string) ($post['sig'] ?? '');
        if ($secret === '' || $sig === '') { return false; }

        unset($post['sig']);
        ksort($post);
        $data = '';
        foreach ($post as $k => $v) {
            $val = is_array($v) ? implode('', $v) : (string) $v;
            $data .= '&' . $k . '=' . str_replace(['&', '='], '_', $val);
        }

        $method   = strtolower(trim((string) $this->_cfg('signature_method', 'sha256')));
        $expected = $method === 'md5hash' ? md5($data . $secret) : hash_hmac($this->_hmacAlgo($method), $data, $secret);
        return hash_equals(strtoupper($expected), strtoupper($sig));
    }

    /** Map a Vonage signature method to a hash_hmac algorithm (defaults to sha256). */
    protected function _hmacAlgo(string $method): string
    {
        return in_array($method, ['md5', 'sha1', 'sha256', 'sha512'], true) ? $method : 'sha256';
    }

    /** Normalize a Vonage inbound MO webhook (null for a delivery receipt or non-message payload). */
    public function parseInbound(array $post): ?array
    {
        // A delivery receipt carries `status` + no `text`; an inbound message carries `text` + `msisdn`.
        if (!isset($post['text']) || !isset($post['msisdn'])) { return null; }
        return [
            'provider'   => 'vonage',
            'message_id' => (string) ($post['messageId'] ?? ''),
            'from'       => '+' . ltrim((string) $post['msisdn'], '+'),
            'to'         => '+' . ltrim((string) ($post['to'] ?? ''), '+'),
            'body'       => (string) $post['text'],
            'media'      => [],
            'raw'        => $post,
        ];
    }
}
