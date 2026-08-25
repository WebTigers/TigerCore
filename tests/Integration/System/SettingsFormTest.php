<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Form_Settings;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail_Provider;

/**
 * System_Form_Settings — the declarative schema behind the System settings screen.
 *
 * The form is one big `elements()` array, and a typo in it fails at RENDER time on a live admin
 * screen rather than anywhere a unit test would normally look. Instantiating it is therefore the
 * test: every element gets built, every validator is constructed, and the provider dropdown is
 * populated from the live catalog.
 *
 * The Email SMTP fields get the most attention because they're the ones that can quietly do damage
 * — a mistyped port validator would let an unusable value reach the transport, and the provider
 * dropdown falling out of sync with `Tiger_Mail_Provider` would offer providers that can't be saved.
 */
#[CoversClass(System_Form_Settings::class)]
final class SettingsFormTest extends IntegrationTestCase
{
    private System_Form_Settings $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = new System_Form_Settings();
    }

    #[Test]
    public function the_form_builds_and_declares_every_screen_field(): void
    {
        foreach ([
            'session_ttl', 'session_ttl_guest', 'session_ttl_privileged',
            'autologout_enabled', 'autologout_seconds', 'autologout_action',
            'mail_provider', 'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_ssl', 'mail_smtp_auth',
            'mail_smtp_username', 'mail_smtp_password', 'mail_from_email', 'mail_from_name', 'mail_test_to',
            'recaptcha_enabled', 'recaptcha_version', 'recaptcha_site_key', 'recaptcha_secret_key',
        ] as $name) {
            $this->assertNotNull($this->form->getElement($name), "the form declares $name");
        }
    }

    #[Test]
    public function the_provider_dropdown_matches_the_live_catalog(): void
    {
        $options = $this->form->getElement('mail_provider')->getMultiOptions();

        $this->assertSame(array_keys(Tiger_Mail_Provider::options()), array_keys($options),
            'the dropdown is generated from the catalog, so the two can never drift apart');
        $this->assertArrayHasKey('ses-api', $options, 'the API providers are offered');
        $this->assertArrayHasKey('ses-smtp', $options, 'alongside their SMTP counterparts');
    }

    #[Test]
    public function the_smtp_password_field_is_a_password_input(): void
    {
        // A text input would render the stored secret into the page source.
        $this->assertSame('password', strtolower($this->form->getElement('mail_smtp_password')->getType() === 'Zend_Form_Element_Password' ? 'password' : 'other'),
            'the SMTP password is never a plain text field');
        $this->assertSame('Zend_Form_Element_Password', get_class($this->form->getElement('recaptcha_secret_key')),
            'and neither is the reCAPTCHA secret');
    }

    #[Test]
    public function an_out_of_range_port_is_rejected(): void
    {
        $port = $this->form->getElement('mail_smtp_port');

        $this->assertTrue($port->isValid('587'), 'a normal submission port passes');
        $this->assertTrue($port->isValid('465'), 'so does implicit-SSL');
        $this->assertFalse($port->isValid('99999'), 'a port above the TCP range is refused at the form');
        $this->assertFalse($port->isValid('smtp'), 'and a non-numeric port never reaches the transport');
    }

    #[Test]
    public function the_from_and_test_addresses_must_be_real_email_addresses(): void
    {
        $from = $this->form->getElement('mail_from_email');

        $this->assertTrue($from->isValid('no-reply@example.com'), 'an ordinary address passes');
        $this->assertFalse($from->isValid('not-an-address'),
            'a malformed From address is caught before it reaches the transport');
        $this->assertFalse($this->form->getElement('mail_test_to')->isValid('nope'),
            'and the test recipient is validated the same way');
    }

    #[Test]
    public function a_local_hostname_sender_is_allowed(): void
    {
        // core.ini ships `mail.from.email = no-reply@localhost`, and an intranet install genuinely
        // sends from a local hostname — DNS-only validation would reject the platform's own default.
        $this->assertTrue($this->form->getElement('mail_from_email')->isValid('no-reply@localhost'),
            'the shipped default From address must be saveable');
    }

    #[Test]
    public function optional_mail_fields_stay_optional(): void
    {
        // The whole SMTP block is irrelevant to the sendmail transport, so nothing here may be
        // required — otherwise saving an unrelated tab (reCAPTCHA, cookies) would fail validation.
        foreach (['mail_smtp_host', 'mail_smtp_port', 'mail_smtp_username', 'mail_smtp_password',
                  'mail_from_email', 'mail_from_name', 'mail_test_to'] as $name) {
            $this->assertFalse($this->form->getElement($name)->isRequired(), "$name is optional");
            $this->assertTrue($this->form->getElement($name)->isValid(''), "$name accepts empty");
        }
    }
}
