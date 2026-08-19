<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Agent_Contract;
use Tiger_Agent_Forge;
use Tiger_Agent_Mcp_Client;

/**
 * The OUTBOUND MCP pieces, pure: the `mcp` action's contract shape (normalize + read/write classification +
 * auto-rank) and the client's JSON-RPC response parsing (its HTTP seam overridden with canned replies).
 */
#[CoversClass(Tiger_Agent_Contract::class)]
#[CoversClass(Tiger_Agent_Mcp_Client::class)]
final class AgentMcpClientTest extends UnitTestCase
{
    // ----- contract -----------------------------------------------------------

    #[Test]
    public function an_mcp_action_normalizes_and_is_a_write(): void
    {
        $a = Tiger_Agent_Contract::normalizeAction(['type' => 'mcp', 'connection' => 'gh-1', 'tool' => 'create_issue', 'args' => ['title' => 'x'], 'reason' => 'file a bug']);
        $this->assertSame('mcp', $a['type']);
        $this->assertSame('gh-1', $a['connection']);
        $this->assertSame('create_issue', $a['tool']);
        $this->assertSame(['title' => 'x'], $a['args']);

        $this->assertFalse(Tiger_Agent_Contract::isRead('mcp'), 'an mcp call is a write (approval-gated)');
        $this->assertContains('mcp', Tiger_Agent_Contract::WRITE_TYPES);
    }

    #[Test]
    public function a_malformed_mcp_action_is_dropped(): void
    {
        $this->assertNull(Tiger_Agent_Contract::normalizeAction(['type' => 'mcp', 'tool' => 'x']), 'no connection → dropped');
        $this->assertNull(Tiger_Agent_Contract::normalizeAction(['type' => 'mcp', 'connection' => 'c']), 'no tool → dropped');
    }

    #[Test]
    public function mcp_auto_rank_is_a_guarded_write(): void
    {
        $this->assertSame(0, Tiger_Agent_Forge::autoRank(['type' => 'mcp']), 'auto-runs in auto/yolo, proposed in ask');
    }

    // ----- client parsing (HTTP seam overridden) ------------------------------

    #[Test]
    public function list_tools_parses_the_remote_catalog(): void
    {
        FakeMcpClient::$responses = [
            'initialize' => '{"jsonrpc":"2.0","id":1,"result":{"capabilities":{}}}',
            'tools/list' => '{"jsonrpc":"2.0","id":1,"result":{"tools":[{"name":"create_issue","description":"File an issue","inputSchema":{"type":"object","properties":{"title":{"type":"string"}}}}]}}',
        ];
        $tools = FakeMcpClient::listTools(['url' => 'https://x/mcp']);
        $this->assertCount(1, $tools);
        $this->assertSame('create_issue', $tools[0]['name']);
        $this->assertArrayHasKey('title', $tools[0]['inputSchema']['properties']);
    }

    #[Test]
    public function call_tool_flattens_content_to_text(): void
    {
        FakeMcpClient::$responses = ['tools/call' => '{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"issue #42 created"}],"isError":false}}'];
        $r = FakeMcpClient::callTool(['url' => 'https://x/mcp'], 'create_issue', ['title' => 'Bug']);
        $this->assertTrue($r['ok']);
        $this->assertSame('issue #42 created', $r['text']);
    }

    #[Test]
    public function call_tool_reports_a_tool_error(): void
    {
        FakeMcpClient::$responses = ['tools/call' => '{"jsonrpc":"2.0","id":1,"result":{"content":[{"type":"text","text":"nope"}],"isError":true}}'];
        $r = FakeMcpClient::callTool(['url' => 'https://x/mcp'], 't', []);
        $this->assertFalse($r['ok']);
    }

    #[Test]
    public function a_transport_failure_yields_no_tools_and_a_failed_call(): void
    {
        FakeMcpClient::$responses = [];   // _post returns null for everything
        $this->assertSame([], FakeMcpClient::listTools(['url' => 'https://x/mcp']));
        $this->assertFalse(FakeMcpClient::callTool(['url' => 'https://x/mcp'], 't', [])['ok']);
    }
}

/** A client whose HTTP is canned by method (no network). */
final class FakeMcpClient extends Tiger_Agent_Mcp_Client
{
    /** @var array<string,?string> JSON-RPC response body by request method */
    public static $responses = [];

    protected static function _post($url, array $headers, $payload)
    {
        $msg = json_decode($payload, true);
        return self::$responses[$msg['method'] ?? ''] ?? null;
    }
}
