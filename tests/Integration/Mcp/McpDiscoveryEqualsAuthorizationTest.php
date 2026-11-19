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
 * TIGER-498 — discovery == authorization (TIGERMCP §4): `tools/list` must never advertise a tool the same
 * token's `tools/call` would refuse. A read-only token used to list every write (blog__post__delete …) and
 * only learn "This token is read-only." on the call. The controller now decides the token policy in ONE
 * place (`_tokenDeny`) and hands it to `tools/list` as a filter, so the list and the call can't disagree.
 */
#[CoversClass(Tiger_Mcp_Server::class)]
#[CoversClass(Mcp_ServerController::class)]
final class McpDiscoveryEqualsAuthorizationTest extends ControllerTestCase
{
    private $origConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $this->origConfig ? $this->origConfig->toArray() : [];
        $arr['tiger']['log']['writer'] = 'null';
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));
        \Tiger_Log::reset();
        $this->login('u-498', 'org-test', 'superadmin');
    }

    protected function tearDown(): void
    {
        if ($this->origConfig !== null) { Zend_Registry::set('Zend_Config', $this->origConfig); }
        \Tiger_Log::reset();
        parent::tearDown();
    }

    private function controller498(): PolicyProbeMcpController
    {
        return new PolicyProbeMcpController(new \Zend_Controller_Request_Http(), new \Zend_Controller_Response_Http());
    }

    /** tools/list exactly as the endpoint runs it for this token config. */
    private function listFor(?array $config, string $role): array
    {
        $allowed = $config['modules'] ?? null;
        $out = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], $role, fn() => null,
            $allowed, null, $this->controller498()->permits($config));
        return array_column($out['result']['tools'], 'name');
    }

    private function token(array $over = []): array
    {
        return $over + ['modules' => ['cms', 'blog'], 'read_only' => false, 'org_scoped' => false, 'role' => 'admin', 'org_id' => '', 'resources' => []];
    }

    #[Test]
    public function a_read_only_token_lists_no_write_tools(): void
    {
        $names = $this->listFor($this->token(['read_only' => true]), 'admin');

        $this->assertContains('blog__post__datatable', $names, 'reads stay listed');
        $this->assertNotContains('blog__post__save', $names, 'a write is not advertised to a read-only token');
        $this->assertNotContains('blog__post__delete', $names, 'nor a destructive write');
        foreach ($names as $n) {
            $p = Tiger_Mcp_Server::parseToolName($n);
            if ($p === null || $p[0] === 'agent') { continue; }
            $this->assertContains(strtolower($p[2]), \Tiger_Agent_Forge::READ_VERBS, "$n is listed to a read-only token but is a write");
        }
    }

    #[Test]
    public function a_writable_token_still_lists_its_writes(): void
    {
        $names = $this->listFor($this->token(), 'admin');
        $this->assertContains('blog__post__save', $names);
        $this->assertContains('blog__post__delete', $names);
    }

    #[Test]
    public function every_listed_tool_is_one_the_same_token_may_call(): void
    {
        // The invariant itself, across token shapes: list ⊆ what _tokenDeny (the tools/call gate) allows.
        $shapes = [
            'read-only'      => $this->token(['read_only' => true]),
            'resource fence' => $this->token(['resources' => ['post' => ['datatable', 'save']]]),
            'agent ro'       => $this->token(['modules' => ['agent'], 'read_only' => true, 'role' => 'superadmin']),
        ];
        $c = $this->controller498();
        foreach ($shapes as $label => $config) {
            foreach ($this->listFor($config, 'superadmin') as $n) {
                if ($n === Tiger_Mcp_Server::SKILL_TOOL) { continue; }   // know-how, any token (TIGER-532)
                $p = Tiger_Mcp_Server::parseToolName($n);
                $this->assertNull($c->deny($p[0], $p[1], $p[2], $config), "[$label] $n is listed but its call would be refused");
            }
        }
    }

    #[Test]
    public function a_resource_fence_lists_only_the_fenced_methods(): void
    {
        $names = array_values(array_filter($this->listFor($this->token(['resources' => ['post' => ['datatable']]]), 'admin'),
            fn($n) => $n !== Tiger_Mcp_Server::SKILL_TOOL));
        $this->assertSame(['blog__post__datatable'], $names, 'only the one fenced method of the one fenced service');
    }

    #[Test]
    public function a_read_only_agent_token_lists_scout_but_not_forge(): void
    {
        $names = $this->listFor($this->token(['modules' => ['agent'], 'read_only' => true, 'role' => 'superadmin']), 'superadmin');
        $this->assertContains('agent__scout__inventory', $names);
        $this->assertNotContains('agent__forge__file', $names, 'a read-only token cannot write files, so Forge is not listed');

        $writable = $this->listFor($this->token(['modules' => ['agent'], 'role' => 'superadmin']), 'superadmin');
        $this->assertContains('agent__forge__file', $writable, 'a writable agent token still lists Forge');
    }

    #[Test]
    public function a_session_caller_has_no_token_policy(): void
    {
        // No token config = the full role surface (the role's ACL still gates every call).
        $names = $this->listFor(null, 'admin');
        $this->assertContains('blog__post__save', $names);
    }
}

/** Test double: expose the controller's token policy — the list filter and the call gate. */
class PolicyProbeMcpController extends Mcp_ServerController
{
    public function permits($config): callable { return $this->_permits($config); }
    public function deny($module, $service, $method, $config) { return $this->_tokenDeny($module, $service, $method, $config); }
}
