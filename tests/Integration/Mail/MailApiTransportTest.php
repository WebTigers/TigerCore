<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail_Transport_Api;
use Tiger_Mail_Transport_Brevo;
use Tiger_Mail_Transport_Mailgun;
use Tiger_Mail_Transport_Mailjet;
use Tiger_Mail_Transport_Postmark;
use Tiger_Mail_Transport_Resend;
use Tiger_Mail_Transport_SendGrid;
use Zend_Mail;

/**
 * The provider API drivers — the request each one would send, asserted WITHOUT touching the network.
 *
 * Every driver turns the same `Zend_Mail` into a different provider-specific body, and a wrong field
 * name surfaces only as an opaque 4xx from the provider in production. So each payload is pinned
 * here against its documented shape.
 *
 * The load-bearing one is `body_is_never_quoted_printable_encoded`. `Zend_Mail::getBodyHtml(true)`
 * and `Zend_Mime_Part::getContent()` return the part **encoded for wire transmission** — feeding
 * that into a JSON field would deliver visible `=3D` soup to the recipient. The base deliberately
 * uses `getRawContent()` instead, and that distinction is invisible until someone reads a real
 * email, which is exactly why it gets a test.
 */
#[CoversClass(Tiger_Mail_Transport_Api::class)]
#[CoversClass(Tiger_Mail_Transport_Brevo::class)]
#[CoversClass(Tiger_Mail_Transport_Mailgun::class)]
#[CoversClass(Tiger_Mail_Transport_Mailjet::class)]
#[CoversClass(Tiger_Mail_Transport_Postmark::class)]
#[CoversClass(Tiger_Mail_Transport_Resend::class)]
#[CoversClass(Tiger_Mail_Transport_SendGrid::class)]
final class MailApiTransportTest extends IntegrationTestCase
{
    /** An HTML body with characters quoted-printable WOULD mangle (= and a long line). */
    private const HTML = '<p>Totals: 40=40 &amp; rising — a deliberately long line so quoted-printable would insert a soft break somewhere in the middle of it.</p>';

    /** Build a realistic Zend_Mail, as Tiger_Mail::send() would. */
    private function mail(): Zend_Mail
    {
        $mail = new Zend_Mail('UTF-8');
        $mail->setFrom('no-reply@example.test', 'Tiger Support');
        $mail->addTo('buyer@example.test');
        $mail->setReplyTo('help@example.test');
        $mail->setSubject('Your receipt');
        $mail->setBodyHtml(self::HTML);
        $mail->setBodyText('Totals: 40=40 & rising');
        return $mail;
    }

    /** Run a driver's protected _payload() against the mail, returning the decoded body. */
    private function payload(Tiger_Mail_Transport_Api $t): array
    {
        $mail = $this->mail();

        $prop = new ReflectionProperty(Tiger_Mail_Transport_Api::class, '_mail');
        $prop->setValue($t, $mail);

        $msg = new ReflectionMethod($t, '_message');
        $message = $msg->invoke($t);

        $pay = new ReflectionMethod($t, '_payload');
        $out = $pay->invoke($t, $message);

        if (is_string($out)) { parse_str($out, $parsed); return $parsed; }   // Mailgun posts form-encoded
        return $out;
    }

    #[Test]
    public function body_is_never_quoted_printable_encoded(): void
    {
        $p = $this->payload(new Tiger_Mail_Transport_Resend(['key' => 'k']));

        $this->assertSame(self::HTML, $p['html'],
            'the HTML must reach the API verbatim — getContent()/getBodyHtml(true) would hand over =3D-encoded soup');
        $this->assertStringNotContainsString('=3D', $p['html'], 'no quoted-printable escapes');
        $this->assertStringNotContainsString("=\r\n", $p['html'], 'no soft line breaks');
    }

    #[Test]
    public function the_sender_display_name_survives(): void
    {
        // Zend_Mail has no getFromName(); the base parses it back out of the formatted From header.
        $p = $this->payload(new Tiger_Mail_Transport_SendGrid(['key' => 'k']));
        $this->assertSame('Tiger Support', $p['from']['name'], 'the display name is recovered from the From header');
        $this->assertSame('no-reply@example.test', $p['from']['email']);
    }

