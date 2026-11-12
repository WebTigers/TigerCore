<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Support;

use Tiger_Sns;

/**
 * Signs SNS envelopes the way AWS does, with a throwaway key + self-signed certificate, so Tiger_Sns is
 * tested against real signatures rather than a mocked verifier. Installs a fetch seam that serves the
 * certificate at CERT_URL and records every URL requested.
 */
final class SnsSigner
{
    const CERT_URL = 'https://sns.us-east-1.amazonaws.com/SimpleNotificationService-0000.pem';
    const TOPIC    = 'arn:aws:sns:us-east-1:123456789012:tiger-video-moderation';

    /** @var \OpenSSLAsymmetricKey */
    private $key;
    /** @var string */
    public $certPem;
    /** @var array<int,string> every URL Tiger_Sns fetched */
    public $fetched = [];

    public function __construct()
    {
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr       = openssl_csr_new(['commonName' => 'sns.amazonaws.com'], $this->key);
        openssl_x509_export(openssl_csr_sign($csr, null, $this->key, 1), $pem);
        $this->certPem = $pem;
    }

    /** Point Tiger_Sns's fetch at this signer: CERT_URL → the certificate, anything else → 'ok'. */
    public function install(): void
    {
        Tiger_Sns::reset();
        Tiger_Sns::$_fetch = function (string $url) {
            $this->fetched[] = $url;
            return $url === self::CERT_URL ? $this->certPem : 'ok';
        };
    }

    /** A signed envelope of $type; $over replaces fields BEFORE signing. */
    public function message(string $type, array $over = [], string $version = '2'): array
    {
        $msg = array_merge([
            'Type'             => $type,
            'MessageId'        => '6f1c1d8b-0000-4000-8000-000000000001',
            'TopicArn'         => self::TOPIC,
            'Message'          => $type === 'Notification' ? '{"JobId":"abc123","Status":"SUCCEEDED"}' : 'You have chosen to subscribe.',
            'Timestamp'        => '2026-11-12T09:00:00.000Z',
            'SignatureVersion' => $version,
            'SigningCertURL'   => self::CERT_URL,
        ], $type === 'Notification' ? [] : [
            'Token'        => 'tok-123',
            'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=ConfirmSubscription&TopicArn=' . self::TOPIC . '&Token=tok-123',
        ], $over);

        $canonical = '';
        foreach (Tiger_Sns::SIGNED_FIELDS[$type] as $field) {
            if (array_key_exists($field, $msg)) { $canonical .= $field . "\n" . $msg[$field] . "\n"; }
        }
        openssl_sign($canonical, $sig, $this->key, $version === '1' ? OPENSSL_ALGO_SHA1 : OPENSSL_ALGO_SHA256);
        $msg['Signature'] = base64_encode($sig);
        return $msg;
    }
}
