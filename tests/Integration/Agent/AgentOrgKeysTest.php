<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Service_Agents;
use Agent_Service_Mcp;
use Agent_Service_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent;
use Tiger_Agent_Mcp;
use Tiger_Comment_Spam;
use Tiger_Crypto;
use Tiger_Model_Agent;
use Tiger_Model_Config;
use Tiger_Model_Org;
use Tiger_Model_Table;
use Zend_Config;
use Zend_Registry;

/**
 * AI keys are ORG-LEVEL ONLY (TIGER-511) and the explicit-org seam (TIGER-513).
 *
 * The load-bearing invariants: an org resolves only its OWN agent and key — never another org's, never
 * a platform (`org_id = ''`) agent, never the install org's key, never an install-wide config key; an
 * org with no agent has no agent (the features degrade); Tiger_Agent::forOrg() answers for a named org
 * with no fallback; the settings/agents/MCP admin services write at the caller's org and refuse a
 * caller with no org; the AI spam checker uses the SITE org's agent.
 */
#[CoversClass(Tiger_Agent::class)]
#[CoversClass(Tiger_Model_Agent::class)]
#[CoversClass(Tiger_Agent_Mcp::class)]
final class AgentOrgKeysTest extends IntegrationTestCase
{
    private const CRYPTO_KEY  = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';
    private const INSTALL_ORG = 'org-511-install';
    private const ORG_A       = 'org-511-a';
    private const ORG_B       = 'org-511-b';
    private const DEAD_URL    = 'http://127.0.0.1:9/mcp';

    private $configBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBefore = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $this->seedConfig();
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    protected function tearDown(): void
    {
        if ($this->configBefore !== null) {
            Zend_Registry::set('Zend_Config', $this->configBefore);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        }
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Agent::reset();
        foreach ([self::INSTALL_ORG, self::ORG_A, self::ORG_B] as $org) {
            @unlink(APPLICATION_ROOT . '/var/cache/agent-mcp/tools-' . md5($org) . '.json');
        }
        parent::tearDown();
    }

