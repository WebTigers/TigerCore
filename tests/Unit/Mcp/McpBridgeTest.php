<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mcp;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;

/**
 * bin/mcp-bridge.php — the stdio<->HTTP relay's decision logic (mcp_bridge_response), tested pure: which
 * message (if any) reaches stdout for a given request + HTTP result. Including the script defines its
 * functions without running the loop (mcp_bridge_is_main() is false under PHPUnit).
 */
final class McpBridgeTest extends UnitTestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/bin/mcp-bridge.php';
    }

    #[Test]
    public function it_relays_a_json_response_verbatim(): void
    {
        $body = '{"jsonrpc":"2.0","id":1,"result":{"ok":true}}';
        $this->assertSame($body, mcp_bridge_response('{"id":1,"method":"ping"}', true, 200, $body, ''));
    }

    #[Test]
    public function a_notification_relays_nothing(): void
    {
        $this->assertNull(mcp_bridge_response('{"method":"notifications/initialized"}', true, 202, '', ''));
        $this->assertNull(mcp_bridge_response('{"method":"notifications/initialized"}', true, 200, '', ''), 'empty body → nothing');
    }

    #[Test]
    public function a_transport_failure_becomes_a_jsonrpc_error_with_the_request_id(): void
    {
        $out = mcp_bridge_response('{"id":5,"method":"tools/list"}', false, 0, '', 'connect timeout');
        $err = json_decode($out, true);
        $this->assertSame(5, $err['id']);
        $this->assertSame(-32000, $err['error']['code']);
        $this->assertStringContainsString('connect timeout', $err['error']['message']);
    }

    #[Test]
    public function a_transport_failure_on_a_notification_stays_silent(): void
    {
        // no id → nothing to answer to
        $this->assertNull(mcp_bridge_response('{"method":"notifications/x"}', false, 0, '', 'boom'));
    }

    #[Test]
    public function a_non_json_response_never_corrupts_the_stream(): void
    {
        $out = mcp_bridge_response('{"id":9,"method":"ping"}', true, 502, '<html>Bad Gateway</html>', '');
        $err = json_decode($out, true);
        $this->assertSame(9, $err['id']);
        $this->assertSame(-32000, $err['error']['code']);
        $this->assertStringContainsString('non-JSON', $err['error']['message']);
    }

    #[Test]
    public function a_disabled_404_json_error_body_is_relayed(): void
    {
        // /mcp when off returns a JSON-RPC error body — it starts with '{' so it is passed through.
        $body = '{"jsonrpc":"2.0","id":null,"error":{"code":-32601,"message":"MCP is not enabled"}}';
        $this->assertSame($body, mcp_bridge_response('{"id":1,"method":"ping"}', true, 404, $body, ''));
    }
}
