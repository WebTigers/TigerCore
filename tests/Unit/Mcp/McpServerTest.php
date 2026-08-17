<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Mcp;
use Tiger_Mcp_Server;

/**
 * Tiger_Mcp_Server — the MCP JSON-RPC engine, tested pure (no HTTP/DB): the lifecycle methods, the tool-name
 * mapping, and that a tools/call is dispatched through the seam and wrapped as MCP content. tools/list (which
 * reflects the live ACL catalog) is covered in the controller integration test.
 */
#[CoversClass(Tiger_Mcp_Server::class)]
#[CoversClass(Tiger_Mcp::class)]
final class McpServerTest extends UnitTestCase
{
    private function handle(array $msg, ?callable $dispatch = null)
    {
        return Tiger_Mcp_Server::handle($msg, 'admin', $dispatch ?: fn() => null);
    }

    #[Test]
    public function initialize_advertises_tools_and_serverinfo(): void
    {
        $r = $this->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);
        $this->assertSame('2.0', $r['jsonrpc']);
        $this->assertSame(1, $r['id']);
        $this->assertSame('2025-06-18', $r['result']['protocolVersion'], 'echoes a supported version');
        $this->assertArrayHasKey('tools', $r['result']['capabilities']);
        $this->assertSame('Tiger', $r['result']['serverInfo']['name']);
        $this->assertNotEmpty($r['result']['serverInfo']['version']);
    }

    #[Test]
    public function initialize_falls_back_to_our_version_for_an_unknown_one(): void
    {
        $r = $this->handle(['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']]);
        $this->assertSame(Tiger_Mcp::PROTOCOL_VERSION, $r['result']['protocolVersion']);
    }

    #[Test]
    public function ping_returns_an_empty_result(): void
    {
        $r = $this->handle(['id' => 7, 'method' => 'ping']);
        $this->assertSame(7, $r['id']);
        $this->assertEquals(new \stdClass(), $r['result']);
    }

    #[Test]
    public function a_notification_gets_no_response(): void
    {
        $this->assertNull($this->handle(['method' => 'notifications/initialized']));
        $this->assertNull($this->handle(['method' => 'notifications/cancelled']));
        $this->assertNull($this->handle(['method' => 'something/unknown']), 'an unknown NOTIFICATION (no id) is ignored');
    }

    #[Test]
    public function an_unknown_method_with_an_id_is_a_method_not_found_error(): void
    {
        $r = $this->handle(['id' => 9, 'method' => 'resources/list']);
        $this->assertSame(-32601, $r['error']['code']);
        $this->assertSame(9, $r['id']);
    }

    #[Test]
    public function a_batch_is_refused(): void
    {
        $r = Tiger_Mcp_Server::handle([['id' => 1, 'method' => 'ping']], 'admin', fn() => null);
        $this->assertSame(-32600, $r['error']['code']);
    }

    #[Test]
    public function tools_call_dispatches_the_named_op_and_wraps_a_success(): void
    {
        $seen = null;
        $dispatch = function ($m, $s, $meth, $args) use (&$seen) {
            $seen = [$m, $s, $meth, $args];
            $env = new \stdClass();
            $env->result = 1; $env->data = ['id' => 'x1']; $env->messages = [];
            return $env;
        };
        $r = $this->handle([
            'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'cms__page__save', 'arguments' => ['title' => 'Hi']],
        ], $dispatch);

        $this->assertSame(['cms', 'page', 'save', ['title' => 'Hi']], $seen, 'name split → module/service/method + args');
        $this->assertFalse($r['result']['isError']);
        $payload = json_decode($r['result']['content'][0]['text'], true);
        $this->assertSame(1, $payload['result']);
        $this->assertSame('x1', $payload['data']['id']);
    }

    #[Test]
    public function tools_call_marks_a_failed_envelope_as_iserror(): void
    {
        $dispatch = function () { $e = new \stdClass(); $e->result = 0; $e->data = null; $e->messages = []; return $e; };
        $r = $this->handle(['id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'cms__page__delete']], $dispatch);
        $this->assertTrue($r['result']['isError'], 'result=0 → isError');
    }

    #[Test]
    public function tools_call_rejects_a_malformed_tool_name(): void
    {
        $called = false;
        $r = $this->handle(['id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'not-a-tool']],
            function () use (&$called) { $called = true; });
        $this->assertTrue($r['result']['isError']);
        $this->assertFalse($called, 'a bad name never reaches dispatch');
        $this->assertStringContainsString('Unknown tool', $r['result']['content'][0]['text']);
    }

    #[Test]
    public function tool_name_round_trips_and_keeps_method_underscores(): void
    {
        $this->assertSame('access__user__datatable', Tiger_Mcp_Server::toolName('access', 'user', 'datatable'));
        $this->assertSame(['agent', 'skills', 'toggle_active'], Tiger_Mcp_Server::parseToolName('agent__skills__toggle_active'));
        $this->assertNull(Tiger_Mcp_Server::parseToolName('onlytwo__parts'));
        $this->assertNull(Tiger_Mcp_Server::parseToolName(''));
    }
}
