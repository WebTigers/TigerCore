<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent;
use Tiger_Agent_Mcp;
use Tiger_Crypto;
use Tiger_Model_Agent;
use Tiger_Model_Config;
use Tiger_Model_Option;
use Tiger_Model_Table;
use Zend_Config;
use Zend_Registry;

/**
 * Migration 0068 (TIGER-511) — install-wide agent data moves to the INSTALL org.
 *
 * Runs the migration's own callable against seeded rows inside the harness transaction: platform
 * (`org_id = ''`) agents, global `tiger.agent.*` config rows and the global MCP connections option all
 * land on the install org; an org row that already exists is never overwritten; the install org keeps
 * exactly one default; the global rows are retired; and a second run changes nothing. After it, a
 * single-tenant install's own org still resolves its agent and key — and no other org does.
 */
final class AgentOrgMigrationTest extends IntegrationTestCase
{
    private const INSTALL_ORG = 'install-org-0068';
    private const OTHER_ORG   = 'other-org-0068';
    private const CRYPTO_KEY  = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';

    private $configBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBefore = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => [
            'site'   => ['org_id' => self::INSTALL_ORG],
            'crypto' => ['key' => self::CRYPTO_KEY],
        ]]));
        Tiger_Agent::reset();
    }

    protected function tearDown(): void
    {
        if ($this->configBefore !== null) {
            Zend_Registry::set('Zend_Config', $this->configBefore);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        }
        Tiger_Model_Table::setOrg('');
        Tiger_Agent::reset();
        parent::tearDown();
    }

    private function runMigration(): void
    {
        $spec = require dirname(__DIR__, 3) . '/migrations/0068_agent_org_scope.php';
        foreach ($spec['up'] as $step) { $step($this->db); }
        Tiger_Model_Config::resetCache();
        Tiger_Agent::reset();
    }

    private function agentRow(string $id): array
    {
        return $this->db->fetchRow('SELECT * FROM agent WHERE agent_id = ?', [$id]);
    }

    private function configRow(string $scope, string $scopeId, string $key): ?array
    {
        $r = $this->db->fetchRow(
            'SELECT * FROM config WHERE scope = ? AND scope_id = ? AND config_key = ?',
            [$scope, $scopeId, $key]
        );
        return $r ?: null;
    }

    #[Test]
    public function platform_agents_move_to_the_install_org_and_it_keeps_working(): void
    {
        $m  = new Tiger_Model_Agent();
        $id = $m->insert([
            'org_id' => '', 'name' => 'Platform', 'provider' => 'openai', 'model' => 'gpt-4.1',
            'api_key_enc' => Tiger_Crypto::encrypt('sk-platform'), 'enabled' => 1, 'is_default' => 1,
        ]);

        // Before: an org with nothing of its own gets nothing (no platform fallback any more).
        $this->assertNull(Tiger_Agent::forOrg(self::INSTALL_ORG));

        $this->runMigration();

        $row = $this->agentRow($id);
        $this->assertSame(self::INSTALL_ORG, $row['org_id'], 'the platform agent now belongs to the install org');
        $this->assertSame(1, (int) $row['is_default'], 'and stays the default (the install org had none)');

        $agent = Tiger_Agent::forOrg(self::INSTALL_ORG);
        $this->assertNotNull($agent, 'a single-tenant install keeps its agent');
        $this->assertSame('sk-platform', $agent['api_key']);
        $this->assertTrue($agent['available']);

        $this->assertNull(Tiger_Agent::forOrg(self::OTHER_ORG), 'any other org still has no agent');
    }

    #[Test]
    public function a_moved_default_is_demoted_when_the_install_org_already_has_one(): void
    {
        $m    = new Tiger_Model_Agent();
        $own  = $m->insert(['org_id' => self::INSTALL_ORG, 'name' => 'Own', 'provider' => 'gemini', 'enabled' => 1, 'is_default' => 1]);
        $plat = $m->insert(['org_id' => '', 'name' => 'Platform', 'provider' => 'openai', 'enabled' => 1, 'is_default' => 1]);

        $this->runMigration();

        $this->assertSame(1, (int) $this->agentRow($own)['is_default'], 'the install org keeps its own default');
        $this->assertSame(0, (int) $this->agentRow($plat)['is_default'], 'the moved platform default is demoted');
        $this->assertSame(self::INSTALL_ORG, $this->agentRow($plat)['org_id']);
        $this->assertSame('gemini', Tiger_Agent::forOrg(self::INSTALL_ORG)['provider']);
    }

    #[Test]
    public function global_agent_config_moves_to_the_install_org_without_overwriting_an_org_row(): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set('global', '', Tiger_Agent::CFG_PROVIDER, 'openai');
        $cfg->set('global', '', Tiger_Agent::CFG_KEY_ENC, Tiger_Crypto::encrypt('sk-global'));
        $cfg->set('global', '', Tiger_Agent::CFG_ENABLED, '1');
        $cfg->set('global', '', Tiger_Agent::CFG_MODE_MAX, 'yolo');
        // The install org already chose its own model + ceiling — those must survive.
        $cfg->set('org', self::INSTALL_ORG, Tiger_Agent::CFG_MODE_MAX, 'ask');
        $cfg->set('global', '', Tiger_Agent::CFG_MODEL, 'gpt-global');
        $cfg->set('org', self::INSTALL_ORG, Tiger_Agent::CFG_MODEL, 'gpt-own');
        // An unrelated global key is not touched.
        $cfg->set('global', '', 'tiger.agent.skills.active', 'a,b');

        $this->runMigration();

        $this->assertSame('openai', $this->configRow('org', self::INSTALL_ORG, Tiger_Agent::CFG_PROVIDER)['config_value']);
        $this->assertSame('ask', $this->configRow('org', self::INSTALL_ORG, Tiger_Agent::CFG_MODE_MAX)['config_value'], 'an existing org row is never overwritten');
        $this->assertSame('gpt-own', $this->configRow('org', self::INSTALL_ORG, Tiger_Agent::CFG_MODEL)['config_value']);

        foreach ([Tiger_Agent::CFG_PROVIDER, Tiger_Agent::CFG_KEY_ENC, Tiger_Agent::CFG_ENABLED, Tiger_Agent::CFG_MODE_MAX, Tiger_Agent::CFG_MODEL] as $key) {
            $this->assertSame(1, (int) $this->configRow('global', '', $key)['deleted'], $key . ': the install-wide row is retired');
        }
        $this->assertSame(0, (int) $this->configRow('global', '', 'tiger.agent.skills.active')['deleted'], 'non-key agent config is untouched');

        $agent = Tiger_Agent::forOrg(self::INSTALL_ORG);
        $this->assertSame('sk-global', $agent['api_key'], 'the install org keeps the key it was using');
        $this->assertSame('gpt-own', $agent['model']);
        $this->assertNull(Tiger_Agent::forOrg(self::OTHER_ORG), 'no other org inherits it');
    }

    #[Test]
    public function the_global_mcp_connections_move_to_the_install_org(): void
    {
        $opt  = new Tiger_Model_Option();
        $list = [['id' => 'mcp-1', 'label' => 'Docs', 'url' => 'https://mcp.example.test', 'enabled' => true, 'token_enc' => '']];
        $opt->setJson('global', '', Tiger_Agent_Mcp::OPT_CONNECTIONS, $list);

        $this->runMigration();

        $this->assertSame($list, $opt->getJson('org', self::INSTALL_ORG, Tiger_Agent_Mcp::OPT_CONNECTIONS));
        $this->assertNull($opt->get('global', '', Tiger_Agent_Mcp::OPT_CONNECTIONS), 'the global option is retired');
        $this->assertCount(1, Tiger_Agent_Mcp::all(self::INSTALL_ORG));
        $this->assertSame([], Tiger_Agent_Mcp::all(self::OTHER_ORG));
    }

    #[Test]
    public function an_existing_org_mcp_list_is_not_overwritten(): void
    {
        $opt = new Tiger_Model_Option();
        $opt->setJson('global', '', Tiger_Agent_Mcp::OPT_CONNECTIONS, [['id' => 'mcp-global']]);
        $opt->setJson('org', self::INSTALL_ORG, Tiger_Agent_Mcp::OPT_CONNECTIONS, [['id' => 'mcp-own']]);

        $this->runMigration();

        $this->assertSame([['id' => 'mcp-own']], $opt->getJson('org', self::INSTALL_ORG, Tiger_Agent_Mcp::OPT_CONNECTIONS));
    }

    #[Test]
    public function a_second_run_changes_nothing(): void
    {
        $m  = new Tiger_Model_Agent();
        $id = $m->insert(['org_id' => '', 'name' => 'Platform', 'provider' => 'openai', 'enabled' => 1, 'is_default' => 1]);
        (new Tiger_Model_Config())->set('global', '', Tiger_Agent::CFG_PROVIDER, 'openai');

        $this->runMigration();
        $agentAfter  = $this->agentRow($id);
        $configAfter = $this->configRow('org', self::INSTALL_ORG, Tiger_Agent::CFG_PROVIDER);
        $count       = (int) $this->db->fetchOne('SELECT COUNT(*) FROM config');

        $this->runMigration();

        $this->assertSame($agentAfter['org_id'], $this->agentRow($id)['org_id']);
        $this->assertSame($agentAfter['is_default'], $this->agentRow($id)['is_default']);
        $this->assertSame($configAfter, $this->configRow('org', self::INSTALL_ORG, Tiger_Agent::CFG_PROVIDER));
        $this->assertSame($count, (int) $this->db->fetchOne('SELECT COUNT(*) FROM config'), 'no row added on a re-run');
    }
}
