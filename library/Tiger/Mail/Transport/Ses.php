<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Ses — send over the Amazon SES v2 API using the vendored AWS SDK.
 *
 * This is the one driver that goes through an SDK rather than a hand-rolled HTTPS call, and it's
 * deliberate: the SDK owns SigV4 signing, regional endpoints, retries, and — the real prize — the
 * **default credential chain**. Leave the key/secret blank and the SDK picks up the EC2 instance
 * role / ECS task role / environment credentials, so a Tiger install on AWS can send mail with
 * **no stored secret at all**. That's strictly better than SES SMTP, which requires a static
 * username and password sitting in the config table.
 *
 * Requires the optional `tiger-sdk-aws` module (capability-detected by
 * `Tiger_Mail_Provider::isAvailable`, so the option is offered only when the SDK is installed).
 * Core never hard-depends on it.
 *
 * Sends the fully-rendered MIME via `SendEmail` with `Content.Raw` — SES accepts raw messages, so
 * the message Zend already built goes out byte-for-byte (correct multipart, headers, and encoding)
 * instead of being re-assembled from JSON fields.
 *
 * Config: `region`, optional `key` + `secret`.
 *
 * @api
 */
class Tiger_Mail_Transport_Ses extends Tiger_Mail_Transport_Api
{
    /** Unused — the SDK owns the endpoint. Required by the base contract. */
    protected function _endpoint()
    {
        return '';
    }

    /** Unused — the SDK signs the request. Required by the base contract. */
    protected function _headers()
    {
        return [];
    }

    /** Unused — `_sendMail()` is overridden to hand raw MIME to the SDK. */
    protected function _payload(array $msg)
    {
        return [];
    }

    /**
     * Send the rendered MIME message through the SES v2 client.
     *
     * @return void
     * @throws Zend_Mail_Transport_Exception when the SDK is absent or SES refuses the message
     */
    protected function _sendMail()
    {
        if (!class_exists('Aws\\SesV2\\SesV2Client')) {
            throw new Zend_Mail_Transport_Exception(
                'The Amazon SES API driver requires the AWS SDK module (tiger-sdk-aws). '
                . 'Install and activate it, or use the Amazon SES (SMTP) provider instead.'
            );
        }

        $args = ['version' => '2019-09-27', 'region' => $this->_cfg('region') ?: 'us-east-1'];

        // Explicit credentials only when BOTH are given; otherwise fall through to the SDK's
        // default provider chain (instance role, env, shared config) — the no-stored-secret path.
        $key    = $this->_cfg('key');
        $secret = $this->_cfg('secret');
        if ($key !== '' && $secret !== '') {
            $args['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        try {
            $client = new Aws\SesV2\SesV2Client($args);
            $client->sendEmail([
                'Content'          => ['Raw' => ['Data' => $this->_rawMessage()]],
                'Destination'      => ['ToAddresses' => (array) $this->_mail->getRecipients()],
                'FromEmailAddress' => (string) $this->_mail->getFrom(),
            ]);
        } catch (Throwable $e) {
            // Surface SES's own message — on a send failure it's the entire diagnostic (an
            // unverified identity, a sandbox restriction, a bad region).
            throw new Zend_Mail_Transport_Exception('Amazon SES rejected the message: ' . $e->getMessage());
        }
    }

    /**
     * The complete RFC 5322 message — the headers Zend rendered for this transport plus the MIME
     * body — which is exactly what SES `Content.Raw` expects.
     *
     * @return string the raw message
     */
    protected function _rawMessage()
    {
        return $this->header . Zend_Mime::LINEEND . $this->body;
    }
}
