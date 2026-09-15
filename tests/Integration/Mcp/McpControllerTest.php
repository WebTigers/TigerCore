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
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION', 'HTTP_SEC_FETCH_SITE', 'HTTP_ORIGIN', 'CONTENT_TYPE', 'HTTP_CONTENT_TYPE'] as $k) { unset($_SERVER[$k]); }
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

    /** A presented Bearer that does not verify is 401 — never a silent downgrade to the guest surface (TIGER-138). */
    #[Test]
    public function an_invalid_bearer_is_401_not_guest(): void
    {
        $this->enableMcp();
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tgr_deadbeefdead_' . str_repeat('0', 48);
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/list']);
        $this->assertSame(401, $code);
        $this->assertSame(7, $out['id']);
        $this->assertStringContainsString('not valid', $out['error']['message']);
        $this->assertArrayNotHasKey('result', $out, 'no tool list for a bad key');
    }

    /** Apache/FPM may surface the header only as REDIRECT_HTTP_AUTHORIZATION (the .htaccess rewrite-env fallback). */
    #[Test]
    public function the_authorization_header_is_read_from_the_redirect_env_too(): void
    {
        $this->enableMcp();
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer tgr_deadbeefdead_' . str_repeat('0', 48);
        [$code] = $this->post(['jsonrpc' => '2.0', 'id' => 8, 'method' => 'tools/list']);
        $this->assertSame(401, $code, 'the token was SEEN (and refused) — it did not vanish into the guest path');
    }

    /** A session (cookie) identity is honoured only from its own origin: the CSRF shape is refused (F4). */
    #[Test]
    public function a_cross_site_request_on_a_session_is_403(): void
    {
        $this->enableMcp();
        $this->loginAs('admin');
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/list']);
        $this->assertSame(403, $code);
        $this->assertStringContainsString('own origin', $out['error']['message']);

        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 10, 'method' => 'tools/list']);
        $this->assertSame(200, $code);
        $this->assertNotEmpty($out['result']['tools'], 'the same admin, same-origin, is served');
    }

    /** JSON-RPC is JSON: a text/plain body (the enctype a cross-site form can send) is 415. */
    #[Test]
    public function a_non_json_content_type_is_415(): void
    {
        $this->enableMcp();
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        [$code] = $this->post(['jsonrpc' => '2.0', 'id' => 11, 'method' => 'tools/list']);
        $this->assertSame(415, $code);
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
