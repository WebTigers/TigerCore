<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent;
use Tiger_Crypto;
use Tiger_Model_Agent;
use Tiger_Model_Table;
use Tiger_Uuid;
use Zend_Config;
use Zend_Registry;

/**
 * The agent registry (migration 0050, TIGER-151) and its read-through facade.
 *
 * The load-bearing invariants: an EMPTY registry leaves Tiger_Agent behaving exactly as the legacy
 * singleton (it reads the `tiger.agent.*` config keys); a Default row overrides that per org; an org
 * with no agent of its own resolves to the global ('') default; setDefault keeps exactly one default
 * per scope; and get()/all() never leak another org's agents.
 */
#[CoversClass(Tiger_Agent::class)]
#[CoversClass(Tiger_Model_Agent::class)]
final class AgentRegistryTest extends IntegrationTestCase
{
    /** A valid 32-byte base64 crypto key. */
    private const CRYPTO_KEY = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';

    protected function tearDown(): void
    {
        Tiger_Model_Table::setOrg(null);
        Tiger_Agent::reset();
        parent::tearDown();
    }

    /** Put a Zend_Config in the registry with optional legacy agent keys + optional crypto. */
    private function seedConfig(array $agent = [], bool $withCrypto = false): void
    {
        $tiger = [];
        if ($agent)      { $tiger['agent']  = $agent; }
        if ($withCrypto) { $tiger['crypto'] = ['key' => self::CRYPTO_KEY]; }
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger], true));
        Tiger_Agent::reset();
    }

    #[Test]
    public function an_empty_registry_falls_back_to_the_legacy_singleton_config(): void
    {
        $this->seedConfig([
            'enabled'  => '1',
            'provider' => 'openai',
            'model'    => 'gpt-4.1',
        ]);
        Tiger_Model_Table::setOrg(Tiger_Uuid::v7());   // an org that has registered nothing

        $this->assertTrue(Tiger_Agent::isEnabled(), 'enabled reads through to the legacy flag');
        $this->assertSame('openai', Tiger_Agent::provider());
        $this->assertSame('gpt-4.1', Tiger_Agent::model());
        $this->assertNull(Tiger_Agent::default()['id'], 'the synthesized default has no row id');
        $this->assertSame([], Tiger_Agent::all(), 'no rows means an empty roster');
    }

    #[Test]
    public function a_disabled_legacy_flag_reads_as_disabled(): void
    {
        $this->seedConfig(['enabled' => '0', 'provider' => 'anthropic']);
        Tiger_Model_Table::setOrg(Tiger_Uuid::v7());
        $this->assertFalse(Tiger_Agent::isEnabled());
    }

    #[Test]
    public function a_default_row_overrides_the_legacy_config_for_its_org(): void
    {
        // Legacy config says openai; the org's Default row says gemini — the row wins.
        $this->seedConfig(['enabled' => '1', 'provider' => 'openai', 'model' => 'gpt-4.1']);
        $org = Tiger_Uuid::v7();
        Tiger_Model_Table::setOrg($org);

        $model = new Tiger_Model_Agent();
        $model->insert([
            'org_id'     => $org,
            'name'       => 'House',
            'provider'   => 'gemini',
            'model'      => 'gemini-2.0-flash',
            'enabled'    => 1,
            'is_default' => 1,
        ]);
        Tiger_Agent::reset();

        $this->assertSame('gemini', Tiger_Agent::provider());
        $this->assertSame('gemini-2.0-flash', Tiger_Agent::model());
        $this->assertNotNull(Tiger_Agent::default()['id'], 'now resolving a real row');
        $this->assertCount(1, Tiger_Agent::all());
    }

    #[Test]
    public function an_org_with_no_agent_resolves_to_the_global_default(): void
    {
        $this->seedConfig(['provider' => 'openai']);   // legacy would say openai
        $model = new Tiger_Model_Agent();
        // Global ('') default set by the platform.
        $model->insert([
            'org_id'     => '',
            'name'       => 'Platform',
            'provider'   => 'anthropic',
            'model'      => 'claude-opus',
            'enabled'    => 1,
            'is_default' => 1,
        ]);

        Tiger_Model_Table::setOrg(Tiger_Uuid::v7());   // a tenant with nothing of its own
        Tiger_Agent::reset();

        $this->assertSame('anthropic', Tiger_Agent::provider(), 'falls back to the global default row, not legacy config');
    }

    #[Test]
    public function set_default_keeps_exactly_one_default_per_scope(): void
    {
        $org   = Tiger_Uuid::v7();
        $model = new Tiger_Model_Agent();
        Tiger_Model_Table::setOrg($org);

        $a = $model->insert(['org_id' => $org, 'name' => 'A', 'provider' => 'openai',   'is_default' => 1]);
        $b = $model->insert(['org_id' => $org, 'name' => 'B', 'provider' => 'gemini',   'is_default' => 0]);

        $model->setDefault($org, $b);

        $this->assertSame(0, (int) $model->findById($a)->is_default, 'the old default was cleared');
        $this->assertSame(1, (int) $model->findById($b)->is_default, 'the new one is the sole default');
        Tiger_Agent::reset();
        $this->assertSame('gemini', Tiger_Agent::provider());
    }

    #[Test]
    public function get_and_all_are_scoped_to_the_current_org(): void
    {
        $mine  = Tiger_Uuid::v7();
        $other = Tiger_Uuid::v7();
        $model = new Tiger_Model_Agent();

        Tiger_Model_Table::setOrg($mine);
        $myId    = $model->insert(['org_id' => $mine,  'name' => 'Mine',  'provider' => 'openai',    'is_default' => 1]);
        Tiger_Model_Table::setOrg($other);
        $otherId = $model->insert(['org_id' => $other, 'name' => 'Other', 'provider' => 'anthropic', 'is_default' => 1]);

        Tiger_Model_Table::setOrg($mine);
        Tiger_Agent::reset();

        $this->assertNotNull(Tiger_Agent::get($myId), 'own agent is reachable');
        $this->assertNull(Tiger_Agent::get($otherId), 'another org\'s agent is NOT reachable');
        $names = array_column(Tiger_Agent::all(), 'name');
        $this->assertSame(['Mine'], $names, 'all() lists only the current org');
    }

    #[Test]
    public function the_default_rows_encrypted_key_decrypts_through_the_facade(): void
    {
        $this->seedConfig([], true);   // crypto configured, no legacy agent key
        $org   = Tiger_Uuid::v7();
        $model = new Tiger_Model_Agent();
        Tiger_Model_Table::setOrg($org);

        $model->insert([
            'org_id'      => $org,
            'name'        => 'Keyed',
            'provider'    => 'openai',
            'model'       => 'gpt-4.1',
            'api_key_enc' => Tiger_Crypto::encrypt('sk-secret-123'),
            'enabled'     => 1,
            'is_default'  => 1,
        ]);
        Tiger_Agent::reset();

        $this->assertSame('sk-secret-123', Tiger_Agent::apiKey());
        $this->assertTrue(Tiger_Agent::isConnected());
    }
}
