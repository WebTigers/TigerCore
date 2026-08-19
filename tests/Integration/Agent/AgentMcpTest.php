<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Service_Mcp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Forge;
use Tiger_Agent_Mcp;

/**
 * The OUTBOUND connections: Tiger_Agent_Mcp (registry in the option tier — save/mask/enable/remove, blank-
 * token-keeps), the admin service (validate + admin-gate), the ACL-driven role gate, and the Forge's mcp
 * action (role-gated + approval-gated, no network on the deferred/denied paths).
 */
#[CoversClass(Tiger_Agent_Mcp::class)]
#[CoversClass(Agent_Service_Mcp::class)]
final class AgentMcpTest extends IntegrationTestCase
{
    // A URL that refuses instantly (discard port) so save()'s cache refresh never hangs on a real host.
    private const DEAD_URL = 'http://127.0.0.1:9/mcp';

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure Tiger_Crypto has a key so an encrypted token round-trips (like the agent's own BYO key).
        $cfg = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config') : new \Zend_Config([], true);
        if ($cfg->readOnly()) { $cfg = new \Zend_Config($cfg->toArray(), true); }
        $cfg->merge(new \Zend_Config(['tiger' => ['crypto' => ['key' => base64_encode(str_repeat("\x11", 32))]]], true));
        \Zend_Registry::set('Zend_Config', $cfg);
    }

    protected function tearDown(): void
    {
        @unlink(APPLICATION_ROOT . '/var/cache/agent-mcp/tools.json');
        parent::tearDown();
    }

    private function call(string $method, array $params = []): object
    {
        return (new Agent_Service_Mcp(['action' => $method] + $params))->getResponse();
    }

    #[Test]
    public function registry_saves_masks_the_token_and_removes(): void
    {
        $id = Tiger_Agent_Mcp::save(['label' => 'GitHub', 'url' => self::DEAD_URL, 'token' => 'secret', 'enabled' => true]);

        $mine = array_values(array_filter(Tiger_Agent_Mcp::forAdmin(), fn($c) => $c['id'] === $id))[0];
        $this->assertSame('GitHub', $mine['label']);
        $this->assertSame(self::DEAD_URL, $mine['url']);
        $this->assertTrue($mine['enabled']);
        $this->assertArrayNotHasKey('token', $mine, 'the secret never leaks to the admin view');
        $this->assertArrayNotHasKey('token_enc', $mine);

        $conn = Tiger_Agent_Mcp::connection($id);
        $this->assertNotNull($conn);
        $this->assertSame(self::DEAD_URL, $conn['url']);

        Tiger_Agent_Mcp::remove($id);
        $this->assertNull(Tiger_Agent_Mcp::connection($id));
    }

    #[Test]
    public function a_blank_token_on_update_keeps_the_existing_one(): void
    {
        $id = Tiger_Agent_Mcp::save(['label' => 'A', 'url' => self::DEAD_URL, 'token' => 'tok', 'enabled' => true]);
        Tiger_Agent_Mcp::save(['id' => $id, 'label' => 'A2', 'url' => self::DEAD_URL, 'token' => '', 'enabled' => true]);

        $mine = array_values(array_filter(Tiger_Agent_Mcp::forAdmin(), fn($c) => $c['id'] === $id))[0];
        $this->assertSame('A2', $mine['label']);
        $this->assertTrue($mine['has_token'], 'a blank token on update keeps the original');
    }

    #[Test]
    public function service_validates_the_url_and_is_admin_only(): void
    {
        $this->loginAs('admin');
        $this->assertSame(0, (int) $this->call('save', ['label' => 'X', 'url' => 'ftp://nope'])->result, 'bad url refused');
        $this->assertSame(0, (int) $this->call('save', ['label' => '', 'url' => self::DEAD_URL])->result, 'blank label refused');

        $ok = $this->call('save', ['label' => 'GH', 'url' => self::DEAD_URL, 'enabled' => 1]);
        $this->assertSame(1, (int) $ok->result);
        $this->assertContains('GH', array_column($ok->data['connections'], 'label'));

        $this->login('u1', 'org-test', 'user');
        $this->assertSame(0, (int) $this->call('connections')->result, 'admin-gated');
    }

    #[Test]
    public function allowed_for_role_follows_the_admin_acl(): void
    {
        $this->loginAs('admin');   // builds the ACL
        $this->assertTrue(Tiger_Agent_Mcp::allowedForRole('admin'));
        $this->assertFalse(Tiger_Agent_Mcp::allowedForRole('user'), 'a content role cannot use connected MCP tools');
    }

    #[Test]
    public function forge_gates_the_mcp_action_by_role_and_defers_before_running(): void
    {
        $this->loginAs('admin');   // builds the ACL for allowedForRole
        $id     = Tiger_Agent_Mcp::save(['label' => 'GH', 'url' => self::DEAD_URL, 'enabled' => true]);
        $action = ['type' => 'mcp', 'connection' => $id, 'tool' => 'create_issue', 'args' => [], 'reason' => 'x'];

        $this->assertSame('denied',   (new Tiger_Agent_Forge('user'))->execute($action)['status'], 'a content role is denied');
        $this->assertSame('proposed', (new Tiger_Agent_Forge('admin'))->execute($action)['status'], 'a write waits for approval (no network)');
        $this->assertSame('error',    (new Tiger_Agent_Forge('admin'))->execute(
            ['type' => 'mcp', 'connection' => 'nope', 'tool' => 't', 'args' => [], 'approved' => true])['status'], 'unknown connection → error');
    }
}
