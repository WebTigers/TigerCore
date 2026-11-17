<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Register;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Register_Form_Action;
use Register_Form_Register;
use Register_Service_Registration;
use Tiger\Tests\Support\IntegrationTestCase;
use Zend_Registry;
use Zend_Session;

/**
 * The parameterless registration writes carry a CSRF token (TIGER-528).
 *
 * verifyDomain calls the registry and rewrites stored state; resendEmail mints a token and sends mail.
 * Neither takes input, so neither had a form — and therefore no CSRF. They now validate a token-only
 * form (Register_Form_Action) on the module's shared salt. Pinned: a tokenless cookie-mode call is
 * refused before anything runs; the token the Settings screen / widget renders gets past the gate (the
 * service then answers on its own terms — here "not registered", since the harness has no registration).
 */
#[CoversClass(Register_Service_Registration::class)]
#[CoversClass(Register_Form_Action::class)]
final class RegistrationCsrfTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    /** Run $fn as a browser session would: CSRF applies, against an array-backed session. */
    private function cookieMode(callable $fn)
    {
        Zend_Registry::set('tiger.auth.stateless', false);
        $prior = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        // A request always has a writable session; an earlier test may have write-closed it.
        $w = new \ReflectionProperty(\Zend_Session_Abstract::class, '_writable');
        $priorW = (bool) $w->getValue();
        $w->setValue(null, true);
        try {
            return $fn();
        } finally {
            Zend_Registry::set('tiger.auth.stateless', true);
            Zend_Session::$_unitTestEnabled = $prior;
            $_SESSION = [];
            $w->setValue(null, $priorW);
        }
    }

    #[Test]
    public function verify_and_resend_refuse_a_tokenless_cross_site_request(): void
    {
        $this->loginAs('admin');
        $this->cookieMode(function () {
            foreach (['verifyDomain', 'resendEmail'] as $action) {
                $res = (new Register_Service_Registration(['action' => $action]))->getResponse();
                $this->assertSame(0, (int) $res->result, "$action without a token is refused");
                $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$action: the CSRF refusal");
            }
        });
    }

    #[Test]
    public function the_screens_token_gets_past_the_gate(): void
    {
        $this->loginAs('admin');
        $msgs = $this->cookieMode(function () {
            // The token the screen renders comes from Register_Form_Register — same salt as the action form.
            $el = (new Register_Form_Register())->getElement('_csrf');
            $el->initCsrfToken();
            $tok = (string) $el->getHash();
            $out = [];
            foreach (['verifyDomain', 'resendEmail'] as $action) {
                $out[$action] = json_encode((new Register_Service_Registration(['action' => $action, '_csrf' => $tok]))->getResponse()->messages);
            }
            return $out;
        });
        foreach ($msgs as $action => $json) {
            $this->assertStringNotContainsString('core.api.error.csrf', $json, "$action: the token is accepted");
            $this->assertStringContainsString('register.error.not_registered', $json, "$action: the service ran its own checks");
        }
    }
}