    #[Test]
    public function sendgrid_uses_personalizations_and_typed_content(): void
    {
        $p = $this->payload(new Tiger_Mail_Transport_SendGrid(['key' => 'k']));

        $this->assertSame('buyer@example.test', $p['personalizations'][0]['to'][0]['email']);
        $this->assertSame('Your receipt', $p['subject']);
        $types = array_column($p['content'], 'type');
        $this->assertSame(['text/plain', 'text/html'], $types, 'plain part precedes html, as SendGrid requires');
        $this->assertSame('help@example.test', $p['reply_to']['email']);
    }

    #[Test]
    public function postmark_uses_pascal_case_and_a_message_stream(): void
    {
        $p = $this->payload(new Tiger_Mail_Transport_Postmark(['key' => 't']));

        $this->assertSame('"Tiger Support" <no-reply@example.test>', $p['From']);
        $this->assertSame('buyer@example.test', $p['To']);
        $this->assertSame('outbound', $p['MessageStream'], 'defaults to the outbound stream');
        $this->assertSame(self::HTML, $p['HtmlBody']);
        $this->assertSame('help@example.test', $p['ReplyTo']);
    }

    #[Test]
    public function mailgun_posts_form_encoded_fields(): void
    {
        $p = $this->payload(new Tiger_Mail_Transport_Mailgun(['key' => 'k', 'domain' => 'mg.example.test']));

        $this->assertSame('Tiger Support <no-reply@example.test>', $p['from']);
        $this->assertSame('buyer@example.test', $p['to']);
        $this->assertSame(self::HTML, $p['html']);
        $this->assertSame('help@example.test', $p['h:Reply-To'], 'reply-to rides as a custom header');
    }

    #[Test]
    public function mailgun_endpoint_honors_the_region_base(): void
    {
        $eu = new Tiger_Mail_Transport_Mailgun(['key' => 'k', 'domain' => 'mg.example.test', 'endpoint' => 'https://api.eu.mailgun.net']);
        $m  = new ReflectionMethod($eu, '_endpoint');
        $this->assertSame('https://api.eu.mailgun.net/v3/mg.example.test/messages', $m->invoke($eu),
            'an EU key must not be sent to the US endpoint');

        $us = new Tiger_Mail_Transport_Mailgun(['key' => 'k', 'domain' => 'mg.example.test']);
        $this->assertStringStartsWith('https://api.mailgun.net/', $m->invoke($us), 'US is the default base');
    }

    #[Test]
    public function brevo_and_mailjet_use_their_own_envelopes(): void
    {
        $b = $this->payload(new Tiger_Mail_Transport_Brevo(['key' => 'k']));
        $this->assertSame('no-reply@example.test', $b['sender']['email']);
        $this->assertSame('buyer@example.test', $b['to'][0]['email']);
        $this->assertSame(self::HTML, $b['htmlContent']);

        $j = $this->payload(new Tiger_Mail_Transport_Mailjet(['key' => 'k', 'secret' => 's']));
        $this->assertArrayHasKey('Messages', $j, 'Mailjet wraps in a Messages array');
        $this->assertSame('no-reply@example.test', $j['Messages'][0]['From']['Email']);
        $this->assertSame(self::HTML, $j['Messages'][0]['HTMLPart']);
    }

    #[Test]
    public function each_driver_sends_its_credential_in_the_right_header(): void
    {
        $headers = static function (Tiger_Mail_Transport_Api $t): string {
            $m = new ReflectionMethod($t, '_headers');
            return implode("\n", $m->invoke($t));
        };

        $this->assertStringContainsString('Authorization: Bearer SG.key', $headers(new Tiger_Mail_Transport_SendGrid(['key' => 'SG.key'])));
        $this->assertStringContainsString('X-Postmark-Server-Token: tok', $headers(new Tiger_Mail_Transport_Postmark(['key' => 'tok'])));
        $this->assertStringContainsString('api-key: xkeysib', $headers(new Tiger_Mail_Transport_Brevo(['key' => 'xkeysib'])));
        $this->assertStringContainsString('Authorization: Basic ' . base64_encode('api:k'), $headers(new Tiger_Mail_Transport_Mailgun(['key' => 'k'])),
            'Mailgun authenticates as the literal user "api"');
        $this->assertStringContainsString('Authorization: Basic ' . base64_encode('k:s'), $headers(new Tiger_Mail_Transport_Mailjet(['key' => 'k', 'secret' => 's'])),
            'Mailjet is the one provider needing a key AND a secret');
    }
}
