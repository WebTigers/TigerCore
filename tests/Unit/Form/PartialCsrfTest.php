<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Form;
use Zend_Session;

/**
 * Tiger_Form::isValidPartial() — a sparse PATCH still has to carry the CSRF token.
 *
 * Zend_Form::isValidPartial() validates only the keys PRESENT in the data (its loop body is guarded
 * by isset($check[$key])), and `_csrf` is a key like any other — so on the stock method a caller who
 * simply OMITS the token is never checked for it and the CSRF gate becomes opt-out. The override
 * force-presents every hash element before delegating, which puts the token back in the validated
 * set without demanding the data fields a partial validation is there to leave out.
 *
 * Tested the way the request actually flows: arm the token through the form the VIEW renders, then
 * validate through a FRESHLY CONSTRUCTED form, because Zend_Form_Element_Hash wires its Identical
 * validator in its CONSTRUCTOR from the session namespace. (getHash() mints a fresh random value per
 * instance and never reads the session, so comparing hashes across instances measures nothing.)
 *
 * Runs in its own process: Zend_Session's array-backed unit-test mode is global state.
 */
#[CoversClass(Tiger_Form::class)]
final class PartialCsrfTest extends UnitTestCase
{
    /** Arm a token in the session and return the value a caller would post back. */
    private function armToken(): string
    {
        $armed = new PartialCsrfTestForm();
        $armed->getElement('_csrf')->initCsrfToken();

        return (string) $armed->getElement('_csrf')->getHash();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function partialValidationRefusesAWriteThatOmitsTheToken(): void
    {
        Zend_Session::$_unitTestEnabled = true;
        $this->armToken();

        $form = new PartialCsrfTestForm();

        // The sparse PATCH an attacker's page can mount: one field, no token.
        $this->assertFalse(
            $form->isValidPartial(['subject' => 'changed']),
            'isValidPartial() must still demand the CSRF token — omitting it is not consent'
        );
        $this->assertArrayHasKey(
            '_csrf',
            $form->getMessages(),
            'the refusal is the token, so the service can report it as a CSRF failure'
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function partialValidationStillLeavesAbsentDATAFieldsAlone(): void
    {
        Zend_Session::$_unitTestEnabled = true;
        $token = $this->armToken();

        $form = new PartialCsrfTestForm();

        // `priority` is REQUIRED yet absent — the whole point of a partial validation. Only the
        // hash element is force-presented, so this must still pass.
        $this->assertTrue(
            $form->isValidPartial(['_csrf' => $token, 'subject' => 'changed']),
            'a required DATA field may be absent from a PATCH; only the token is mandatory'
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function partialValidationRefusesAForgedToken(): void
    {
        Zend_Session::$_unitTestEnabled = true;
        $this->armToken();

        $form = new PartialCsrfTestForm();

        $this->assertFalse(
            $form->isValidPartial(['_csrf' => 'not-the-armed-token', 'subject' => 'changed']),
            'a guessed token is refused — presence is not the check, identity is'
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function partialValidationPassesOnAFormThatDisabledCsrf(): void
    {
        Zend_Session::$_unitTestEnabled = true;

        $form = new PartialCsrfTestNoCsrfForm();

        // Nothing to force-present, so the override is a no-op and a sparse PATCH works as ZF1
        // intends. This is the idempotent search/filter-form case.
        $this->assertTrue(
            $form->isValidPartial(['subject' => 'changed']),
            'a form that opted out of CSRF has no hash element to demand'
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function fullValidationIsUnchangedByTheOverride(): void
    {
        Zend_Session::$_unitTestEnabled = true;
        $token = $this->armToken();

        $form = new PartialCsrfTestForm();

        $this->assertFalse(
            $form->isValid(['_csrf' => $token, 'subject' => 'changed']),
            'full isValid() still demands a required field — the override touches only the partial path'
        );
    }
}

/** A CSRF-protected form with one optional and one required data field. */
final class PartialCsrfTestForm extends Tiger_Form
{
    protected function csrfSalt(): string
    {
        return 'PartialCsrfTest';
    }

    protected function elements(): array
    {
        return [
            ['text', 'subject',  ['required' => false]],
            ['text', 'priority', ['required' => true]],
        ];
    }
}

/** The same form with CSRF deliberately off (an idempotent search/filter form). */
final class PartialCsrfTestNoCsrfForm extends Tiger_Form
{
    protected function csrf(): bool
    {
        return false;
    }

    protected function elements(): array
    {
        return [
            ['text', 'subject', ['required' => false]],
        ];
    }
}
