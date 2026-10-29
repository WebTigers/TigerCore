<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Sms;
use Tiger_Sms_Adapter_Abstract;
use Tiger_Sms_Adapter_Twilio;
use Tiger_Sms_Adapter_Sns;
use Tiger_Sms_Adapter_Vonage;
use Tiger_Sms_Adapter_Plivo;
use Tiger_Sms_Adapter_Telnyx;
use Zend_Config;
use Zend_Registry;

/** A tiny in-test adapter so the facade's config-driven routing can be exercised network-free. */
final class StubSmsAdapter extends Tiger_Sms_Adapter_Abstract
{
    public function isConfigured(): bool { return true; }
    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        return ['ok' => true, 'id' => 'stub-1', 'from_used' => $from, 'msg' => $message];
    }
}

/** The provider-agnostic SMS facade + every built-in adapter (Twilio/SNS/Vonage/Plivo/Telnyx) —
 *  request shaping, response parsing, config detection, and inbound verify/parse — all network-free
 *  (the HTTP seam is overridden). */
#[CoversClass(Tiger_Sms::class)]
#[CoversClass(Tiger_Sms_Adapter_Twilio::class)]
#[CoversClass(Tiger_Sms_Adapter_Sns::class)]
#[CoversClass(Tiger_Sms_Adapter_Vonage::class)]
#[CoversClass(Tiger_Sms_Adapter_Plivo::class)]
#[CoversClass(Tiger_Sms_Adapter_Telnyx::class)]
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
    public function facade_fails_cleanly_with_no_provider(): void
    {
        $r = Tiger_Sms::send('+14075551234', 'hi');          // nothing configured in a unit run
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no SMS provider', $r['error']);
        foreach (['twilio', 'sns', 'vonage', 'plivo', 'telnyx'] as $p) {       // the built-ins self-seed
            $this->assertContains($p, Tiger_Sms::providers());
        }
    }

    /** A config shape helper: run $fn with `tiger` set to $tiger, then restore the registry. */
    private function withConfig(array $tiger, callable $fn): void
    {
        $prev = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger]));
        try { $fn(); } finally {
            if ($prev !== null) { Zend_Registry::set('Zend_Config', $prev); }
            else { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); }
        }
    }

    #[Test]
    public function brand_prefers_the_sms_brand_over_the_site_name(): void
    {
        // The whole point: an install whose SITE is named for the product can still send SMS under the
        // company it registered with the carrier.
        $this->withConfig(
            ['sms' => ['brand' => 'WebTigers'], 'site' => ['name' => 'Tiger']],
            function () {
                $this->assertSame('WebTigers', Tiger_Sms::brand());
                $this->assertTrue(Tiger_Sms::brandIsSet());
            }
        );
    }

    #[Test]
    public function brand_falls_back_to_the_site_name_and_says_so(): void
    {
        // Falling back is deliberate (an unsigned message is worse), but it must be REPORTABLE so the
        // settings screen can warn — that is the difference between a fallback and a silent default.
        $this->withConfig(['site' => ['name' => 'Acme Books']], function () {
            $this->assertSame('Acme Books', Tiger_Sms::brand());
            $this->assertFalse(Tiger_Sms::brandIsSet());
        });

        // A blank brand is not a brand.
        $this->withConfig(['sms' => ['brand' => '  '], 'site' => ['name' => 'Acme Books']], function () {
            $this->assertSame('Acme Books', Tiger_Sms::brand());
            $this->assertFalse(Tiger_Sms::brandIsSet());
        });
    }

    #[Test]
    public function brand_never_returns_empty(): void
    {
        // Callers interpolate this straight into a message body and into the consent sentence, so ''
        // would ship "  verification code: 123456" and a legal disclosure with a hole in it.
        $this->withConfig([], function () {
            $this->assertSame('Tiger', Tiger_Sms::brand());
            $this->assertFalse(Tiger_Sms::brandIsSet());
        });

        $prev = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        try {
            $this->assertSame('Tiger', Tiger_Sms::brand());   // no config registered at all
            $this->assertFalse(Tiger_Sms::brandIsSet());
        } finally {
            if ($prev !== null) { Zend_Registry::set('Zend_Config', $prev); }
        }
    }

    #[Test]
    public function brand_is_trimmed(): void
    {
        $this->withConfig(['sms' => ['brand' => "  WebTigers\t"]], function () {
            $this->assertSame('WebTigers', Tiger_Sms::brand());
        });
    }

    #[Test]
    public function facade_routes_normalizes_and_guards_with_a_configured_provider(): void
    {
        Tiger_Sms::register('stub', StubSmsAdapter::class);
        $prev = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['sms' => [
            'provider' => 'stub',
            'adapters' => ['stub' => ['from' => '+15550001111']],
        ]]]));
        try {
            $this->assertSame('stub', Tiger_Sms::provider());
            $this->assertTrue(Tiger_Sms::isConfigured());

            $ok = Tiger_Sms::send('(407) 555-1234', 'hello');
            $this->assertTrue($ok['ok']);
            $this->assertSame('stub', $ok['provider']);
            $this->assertSame('+14075551234', $ok['to']);          // normalized on the way through
            $this->assertSame('+15550001111', $ok['from_used']);   // resolved from the adapter's config

            $this->assertFalse(Tiger_Sms::send('nope', 'hi')['ok']);           // invalid number
            $this->assertFalse(Tiger_Sms::send('+14075551234', '')['ok']);     // empty message
        } finally {
            if ($prev !== null) { Zend_Registry::set('Zend_Config', $prev); }
            else { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); }
        }
    }

    #[Test]
    public function twilio_uses_messaging_service_sid_when_no_from(): void
    {
        $a = new class(['account_sid' => 'ACx', 'auth_token' => 't', 'messaging_service_sid' => 'MGx']) extends Tiger_Sms_Adapter_Twilio {
            public $body = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->body = $body;
                return [201, json_encode(['sid' => 'SMx'])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '');   // no From → must fall back to the Messaging Service SID
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('MessagingServiceSid=MGx', $a->body);
        $this->assertSame('Twilio', $a->label());
    }

    #[Test]
    public function sns_without_credentials_or_ec2_role_fails_cleanly(): void
    {
        $a = new class(['region' => 'us-east-1']) extends Tiger_Sms_Adapter_Sns {  // no keys
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                return [0, 'unreachable'];   // simulate: not on EC2 (no IMDS)
            }
        };
        $r = $a->send('+14075551234', 'hi', '+18885551234');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no credentials', $r['error']);
        $this->assertNotEmpty($a->fields());
        $this->assertSame('Amazon SNS', $a->label());
    }

    #[Test]
    public function inbound_dispatch_fans_out_and_isolates_a_bad_listener(): void
    {
        $seen = [];
        Tiger_Sms::onInbound(function (array $m) use (&$seen) { $seen[] = $m['body']; });
        Tiger_Sms::onInbound(function () { throw new \RuntimeException('boom'); });   // must not break the rest
        Tiger_Sms::onInbound(function (array $m) use (&$seen) { $seen[] = strtoupper($m['body']); });

        // The isolated listener logs its failure; point Tiger_Log at the null sink so the test stays quiet.
        $prev = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['log' => ['writer' => 'null']]]));
        \Tiger_Log::reset();
        try {
            $delivered = Tiger_Sms::dispatchInbound(['provider' => 'twilio', 'from' => '+1555', 'body' => 'help']);
        } finally {
            if ($prev !== null) { Zend_Registry::set('Zend_Config', $prev); }
            else { Zend_Registry::getInstance()->offsetUnset('Zend_Config'); }
            \Tiger_Log::reset();
        }
        $this->assertSame(2, $delivered, 'two good listeners notified, the throwing one isolated');
        $this->assertSame(['help', 'HELP'], $seen);
    }

    #[Test]
    public function twilio_verifies_its_webhook_signature(): void
    {
        $a     = new Tiger_Sms_Adapter_Twilio(['account_sid' => 'ACx', 'auth_token' => 'tok', 'from' => '+1555']);
        $url   = 'https://app.example.com/sms/inbound/twilio';
        $post  = ['Body' => 'hello', 'From' => '+14075551234', 'To' => '+18885551234'];
        // Twilio's scheme: url + each key.value sorted by key, HMAC-SHA1, base64.
        ksort($post);
        $data  = $url; foreach ($post as $k => $v) { $data .= $k . $v; }
        $good  = base64_encode(hash_hmac('sha1', $data, 'tok', true));

        $this->assertTrue($a->verifyInbound($url, $post, ['X-Twilio-Signature' => $good]));
        $this->assertFalse($a->verifyInbound($url, $post, ['X-Twilio-Signature' => 'wrong']));
        $this->assertFalse($a->verifyInbound($url, $post, []), 'missing signature fails closed');
        $this->assertFalse((new Tiger_Sms_Adapter_Twilio([]))->verifyInbound($url, $post, ['X-Twilio-Signature' => $good]), 'no token fails closed');
    }

    #[Test]
    public function twilio_parses_inbound_message_and_ignores_non_messages(): void
    {
        $a = new Tiger_Sms_Adapter_Twilio([]);
        $msg = $a->parseInbound(['MessageSid' => 'SM9', 'From' => '+14075551234', 'To' => '+18885551234', 'Body' => 'STOP', 'NumMedia' => '1', 'MediaUrl0' => 'https://x/y.jpg']);
        $this->assertSame('twilio', $msg['provider']);
        $this->assertSame('+14075551234', $msg['from']);
        $this->assertSame('STOP', $msg['body']);
        $this->assertSame(['https://x/y.jpg'], $msg['media']);
        $this->assertNull($a->parseInbound(['MessageStatus' => 'delivered', 'MessageSid' => 'SM9']), 'a status callback is not an inbound message');
    }

    #[Test]
    public function sns_inbound_refuses_by_default(): void
    {
        $a = new Tiger_Sms_Adapter_Sns(['region' => 'us-east-1']);
        $this->assertFalse($a->verifyInbound('https://x/sms/inbound/sns', ['Message' => 'hi'], []));
        $this->assertNull($a->parseInbound(['Message' => 'hi']));
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

    // ----- Vonage -----------------------------------------------------------------------------------

    #[Test]
    public function vonage_config_detection(): void
    {
        $this->assertFalse((new Tiger_Sms_Adapter_Vonage([]))->isConfigured());
        $this->assertFalse((new Tiger_Sms_Adapter_Vonage(['api_key' => 'k', 'api_secret' => 's']))->isConfigured(), 'no sender');
        $this->assertTrue((new Tiger_Sms_Adapter_Vonage(['api_key' => 'k', 'api_secret' => 's', 'from' => 'Brand']))->isConfigured());
    }

    #[Test]
    public function vonage_send_shapes_request_marks_unicode_and_parses_id(): void
    {
        $a = new class(['api_key' => 'k', 'api_secret' => 's', 'from' => 'Brand']) extends Tiger_Sms_Adapter_Vonage {
            public $url = ''; public $body = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->url = $url; $this->body = $body;
                return [200, json_encode(['message-count' => '1', 'messages' => [['status' => '0', 'message-id' => 'VON1']]])];
            }
        };
        $r = $a->send('+14075551234', "caf\u{00e9}", 'Brand');   // a non-ASCII body must flip type=unicode
        $this->assertTrue($r['ok']);
        $this->assertSame('VON1', $r['id']);
        $this->assertStringContainsString('rest.nexmo.com/sms/json', $a->url);
        $this->assertStringContainsString('to=14075551234', $a->body);   // digits, no leading +
        $this->assertStringContainsString('type=unicode', $a->body);
        $this->assertSame('Vonage', $a->label());
    }

    #[Test]
    public function vonage_reports_api_error_even_on_http_200(): void
    {
        $a = new class(['api_key' => 'k', 'api_secret' => 's', 'from' => 'Brand']) extends Tiger_Sms_Adapter_Vonage {
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                return [200, json_encode(['messages' => [['status' => '2', 'error-text' => 'Missing api_secret']]])];
            }
        };
        $r = $a->send('+14075551234', 'hi', 'Brand');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Missing api_secret', $r['error']);
    }

    #[Test]
    public function vonage_verifies_signed_inbound_and_parses(): void
    {
        $a    = new Tiger_Sms_Adapter_Vonage(['api_key' => 'k', 'api_secret' => 's', 'from' => 'Brand', 'signature_secret' => 'sig-secret']);
        $post = ['msisdn' => '14075551234', 'to' => '447700900000', 'text' => 'hello', 'messageId' => 'abc', 'timestamp' => '1600000000'];
        // The scheme: sorted &key=value (with &/= in values → _), HMAC-SHA256 (default), compared case-insensitive.
        $p = $post; ksort($p);
        $data = ''; foreach ($p as $k => $v) { $data .= '&' . $k . '=' . str_replace(['&', '='], '_', (string) $v); }
        $good = strtoupper(hash_hmac('sha256', $data, 'sig-secret'));

        $this->assertTrue($a->verifyInbound('https://x/sms/inbound/vonage', $post + ['sig' => $good], []));
        $this->assertFalse($a->verifyInbound('https://x/sms/inbound/vonage', $post + ['sig' => 'nope'], []));
        $this->assertFalse($a->verifyInbound('https://x/sms/inbound/vonage', $post, []), 'missing sig fails closed');
        $this->assertFalse((new Tiger_Sms_Adapter_Vonage([]))->verifyInbound('https://x', $post + ['sig' => $good], []), 'no secret fails closed');

        $msg = $a->parseInbound(['msisdn' => '14075551234', 'to' => '447700900000', 'text' => 'STOP', 'messageId' => 'abc']);
        $this->assertSame('vonage', $msg['provider']);
        $this->assertSame('+14075551234', $msg['from']);
        $this->assertSame('STOP', $msg['body']);
        $this->assertNull($a->parseInbound(['status' => 'delivered', 'messageId' => 'abc']), 'a delivery receipt is not an inbound message');
    }

    // ----- Plivo ------------------------------------------------------------------------------------

    #[Test]
    public function plivo_config_detection(): void
    {
        $this->assertFalse((new Tiger_Sms_Adapter_Plivo([]))->isConfigured());
        $this->assertFalse((new Tiger_Sms_Adapter_Plivo(['auth_id' => 'MA', 'auth_token' => 't']))->isConfigured(), 'no sender');
        $this->assertTrue((new Tiger_Sms_Adapter_Plivo(['auth_id' => 'MA', 'auth_token' => 't', 'from' => '+15551230000']))->isConfigured());
        $this->assertTrue((new Tiger_Sms_Adapter_Plivo(['auth_id' => 'MA', 'auth_token' => 't', 'powerpack_uuid' => 'pp']))->isConfigured());
    }

    #[Test]
    public function plivo_send_shapes_request_and_parses_uuid(): void
    {
        $a = new class(['auth_id' => 'MA123', 'auth_token' => 'tok', 'from' => '+15551230000']) extends Tiger_Sms_Adapter_Plivo {
            public $url = ''; public $body = ''; public $auth = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->url = $url; $this->body = $body; $this->auth = implode("\n", $headers);
                return [202, json_encode(['message' => 'message(s) queued', 'message_uuid' => ['PL-1'], 'api_id' => 'x'])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '+15551230000');
        $this->assertTrue($r['ok']);
        $this->assertSame('PL-1', $r['id']);
        $this->assertStringContainsString('Account/MA123/Message/', $a->url);
        $this->assertStringContainsString('"dst":"14075551234"', $a->body);
        $this->assertStringContainsString('"src":"15551230000"', $a->body);
        $this->assertStringContainsString('Authorization: Basic ' . base64_encode('MA123:tok'), $a->auth);
        $this->assertSame('Plivo', $a->label());
    }

    #[Test]
    public function plivo_uses_powerpack_and_reports_api_error(): void
    {
        $a = new class(['auth_id' => 'MA', 'auth_token' => 't', 'powerpack_uuid' => 'pp-9']) extends Tiger_Sms_Adapter_Plivo {
            public $body = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->body = $body;
                return [400, json_encode(['error' => 'invalid destination number'])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '');   // no From → the PowerPack is the sender
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('invalid destination number', $r['error']);
        $this->assertStringContainsString('"powerpack_uuid":"pp-9"', $a->body);
    }

    #[Test]
    public function plivo_verifies_v3_signature_and_parses(): void
    {
        $a     = new Tiger_Sms_Adapter_Plivo(['auth_id' => 'MA', 'auth_token' => 'tok', 'from' => '+1555']);
        $url   = 'https://app.example.com/sms/inbound/plivo';
        $nonce = 'nonce-123';
        $good  = base64_encode(hash_hmac('sha256', $url . $nonce, 'tok', true));
        $hdr   = ['X-Plivo-Signature-V3' => $good, 'X-Plivo-Signature-V3-Nonce' => $nonce];

        $this->assertTrue($a->verifyInbound($url, [], $hdr));
        $this->assertTrue($a->verifyInbound($url, [], ['X-Plivo-Signature-V3' => 'other,' . $good, 'X-Plivo-Signature-V3-Nonce' => $nonce]), 'any of a comma list passes');
        $this->assertFalse($a->verifyInbound($url, [], ['X-Plivo-Signature-V3' => 'wrong', 'X-Plivo-Signature-V3-Nonce' => $nonce]));
        $this->assertFalse($a->verifyInbound($url, [], []), 'missing header fails closed');

        $msg = $a->parseInbound(['From' => '14075551234', 'To' => '18885551234', 'Text' => 'STOP', 'MessageUUID' => 'PL-1', 'Type' => 'sms']);
        $this->assertSame('plivo', $msg['provider']);
        $this->assertSame('+14075551234', $msg['from']);
        $this->assertSame('STOP', $msg['body']);
        $this->assertNull($a->parseInbound(['Type' => 'mms_status']), 'a status payload is not an inbound message');
    }

    // ----- Telnyx -----------------------------------------------------------------------------------

    #[Test]
    public function telnyx_config_detection(): void
    {
        $this->assertFalse((new Tiger_Sms_Adapter_Telnyx([]))->isConfigured());
        $this->assertFalse((new Tiger_Sms_Adapter_Telnyx(['api_key' => 'KEY']))->isConfigured(), 'no sender');
        $this->assertTrue((new Tiger_Sms_Adapter_Telnyx(['api_key' => 'KEY', 'from' => '+15551230000']))->isConfigured());
        $this->assertTrue((new Tiger_Sms_Adapter_Telnyx(['api_key' => 'KEY', 'messaging_profile_id' => 'mp']))->isConfigured());
    }

    #[Test]
    public function telnyx_send_shapes_request_and_parses_id(): void
    {
        $a = new class(['api_key' => 'KEYabc', 'from' => '+15551230000']) extends Tiger_Sms_Adapter_Telnyx {
            public $url = ''; public $body = ''; public $auth = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->url = $url; $this->body = $body; $this->auth = implode("\n", $headers);
                return [200, json_encode(['data' => ['id' => 'TLX-1', 'record_type' => 'message']])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '+15551230000');
        $this->assertTrue($r['ok']);
        $this->assertSame('TLX-1', $r['id']);
        $this->assertStringContainsString('/v2/messages', $a->url);
        $this->assertStringContainsString('"to":"+14075551234"', $a->body);     // Telnyx keeps the leading +
        $this->assertStringContainsString('"from":"+15551230000"', $a->body);
        $this->assertStringContainsString('Authorization: Bearer KEYabc', $a->auth);
        $this->assertSame('Telnyx', $a->label());
    }

    #[Test]
    public function telnyx_uses_messaging_profile_and_reports_api_error(): void
    {
        $a = new class(['api_key' => 'KEY', 'messaging_profile_id' => 'mp-9']) extends Tiger_Sms_Adapter_Telnyx {
            public $body = '';
            protected function _http(string $method, string $url, array $headers = [], string $body = '', int $timeout = 15): array
            {
                $this->body = $body;
                return [422, json_encode(['errors' => [['title' => 'Invalid request', 'detail' => 'from is required']]])];
            }
        };
        $r = $a->send('+14075551234', 'hi', '');   // no From → the messaging profile selects the number
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('from is required', $r['error']);
        $this->assertStringContainsString('"messaging_profile_id":"mp-9"', $a->body);
    }

    #[Test]
    public function telnyx_inbound_refuses_by_default(): void
    {
        $a = new Tiger_Sms_Adapter_Telnyx(['api_key' => 'KEY', 'from' => '+1555']);
        $this->assertFalse($a->verifyInbound('https://x/sms/inbound/telnyx', [], []));
        $this->assertNull($a->parseInbound([]));
    }
}
