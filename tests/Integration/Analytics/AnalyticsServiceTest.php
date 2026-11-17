<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Analytics;

use Analytics_Service_Analytics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Zend_Registry;

/**
 * Analytics_Service_Analytics — the /api service behind the Google Analytics settings screen. It
 * validates the GA4 Measurement ID, then writes the analytics config (enabled + id + exclude-signed-in,
 * plus the reporting/OAuth connect fields) to the GLOBAL config tier inside a transaction. Wave-4
 * coverage: the ACL gate, the validate-and-persist happy path (each key lands in `config`), the
 * invalid-id reject (form errors, nothing written), and the boolean switches (enabled / exclude).
 */
#[CoversClass(Analytics_Service_Analytics::class)]
final class AnalyticsServiceTest extends IntegrationTestCase
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

    private function call(array $params = []): object
    {
        return (new Analytics_Service_Analytics(['action' => 'save'] + $params))->getResponse();
    }

    /** The caller's org row — where analytics lands (TIGER-511; loginAs() signs in to `org-test`). */
    private function cfg(string $key): ?string
    {
        return (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_ORG, 'org-test', $key);
    }

    // ----- ACL ----------------------------------------------------------------------------------

    #[Test]
    public function guest_is_denied(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $res = $this->call(['ga4_measurement_id' => 'G-ABC123', 'enabled' => '1']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }

    #[Test]
    public function a_plain_user_is_denied(): void
    {
        $this->loginAs('user');
        $res = $this->call(['ga4_measurement_id' => 'G-ABC123']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }

    // ----- save ---------------------------------------------------------------------------------

    #[Test]
    public function the_measurement_protocol_secret_is_stored_encrypted_and_a_blank_keeps_it(): void
    {
        // The booted app has an encryption key in local.ini; give the test config one the same way.
        $was = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $cfg = $was ? $was->toArray() : [];
        $cfg['tiger']['crypto']['key'] = base64_encode(str_repeat("\x11", 32));
        Zend_Registry::set('Zend_Config', new \Zend_Config($cfg, true));
        try {
            $this->_assertMpSecretRoundTrip();
        } finally {
            if ($was) { Zend_Registry::set('Zend_Config', $was); }
        }
    }

    private function _assertMpSecretRoundTrip(): void
    {
        $this->assertTrue(\Tiger_Crypto::isConfigured());
        $this->loginAs('admin');
        $this->assertSame(1, (int) $this->call(['ga4_measurement_id' => 'G-ABC123', 'mp_api_secret' => 'mp-s3cret'])->result);

        $enc = (string) $this->cfg(\Tiger_Analytics_Adapter_Ga::CFG_API_SECRET);
        $this->assertNotSame('', $enc);
        $this->assertStringNotContainsString('mp-s3cret', $enc, 'never stored in the clear');
        $this->assertSame('mp-s3cret', \Tiger_Crypto::decrypt($enc));

        $this->assertSame(1, (int) $this->call(['ga4_measurement_id' => 'G-ABC123', 'mp_api_secret' => ''])->result);
        $this->assertSame($enc, (string) $this->cfg(\Tiger_Analytics_Adapter_Ga::CFG_API_SECRET), 'a blank field keeps the stored secret');
    }

    #[Test]
    public function save_persists_the_measurement_id_and_switches(): void
    {
        $this->loginAs('admin');
        $res = $this->call([
            'ga4_measurement_id' => 'G-ABC123XYZ',
            'enabled'            => '1',
            'exclude_signed_in'  => '1',
        ]);

        $this->assertSame(1, (int) $res->result);
        $this->assertSame('1', $this->cfg('tiger.analytics.enabled'));
        $this->assertSame('G-ABC123XYZ', $this->cfg('tiger.analytics.ga4.measurement_id'));
        $this->assertSame('1', $this->cfg('tiger.analytics.exclude_signed_in'));
        // The reporting bridge (Tiger_Google_Analytics) writes the connect mode too.
        $this->assertNotNull($this->cfg('tiger.analytics.oauth.mode'));
    }

    #[Test]
    public function save_records_disabled_and_included_when_switches_are_off(): void
    {
        $this->loginAs('admin');
        // No `enabled`/`exclude_signed_in` keys → both persist as '0'.
        $res = $this->call(['ga4_measurement_id' => 'G-ONLYID9']);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('0', $this->cfg('tiger.analytics.enabled'));
        $this->assertSame('0', $this->cfg('tiger.analytics.exclude_signed_in'));
    }

    #[Test]
    public function save_accepts_an_empty_measurement_id(): void
    {
        // The id is optional (a site may enable later); an empty value validates and stores ''.
        $this->loginAs('admin');
        $res = $this->call(['ga4_measurement_id' => '', 'enabled' => '0']);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('', $this->cfg('tiger.analytics.ga4.measurement_id'));
    }

    #[Test]
    public function save_persists_the_byo_oauth_connect_fields(): void
    {
        $this->loginAs('admin');
        $res = $this->call([
            'ga4_measurement_id' => 'G-CONNECT1',
            'enabled'            => '1',
            'oauth_mode'         => 'byo',
            'oauth_client_id'    => 'client-abc.apps.googleusercontent.com',
            'property_id'        => '123456789',
        ]);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('byo', $this->cfg('tiger.analytics.oauth.mode'));
        $this->assertSame('client-abc.apps.googleusercontent.com', $this->cfg('tiger.analytics.oauth.client_id'));
        $this->assertSame('123456789', $this->cfg('tiger.analytics.property_id'), 'digits-only property id stored');
    }

    #[Test]
    public function an_invalid_measurement_id_returns_form_errors_and_writes_nothing(): void
    {
        $this->loginAs('admin');
        $before = (int) $this->db->fetchOne('SELECT COUNT(*) FROM config');

        // 'UA-12345-6' is a Universal Analytics id, not a GA4 `G-XXXX` — the regex rejects it.
        $res = $this->call(['ga4_measurement_id' => 'UA-12345-6', 'enabled' => '1']);

        $this->assertSame(0, (int) $res->result);
        $this->assertNotNull($res->form);
        $this->assertArrayHasKey('ga4_measurement_id', $res->form);
        $this->assertSame($before, (int) $this->db->fetchOne('SELECT COUNT(*) FROM config'), 'nothing written on a reject');
    }

    #[Test]
    public function the_settings_form_declares_every_field_the_save_reads(): void
    {
        // TIGER-528: the form is the complete input contract of save(), not just the GA4 id.
        $form = new \Analytics_Form_Settings();
        foreach (['ga4_measurement_id', 'enabled', 'exclude_signed_in', 'oauth_mode', 'oauth_client_id', 'oauth_client_secret', 'property_id'] as $f) {
            $this->assertNotNull($form->getElement($f), $f . ' is declared');
        }
        // Declaring them refuses nothing the save accepted before: an odd oauth_mode still saves (as broker).
        $this->loginAs('admin');
        $res = (new Analytics_Service_Analytics(['action' => 'save', 'enabled' => '1', 'oauth_mode' => 'weird', 'property_id' => 'abc']))->getResponse();
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
    }
}