    /** Config with crypto + the install org; optional install-wide (ini-tier) agent keys. */
    private function seedConfig(array $agent = []): void
    {
        $tiger = ['crypto' => ['key' => self::CRYPTO_KEY], 'site' => ['org_id' => self::INSTALL_ORG]];
        if ($agent) { $tiger['agent'] = $agent; }
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger], true));
        Tiger_Model_Config::resetCache();
        Tiger_Agent::reset();
    }

    private function agentFor(string $org, string $key, string $provider = 'openai'): string
    {
        Tiger_Agent::reset();   // the facade memoizes per org per request
        return (new Tiger_Model_Agent())->insert([
            'org_id'      => $org,
            'name'        => 'Agent ' . $org,
            'provider'    => $provider,
            'model'       => 'model-' . $org,
            'api_key_enc' => Tiger_Crypto::encrypt($key),
            'enabled'     => 1,
            'is_default'  => 1,
        ]);
    }

    // ----- resolution: no cross-org, no fallback --------------------------------------------------

    #[Test]
    public function an_org_resolves_only_its_own_agent_and_key(): void
    {
        $this->agentFor(self::ORG_A, 'sk-a', 'openai');
        $this->agentFor(self::ORG_B, 'sk-b', 'gemini');

        $a = Tiger_Agent::forOrg(self::ORG_A);
        $b = Tiger_Agent::forOrg(self::ORG_B);
        $this->assertSame('sk-a', $a['api_key']);
        $this->assertSame('openai', $a['provider']);
        $this->assertSame(self::ORG_A, $a['org_id']);
        $this->assertSame('sk-b', $b['api_key']);
        $this->assertSame('gemini', $b['provider']);

        // The request-scoped facade answers for the signed-in org only.
        Tiger_Model_Table::setOrg(self::ORG_A);
        Tiger_Agent::reset();
        $this->assertSame('sk-a', Tiger_Agent::apiKey());
        Tiger_Model_Table::setOrg(self::ORG_B);
        Tiger_Agent::reset();
        $this->assertSame('sk-b', Tiger_Agent::apiKey());
    }

    #[Test]
    public function a_platform_agent_is_never_a_fallback(): void
    {
        $this->agentFor('', 'sk-platform');

        $this->assertNull(Tiger_Agent::forOrg(self::ORG_A), 'no platform (org_id = \'\') fallback');
        $this->assertNull((new Tiger_Model_Agent())->defaultForOrg(self::ORG_A));

        Tiger_Model_Table::setOrg(self::ORG_A);
        Tiger_Agent::reset();
        $this->assertSame('', Tiger_Agent::apiKey());
        $this->assertFalse(Tiger_Agent::isConnected(), 'the agent is unavailable for an org with none');
    }

    #[Test]
    public function the_install_orgs_key_is_never_a_fallback_for_another_org(): void
    {
        $this->agentFor(self::INSTALL_ORG, 'sk-install');

        $this->assertSame('sk-install', Tiger_Agent::forOrg(self::INSTALL_ORG)['api_key']);
        $this->assertNull(Tiger_Agent::forOrg(self::ORG_A), 'org A does not spend the install org\'s key');
        $this->assertFalse(Tiger_Agent::isAvailableForOrg(self::ORG_A));
    }

    #[Test]
    public function install_wide_config_keys_serve_only_the_install_org(): void
    {
        // A single-tenant install that configured the agent in its ini/global tier keeps working…
        $this->seedConfig(['enabled' => '1', 'provider' => 'openai', 'api_key_enc' => Tiger_Crypto::encrypt('sk-ini')]);
        (new Tiger_Model_Config())->set('global', '', Tiger_Agent::CFG_MODEL, 'gpt-global');

        $install = Tiger_Agent::forOrg(self::INSTALL_ORG);
        $this->assertNotNull($install);
        $this->assertSame('sk-ini', $install['api_key']);
        $this->assertSame('gpt-global', $install['model']);
        $this->assertTrue($install['available']);

        // …but no other org inherits it.
        $this->assertNull(Tiger_Agent::forOrg(self::ORG_A));
        Tiger_Model_Table::setOrg(self::ORG_A);
        Tiger_Agent::reset();
        $this->assertSame('', Tiger_Agent::apiKey());
        $this->assertFalse(Tiger_Agent::isEnabled());
    }

    #[Test]
    public function an_org_tier_legacy_config_is_that_orgs_own_agent(): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set('org', self::ORG_A, Tiger_Agent::CFG_ENABLED, '1');
        $cfg->set('org', self::ORG_A, Tiger_Agent::CFG_PROVIDER, 'openai');
        $cfg->set('org', self::ORG_A, Tiger_Agent::CFG_KEY_ENC, Tiger_Crypto::encrypt('sk-a-config'));

        $this->assertSame('sk-a-config', Tiger_Agent::forOrg(self::ORG_A)['api_key']);
        $this->assertNull(Tiger_Agent::forOrg(self::ORG_B));
    }

    #[Test]
    public function for_org_reports_a_disabled_agent_as_unavailable_and_ignores_the_signed_in_org(): void
    {
        $id = $this->agentFor(self::ORG_B, 'sk-b');
        (new Tiger_Model_Agent())->update(['enabled' => 0], ['agent_id = ?' => $id]);
        $this->agentFor(self::ORG_A, 'sk-a');

        Tiger_Model_Table::setOrg(self::ORG_A);   // a job running as A asks for B explicitly
        $b = Tiger_Agent::forOrg(self::ORG_B);
        $this->assertSame('sk-b', $b['api_key']);
        $this->assertFalse($b['enabled']);
        $this->assertTrue($b['connected']);
        $this->assertFalse($b['available']);
        $this->assertNull(Tiger_Agent::forOrg(''), 'no org = no agent');
    }

    // ----- writes land at the caller's org --------------------------------------------------------

    #[Test]
    public function settings_write_at_the_callers_org_and_never_reach_another(): void
    {
        $this->login('u-a', self::ORG_A, 'admin');
        $res = (new Agent_Service_Settings([
            'action' => 'save', 'enabled' => '1', 'provider' => 'openai', 'model' => 'gpt-a',
            'api_key' => 'sk-a-saved', 'mode_max' => 'ask',
        ]))->getResponse();
        $this->assertSame(1, $res->result, json_encode($res->messages));

        $cfg = new Tiger_Model_Config();
        $this->assertSame('gpt-a', $cfg->get('org', self::ORG_A, Tiger_Agent::CFG_MODEL));
        $this->assertNull($cfg->get('global', '', Tiger_Agent::CFG_MODEL), 'nothing is written install-wide');
        $this->assertNull($cfg->get('global', '', Tiger_Agent::CFG_KEY_ENC));
        $this->assertSame('sk-a-saved', Tiger_Agent::forOrg(self::ORG_A)['api_key']);
        $this->assertNull(Tiger_Agent::forOrg(self::ORG_B));

        $res = (new Agent_Service_Settings(['action' => 'mode', 'mode_max' => 'yolo']))->getResponse();
        $this->assertSame(1, $res->result);
        $this->assertSame('yolo', $cfg->get('org', self::ORG_A, Tiger_Agent::CFG_MODE_MAX));
        $this->assertNull($cfg->get('global', '', Tiger_Agent::CFG_MODE_MAX));
    }

    #[Test]
    public function the_agents_registry_writes_at_the_callers_org(): void
    {
        $this->login('u-a', self::ORG_A, 'admin');
        $res = (new Agent_Service_Agents([
            'action' => 'save', 'name' => 'Mine', 'provider' => 'openai', 'model' => 'gpt', 'api_key' => 'sk-mine', 'enabled' => '1',
        ]))->getResponse();
        $this->assertSame(1, $res->result, json_encode($res->messages));

        $this->assertSame('sk-mine', Tiger_Agent::forOrg(self::ORG_A)['api_key']);
        $this->assertNull(Tiger_Agent::forOrg(self::ORG_B));
        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM agent WHERE org_id = ''"), 'no platform row is written');
    }

    #[Test]
    public function a_caller_with_no_org_cannot_write_an_install_wide_agent(): void
    {
        $this->login('u-none', '', 'admin');
        Tiger_Model_Table::setOrg('');

        $res = (new Agent_Service_Agents(['action' => 'save', 'name' => 'X', 'provider' => 'openai', 'api_key' => 'sk-x', 'enabled' => '1']))->getResponse();
        $this->assertSame(0, $res->result);
        $res = (new Agent_Service_Settings(['action' => 'save', 'provider' => 'openai', 'api_key' => 'sk-x', 'mode_max' => 'auto']))->getResponse();
        $this->assertSame(0, $res->result);
        $res = (new Agent_Service_Settings(['action' => 'mode', 'mode_max' => 'yolo']))->getResponse();
        $this->assertSame(0, $res->result);
        $res = (new Agent_Service_Mcp(['action' => 'save', 'label' => 'X', 'url' => self::DEAD_URL]))->getResponse();
        $this->assertSame(0, $res->result);

        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM agent WHERE org_id = ''"));
        $this->assertNull((new Tiger_Model_Config())->get('global', '', Tiger_Agent::CFG_KEY_ENC));
        $this->assertNull((new Tiger_Model_Config())->get('global', '', Tiger_Agent::CFG_MODE_MAX));
    }

    // ----- MCP connections are org-scoped ---------------------------------------------------------

    #[Test]
    public function mcp_connections_are_the_callers_orgs_only(): void
    {
        $this->login('u-a', self::ORG_A, 'admin');
        $res = (new Agent_Service_Mcp(['action' => 'save', 'label' => 'A conn', 'url' => self::DEAD_URL, 'token' => 'tok-a', 'enabled' => '1']))->getResponse();
        $this->assertSame(1, $res->result, json_encode($res->messages));
        $idA = (string) $res->data['id'];

        $this->assertCount(1, Tiger_Agent_Mcp::all(self::ORG_A));
        $this->assertSame('tok-a', Tiger_Agent_Mcp::connection($idA)['token']);
        $this->assertSame([], Tiger_Agent_Mcp::all(self::ORG_B), 'org B sees none of A\'s connections');
        $this->assertSame([], Tiger_Agent_Mcp::all(''), 'no org, no connections');

        // Org B's admin can neither list, test nor remove A's connection.
        $this->login('u-b', self::ORG_B, 'admin');
        $this->assertSame([], Tiger_Agent_Mcp::forAdmin());
        $this->assertNull(Tiger_Agent_Mcp::connection($idA), 'B cannot reach A\'s token');
        (new Agent_Service_Mcp(['action' => 'remove', 'id' => $idA]))->getResponse();
        $this->assertCount(1, Tiger_Agent_Mcp::all(self::ORG_A), 'B\'s remove never touches A');
        $this->assertNull((new \Tiger_Model_Option())->get('global', '', Tiger_Agent_Mcp::OPT_CONNECTIONS), 'nothing install-wide');
    }

    // ----- the AI spam checker reads the SITE org's agent -----------------------------------------

    #[Test]
    public function the_spam_checker_uses_the_site_orgs_agent_not_the_install_orgs(): void
    {
        $this->agentFor(self::INSTALL_ORG, 'sk-install');
        Tiger_Model_Org::setSiteOrgId(self::ORG_A);   // a host that maps to org A, which has no agent

        $this->assertFalse(Tiger_Comment_Spam::agentAvailable(), 'site A has no agent — no install-key fallback');
        $this->assertTrue(Tiger_Comment_Spam::agentAvailable(self::INSTALL_ORG));

        $this->agentFor(self::ORG_A, 'sk-a');
        $this->assertTrue(Tiger_Comment_Spam::agentAvailable(), 'once A connects its own, the site checker can run');
    }
}
