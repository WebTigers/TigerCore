<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Profile_Form_Password;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Tiger_Service_Validate;
use Zend_Auth;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-150 — the change-password field must fail LIVE (on blur) for the same reasons it fails at
 * submit: a password shorter than the (now 12-char) minimum, and a password the user has used before.
 *
 * The live path is the real one the browser hits: tiger.validate.js (blur) -> /api ->
 * Tiger_Service_Validate::field -> Profile_Form_Password::convenienceValidate -> the new_password
 * element's Tiger_Validate_Password. These tests drive that exact server entry point (not the submit
 * service) and prove both checks fire. The reuse check needs the acting user's context; before the fix
 * the convenience service built the form with a bare `new $class()` and no user, so reuse never fired
 * on blur — the last assertion pins that the current identity is what makes it fire.
 */
#[CoversClass(Tiger_Service_Validate::class)]
#[CoversClass(Profile_Form_Password::class)]
final class PasswordLiveValidationTest extends IntegrationTestCase
{
    private const REUSED = 'S3cure-P@ssw0rd-9x';   // 18 chars — clears the min so reuse is isolated
    private const FRESH  = 'Br@nd-New-P4ss-2x';    // 17 chars — never used, passes everything

    private mixed $priorConfig = null;
    private bool $hadConfig = false;

    protected function setUp(): void
    {
        parent::setUp();
        // No CSRF/session when the form is built off-request (matches the profile Security service).
        Zend_Registry::set('tiger.auth.stateless', true);

        $reg              = Zend_Registry::getInstance();
        $this->hadConfig  = $reg->offsetExists('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
        // The policy the change-password field enforces: a 12-char minimum + reuse-prevention.
        Zend_Registry::set('Zend_Config', new Zend_Config(
            ['tiger' => ['password' => ['min_length' => 12, 'history' => 5]]],
            true
        ));
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        if ($this->hadConfig) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    /** The exact live/blur entry point: convenience validation of one field of the change-password form. */
    private function liveField(string $value): array
    {
        return (array) (new Tiger_Service_Validate([
            'action'      => 'field',
            'form_module' => 'profile',
            'form'        => 'Password',
            'field'       => 'new_password',
            'value'       => $value,
        ]))->getResponse()->data;
    }

    #[Test]
    public function a_password_shorter_than_the_minimum_fails_on_the_live_path(): void
    {
        $id = (new Tiger_Model_User())->insert(['email' => 'short@t150.test', 'status' => 'active']);
        $this->login($id, 'org-test', 'user');

        // 11 chars, otherwise strong: clears the OLD 8-char minimum, fails the NEW 12-char one.
        $d = $this->liveField('Sh0rt-P@ss1');
        $this->assertFalse($d['valid'], 'a sub-minimum password is rejected on blur, not only at submit');
        $this->assertNotSame('', $d['message'], 'the policy message is surfaced inline');

        // Control: a long, strong, unused password passes the live path.
        $this->assertTrue($this->liveField(self::FRESH)['valid'], 'a compliant password is accepted live');
    }

    #[Test]
    public function a_reused_password_fails_on_the_live_path_with_the_users_context(): void
    {
        $id = (new Tiger_Model_User())->insert(['email' => 'reuse@t150.test', 'status' => 'active']);
        // Give the user a current password (>= min so length isn't what trips it).
        (new Tiger_Model_UserCredential())->setPassword($id, self::REUSED);

        $this->login($id, 'org-test', 'user');
        $d = $this->liveField(self::REUSED);
        $this->assertFalse($d['valid'], 'the current password is caught as reuse on blur');
        $this->assertNotSame('', $d['message'], 'the reuse message is surfaced inline');

        // And a fresh compliant password still passes for this same logged-in user.
        $this->assertTrue($this->liveField(self::FRESH)['valid'], 'an unused compliant password is accepted live');
    }

    #[Test]
    public function reuse_is_caught_on_the_live_path_only_because_the_current_user_is_threaded_through(): void
    {
        $id = (new Tiger_Model_User())->insert(['email' => 'ctx@t150.test', 'status' => 'active']);
        (new Tiger_Model_UserCredential())->setPassword($id, self::REUSED);

        // No identity → the convenience path can't scope reuse to anyone, so the SAME reused password
        // reads as valid (length-only). This is exactly the pre-fix behavior for the logged-in case,
        // and proves the fix is the user context now threaded into the form on the blur path.
        Zend_Auth::getInstance()->clearIdentity();
        $this->assertTrue($this->liveField(self::REUSED)['valid'], 'without an identity reuse is not — cannot be — checked');

        // Log the user in and the very same value is now caught as reuse.
        $this->login($id, 'org-test', 'user');
        $this->assertFalse($this->liveField(self::REUSED)['valid'], 'with the user threaded through, reuse fires on blur');
    }
}
