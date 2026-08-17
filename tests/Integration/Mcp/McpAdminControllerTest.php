<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;

/**
 * Mcp_AdminController — the Connect screen shell + the bridge download, dispatched through the harness.
 */
#[CoversClass(Mcp_AdminController::class)]
final class McpAdminControllerTest extends ControllerTestCase
{
    #[Test]
    public function the_connect_screen_exposes_the_url_and_bridge_path(): void
    {
        $this->loginAs('admin');
        $res = $this->dispatchAction(Mcp_AdminController::class, 'index', [], 'GET');
        $this->assertSame(200, $res->getHttpResponseCode());

        $view = $this->controller()->view;
        $this->assertStringContainsString('MCP', $view->title);
        $this->assertStringEndsWith('/mcp', $view->mcpUrl, 'the config points at the /mcp endpoint');
        $this->assertStringEndsWith('bin/mcp-bridge.php', $view->bridge, 'the download path is the shipped bridge');
    }

    #[Test]
    public function download_streams_the_stdio_bridge(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Mcp_AdminController::class, 'download', [], 'GET');
        $this->assertStringContainsString('mcp_bridge_run', $this->echoed, 'the zero-Node bridge script is served');
        $this->assertStringStartsWith('#!/usr/bin/env php', $this->echoed);
    }
}
