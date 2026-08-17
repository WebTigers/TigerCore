<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_ServerController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Zend_Config;
use Zend_Registry;

/**
 * Mcp_ServerController — the /mcp endpoint, dispatched through the harness (rendering off). Confirms the
 * OFF-by-default gate (404), and — once enabled — a real JSON-RPC round-trip: initialize returns serverInfo,
 * and tools/list reflects the acting role's ACL-filtered /api catalog. The JSON-RPC body is injected via a
 * test subclass that overrides _rawBody() (the harness has no php://input); tiger.mcp.enabled is toggled by
 * swapping the registered Zend_Config for the test.
 */
#[CoversClass(Mcp_ServerController::class)]
final class McpControllerTest extends ControllerTestCase
{
    private $origConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        FakeMcpController::$body = '';
    }

    protected function tearDown(): void
    {
        if ($this->origConfig !== null) { Zend_Registry::set('Zend_Config', $this->origConfig); }
        parent::tearDown();
    }

    private function enableMcp(): void
    {
        $arr = $this->origConfig ? $this->origConfig->toArray() : [];
        $arr['tiger']['mcp']['enabled'] = 1;
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));
    }

    private function post(array $msg): array
    {
        FakeMcpController::$body = json_encode($msg);
        $res = $this->dispatchAction(FakeMcpController::class, 'index', [], 'POST');
        return [$res->getHttpResponseCode(), json_decode($this->echoed, true)];
    }

    #[Test]
    public function the_endpoint_is_404_when_disabled(): void
    {
        // default: tiger.mcp.enabled is off → the endpoint does not exist.
        [$code] = $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);
        $this->assertSame(404, $code, 'off by default');
    }

    #[Test]
    public function a_browser_get_returns_a_helpful_405_not_a_parse_error(): void
    {
        $this->enableMcp();
        $res = $this->dispatchAction(FakeMcpController::class, 'index', [], 'GET');
        $this->assertSame(405, $res->getHttpResponseCode(), 'GET is not a JSON-RPC call');
        $out = json_decode($this->echoed, true);
        $this->assertSame('Tiger', $out['name']);
        $this->assertStringContainsString('POST a JSON-RPC', $out['message']);
    }

    #[Test]
    public function initialize_returns_serverinfo_when_enabled(): void
    {
        $this->enableMcp();
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]);
        $this->assertSame(200, $code);
        $this->assertSame('Tiger', $out['result']['serverInfo']['name']);
        $this->assertArrayHasKey('tools', $out['result']['capabilities']);
    }

    #[Test]
    public function tools_list_reflects_the_role_catalog_when_enabled(): void
    {
        $this->enableMcp();
        $this->loginAs('admin');
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $this->assertSame(200, $code);
        $this->assertNotEmpty($out['result']['tools'], 'an admin sees a non-empty tool surface');
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+__[a-z]+__[a-z0-9_]+$/i', $out['result']['tools'][0]['name']);
        $this->assertArrayHasKey('inputSchema', $out['result']['tools'][0]);

        // A method that declares @apiRequest gets a TYPED inputSchema from its Form (increment 2).
        $byName = array_column($out['result']['tools'], null, 'name');
        $this->assertArrayHasKey('cms__page__save', $byName, 'the page-save tool is exposed to an admin');
        $schema = $byName['cms__page__save']['inputSchema'];
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('title', $schema['properties'], 'the Cms_Form_Page fields are typed into the schema');
        $this->assertArrayHasKey('slug', $schema['properties']);
    }
}

/** Test double: inject the JSON-RPC body without php://input. */
class FakeMcpController extends Mcp_ServerController
{
    public static $body = '';
    protected function _rawBody()
    {
        return self::$body;
    }
}
