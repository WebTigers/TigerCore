<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_Service_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Ajax_ServiceFactory;
use Tiger_Mcp_Server;
use Tiger_Mcp_Token;
use Tiger_Model_Option;
use Tiger_Model_User;
use Zend_Controller_Request_Http;

/**
 * Increment 4 — the MCP guardrails: token scope (allow-list + read-only) in the option tier, the soft rate
 * limit, scoped minting via Mcp_Service_Settings, the ServiceFactory identity override (org-acting dispatch),
 * and the tools/list scope clip.
 */
#[CoversClass(Tiger_Mcp_Token::class)]
#[CoversClass(Mcp_Service_Settings::class)]
final class McpTokenScopeTest extends IntegrationTestCase
{
    private function call(string $method, array $params = []): object
    {
        return (new Mcp_Service_Settings(['action' => $method] + $params))->getResponse();
    }

    /** Seed a REAL user (user_credential FKs to user) and log in as an admin who IS that user. */
    private function seedAdmin(): string
    {
        $uid = (new Tiger_Model_User())->insert(['email' => 'mcp-admin@test.local', 'username' => 'mcpadmin', 'status' => 'active']);
        $this->login($uid, 'org-test', 'admin');
        return (string) $uid;
    }

    #[Test]
    public function token_config_round_trips_through_the_option_tier(): void
    {
        $cid = 'cred-abc';
        $this->assertContains('cms', Tiger_Mcp_Token::config($cid)['modules'], 'no saved config → curated default');

        Tiger_Mcp_Token::saveConfig($cid, ['modules' => ['cms', 'blog'], 'read_only' => true, 'org_scoped' => true, 'role' => 'admin', 'org_id' => 'org-x']);
        $c = Tiger_Mcp_Token::config($cid);
        $this->assertSame(['cms', 'blog'], $c['modules']);
        $this->assertTrue($c['read_only']);
        $this->assertTrue($c['org_scoped']);
        $this->assertSame('org-x', $c['org_id']);
    }

    #[Test]
    public function meter_enforces_the_per_token_cap(): void
    {
        $prefix = 'abcdef012345';
        (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '',
            Tiger_Mcp_Token::OPT_METER . '.' . $prefix, ['window' => time(), 'count' => Tiger_Mcp_Token::RATE_CAP - 1]);
        $this->assertTrue(Tiger_Mcp_Token::meter($prefix),  'the last call within the cap');
        $this->assertFalse(Tiger_Mcp_Token::meter($prefix), 'now at the cap → denied');
    }

    #[Test]
    public function mint_token_saves_the_scope_lists_it_and_revokes_it(): void
    {
        $this->seedAdmin();

        $r = $this->call('mintToken', ['modules' => 'cms,blog', 'read_only' => 1]);
        $this->assertSame(1, (int) $r->result);
        $this->assertStringStartsWith('tgr_', $r->data['token']);

        $c = Tiger_Mcp_Token::config($r->data['credential_id']);
        $this->assertSame(['cms', 'blog'], $c['modules']);
        $this->assertTrue($c['read_only']);

        $mine = array_values(array_filter($this->call('tokens')->data['tokens'], fn($t) => $t['credential_id'] === $r->data['credential_id']));
        $this->assertNotEmpty($mine, 'the token is listed with its scope');
        $this->assertSame(['cms', 'blog'], $mine[0]['modules']);
        $this->assertTrue($mine[0]['read_only']);

        $this->assertSame(1, (int) $this->call('revokeToken', ['credential_id' => $r->data['credential_id']])->result);
    }

    #[Test]
    public function mint_defaults_to_the_curated_set(): void
    {
        $this->seedAdmin();
        $r = $this->call('mintToken', []);
        $this->assertSame(Tiger_Mcp_Token::DEFAULT_MODULES, Tiger_Mcp_Token::config($r->data['credential_id'])['modules']);
    }

    #[Test]
    public function mint_is_admin_gated(): void
    {
        $this->login('u1', 'org-test', 'user');
        $this->assertSame(0, (int) $this->call('mintToken')->result);
    }

    #[Test]
    public function tools_list_is_clipped_to_the_allowed_modules(): void
    {
        $out   = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'admin', fn() => null, ['cms']);
        $tools = $out['result']['tools'];
        $this->assertNotEmpty($tools);
        foreach ($tools as $t) {
            $this->assertStringStartsWith('cms__', $t['name'], 'the scope clips tools/list to the allow-list');
        }
    }

    #[Test]
    public function service_factory_identity_override_drives_the_acl(): void
    {
        $req = new Zend_Controller_Request_Http();
        $req->setParam('svc_module', 'cms');
        $req->setParam('svc_service', 'page');
        $req->setParam('svc_action', 'datatable');   // an admin-gated read

        $asAdmin = (new Tiger_Ajax_ServiceFactory($req, (object) ['user_id' => null, 'org_id' => 'org-test', 'role' => 'admin']))->getResponse();
        $this->assertSame(1, (int) $asAdmin->result, 'the org-acting admin override is allowed');

        $asGuest = (new Tiger_Ajax_ServiceFactory($req, (object) ['user_id' => null, 'org_id' => null, 'role' => 'guest']))->getResponse();
        $this->assertSame(0, (int) $asGuest->result, 'a guest override is denied by the same ACL');
    }
}
