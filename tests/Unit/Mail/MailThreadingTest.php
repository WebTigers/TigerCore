<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Mail;
use Tiger_Mail_Transport_Api;
use Tiger_Mail_Transport_Resend;
use Zend_Config;
use Zend_Mail;
use Zend_Mail_Transport_Abstract;
use Zend_Registry;

/**
 * Tiger_Mail RFC 5322 threading headers (TIGER-445): messageId()/inReplyTo()/references() applied in send()
 * and getMessageId() read back, plus the API-transport flat shape carrying the three fields for driver opt-in.
 * Exercised through the real Zend_Mail render path via capturing transports — no network, no DB.
 */
#[CoversClass(Tiger_Mail::class)]
#[CoversClass(Tiger_Mail_Transport_Api::class)]
final class MailThreadingTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'mail' => ['from' => ['email' => 'support@acme.test', 'name' => 'Acme Support']],
        ], true));
    }

    private function mail(): Tiger_Mail
    {
        return (new Tiger_Mail())->to('buyer@example.test', 'Buyer')->subject('Re: [#AB-CD] Hello')->text('Hi');
    }

    #[Test]
    public function an_explicit_message_id_is_stamped_and_read_back(): void
    {
        $cap = new CaptureTransport();
        $m   = $this->mail()->messageId('ticket-1@acme.test');
        $m->send($cap);

        $this->assertSame('ticket-1@acme.test', $m->getMessageId(), 'getMessageId reflects the id sent');
        $this->assertSame('<ticket-1@acme.test>', $cap->headerValue('Message-Id'), 'the header wraps it in angle brackets');
    }

    #[Test]
    public function a_message_id_is_generated_when_none_is_set(): void
    {
        $cap = new CaptureTransport();
        $m   = $this->mail();
        $this->assertSame('', $m->getMessageId(), 'no id before send');
        $m->send($cap);

        $this->assertNotSame('', $m->getMessageId(), 'send generates and captures an id');
        $this->assertSame('<' . $m->getMessageId() . '>', $cap->headerValue('Message-Id'));
    }

    #[Test]
    public function in_reply_to_sets_its_header_and_seeds_references(): void
    {
        $cap = new CaptureTransport();
        $this->mail()->inReplyTo('parent@acme.test')->send($cap);

        $this->assertSame('<parent@acme.test>', $cap->headerValue('In-Reply-To'));
        $this->assertStringContainsString('<parent@acme.test>', $cap->headerValue('References'),
            'the parent is folded into References when absent');
    }

    #[Test]
    public function references_accepts_an_array_or_a_string_and_appends_the_parent(): void
    {
        $cap = new CaptureTransport();
        $this->mail()->references(['a@acme.test', 'b@acme.test'])->inReplyTo('c@acme.test')->send($cap);

        $refs = $cap->headerValue('References');
        $this->assertSame('<a@acme.test> <b@acme.test> <c@acme.test>', $refs, 'oldest first, parent appended once');

        $cap2 = new CaptureTransport();
        $this->mail()->references('x@acme.test  y@acme.test')->send($cap2);
        $this->assertSame('<x@acme.test> <y@acme.test>', $cap2->headerValue('References'), 'a string splits on whitespace');
    }

    #[Test]
    public function no_threading_setters_means_no_in_reply_to_or_references(): void
    {
        $cap = new CaptureTransport();
        $this->mail()->send($cap);
        $this->assertSame('', $cap->headerValue('In-Reply-To'));
        $this->assertSame('', $cap->headerValue('References'));
    }

    #[Test]
    public function the_api_transport_flat_shape_carries_the_threading_fields(): void
    {
        $probe = new ProbeApiMessage(['key' => 'k']);
        $this->mail()->messageId('m@acme.test')->inReplyTo('p@acme.test')->references(['a@acme.test'])->send($probe);

        $this->assertSame('m@acme.test', $probe->msg['message_id'], 'brackets stripped');
        $this->assertSame('p@acme.test', $probe->msg['in_reply_to']);
        $this->assertStringContainsString('a@acme.test', $probe->msg['references']);
        $this->assertStringContainsString('p@acme.test', $probe->msg['references'], 'parent folded in');
    }
}

/** Capture the Zend_Mail through the real render path (abstract send() → _sendMail). */
final class CaptureTransport extends Zend_Mail_Transport_Abstract
{
    public ?Zend_Mail $mail = null;
    /** @var array<string,array<int,string>> */
    public array $headers = [];

    protected function _sendMail()
    {
        $this->mail    = $this->_mail;
        $this->headers = (array) $this->_mail->getHeaders();
    }

    public function headerValue(string $name): string
    {
        return isset($this->headers[$name][0]) ? (string) $this->headers[$name][0] : '';
    }
}

/** An API driver that captures the normalized flat message instead of posting it. */
final class ProbeApiMessage extends Tiger_Mail_Transport_Resend
{
    /** @var array<string,mixed> */
    public array $msg = [];

    protected function _sendMail()
    {
        $this->msg = $this->_message();
    }
}
