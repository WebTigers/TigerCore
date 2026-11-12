<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\SnsSigner;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Sns;

/**
 * Tiger_Sns (TIGER-295) — an SNS envelope is trusted only with a valid signature from a certificate on an
 * SNS host, and a subscription is confirmed only at an SNS host. Signatures are real (SnsSigner).
 */
#[CoversClass(Tiger_Sns::class)]
final class SnsTest extends UnitTestCase
{
    private SnsSigner $sns;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sns = new SnsSigner();
        $this->sns->install();
    }

    protected function tearDown(): void
    {
        Tiger_Sns::reset();
        parent::tearDown();
    }

    #[Test]
    public function a_genuine_message_verifies_under_both_signature_versions(): void
    {
        $this->assertTrue(Tiger_Sns::verify($this->sns->message('Notification')));
        $this->assertTrue(Tiger_Sns::verify($this->sns->message('Notification', [], '1')));
        $this->assertTrue(Tiger_Sns::verify($this->sns->message('Notification', ['Subject' => 'Moderation'])), 'Subject is signed when present');
        $this->assertTrue(Tiger_Sns::verify($this->sns->message('SubscriptionConfirmation')));
    }

    #[Test]
    public function any_change_after_signing_fails(): void
    {
        foreach (['Message' => '{"JobId":"other"}', 'TopicArn' => 'arn:aws:sns:us-east-1:1:x', 'Type' => 'UnsubscribeConfirmation',
                  'SubscribeURL' => 'https://sns.us-east-1.amazonaws.com/?Action=Other'] as $field => $value) {
            $msg = $this->sns->message('SubscriptionConfirmation');
            $msg[$field] = $value;
            $this->assertFalse(Tiger_Sns::verify($msg), "$field changed after signing");
        }
        $msg = $this->sns->message('Notification');
        $msg['Signature'] = base64_encode(str_repeat('x', 256));
        $this->assertFalse(Tiger_Sns::verify($msg), 'forged signature');
    }

    #[Test]
    public function a_message_signed_by_another_key_fails(): void
    {
        $other = (new SnsSigner())->message('Notification');   // same cert URL, different key
        $this->assertFalse(Tiger_Sns::verify($other));
    }

    public static function foreignCertUrls(): array
    {
        return [
            'plain http'      => ['http://sns.us-east-1.amazonaws.com/cert.pem'],
            'other host'      => ['https://evil.example/cert.pem'],
            'suffix trick'    => ['https://sns.us-east-1.amazonaws.com.evil.example/cert.pem'],
            'userinfo trick'  => ['https://sns.us-east-1.amazonaws.com@evil.example/cert.pem'],
            'explicit port'   => ['https://sns.us-east-1.amazonaws.com:8443/cert.pem'],
            'not a pem'       => ['https://sns.us-east-1.amazonaws.com/cert.txt'],
            'metadata host'   => ['https://169.254.169.254/latest/meta-data/x.pem'],
        ];
    }

    #[Test]
    #[DataProvider('foreignCertUrls')]
    public function a_certificate_off_an_sns_host_is_never_fetched(string $url): void
    {
        $msg = $this->sns->message('Notification', ['SigningCertURL' => $url]);
        $this->assertFalse(Tiger_Sns::verify($msg));
        $this->assertSame([], $this->sns->fetched, 'nothing was requested');
    }

    #[Test]
    public function an_unknown_type_version_or_missing_field_fails(): void
    {
        $this->assertFalse(Tiger_Sns::verify($this->sns->message('Notification', [], '3')));
        $this->assertFalse(Tiger_Sns::verify(['Type' => 'Other'] + $this->sns->message('Notification')));
        $msg = $this->sns->message('SubscriptionConfirmation');
        unset($msg['Token']);
        $this->assertFalse(Tiger_Sns::verify($msg));
        $this->assertFalse(Tiger_Sns::verify([]));
    }

    #[Test]
    public function a_subscription_is_confirmed_only_at_an_sns_host(): void
    {
        $good = $this->sns->message('SubscriptionConfirmation');
        $this->assertTrue(Tiger_Sns::confirmSubscription($good));
        $this->assertContains($good['SubscribeURL'], $this->sns->fetched);

        $this->sns->fetched = [];
        foreach (['http://169.254.169.254/latest/meta-data/', 'http://127.0.0.1/admin', 'https://evil.example/x'] as $url) {
            $this->assertFalse(Tiger_Sns::confirmSubscription(['Type' => 'SubscriptionConfirmation', 'SubscribeURL' => $url]));
        }
        $this->assertFalse(Tiger_Sns::confirmSubscription(['Type' => 'Notification'] + $good), 'only a SubscriptionConfirmation');
        $this->assertSame([], $this->sns->fetched, 'no off-host URL was requested');
    }
}
