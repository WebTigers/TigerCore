<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sns — authenticate an Amazon SNS HTTP(S) delivery before acting on it (TIGER-295).
 *
 * An SNS subscriber endpoint is guest-reachable (SNS carries no session), so anything can POST a
 * body that LOOKS like an SNS envelope. Before a receiver trusts one it must:
 *
 *  1. {@see verify()} the signature — the signing certificate is fetched only over https from an
 *     `sns.<region>.amazonaws.com` host, and the signature is checked over the canonical string AWS
 *     defines for the message type (SignatureVersion 1 = SHA1, 2 = SHA256);
 *  2. check the `TopicArn` is ITS topic — a valid signature proves only that SNS sent it, and anyone
 *     can create an SNS topic and point it at your URL;
 *  3. confirm a subscription only via {@see confirmSubscription()}, which refuses any `SubscribeURL`
 *     not on an SNS host. Fetching a caller-supplied URL is a server-side request forgery otherwise.
 *
 * No SDK needed. Certificates are cached in memory per request; the fetch is a seam for tests.
 *
 * @api
 * @see https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
 */
class Tiger_Sns
{
    /** An SNS endpoint host — `sns.us-east-1.amazonaws.com`, incl. China and GovCloud partitions. */
    const HOST_PATTERN = '/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/';

    /** Fields signed for each message type, in AWS's canonical (byte) order. Optional ones may be absent. */
    const SIGNED_FIELDS = [
        'Notification'             => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
        'SubscriptionConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
        'UnsubscribeConfirmation'  => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
    ];

    /** @var callable|null test seam: fn(string $url): ?string returning the certificate PEM */
    public static $_fetch = null;

    /** @var array<string,string> certificate PEM by URL, for this request */
    protected static $_certs = [];

    /**
     * Is $msg a genuine SNS message? Checks the certificate URL, then the signature.
     *
     * @param  array $msg the decoded JSON envelope
     * @return bool
     */
    public static function verify(array $msg)
    {
        $type   = (string) ($msg['Type'] ?? '');
        $fields = self::SIGNED_FIELDS[$type] ?? null;
        $algo   = ['1' => OPENSSL_ALGO_SHA1, '2' => OPENSSL_ALGO_SHA256][(string) ($msg['SignatureVersion'] ?? '')] ?? null;
        $sig    = base64_decode((string) ($msg['Signature'] ?? ''), true);
        $cert   = (string) ($msg['SigningCertURL'] ?? '');
        if ($fields === null || $algo === null || $sig === false || $sig === '' || !self::isSnsUrl($cert)
            || !preg_match('#\.pem$#i', (string) parse_url($cert, PHP_URL_PATH))) {
            return false;
        }

        $canonical = '';
        foreach ($fields as $field) {
            if (!array_key_exists($field, $msg)) {
                if ($field === 'Subject') { continue; }   // the only optional signed field
                return false;
            }
            $canonical .= $field . "\n" . $msg[$field] . "\n";
        }

        $pem = self::_cert($cert);
        if ($pem === null) {
            return false;
        }
        $key = openssl_pkey_get_public($pem);
        return $key !== false && openssl_verify($canonical, $sig, $key, $algo) === 1;
    }

    /**
     * Is $url an https URL on an SNS host? Gates both the signing certificate and `SubscribeURL`.
     *
     * @param  string $url
     * @return bool
     */
    public static function isSnsUrl($url)
    {
        $p = parse_url((string) $url);
        // parse_url takes the host AFTER any `user@`, so `https://sns…amazonaws.com@evil.example/` fails here.
        return is_array($p) && strtolower($p['scheme'] ?? '') === 'https' && !isset($p['port'])
            && preg_match(self::HOST_PATTERN, strtolower($p['host'] ?? '')) === 1;
    }

    /**
     * Confirm a VERIFIED SubscriptionConfirmation by visiting its SubscribeURL — only if that URL is on an
     * SNS host. Call after {@see verify()} and the caller's own TopicArn check.
     *
     * @param  array $msg the verified envelope
     * @return bool true when the confirmation request was made and answered 2xx
     */
    public static function confirmSubscription(array $msg)
    {
        $url = (string) ($msg['SubscribeURL'] ?? '');
        if (($msg['Type'] ?? '') !== 'SubscriptionConfirmation' || !self::isSnsUrl($url)) {
            return false;
        }
        return self::_get($url) !== null;
    }

    /** The certificate PEM for $url (cached for this request), or null when it can't be fetched. */
    protected static function _cert($url)
    {
        if (!isset(self::$_certs[$url])) {
            $pem = self::_get($url);
            if ($pem === null || strpos($pem, '-----BEGIN CERTIFICATE-----') === false) {
                return null;
            }
            self::$_certs[$url] = $pem;
        }
        return self::$_certs[$url];
    }

    /** GET $url (https, no redirects, short timeouts); the body on 2xx, else null. */
    protected static function _get($url)
    {
        if (self::$_fetch !== null) {
            $out = (self::$_fetch)($url);
            return is_string($out) ? $out : null;
        }
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,           // a redirect could leave the SNS host
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 6,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return (is_string($body) && $code >= 200 && $code < 300) ? $body : null;
    }

    /** Test helper: forget cached certificates and the fetch seam. */
    public static function reset()
    {
        self::$_certs = [];
        self::$_fetch = null;
    }
}
