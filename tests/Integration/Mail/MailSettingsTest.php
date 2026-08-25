<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto;
use Tiger_Mail;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Mail_Transport_Sendmail;
use Zend_Mail_Transport_Smtp;
use Zend_Registry;

/**
 * Tiger_Mail's settings surface — what the System → Email SMTP admin screen reads and writes.
 *
 * Three things are load-bearing and each is pinned here. (1) The SMTP password is encrypted at
 * rest but a LEGACY plaintext `mail.smtp.password` must keep working, because installs configured
 * before the admin screen existed have one — an upgrade that silently broke outgoing mail would be
 * the worst possible failure, since a dead MTA is invisible (the password-reset flow reveals
 * nothing by design). (2) A blank password on save KEEPS the stored one, so editing the host can't
 * wipe the secret. (3) `settings()` never returns the password, so the UI can't read it back out.
 */
#[CoversClass(Tiger_Mail::class)]
final class MailSettingsTest extends IntegrationTestCase
{
    private const KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAo=';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedConfig([]);
    }

    /** Put a resolved config into the registry, the way the booted app would. */
    private function seedConfig(array $mail, ?string $cryptoKey = null): void
    {
        $tiger = [];
        if ($cryptoKey !== null) { $tiger['crypto'] = ['key' => $cryptoKey]; }
        Zend_Registry::set('Zend_Config', new Zend_Config(['mail' => $mail, 'tiger' => $tiger], true));
    }

    #[Test]
    public function transport_falls_back_to_sendmail_without_smtp(): void
    {
        $this->seedConfig(['transport' => 'mail']);
        $this->assertInstanceOf(Zend_Mail_Transport_Sendmail::class, (new Tiger_Mail())->transport(),
            'the default transport is PHP mail()');

        // smtp selected but no host is not a usable SMTP config — fall back rather than fatal.
        $this->seedConfig(['transport' => 'smtp', 'smtp' => ['host' => '']]);
        $this->assertInstanceOf(Zend_Mail_Transport_Sendmail::class, (new Tiger_Mail())->transport(),
            'smtp with no host degrades to sendmail instead of erroring');
    }

    #[Test]
    public function a_legacy_plaintext_password_still_builds_an_smtp_transport(): void
    {
        $this->seedConfig(['transport' => 'smtp', 'smtp' => [
            'host' => 'smtp.example.test', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login',
            'username' => 'user', 'password' => 'legacy-secret',
        ]]);
        $this->assertInstanceOf(Zend_Mail_Transport_Smtp::class, (new Tiger_Mail())->transport(),
            'an install predating the admin screen keeps working on its plaintext password');
    }

    #[Test]
    public function an_encrypted_password_is_decrypted_and_wins_over_the_plaintext_key(): void
    {
        if (!Tiger_Crypto::isConfigured()) {
            $this->seedConfig([], self::KEY);
        }
        $this->seedConfig(['transport' => 'smtp', 'smtp' => [
            'host' => 'smtp.example.test', 'auth' => 'login', 'username' => 'u',
            'password'     => 'stale-plaintext',
            'password_enc' => Tiger_Crypto::encrypt('the-real-secret'),
        ]], self::KEY);

        $this->assertSame('the-real-secret', Tiger_Mail::storedSmtpPassword(),
            'the encrypted key is decrypted and takes precedence over any lingering plaintext');
    }

    #[Test]
    public function settings_never_exposes_the_password(): void
    {
        $this->seedConfig(['transport' => 'smtp', 'smtp' => [
            'host' => 'smtp.example.test', 'port' => 587, 'username' => 'u', 'password' => 'super-secret',
        ]]);

        $s = Tiger_Mail::settings();
        $this->assertTrue($s['has_password'], 'the screen is told a password EXISTS');
        $this->assertArrayNotHasKey('password', $s, 'but the value itself is never returned');
        $this->assertNotContains('super-secret', $s, 'and it appears nowhere in the payload');
        $this->assertSame('smtp.example.test', $s['host'], 'the non-secret fields still round-trip');
    }

    #[Test]
    public function saving_persists_to_the_config_tier_and_normalizes_port_and_protocol(): void
    {
        Tiger_Mail::saveSettings([
            'transport' => 'smtp', 'host' => 'smtp.example.test',
            'port' => '99999',            // out of range -> falls back to 587
            'ssl'  => 'BOGUS',            // not tls/ssl  -> stored as none
            'auth' => 'login', 'username' => 'u', 'from_email' => 'no-reply@example.test',
        ]);

        $cfg = new Tiger_Model_Config();
        $g   = Tiger_Model_Config::SCOPE_GLOBAL;
        $this->assertSame('smtp', $cfg->get($g, '', 'mail.transport'));
        $this->assertSame('smtp.example.test', $cfg->get($g, '', 'mail.smtp.host'));
        $this->assertSame('587', $cfg->get($g, '', 'mail.smtp.port'), 'an out-of-range port is normalized, not stored');
        $this->assertSame('', $cfg->get($g, '', 'mail.smtp.ssl'), 'an unknown protocol is refused rather than passed to the transport');
    }

    #[Test]
    public function a_blank_password_keeps_the_stored_one(): void
    {
        Tiger_Mail::saveSettings(['transport' => 'smtp', 'host' => 'a.example.test', 'password' => 'first-secret']);
        $cfg   = new Tiger_Model_Config();
        $g     = Tiger_Model_Config::SCOPE_GLOBAL;
        $after = $cfg->get($g, '', 'mail.smtp.password_enc') ?: $cfg->get($g, '', 'mail.smtp.password');
        $this->assertNotSame('', (string) $after, 'precondition: a secret is stored');

        // Edit the host only — the password field comes back blank from the form.
        Tiger_Mail::saveSettings(['transport' => 'smtp', 'host' => 'b.example.test', 'password' => '']);

        $now = $cfg->get($g, '', 'mail.smtp.password_enc') ?: $cfg->get($g, '', 'mail.smtp.password');
        $this->assertSame((string) $after, (string) $now, 'editing the host must not wipe the stored password');
        $this->assertSame('b.example.test', $cfg->get($g, '', 'mail.smtp.host'), 'and the host did change');
    }

    #[Test]
    public function transport_for_builds_from_explicit_values_so_a_test_needs_no_save(): void
    {
        $smtp = Tiger_Mail::transportFor([
            'transport' => 'smtp', 'host' => 'smtp.example.test', 'port' => 2525,
            'ssl' => 'tls', 'auth' => 'login', 'username' => 'u', 'password' => 'p',
        ]);
        $this->assertInstanceOf(Zend_Mail_Transport_Smtp::class, $smtp,
            'the admin "Send test" can build a transport from unsaved form values');

        $this->assertInstanceOf(Zend_Mail_Transport_Sendmail::class,
            Tiger_Mail::transportFor(['transport' => 'mail']),
            'and honors the sendmail transport the same way');
    }
}
