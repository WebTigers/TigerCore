<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_ServerController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Mcp_Server;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-168 — the agent's own Scout (read) + Forge (file-write) surface exposed over MCP. Two halves:
 *  - **advertising** (`tools/list`): the agent tools are opt-in on the token's `agent` scope AND gated by the
 *    SAME ACL the executors enforce (inventory=admin+, tree/file/grep/guide + forge.file=superadmin+);
 *  - **dispatch**: `agent__*` routes to `Tiger_Agent_Scout`/`Tiger_Agent_Forge` (not /api), with the token's
 *    scope + read-only fence on top and the ledger entry mapped into the standard envelope.
 */
#[CoversClass(Tiger_Mcp_Server::class)]
#[CoversClass(Mcp_ServerController::class)]
final class McpAgentToolsTest extends ControllerTestCase
{
    private $origConfig;

    protected function setUp(): void
    {
        parent::setUp();
        // A dispatch writes a Tiger_Log line to the default (errorlog) sink, which the strict-output suite
        // flags as risky. Point logging at the null sink for these tests.
        $this->origConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $this->origConfig ? $this->origConfig->toArray() : [];
        $arr['tiger']['log']['writer'] = 'null';
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));
        \Tiger_Log::reset();
        // Register a real Tiger_Acl_Acl (core + every module's acl.ini, agent included), so the agent-tool
        // gate answers isAllowed() for any role — the executors (Scout/Forge) read the same ACL. Without a
        // registered ACL the /api catalog fails OPEN (all tools) while the agent gate fails CLOSED (none);
        // in the running app the ACL is always built, so we build it here to mirror production.
        $this->login('u-168', 'org-test', 'superadmin');
    }

    protected function tearDown(): void
    {
        if ($this->origConfig !== null) { Zend_Registry::set('Zend_Config', $this->origConfig); }
        \Tiger_Log::reset();
        parent::tearDown();
    }

    /** Tool names in a tools/list for a role, optionally clipped to a token scope. */
    private function toolNames($role, ?array $allowed = null): array
    {
        $out = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $role, fn() => null, $allowed);
        return array_column($out['result']['tools'], 'name');
    }

    #[Test]
    public function an_admin_sees_only_the_inventory_scout_tool(): void
    {
        $names = $this->toolNames('admin');
        $this->assertContains('agent__scout__inventory', $names, 'inventory is admin+');
        $this->assertNotContains('agent__scout__file', $names, 'reading files is superadmin+');
        $this->assertNotContains('agent__scout__grep', $names);
        $this->assertNotContains('agent__forge__file', $names, 'writing files is superadmin+');
    }

    #[Test]
    public function a_superadmin_sees_the_full_scout_surface_and_forge_file(): void
    {
        $names = $this->toolNames('superadmin');
        foreach (['inventory', 'tree', 'file', 'grep', 'guide'] as $verb) {
            $this->assertContains('agent__scout__' . $verb, $names, "superadmin sees scout.$verb");
        }
        $this->assertContains('agent__forge__file', $names, 'superadmin can write module files');
    }

    #[Test]
    public function the_agent_surface_is_opt_in_on_the_token_scope(): void
    {
        // A curated/scoped token that does NOT include the pseudo-module `agent` gets no agent tools —
        // even for a superadmin. The default set (DEFAULT_MODULES) deliberately excludes it.
        $clipped = $this->toolNames('superadmin', ['cms']);
        $this->assertNotContains('agent__scout__inventory', $clipped);
        $this->assertNotContains('agent__forge__file', $clipped);

        // Widen the token to `agent` and the surface appears (role still decides which verbs).
        $scoped = $this->toolNames('superadmin', ['agent']);
        $this->assertContains('agent__scout__inventory', $scoped);
        $this->assertContains('agent__forge__file', $scoped);
    }

    #[Test]
    public function the_forge_file_tool_is_typed_and_a_write(): void
    {
        $out    = Tiger_Mcp_Server::handle(['id' => 1, 'method' => 'tools/list'], 'superadmin', fn() => null);
        $byName = array_column($out['result']['tools'], null, 'name');
        $schema = $byName['agent__forge__file']['inputSchema'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('path', $schema['properties']);
        $this->assertArrayHasKey('contents', $schema['properties']);
        $this->assertSame(['path', 'contents'], $schema['required']);
        $this->assertFalse($byName['agent__forge__file']['annotations']['readOnlyHint'], 'forge.file writes');
        $this->assertTrue($byName['agent__scout__inventory']['annotations']['readOnlyHint'], 'scout reads');
    }

    // ----- dispatch ----------------------------------------------------------

    private function dispatcher(): FakeAgentMcpController
    {
        return new FakeAgentMcpController(
            new \Zend_Controller_Request_Http(),
            new \Zend_Controller_Response_Http()
        );
    }

    #[Test]
    public function scout_inventory_dispatches_and_maps_to_a_success_envelope(): void
    {
        // superadmin, unscoped token (config null) → the inventory read runs and its payload rides in data.
        $env = $this->dispatcher()->runTool('agent', 'scout', 'inventory', [], null, '', null, 'superadmin');
        $this->assertSame(1, (int) $env->result, 'inventory succeeds for a superadmin');
        $this->assertNotEmpty($env->data['output'], 'the repo map rides in data.output');
    }

    #[Test]
    public function scout_read_is_refused_for_an_admin_by_the_scout_role_gate(): void
    {
        // Even reaching dispatch, Scout self-gates: reading a file is superadmin+. Defense in depth.
        $env = $this->dispatcher()->runTool('agent', 'scout', 'file', ['path' => 'README.md'], null, '', null, 'admin');
        $this->assertSame(0, (int) $env->result, 'admin cannot read files via Scout');
    }

    #[Test]
    public function a_token_not_scoped_to_agent_is_refused_out_of_scope(): void
    {
        $config = ['modules' => ['cms'], 'read_only' => false, 'org_scoped' => false, 'role' => 'superadmin', 'org_id' => ''];
        $env = $this->dispatcher()->runTool('agent', 'scout', 'inventory', [], $config, 'aaaaaaaaaaaa', null, 'superadmin');
        $this->assertSame(0, (int) $env->result, 'out of scope — the token has no agent grant');
        $this->assertStringContainsString('scoped', strtolower($env->messages[0]->message));
    }

    #[Test]
    public function a_read_only_token_can_scout_but_cannot_forge(): void
    {
        $config = ['modules' => ['agent'], 'read_only' => true, 'org_scoped' => false, 'role' => 'superadmin', 'org_id' => ''];

        // A read passes on a read-only token...
        $read = $this->dispatcher()->runTool('agent', 'scout', 'inventory', [], $config, 'bbbbbbbbbbbb', null, 'superadmin');
        $this->assertSame(1, (int) $read->result, 'scout reads are allowed on a read-only token');

        // ...but a file write is refused BEFORE Forge runs (no file is touched).
        $write = $this->dispatcher()->runTool('agent', 'forge', 'file',
            ['path' => 'nope/should-not-write.php', 'contents' => '<?php'], $config, 'bbbbbbbbbbbb', null, 'superadmin');
        $this->assertSame(0, (int) $write->result, 'a read-only token cannot write files');
        $this->assertStringContainsString('read-only', strtolower($write->messages[0]->message));
    }
}

/** Test double: expose the protected dispatch seam so the agent-tool gating can be driven directly. */
class FakeAgentMcpController extends Mcp_ServerController
{
    public function runTool($module, $service, $method, array $args, $config, $prefix, $orgIdentity, $role)
    {
        return $this->_dispatchTool($module, $service, $method, $args, $config, $prefix, $orgIdentity, $role);
    }
}
