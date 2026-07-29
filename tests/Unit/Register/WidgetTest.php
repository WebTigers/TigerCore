<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Register;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Register_Widget_Registration;
use Zend_Session;

/**
 * Register_Widget_Registration — the dashboard widget body. With no registration state it renders the
 * "register" step (email + button) and its self-contained /api poster; it never shows the verified state.
 * No DB (state fail-safes to "nothing done"); session test-mode so the form's CSRF hash resolves in CLI.
 */
#[CoversClass(Register_Widget_Registration::class)]
final class WidgetTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Session::$_unitTestEnabled = true;
    }

    #[Test]
    public function it_renders_the_register_step_when_nothing_is_done(): void
    {
        $html = (new Register_Widget_Registration())->render();

        $this->assertStringContainsString('data-reg="email"', $html, 'the email field');
        $this->assertStringContainsString('data-reg="register"', $html, 'the register button');
        $this->assertStringContainsString('"register"', $html, 'the /api service is named in the JS');
        $this->assertStringNotContainsString('fa-circle-check', $html, 'not the verified state');
    }
}
