<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Form;
use Tiger_Form_Element_Hash;
use Zend_Registry;
use Zend_Session;

/**
 * CSRF across several open pages that share one salt — real Tiger_Form objects on a real (unit-test
 * mode) session.
 *
 * Every render used to overwrite the salt's session token, so opening a second page that renders a
 * same-salt form broke the first page at once. Pinned: earlier tokens stay valid until timeout, a
 * failed-then-corrected submit still works, and expired, forged and other-salt tokens still fail.
 */
#[CoversClass(Tiger_Form_Element_Hash::class)]
final class CsrfMultiTabTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        Zend_Registry::set('tiger.auth.stateless', false);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Zend_Registry::set('tiger.auth.stateless', true);
        parent::tearDown();
    }

    /** A one-field form on the given salt. */
    private function form(string $salt): Tiger_Form
    {
        return new class (['csrfSaltName' => $salt]) extends Tiger_Form {
            private string $salt = 'shared';
            public function setCsrfSaltName($s): void { $this->salt = (string) $s; }
            protected function csrfSalt(): string { return $this->salt; }
            protected function elements(): array
            {
                return [['text', 'title', ['required' => true, 'filters' => ['StringTrim']]]];
            }
        };
    }

    /** Render a form's token the way the view does (render() arms the session), and return it. */
    private function render(Tiger_Form $form): string
    {
        $el = $form->getElement('_csrf');
        $el->initCsrfToken();
        return (string) $el->getHash();
    }

    #[Test]
    public function aSecondPageOnTheSameSaltDoesNotBreakTheFirst(): void
    {
        $tokenA = $this->render($this->form('shared'));
        $tokenB = $this->render($this->form('shared'));

        $this->assertTrue($this->form('shared')->isValid(['title' => 'x', '_csrf' => $tokenA]), 'page A still submits');
        $this->assertTrue($this->form('shared')->isValid(['title' => 'x', '_csrf' => $tokenB]), 'page B submits');
    }

    #[Test]
    public function aFailedSubmitCanBeCorrectedAndResubmitted(): void
    {
        $token = $this->render($this->form('shared'));

        $this->assertFalse($this->form('shared')->isValid(['title' => '', '_csrf' => $token]), 'a field error');
        $this->assertTrue($this->form('shared')->isValid(['title' => 'fixed', '_csrf' => $token]), 'same token, corrected');
    }

    #[Test]
    public function forgedOtherSaltAndExpiredTokensStillFail(): void
    {
        $token = $this->render($this->form('shared'));
        $other = $this->render($this->form('elsewhere'));

        $this->assertFalse($this->form('shared')->isValid(['title' => 'x', '_csrf' => 'forged']), 'forged');
        $this->assertFalse($this->form('shared')->isValid(['title' => 'x', '_csrf' => $other]), 'another salt');
        $this->assertFalse($this->form('elsewhere')->isValid(['title' => 'x', '_csrf' => $token]), 'and the reverse');

        // Expiry: once the session drops the salt's namespace, the old token is gone for good and the
        // next render mints a different one.
        foreach (array_keys($_SESSION) as $ns) {
            if (str_contains((string) $ns, 'shared')) { unset($_SESSION[$ns]); }
        }
        $this->assertFalse($this->form('shared')->isValid(['title' => 'x', '_csrf' => $token]), 'expired');
        $this->assertNotSame($token, $this->render($this->form('shared')), 'a fresh token after expiry');
    }
}
