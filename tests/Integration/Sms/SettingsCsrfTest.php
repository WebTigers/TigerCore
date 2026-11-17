<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Sms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sms_Form_Settings;
use Sms_Service_Settings;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Zend_Registry;
use Zend_Session;

/**
 * The SMS settings writes carry a CSRF token (TIGER-528).
 *
 * `save` stores provider SECRETS and `test` sends a paid message to a number of the caller's choosing;
 * neither had a form, so any page could drive them through a signed-in admin's browser. Pinned: a
 * tokenless cookie-mode call is refused and writes nothing; the screen's token (Sms_Form_Write's shared
 * salt) lets save through. The form declares every registered adapter's fields, discovered the way the
 * service discovers them.
 */
#[CoversClass(Sms_Service_Settings::class)]
#[CoversClass(Sms_Form_Settings::class)]
final class SettingsCsrfTest extends IntegrationTestCase
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

    private function brand(): string
    {
        return (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.sms.brand');
    }

    #[Test]
    public function save_and_test_refuse_a_tokenless_cross_site_request(): void
    {
        $this->loginAs('admin');
        $before = $this->brand();

        $this->cookieMode(function () {
            foreach ([
                'save' => ['brand' => 'Hijacked', 'provider' => ''],
                'test' => ['to' => '+15555550100', 'message' => 'spam'],
            ] as $action => $params) {
                $res = (new Sms_Service_Settings(['action' => $action] + $params))->getResponse();
                $this->assertSame(0, (int) $res->result, "$action without a token is refused");
                $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$action: the CSRF refusal");
            }
        });

        $this->assertSame($before, $this->brand(), 'nothing written');
    }

    #[Test]
    public function save_with_the_screen_token_goes_through(): void
    {
        $this->loginAs('admin');
        $res = $this->cookieMode(function () {
            $el = (new Sms_Form_Settings())->getElement('_csrf');   // the token the settings view renders
            $el->initCsrfToken();
            return (new Sms_Service_Settings(['action' => 'save', 'brand' => 'Acme', 'provider' => '', '_csrf' => (string) $el->getHash()]))->getResponse();
        });
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame('Acme', $this->brand());
    }

    #[Test]
    public function the_form_declares_every_registered_adapters_fields(): void
    {
        $form = new Sms_Form_Settings();
        foreach (\Tiger_Sms::providers() as $provider) {
            $class = 'Tiger_Sms_Adapter_' . ucfirst((string) $provider);
            if (!class_exists($class)) { continue; }
            foreach ((new $class())->fields() as $f) {
                $this->assertNotNull($form->getElement((string) $f['key']), "$provider field {$f['key']} is declared");
            }
        }
        $this->assertNotNull($form->getElement('brand'));
        $this->assertNotNull($form->getElement('provider'));
    }
}
