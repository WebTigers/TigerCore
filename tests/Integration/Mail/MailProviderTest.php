<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail;
use Tiger_Mail_Provider;
use Tiger_Mail_Transport_Api;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Mail_Transport_Sendmail;
use Zend_Registry;

/**
 * Tiger_Mail_Provider — the catalog behind the provider dropdown, and how a chosen provider turns
 * into a transport.
 *
 * The properties worth pinning: every declared API provider actually has a loadable driver (a typo
 * in the table would otherwise surface as mail silently falling back to sendmail); an API provider
 * whose optional SDK is missing reports UNAVAILABLE rather than being offered and then failing at
 * send time; and `smtpFor()` refuses to hand back a half-interpolated host, since
 * `email-smtp.{region}.amazonaws.com` would be a confusing DNS error instead of an obvious "fill
 * the region in" state.
 */
#[CoversClass(Tiger_Mail_Provider::class)]
final class MailProviderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('Zend_Config', new Zend_Config(['mail' => [], 'tiger' => []], true));
    }

    #[Test]
    public function every_api_provider_declares_a_driver_that_exists(): void
    {
        $api = 0;
        foreach (Tiger_Mail_Provider::all() as $key => $def) {
            if ($def['kind'] !== Tiger_Mail_Provider::KIND_API) { continue; }
            $api++;
            $this->assertArrayHasKey('transport', $def, "$key declares a transport class");
            $this->assertTrue(class_exists($def['transport']), "$key's driver {$def['transport']} is loadable");
            $this->assertTrue(
                is_subclass_of($def['transport'], Tiger_Mail_Transport_Api::class),
                "$key's driver extends the API transport base"
            );
        }
        $this->assertGreaterThanOrEqual(7, $api, 'the catalog ships the API drivers');
    }

    #[Test]
    public function every_smtp_provider_declares_usable_connection_defaults(): void
    {
        foreach (Tiger_Mail_Provider::all() as $key => $def) {
            if ($def['kind'] !== Tiger_Mail_Provider::KIND_SMTP) { continue; }
            $this->assertArrayHasKey('smtp', $def, "$key declares smtp defaults");
            $ssl = (string) $def['smtp']['ssl'];
            $this->assertContains($ssl, ['tls', 'ssl', ''], "$key's encryption is a value the transport accepts");
        }
    }

    #[Test]
    public function a_placeholder_host_is_interpolated_from_the_providers_own_fields(): void
    {
        $smtp = Tiger_Mail_Provider::smtpFor('ses-smtp', ['region' => 'eu-west-1']);
        $this->assertSame('email-smtp.eu-west-1.amazonaws.com', $smtp['host'], 'the region is substituted into the host');
        $this->assertSame('587', $smtp['port']);
        $this->assertSame('tls', $smtp['ssl']);
    }

    #[Test]
    public function an_unfilled_placeholder_yields_no_host_rather_than_a_broken_one(): void
    {
        $smtp = Tiger_Mail_Provider::smtpFor('ses-smtp', []);   // region not supplied yet
        $this->assertSame('', $smtp['host'],
            'a literal "{region}" host would be a baffling DNS failure — an empty host degrades to sendmail instead');
    }

    #[Test]
    public function ses_api_is_unavailable_without_the_aws_sdk(): void
    {
        // The SDK module is optional and is NOT installed in the test environment.
        $expected = class_exists('Aws\\SesV2\\SesV2Client');
        $this->assertSame($expected, Tiger_Mail_Provider::isAvailable('ses-api'),
            'availability is capability-detected against the SDK, never assumed');

        // A keyless provider is always available.
        $this->assertTrue(Tiger_Mail_Provider::isAvailable('sendgrid-api'), 'a plain HTTPS driver needs no SDK');
        $this->assertTrue(Tiger_Mail_Provider::isAvailable('custom'), 'SMTP providers need no driver at all');
        $this->assertFalse(Tiger_Mail_Provider::isAvailable('nope'), 'an unknown provider is not available');
    }

    #[Test]
    public function choosing_an_api_provider_builds_its_driver(): void
    {
        $t = Tiger_Mail::apiTransport('postmark-api', ['key' => 'token']);
        $this->assertInstanceOf('Tiger_Mail_Transport_Postmark', $t);

        $this->assertNull(Tiger_Mail::apiTransport('custom', []), 'an SMTP provider has no API driver');
        $this->assertNull(Tiger_Mail::apiTransport('nope', []), 'an unknown provider has no driver');
    }

    #[Test]
    public function an_api_provider_round_trips_through_the_config_tier(): void
    {
        Tiger_Mail::saveSettings([
            'provider'   => 'resend-api',
            'fields'     => ['key' => 're_test_secret'],
            'from_email' => 'no-reply@example.test',
        ]);

        $cfg = new Tiger_Model_Config();
        $g   = Tiger_Model_Config::SCOPE_GLOBAL;
        $this->assertSame('api', $cfg->get($g, '', 'mail.transport'), 'an API provider switches the transport kind');
        $this->assertSame('resend-api', $cfg->get($g, '', 'mail.provider'));
        $this->assertSame('no-reply@example.test', $cfg->get($g, '', 'mail.from.email'),
            'the From identity still applies to an API send');
    }

    #[Test]
    public function stored_credentials_are_read_from_the_resolved_cascade(): void
    {
        // Deliberately seeded into the registry rather than read back after a save: the config tier
        // is "effective NEXT request" by design (Tiger_Model_Config::set does not refresh the
        // in-memory cascade — Tiger_Recaptcha reads the same way), so this mirrors how a live
        // request actually sees stored credentials.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'mail'  => ['api' => ['mailgun-api' => ['domain' => 'mg.example.test', 'key' => 'key-plain', 'endpoint' => '']]],
            'tiger' => [],
        ], true));

        $creds = Tiger_Mail::apiCredentials('mailgun-api');
        $this->assertSame('mg.example.test', $creds['domain'], 'non-secret fields resolve');
        $this->assertSame('key-plain', $creds['key'], 'and the driver gets the key it needs to authenticate');
        $this->assertSame([], Tiger_Mail::apiCredentials(''), 'no provider means no credentials');
    }

    #[Test]
    public function an_api_secret_is_encrypted_at_rest_when_crypto_is_configured(): void
    {
        // With no crypto key the writer documents a plaintext fallback — assert BOTH halves, since
        // "it was encrypted" is only meaningful next to "and it isn't when it can't be".
        Tiger_Mail::saveApiCredentials('brevo-api', ['key' => 'plain-fallback']);
        $cfg = new Tiger_Model_Config();
        $g   = Tiger_Model_Config::SCOPE_GLOBAL;
        $this->assertSame('plain-fallback', (string) $cfg->get($g, '', 'mail.api.brevo-api.key'),
            'with no crypto configured the key is stored as-is rather than being silently dropped');

        // Now configure crypto and re-save: the value must land in the _enc key, not the plain one.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'mail'  => [],
            'tiger' => ['crypto' => ['key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAo=']],
        ], true));
        Tiger_Mail::saveApiCredentials('brevo-api', ['key' => 'secret-key-value']);

        $this->assertSame('', (string) $cfg->get($g, '', 'mail.api.brevo-api.key'),
            'the plaintext row is cleared so the secret never lingers in two places');
        $enc = (string) $cfg->get($g, '', 'mail.api.brevo-api.key_enc');
        $this->assertNotSame('', $enc, 'the encrypted row is written');
        $this->assertStringNotContainsString('secret-key-value', $enc, 'and it is genuinely encrypted, not encoded');
    }

    #[Test]
    public function a_blank_api_secret_keeps_the_stored_one(): void
    {
        Tiger_Mail::saveApiCredentials('sendgrid-api', ['key' => 'SG.first']);
        $cfg   = new Tiger_Model_Config();
        $g     = Tiger_Model_Config::SCOPE_GLOBAL;
        $first = (string) ($cfg->get($g, '', 'mail.api.sendgrid-api.key_enc') ?: $cfg->get($g, '', 'mail.api.sendgrid-api.key'));
        $this->assertNotSame('', $first, 'precondition: a key is stored');

        Tiger_Mail::saveApiCredentials('sendgrid-api', ['key' => '']);   // edited another field, left the key blank

        $now = (string) ($cfg->get($g, '', 'mail.api.sendgrid-api.key_enc') ?: $cfg->get($g, '', 'mail.api.sendgrid-api.key'));
        $this->assertSame($first, $now, 'a blank secret must never wipe the stored API key');
    }

    #[Test]
    public function an_unusable_api_provider_degrades_to_sendmail_instead_of_fataling(): void
    {
        // transport=api but the provider is unknown → the driver can't be built.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'mail'  => ['transport' => 'api', 'provider' => 'not-a-provider'],
            'tiger' => [],
        ], true));

        $this->assertInstanceOf(Zend_Mail_Transport_Sendmail::class, (new Tiger_Mail())->transport(),
            'a broken provider (e.g. its SDK was deactivated) must not fatal every request that sends mail');
    }
}
