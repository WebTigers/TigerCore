<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Sms;
use Tiger_Sms_Adapter_Twilio;
use Tiger_Sms_Adapter_Sns;

/** The provider-agnostic SMS facade + the Twilio/SNS adapters (request shaping, response parsing,
 *  config detection) — all network-free (the HTTP seam is overridden). */
#[CoversClass(Tiger_Sms::class)]
#[CoversClass(Tiger_Sms_Adapter_Twilio::class)]
#[CoversClass(Tiger_Sms_Adapter_Sns::class)]
final class SmsTest extends UnitTestCase
{
    #[Test]
    public function e164_normalizes_and_rejects_junk(): void
    {
        $this->assertSame('+14075551234', Tiger_Sms::e164('(407) 555-1234'));   // bare US 10-digit → +1
        $this->assertSame('+14075551234', Tiger_Sms::e164('+1 407-555-1234'));
        $this->assertSame('+447911123456', Tiger_Sms::e164('+44 7911 123456'));
        $this->assertSame('', Tiger_Sms::e164('12'));          // too short
        $this->assertSame('', Tiger_Sms::e164('words only'));  // no digits
        $this->assertSame('', Tiger_Sms::e164('+0123456789')); // E.164 can't lead with 0
    }

    #[Test]
    public function twilio_config_detection(): void
    {
        $this->assertFalse((new Tiger_Sms_Adapter_Twilio([]))->isConfigured());
        $this->assertFalse((new Tiger_Sms_Adapter_Twilio(['account_sid' => 'ACx', 'auth_token' => 't']))->isConfigured(), 'no sender');
        $this->assertTrue((new Tiger_Sms_Adapter_Twilio(['account_sid' => 'ACx', 'auth_token' => 't', 'from' => '+15551230000']))->isConfigured());
        $this->assertTrue((new Tiger_Sms_Adapter_Twilio(['account_sid' => 'ACx', 'auth_token' => 't', 'messaging_service_sid' => 'MGx']))->isConfigured());
    }

    #[Test]
    public function twilio_send_shapes_request_and_parses_sid(): void
    {
        $a = new class(['account_sid' => 'ACx', 'auth_token' => 'tok', 'from' => '+15551230000']) extends Tiger_Sms_Adapter_Twilio {
            public $url = ''; public $body = ''; public $auth = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->url = $url; $this->body = $body; $this->auth = implode("\n", $headers);
                return [201, json_encode(['sid' => 'SM123', 'status' => 'queued'])];
            }
        };
        $r = $a->send('+14075551234', 'hi there', '+15551230000');
        $this->assertTrue($r['ok']);
        $this->assertSame('SM123', $r['id']);
        $this->assertStringContainsString('Accounts/ACx/Messages.json', $a->url);
        $this->assertStringContainsString('To=%2B14075551234', $a->body);
        $this->assertStringContainsString('Authorization: Basic ' . base64_encode('ACx:tok'), $a->auth);
    }

    #[Test]
    public function twilio_send_reports_api_error(): void
    {
        $a = new class(['account_sid' => 'ACx', 'auth_token' => 'tok', 'from' => '+1555']) extends Tiger_Sms_Adapter_Twilio {
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                return [400, json_encode(['message' => 'The From number is not a valid phone number', 'code' => 21212])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '+1555');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('not a valid phone number', $r['error']);
    }

    #[Test]
    public function sns_send_signs_and_parses_message_id(): void
    {
        $a = new class(['region' => 'us-east-1', 'key' => 'AKIATEST', 'secret_key' => 'secret', 'from' => '+18885551234']) extends Tiger_Sms_Adapter_Sns {
            public $headers = []; public $body = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->headers = $headers; $this->body = $body;
                return [200, '<PublishResponse><PublishResult><MessageId>abc-123</MessageId></PublishResult></PublishResponse>'];
            }
        };
        $r = $a->send('+14075551234', 'hello', '+18885551234');
        $this->assertTrue($r['ok']);
        $this->assertSame('abc-123', $r['id']);
        $auth = implode("\n", $a->headers);
        $this->assertStringContainsString('AWS4-HMAC-SHA256 Credential=AKIATEST/', $auth);
        $this->assertStringContainsString('us-east-1/sns/aws4_request', $auth);
        $this->assertStringContainsString('PhoneNumber=%2B14075551234', $a->body);
    }
}
