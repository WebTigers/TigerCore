<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Profile_Form_Address;
use Profile_Form_Sms;
use Profile_Form_UserProfile;
use Profile_Service_Address;
use Profile_Service_Avatar;
use Profile_Service_Contact;
use Profile_Service_OrgAddress;
use Profile_Service_OrgContact;
use Profile_Service_OrgLogo;
use Profile_Service_Sms;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Option;
use Tiger_Model_User;
use Zend_Registry;
use Zend_Session;
use Zend_View;

/**
 * TIGER-528: the profile writes that had no form — avatar + org-logo upload/remove, the four link deletes and
 * the SMS steps — validate through a Tiger_Form on the browser (cookie-session) path. The deletes and SMS used
 * a hand-rolled `_validCsrf()` (which failed OPEN on any exception); the avatar/logo endpoints had no check at
 * all, so any page could make a signed-in user's browser drop their avatar or swap in an uploaded image.
 */
#[CoversClass(Profile_Service_Avatar::class)]
#[CoversClass(Profile_Service_OrgLogo::class)]
#[CoversClass(Profile_Service_Address::class)]
#[CoversClass(Profile_Service_Contact::class)]
#[CoversClass(Profile_Service_Sms::class)]
final class ProfileCsrfTest extends IntegrationTestCase
{
    private bool $priorUnitTest = false;
    private string $userId = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTest = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        Zend_Registry::set('tiger.auth.stateless', false);   // cookie mode: CSRF applies
        $this->userId = (string) (new Tiger_Model_User())->insert(['email' => 'csrf@w4test.com', 'status' => 'active']);
        $this->login($this->userId, 'org-test', 'admin');
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Zend_Session::$_unitTestEnabled = $this->priorUnitTest;
        parent::tearDown();
    }

    /** A token as the named form renders it on the page. */
    private function token(string $formClass): string
    {
        $el = (new $formClass())->getElement('_csrf');
        $el->setView(new Zend_View());
        $el->render();
        return (string) $el->getValue();
    }

    private function refused(object $res, string $what): void
    {
        $this->assertSame(0, (int) $res->result, "$what without a token is refused");
        $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$what: the CSRF refusal");
    }

    #[Test]
    public function avatar_remove_needs_the_basic_tab_token(): void
    {
        $opt = new Tiger_Model_Option();
        $opt->set(Tiger_Model_Option::SCOPE_USER, $this->userId, Profile_Service_Avatar::OPTION_KEY, 'media-keep');

        $svc = new Profile_Service_Avatar();
        $svc->remove([]);
        $this->refused($svc->getResponse(), 'avatar/remove');
        $this->assertSame('media-keep', $opt->get(Tiger_Model_Option::SCOPE_USER, $this->userId, Profile_Service_Avatar::OPTION_KEY));

        $svc = new Profile_Service_Avatar();
        $svc->upload([]);
        $this->refused($svc->getResponse(), 'avatar/upload');

        $svc = new Profile_Service_Avatar();
        $svc->remove(['_csrf' => $this->token(Profile_Form_UserProfile::class)]);
        $this->assertSame(1, (int) $svc->getResponse()->result, 'the Basic tab token (shared salt) is accepted');
    }

    #[Test]
    public function org_logo_writes_refuse_a_tokenless_request(): void
    {
        foreach (['upload', 'remove'] as $m) {
            $svc = new Profile_Service_OrgLogo();
            $svc->$m([]);
            $this->refused($svc->getResponse(), "orglogo/$m");
        }
    }

    #[Test]
    public function the_link_deletes_refuse_a_tokenless_request(): void
    {
        foreach ([Profile_Service_Address::class, Profile_Service_Contact::class,
                  Profile_Service_OrgAddress::class, Profile_Service_OrgContact::class] as $class) {
            $svc = new $class();
            $svc->delete(['link_id' => 'some-link']);
            $this->refused($svc->getResponse(), "$class::delete");
        }

        // The Addresses card's token passes the gate (the delete then fails on ownership, not on CSRF).
        $svc = new Profile_Service_Address();
        $svc->delete(['link_id' => 'not-mine', '_csrf' => $this->token(Profile_Form_Address::class)]);
        $this->assertStringNotContainsString('core.api.error.csrf', json_encode($svc->getResponse()->messages));
    }

    #[Test]
    public function the_sms_steps_refuse_a_tokenless_request(): void
    {
        $calls = [
            'requestCode' => ['phone' => '+15555550123', 'consent' => '1'],
            'verifyCode'  => ['phone' => '+15555550123', 'code' => '123456'],
            'remove'      => [],
        ];
        foreach ($calls as $m => $args) {
            $svc = new Profile_Service_Sms();
            $svc->$m($args);
            $this->refused($svc->getResponse(), "sms/$m");
        }

        // With the card's token, requestCode gets past the gate to its own rules (consent is required).
        $svc = new Profile_Service_Sms();
        $svc->requestCode(['phone' => '+15555550123', '_csrf' => $this->token(Profile_Form_Sms::class)]);
        $this->assertStringContainsString('profile.sms.consent_required', json_encode($svc->getResponse()->messages));
    }
}
