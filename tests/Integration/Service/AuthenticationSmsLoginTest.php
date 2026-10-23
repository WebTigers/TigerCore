<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_AuthChallenge;
use Tiger_Model_SmsConsent;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Tiger_Service_Authentication;
use Zend_Auth;
use Zend_Auth_Storage_Session;
use Zend_Session;

/**
 * Tiger_Service_Authentication — passwordless login BY PHONE (sms_otp), TIGER-376.
 *
 * requestLoginCodeSms resolves a user from a VERIFIED, globally-unique `sms` factor, issues an sms_otp
 * challenge, and is silent (no enumeration) for an unknown or STOP-suppressed number. verifyLoginCodeSms
 * reuses the channel-agnostic _completeCodeLogin: the right code signs in, a wrong one fails.
 */
#[CoversClass(Tiger_Service_Authentication::class)]
final class AuthenticationSmsLoginTest extends IntegrationTestCase
{
    private Tiger_Service_Authentication $auth;
    private $priorUnitTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        // _finishLogin regenerates the session id (fixation guard); use Zend's array-backed unit-test
        // session mode so that runs without an active PHP session (else a warning fails the suite).
        $this->priorUnitTestMode = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_Session());
        Zend_Auth::getInstance()->clearIdentity();
        $this->auth = new Tiger_Service_Authentication();
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $_SESSION = [];
        Zend_Session::$_unitTestEnabled = $this->priorUnitTestMode;
        parent::tearDown();
    }

    /** Create an active user with a VERIFIED sms factor for $e164; returns the user_id. */
    private function userWithVerifiedPhone(string $email, string $e164): string
    {
        $uid  = (new Tiger_Model_User())->insert(['email' => $email, 'status' => 'active']);
        $cred = new Tiger_Model_UserCredential();
        $cid  = $cred->addSms($uid, $e164);
        $cred->markVerified($cid);
        return $uid;
    }

    #[Test]
    public function request_issues_a_code_for_a_verified_number(): void
    {
        $uid = $this->userWithVerifiedPhone('p1@w376.test', '+14075550201');
        $this->auth->requestLoginCodeSms('(407) 555-0201');   // any form → E.164 normalized
        $this->assertSame(1, (new Tiger_Model_AuthChallenge())->countRecent($uid, 'sms_otp', 3600));
    }

    #[Test]
    public function request_is_silent_for_an_unknown_number(): void
    {
        $uid = $this->userWithVerifiedPhone('p2@w376.test', '+14075550202');
        $this->auth->requestLoginCodeSms('+14075559999');   // not enrolled anywhere
        $this->assertSame(0, (new Tiger_Model_AuthChallenge())->countRecent($uid, 'sms_otp', 3600));
    }

    #[Test]
    public function request_is_silent_for_a_suppressed_number(): void
    {
        $phone = '+14075550203';
        $uid = $this->userWithVerifiedPhone('p3@w376.test', $phone);
        $consent = new Tiger_Model_SmsConsent();
        $consent->record($uid, $phone, [Tiger_Model_SmsConsent::SCOPE_TWOFA], 't', null);
        $consent->optOutByPhone($phone);   // the number replied STOP

        $this->auth->requestLoginCodeSms($phone);
        $this->assertSame(0, (new Tiger_Model_AuthChallenge())->countRecent($uid, 'sms_otp', 3600), 'a STOP number is never texted a login code');
    }

    #[Test]
    public function verify_signs_in_with_the_right_code_and_rejects_a_wrong_one(): void
    {
        $phone = '+14075550204';
        $uid = $this->userWithVerifiedPhone('p4@w376.test', $phone);
        (new Tiger_Model_AuthChallenge())->issue($uid, 'sms_otp', '424242', 600);   // known code (seeded; real one is hashed)

        $this->assertFalse($this->auth->verifyLoginCodeSms($phone, '000000'), 'a wrong code fails');

        $identity = $this->auth->verifyLoginCodeSms($phone, '424242');
        $this->assertIsObject($identity, 'the right code signs in by phone');
        $this->assertSame($uid, $identity->user_id);
    }

    #[Test]
    public function verify_fails_for_a_number_with_no_verified_factor(): void
    {
        $this->assertFalse($this->auth->verifyLoginCodeSms('+14075550205', '424242'));
    }
}
