<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Profile_Service_Sms;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_SmsConsent;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Tiger_Sms;
use Tiger_Sms_Adapter_Abstract;
use Zend_Config;
use Zend_Registry;

/** An in-test SMS adapter that captures sent messages (so the OTP code can be read back), network-free. */
final class CapturingSmsAdapter extends Tiger_Sms_Adapter_Abstract
{
    /** @var array<int,array{to:string,message:string}> */
    public static array $sent = [];
    public function isConfigured(): bool { return true; }
    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        self::$sent[] = ['to' => $to, 'message' => $message];
        return ['ok' => true, 'id' => 'cap-' . count(self::$sent)];
    }
}

/**
 * Profile_Service_Sms — the self-service Phone & SMS opt-in (/api), TIGER-376.
 *
 * Covers the double opt-in: consent is mandatory before a code is sent; requestCode issues an sms_otp
 * challenge + texts a code and creates a pending factor; verifyCode marks the number verified and writes
 * an auditable consent record; a number verified on one account can't be enrolled on another (global
 * uniqueness); a wrong code is refused; remove unlinks the number and the consent; a guest is refused.
 */
#[CoversClass(Profile_Service_Sms::class)]
final class SmsServiceTest extends IntegrationTestCase
{
    private string $userId;
    private string $phone = '+14075550155';
    private $prevConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);

        CapturingSmsAdapter::$sent = [];
        Tiger_Sms::register('capture', CapturingSmsAdapter::class);

        // Merge-swap the config so the facade routes to the capturing provider (preserves the rest).
        $this->prevConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $this->prevConfig ? $this->prevConfig->toArray() : [];
        $arr['tiger']['sms']['provider'] = 'capture';
        $arr['tiger']['sms']['from']     = '+15550001111';
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));

        $this->userId = (new Tiger_Model_User())->insert(['email' => 'sms@w376.test', 'status' => 'active']);
        $this->login($this->userId, 'org-sms', 'user');
    }

    protected function tearDown(): void
    {
        if ($this->prevConfig !== null) { Zend_Registry::set('Zend_Config', $this->prevConfig); }
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new Profile_Service_Sms(['action' => $action] + $params))->getResponse();
    }

    private function lastCode(): string
    {
        $msg = CapturingSmsAdapter::$sent ? end(CapturingSmsAdapter::$sent)['message'] : '';
        return preg_match('/(\d{6})/', $msg, $m) ? $m[1] : '';
    }

    private function enroll(): string
    {
        $this->assertSame(1, (int) $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1'])->result);
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertSame(1, (int) $this->call('verifyCode', ['phone' => $this->phone, 'code' => $code])->result);
        return $code;
    }

    // ----- gate ---------------------------------------------------------------

    #[Test]
    public function a_guest_is_refused(): void
    {
        $this->logout();
        $this->assertSame(0, (int) $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1'])->result);
        $this->assertSame(0, (int) $this->call('status')->result);
    }

    // ----- consent + request --------------------------------------------------

    #[Test]
    public function a_code_is_not_sent_without_consent(): void
    {
        $res = $this->call('requestCode', ['phone' => $this->phone]);   // no consent
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('consent_required', json_encode($res->messages));
        $this->assertCount(0, CapturingSmsAdapter::$sent, 'nothing is texted without consent');
    }

    #[Test]
    public function a_bad_number_is_refused(): void
    {
        $res = $this->call('requestCode', ['phone' => 'nope', 'consent' => '1']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('bad_phone', json_encode($res->messages));
    }

    #[Test]
    public function request_sends_a_code_and_creates_a_pending_unverified_factor(): void
    {
        $res = $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1']);
        $this->assertSame(1, (int) $res->result);
        $this->assertStringContainsString('code_sent', json_encode($res->messages));
        $this->assertCount(1, CapturingSmsAdapter::$sent);
        $this->assertSame($this->phone, CapturingSmsAdapter::$sent[0]['to']);

        $factor = (new Tiger_Model_UserCredential())->factor($this->userId, Tiger_Model_UserCredential::TYPE_SMS, $this->phone);
        $this->assertNotNull($factor);
        $this->assertNull($factor->verified_at, 'the factor stays pending until the code is confirmed');
        $this->assertTrue((bool) $res->data['sms']['pending']);
    }

    // ----- verify -------------------------------------------------------------

    #[Test]
    public function verify_marks_the_number_verified_and_records_consent(): void
    {
        $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1']);
        $res = $this->call('verifyCode', ['phone' => $this->phone, 'code' => $this->lastCode()]);

        $this->assertSame(1, (int) $res->result);
        $this->assertStringContainsString('sms.verified', json_encode($res->messages));
        $this->assertTrue((bool) $res->data['sms']['verified']);

        $factor = (new Tiger_Model_UserCredential())->findVerifiedByIdentifier(Tiger_Model_UserCredential::TYPE_SMS, $this->phone);
        $this->assertNotNull($factor);
        $this->assertSame($this->userId, (string) $factor->user_id);

        $consent = (new Tiger_Model_SmsConsent())->forUser($this->userId);
        $this->assertNotNull($consent, 'an auditable consent record is written on verify');
        $this->assertSame('twofa,alerts', (string) $consent->scopes);
        $this->assertNotNull($consent->consent_at);
    }

    #[Test]
    public function a_wrong_code_is_refused(): void
    {
        $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1']);
        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';
        $res = $this->call('verifyCode', ['phone' => $this->phone, 'code' => $wrong]);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('bad_code', json_encode($res->messages));
    }

    // ----- global uniqueness (login-by-phone needs one number → one user) -----

    #[Test]
    public function a_number_verified_on_another_account_cannot_be_enrolled(): void
    {
        $this->enroll();   // this user verifies $this->phone

        // A second user tries the same number.
        $otherId = (new Tiger_Model_User())->insert(['email' => 'other@w376.test', 'status' => 'active']);
        $this->login($otherId, 'org-sms', 'user');
        CapturingSmsAdapter::$sent = [];

        $res = $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('in_use', json_encode($res->messages));
        $this->assertCount(0, CapturingSmsAdapter::$sent, 'no code is sent for a taken number');
    }

    // ----- remove -------------------------------------------------------------

    #[Test]
    public function remove_unlinks_the_number_and_the_consent(): void
    {
        $this->enroll();
        $res = $this->call('remove');
        $this->assertSame(1, (int) $res->result);
        $this->assertFalse((bool) $res->data['sms']['verified']);

        $this->assertNull((new Tiger_Model_UserCredential())->findVerifiedByIdentifier(Tiger_Model_UserCredential::TYPE_SMS, $this->phone));
        $this->assertNull((new Tiger_Model_SmsConsent())->forUser($this->userId));
    }

    #[Test]
    public function removing_frees_the_number_for_another_account(): void
    {
        $this->enroll();
        $this->call('remove');

        // Now a different user can enroll it.
        $otherId = (new Tiger_Model_User())->insert(['email' => 'reuse@w376.test', 'status' => 'active']);
        $this->login($otherId, 'org-sms', 'user');
        CapturingSmsAdapter::$sent = [];
        $this->assertSame(1, (int) $this->call('requestCode', ['phone' => $this->phone, 'consent' => '1'])->result);
        $this->assertSame(1, (int) $this->call('verifyCode', ['phone' => $this->phone, 'code' => $this->lastCode()])->result);
        $owner = (new Tiger_Model_UserCredential())->findVerifiedByIdentifier(Tiger_Model_UserCredential::TYPE_SMS, $this->phone);
        $this->assertSame($otherId, (string) $owner->user_id);
    }
}
