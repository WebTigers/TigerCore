<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Abstract — the base every SMS provider adapter extends.
 *
 * An adapter is constructed with its own config map (`tiger.sms.adapters.<name>.*`) and implements
 * send(). Secrets are read with {@see _secret} (an encrypted `<key>_enc` is decrypted; a plaintext
 * `<key>` is a fallback). `fields()` declares the adapter's settings so the admin SMS screen can render
 * the right inputs without the module knowing each provider. Keep the HTTP in {@see _http} so adapters
 * stay small and testable.
 *
 * @api
 */
abstract class Tiger_Sms_Adapter_Abstract
{
    /** @var array the adapter's config (tiger.sms.adapters.<name>.*) */
    protected $_config = [];

    /**
     * @param array $config the adapter's config map
     */
    public function __construct(array $config = [])
    {
        $this->_config = $config;
    }

    /**
     * Send one message. Implementations must never throw for an expected failure — return
     * `['ok'=>false,'error'=>...]` so the facade can report it cleanly.
     *
     * @param  string $to      the destination, already E.164-normalized
     * @param  string $message the body
     * @param  string $from    the sender (origination number / sender id)
     * @param  array  $opts    provider-specific options
     * @return array  {ok:bool, id?:string, error?:string}
     */
    abstract public function send(string $to, string $message, string $from, array $opts = []): array;

    /** Whether this adapter has the minimum config to send (credentials + a sender). @return bool */
    abstract public function isConfigured(): bool;

    /**
     * Verify an inbound webhook is authentic BEFORE it is trusted (the provider's request signature).
     * The default REFUSES — an adapter opts in by overriding, so a provider that can't be verified can
     * never be spoofed into the inbound event stream.
     *
     * @param  string $url     the full request URL the provider signed
     * @param  array  $post    the POST parameters
     * @param  array  $headers the request headers
     * @return bool
     */
    public function verifyInbound(string $url, array $post, array $headers): bool
    {
        return false;
    }

    /**
     * Parse a VERIFIED inbound webhook into the normalized message, or null when the payload is not an
     * inbound message (e.g. a delivery-status callback). Shape: `{provider, message_id, from, to, body,
     * media[], raw}` — `received_at` is stamped by the webhook controller.
     *
     * @param  array $post the POST parameters
     * @return array|null
     */
    public function parseInbound(array $post): ?array
    {
        return null;
    }

    /** The adapter's own default sender (`from`), or ''. */
    public function defaultFrom(): string
    {
        return (string) $this->_cfg('from', '');
    }

    /** A human label for the provider picker (default: the class suffix). */
    public function label(): string
    {
        $p = strrpos(static::class, '_');
        return $p !== false ? substr(static::class, $p + 1) : static::class;
    }

    /**
     * The settings fields the admin SMS screen renders for this provider. Each: {key,label,type,secret?}.
     * `secret:true` means the value is write-only + stored encrypted (a blank submit keeps the current).
     *
     * @return array<int,array{key:string,label:string,type:string,secret?:bool,placeholder?:string}>
     */
    public function fields(): array
    {
        return [];
    }

    // ----- helpers ----------------------------------------------------------------------------------

    /** A plain adapter-config value, or $default when unset. */
    protected function _cfg(string $key, $default = '')
    {
        return array_key_exists($key, $this->_config) && $this->_config[$key] !== '' ? $this->_config[$key] : $default;
    }

    /**
     * A secret config value — the encrypted `<key>_enc` (decrypted) wins, else a plaintext `<key>`.
     * Returns '' when unreadable (e.g. a rotated crypto key), so callers fail closed.
     *
     * @param  string $key the base key name (e.g. 'auth_token' → reads auth_token_enc then auth_token)
     * @return string
     */
    protected function _secret(string $key): string
    {
        $enc = (string) $this->_cfg($key . '_enc', '');
        if ($enc !== '' && class_exists('Tiger_Crypto')) {
            try { return (string) Tiger_Crypto::decrypt($enc); } catch (Throwable $e) { return ''; }
        }
        return (string) $this->_cfg($key, '');
    }

    /**
     * A small cURL helper. Returns [httpStatus, responseBody]; httpStatus 0 on a transport error.
     *
     * @param  string $method  GET | POST
     * @param  string $url
     * @param  array  $headers raw header lines
     * @param  string $body    request body (form- or query-encoded by the caller)
     * @param  int    $timeout seconds
     * @return array  [int $status, string $body]
     */
    protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
    {
        if (!function_exists('curl_init')) { return [0, 'curl unavailable']; }
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($method === 'POST') { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
        $out    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        if ($out === false) { return [0, $err ?: 'transport error']; }
        return [$status, (string) $out];
    }
}
