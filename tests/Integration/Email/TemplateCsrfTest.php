<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Email;

use Email_Form_Reset;
use Email_Service_Template;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail;
use Tiger_Model_EmailTemplate;
use Zend_Registry;
use Zend_Session;

/**
 * Email template reset carries a CSRF token (TIGER-528).
 *
 * `reset` deletes an org's authored override, and it had no form — so any page could make a signed-in
 * admin's browser wipe a customized transactional email. Pinned: a tokenless cookie-mode reset is
 * refused and the override survives; the list/editor's token (Email_Form_Write's shared salt) lets it
 * through. Stateless (Bearer) calls stay CSRF-free by design.
 */
#[CoversClass(Email_Service_Template::class)]
#[CoversClass(Email_Form_Reset::class)]
final class TemplateCsrfTest extends IntegrationTestCase
{
    private const KEY = 'reset';

    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Mail::registerTemplate(['key' => self::KEY, 'label' => 'Reset']);
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

    /** Author an override at the signed-in admin's org (the tier the screen edits). */
    private function seedOverride(): void
    {
        $res = (new Email_Service_Template([
            'action' => 'save', 'template_key' => self::KEY, 'subject' => 'Customized', 'body' => '<p>ours</p>',
        ]))->getResponse();
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
    }

    private function overrideSubject(): string
    {
        $row = (new Tiger_Model_EmailTemplate())->byKey(self::KEY, \Tiger_Model_Config::settingScope()[1]);
        return $row ? (string) $row->subject : '';
    }

    #[Test]
    public function reset_refuses_a_tokenless_cross_site_request(): void
    {
        $this->loginAs('admin');
        $this->seedOverride();

        $res = $this->cookieMode(fn () => (new Email_Service_Template(['action' => 'reset', 'key' => self::KEY]))->getResponse());

        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages));
        $this->assertSame('Customized', $this->overrideSubject(), 'the override survives');
    }

    #[Test]
    public function reset_with_the_list_token_goes_through(): void
    {
        $this->loginAs('admin');
        $this->seedOverride();

        $res = $this->cookieMode(function () {
            $el = (new Email_Form_Reset())->getElement('_csrf');   // the token the list renders
            $el->initCsrfToken();
            return (new Email_Service_Template(['action' => 'reset', 'key' => self::KEY, '_csrf' => (string) $el->getHash()]))->getResponse();
        });

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame('', $this->overrideSubject(), 'the override is gone');
    }
}
