<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail_Transport_Api;
use Tiger_Mail_Transport_Brevo;
use Tiger_Mail_Transport_Mailgun;
use Tiger_Mail_Transport_Mailjet;
use Tiger_Mail_Transport_Postmark;
use Tiger_Mail_Transport_Resend;
use Tiger_Mail_Transport_SendGrid;
use Tiger_Mail_Transport_Ses;
use Zend_Mail;
use Zend_Mail_Transport_Exception;

/**
 * The API drivers' SEND path — exercised end to end WITHOUT a network, via probe subclasses that
 * capture the request instead of performing it (the established pattern for covering network code
 * in this suite).
 *
 * This is the half `MailApiTransportTest` can't reach: that one inspects `_payload()` in isolation,
 * while these drive the real `Zend_Mail_Transport_Abstract::send()` entry point — header rendering,
 * `_sendMail()`, JSON encoding, and the endpoint/header assembly — which is what actually runs in
 * production. The failure paths matter just as much as the happy one: a mail send that throws the
 * wrong thing takes down the request that triggered it.
 */
#[CoversClass(Tiger_Mail_Transport_Api::class)]
#[CoversClass(Tiger_Mail_Transport_Brevo::class)]
#[CoversClass(Tiger_Mail_Transport_Mailgun::class)]
#[CoversClass(Tiger_Mail_Transport_Mailjet::class)]
#[CoversClass(Tiger_Mail_Transport_Postmark::class)]
#[CoversClass(Tiger_Mail_Transport_Resend::class)]
#[CoversClass(Tiger_Mail_Transport_SendGrid::class)]
#[CoversClass(Tiger_Mail_Transport_Ses::class)]
final class MailApiSendTest extends IntegrationTestCase
{
    private function mail(): Zend_Mail
    {
        $mail = new Zend_Mail('UTF-8');
        $mail->setFrom('no-reply@example.test', 'Tiger');
        $mail->addTo('buyer@example.test');
        $mail->setSubject('Hello');
        $mail->setBodyHtml('<p>Hi there</p>');
        return $mail;
    }

    #[Test]
    public function every_driver_posts_to_its_endpoint_with_a_well_formed_body(): void
    {
        $cases = [
            'sendgrid' => [new ProbeSendGrid(['key' => 'k']),                       'api.sendgrid.com',  true],
            'postmark' => [new ProbePostmark(['key' => 'k']),                       'api.postmarkapp.com', true],
            'resend'   => [new ProbeResend(['key' => 'k']),                         'api.resend.com',    true],
            'brevo'    => [new ProbeBrevo(['key' => 'k']),                          'api.brevo.com',     true],
            'mailjet'  => [new ProbeMailjet(['key' => 'k', 'secret' => 's']),       'api.mailjet.com',   true],
            'mailgun'  => [new ProbeMailgun(['key' => 'k', 'domain' => 'mg.test']), 'api.mailgun.net',   false],
        ];

        foreach ($cases as $name => [$probe, $host, $isJson]) {
            $probe->send($this->mail());

            $this->assertStringContainsString($host, (string) $probe->url, "$name posts to its own endpoint");
            $this->assertNotSame('', (string) $probe->body, "$name sends a non-empty body");
            $this->assertNotEmpty($probe->headers, "$name sends headers");

            if ($isJson) {
                $decoded = json_decode((string) $probe->body, true);
                $this->assertIsArray($decoded, "$name sends valid JSON");
                $this->assertStringContainsString('Hello', (string) $probe->body, "$name carries the subject");
            } else {
                parse_str((string) $probe->body, $form);
                $this->assertSame('Hello', $form['subject'] ?? null, "$name form-encodes the subject");
            }
        }
    }

    #[Test]
    public function a_transport_failure_becomes_a_mail_transport_exception(): void
    {
        // Port 1 on localhost refuses instantly — a real cURL failure with no network dependency.
        $t = new ProbeUnreachable(['key' => 'k']);

        $this->expectException(Zend_Mail_Transport_Exception::class);
        $this->expectExceptionMessageMatches('/Mail API request failed/');
        $t->send($this->mail());
    }

    #[Test]
    public function ses_without_the_sdk_explains_itself_instead_of_a_class_not_found_fatal(): void
    {
        if (class_exists('Aws\\SesV2\\SesV2Client')) {
            $this->markTestSkipped('The AWS SDK is installed here, so the missing-SDK branch cannot be exercised.');
        }

        $t = new Tiger_Mail_Transport_Ses(['region' => 'us-east-1']);

        $this->expectException(Zend_Mail_Transport_Exception::class);
        // The operator needs to be told what to install and what to use instead — a raw
        // "Class not found" would be a dead end.
        $this->expectExceptionMessageMatches('/AWS SDK module \(tiger-sdk-aws\).*SES \(SMTP\)/s');
        $t->send($this->mail());
    }
}

/** Capture the request instead of performing it. */
trait ProbesTheRequest
{
    public $url;
    public $body;
    public $headers = [];

    protected function _post($url, $body, array $headers)
    {
        $this->url     = $url;
        $this->body    = $body;
        $this->headers = $headers;
        return '{"ok":true}';
    }
}

final class ProbeSendGrid extends Tiger_Mail_Transport_SendGrid { use ProbesTheRequest; }
final class ProbePostmark extends Tiger_Mail_Transport_Postmark { use ProbesTheRequest; }
final class ProbeResend   extends Tiger_Mail_Transport_Resend   { use ProbesTheRequest; }
final class ProbeBrevo    extends Tiger_Mail_Transport_Brevo    { use ProbesTheRequest; }
final class ProbeMailjet  extends Tiger_Mail_Transport_Mailjet  { use ProbesTheRequest; }
final class ProbeMailgun  extends Tiger_Mail_Transport_Mailgun  { use ProbesTheRequest; }

/** Points the REAL _post() at a closed local port to exercise the cURL failure branch. */
final class ProbeUnreachable extends Tiger_Mail_Transport_Resend
{
    protected function _endpoint()
    {
        return 'http://127.0.0.1:1/emails';
    }
}
