<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_License_Authority — the client for a license authority's DOWNLOAD and CLAIM endpoints.
 *
 * `claim()` seats one more domain of a multi-domain install on the main key's license (see
 * Tiger_License_Seats); `download()` is described below.
 *
 * When a buyer installs (or updates) a licensed module, the module's declared authority is the only party
 * that can hand out the code: it verifies the license server-side, then mints a SHORT-LIVED, signed
 * download and returns just its location — so the authority never has to proxy the bytes and the seller's
 * repo token never leaves the seller. This client makes that one call and normalizes the reply to a
 * download descriptor `{url, signature, sha256, version}`; the caller (Tiger_Module_Installer) downloads
 * from `url` and verifies the signature before extracting.
 *
 * Vendor-neutral: the authority URL comes from the module's `pricing.authority`; this client knows nothing
 * about who runs it. The transport is an injectable seam so the flow is testable with no network.
 *
 * @api
 * @see Tiger_Module_Installer::installFromAuthority()
 * @see Tiger_License_Checker
 */
class Tiger_License_Authority
{
    /** @var callable|null injected transport: fn(string $url, array $payload): ?array */
    protected static $transport = null;

    /**
     * Swap the transport (tests inject a fake authority). Pass null to reset to real HTTP.
     *
     * @param  callable|null $transport the transport, or null to reset
     * @return void
     */
    public static function setTransport(?callable $transport): void
    {
        self::$transport = $transport;
    }

    /**
     * Reset the injected transport — for test isolation.
     *
     * @return void
     */
    public static function _reset(): void
    {
        self::$transport = null;
    }

    /**
     * Ask an authority to authorize + locate a licensed download. Returns a normalized descriptor, or null
     * when the authority is unreachable, refuses (invalid/lapsed license), or replies without a usable,
     * signed download. A licensed download MUST carry both an http(s) `url` and a `signature`.
     *
     * @param  string $authority the authority base URL (module.json `pricing.authority`)
     * @param  string $key       the buyer's license key
     * @param  string $product   the product/module slug being installed
     * @param  string $domain    the install host (for the authority's domain-binding check)
     * @return array{url:string,signature:string,sha256:?string,version:?string}|null the download descriptor, or null
     */
    public static function download(string $authority, string $key, string $product, string $domain = ''): ?array
    {
        $resp = self::_post(rtrim($authority, '/') . '/download', [
            'key'     => $key,
            'product' => $product,
            'domain'  => $domain,
        ]);
        if (!is_array($resp)) {
            return null;
        }

        $url = (string) ($resp['url'] ?? '');
        $sig = (string) ($resp['signature'] ?? '');
        if ($url === '' || $sig === '' || !preg_match('#^https?://#i', $url)) {
            return null;   // no usable, signed download → treat as a refusal
        }

        return [
            'url'       => $url,
            'signature' => $sig,
            'sha256'    => isset($resp['sha256']) ? (string) $resp['sha256'] : null,
            'version'   => isset($resp['version']) ? (string) $resp['version'] : null,
        ];
    }

    /**
     * Claim a seat for one more domain of a multi-domain install, on the SAME license as the install's main
     * key — the main domain is the single payer (MARKETPLACE.md §6b). POSTs `{key, domain, product}` to the
     * authority's `/claim` and returns the TRUSTED claim payload `{ok, key?, domain, reason?, seats, in_use}`,
     * or null when the answer can't be trusted (unreachable, unsigned/forged against the pinned key, or a
     * reply about a different domain) — null is "unknown", never "refused", and the caller stores nothing.
     *
     * Signature rules mirror Tiger_License_Checker: with a pinned public key the reply MUST be a `payload`
     * string + a valid Ed25519 `signature` over it; with none pinned (an unsigned/dev authority) the raw
     * reply is read as-is.
     *
     * @param  string $authority the authority base URL (the main key's `authority`)
     * @param  string $key       the install's main (active) key
     * @param  string $domain    the host to seat (the authority seats its apex; `www.` is the same site)
     * @param  string $publicKey the pinned vendor public key (the main key's `public_key`); '' = unsigned/dev
     * @param  string $product   optional product the main key must cover
     * @return array{ok:bool,key?:string,domain:string,reason?:string,seats:int,in_use:int}|null the claim, or null when unknown
     */
    public static function claim(string $authority, string $key, string $domain, string $publicKey = '', string $product = ''): ?array
    {
        $want = Tiger_License_Seats::apex($domain);
        if ($authority === '' || $key === '' || $want === '') {
            return null;
        }
        $resp = self::_post(rtrim($authority, '/') . '/claim', [
            'key'     => $key,
            'domain'  => $want,
            'product' => $product,
        ]);
        if (!is_array($resp)) {
            return null;
        }

        if ($publicKey !== '') {
            $payload   = $resp['payload'] ?? null;
            $signature = (string) ($resp['signature'] ?? '');
            if (!is_string($payload) || $signature === '' || !Tiger_Crypto_Signature::verify($payload, $signature, $publicKey)) {
                return null;   // forged / unsigned against a pinned key → unknown
            }
            $data = json_decode($payload, true);
        } else {
            $data = isset($resp['payload']) && is_string($resp['payload']) ? json_decode($resp['payload'], true) : $resp;
        }
        if (!is_array($data) || !array_key_exists('ok', $data)) {
            return null;
        }

        // A signed answer about ANOTHER domain is not an answer to this question (a replayed reply).
        if (Tiger_License_Seats::apex((string) ($data['domain'] ?? '')) !== $want) {
            return null;
        }

        $out = [
            'ok'     => !empty($data['ok']),
            'domain' => $want,
            'seats'  => max(0, (int) ($data['seats'] ?? 0)),
            'in_use' => max(0, (int) ($data['in_use'] ?? 0)),
        ];
        if ($out['ok']) {
            $seatKey = (string) ($data['key'] ?? '');
            if ($seatKey === '') {
                return null;   // "ok" with no key is malformed → unknown
            }
            $out['key'] = $seatKey;
        } else {
            $out['reason'] = (string) ($data['reason'] ?? 'unknown');
        }
        return $out;
    }

    /** POST a JSON payload to the authority and decode the JSON reply, or null on any failure. */
    protected static function _post(string $url, array $payload): ?array
    {
        if (self::$transport !== null) {
            return (self::$transport)($url, $payload);
        }
        // Send a User-Agent: a WAF-protected authority (ours sits behind one) blocks a UA-less request
        // (AWS CommonRuleSet's NoUserAgent_HEADER → 403), which would otherwise refuse every /download and
        // make a valid, paid license look unauthorized. A named UA also identifies the caller in logs.
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n"
                             . "User-Agent: Tiger-License-Authority\r\n",
            'content'       => (string) json_encode($payload),
            'timeout'       => 15,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
