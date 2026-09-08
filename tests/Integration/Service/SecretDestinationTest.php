<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Settings;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Location;
use Zend_Config;
use Zend_Registry;

/**
 * A stored secret belongs to the destination it was stored FOR (TIGER-73, TIGER-74).
 *
 * Both "test your connection" actions took the DESTINATION from the request and the CREDENTIAL from
 * encrypted storage, with nothing tying the two together. Changing only the host (or the endpoint) and
 * leaving the secret blank therefore sent a saved credential to a caller-chosen server. Admin-gated,
 * but that is the point: an admin can legitimately configure a new server, and should still not be
 * able to READ a secret they cannot otherwise see by pointing a test at a machine they control.
 *
 * The same-destination cases matter as much as the denials — a fix that simply refused every test
 * would pass the denial tests and break the feature.
 */
#[CoversClass(System_Service_Settings::class)]
#[CoversClass(Tiger_Location::class)]
final class SecretDestinationTest extends IntegrationTestCase
{
    private $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
    }

    protected function tearDown(): void
    {
        if ($this->priorConfig !== null) { Zend_Registry::set('Zend_Config', $this->priorConfig); }
        parent::tearDown();
    }

    /** Configure a saved SMTP destination. */
    private function savedSmtp(array $over = []): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'mail' => ['smtp' => array_merge([
                'host' => 'smtp.saved-host.test', 'port' => '587',
                'username' => 'postmaster@saved.test', 'password' => 'the-saved-secret',
            ], $over)],
        ]));
    }

    /** Reach the protected destination-comparison directly — mailTest itself would try to SEND. */
    private function changed(array $params): bool
    {
        $m = new \ReflectionMethod(System_Service_Settings::class, '_smtpDestinationChanged');
        return (bool) $m->invoke(null, $params);
    }

    // ---- SMTP (TIGER-73) -------------------------------------------------------

    #[Test]
    public function a_different_smtp_host_counts_as_a_new_destination(): void
    {
        $this->savedSmtp();
        $this->assertTrue($this->changed(['mail_smtp_host' => 'smtp.attacker.test']),
            'the saved password must not follow the test to another server');
    }

    #[Test]
    public function a_different_smtp_port_or_username_also_counts(): void
    {
        $this->savedSmtp();
        $this->assertTrue($this->changed(['mail_smtp_port' => '2525']));
        $this->assertTrue($this->changed(['mail_smtp_username' => 'someone-else@evil.test']));
    }

    #[Test]
    public function the_same_destination_may_reuse_the_saved_password(): void
    {
        // The positive control: testing an existing setup must not demand a re-typed password.
        $this->savedSmtp();
        $this->assertFalse($this->changed([
            'mail_smtp_host' => 'smtp.saved-host.test', 'mail_smtp_port' => '587',
            'mail_smtp_username' => 'postmaster@saved.test',
        ]));
    }

    #[Test]
    public function blank_fields_mean_unchanged_not_different(): void
    {
        $this->savedSmtp();
        $this->assertFalse($this->changed(['mail_smtp_host' => '', 'mail_smtp_port' => '']));
    }

    #[Test]
    public function with_nothing_saved_nothing_may_be_inherited(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([]));
        $this->assertTrue($this->changed(['mail_smtp_host' => 'smtp.anything.test']),
            'there is no saved destination to match, so no secret may be reused');
    }

    // ---- Location (TIGER-74) ---------------------------------------------------

    #[Test]
    public function a_changed_location_endpoint_does_not_inherit_the_saved_key(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['location' => ['adapters' => ['ipapi' => [
                'endpoint' => 'http://ip-api.com/json', 'key' => 'the-saved-api-key',
            ]]]],
        ]));

        // The REAL resolver — a network call would leave the box, so assert on the config the adapter
        // would be constructed with. (Re-implementing the rule in the test would prove nothing.)
        $cfg = Tiger_Location::testConfig('ipapi', \Tiger_Location_Adapter_IpApi::class,
            ['endpoint' => 'https://attacker.test/collect']);

        $this->assertSame('https://attacker.test/collect', $cfg['endpoint'], 'the caller may change the endpoint');
        $this->assertArrayNotHasKey('key', $cfg, 'but the saved API key must NOT travel to it');
    }

    #[Test]
    public function the_same_location_endpoint_still_uses_the_saved_key(): void
    {
        // Positive control: a fix that stripped the key unconditionally would break the feature.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['location' => ['adapters' => ['ipapi' => [
                'endpoint' => 'http://ip-api.com/json', 'key' => 'the-saved-api-key',
            ]]]],
        ]));

        $cfg = Tiger_Location::testConfig('ipapi', \Tiger_Location_Adapter_IpApi::class,
            ['endpoint' => 'http://ip-api.com/json']);

        $this->assertSame('the-saved-api-key', $cfg['key'] ?? null);
    }

    #[Test]
    public function an_explicitly_supplied_key_is_used_with_a_new_endpoint(): void
    {
        // Changing BOTH is the legitimate "configure a new provider" path and must keep working.
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['location' => ['adapters' => ['ipapi' => [
                'endpoint' => 'http://ip-api.com/json', 'key' => 'the-saved-api-key',
            ]]]],
        ]));

        $cfg = Tiger_Location::testConfig('ipapi', \Tiger_Location_Adapter_IpApi::class,
            ['endpoint' => 'https://pro.ip-api.com/json', 'key' => 'a-freshly-typed-key']);

        $this->assertSame('https://pro.ip-api.com/json', $cfg['endpoint']);
        $this->assertSame('a-freshly-typed-key', $cfg['key'], 'the supplied key is used, not the saved one');
    }
}
