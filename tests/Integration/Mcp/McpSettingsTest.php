<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_Service_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mcp;
use Tiger_Model_Config;

/**
 * Mcp_Service_Settings — the enable toggle writes tiger.mcp.enabled to the global config tier, admin-gated.
 */
#[CoversClass(Mcp_Service_Settings::class)]
final class McpSettingsTest extends IntegrationTestCase
{
    private function call(string $method, array $params = []): object
    {
        return (new Mcp_Service_Settings(['action' => $method] + $params))->getResponse();
    }

    #[Test]
    public function save_writes_the_enabled_flag_to_config(): void
    {
        $this->loginAs('admin');
        $cfg = new Tiger_Model_Config();

        $on = $this->call('save', ['enabled' => 1]);
        $this->assertSame(1, (int) $on->result);
        $this->assertTrue($on->data['enabled']);
        $this->assertSame('1', (string) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED));

        $off = $this->call('save', ['enabled' => 0]);
        $this->assertSame(1, (int) $off->result);
        $this->assertFalse($off->data['enabled']);
        $this->assertSame('0', (string) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED));
    }

    #[Test]
    public function save_is_denied_for_a_non_admin(): void
    {
        $this->login('u1', 'org-test', 'user');
        $res = $this->call('save', ['enabled' => 1]);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }

    // ---- token revocation must be owner-scoped (TIGER-66) ----------------------

    #[Test]
    public function revoking_a_token_you_do_not_own_changes_nothing(): void
    {
        // revokeToken()'s predicate is owner-scoped and its boolean IS that answer. The service used
        // to ignore it and clear the policy anyway — leaving the victim's credential alive but wiped
        // back to the permissive defaults (read_only=false, org_scoped=false), i.e. silently WIDENING
        // somebody else's live token. It also reported success while doing it.
        $owner  = 'u-mcp-owner';
        $this->seedIdentityRows($owner, '', '');            // user_credential.user_id is FK-constrained
        $cred   = (new \Tiger_Model_UserCredential())->createToken($owner);
        $credId = (string) $cred['credential_id'];

        \Tiger_Mcp_Token::saveConfig($credId, ['modules' => ['cms'], 'read_only' => true, 'org_scoped' => true]);

        $this->seedIdentityRows('u-mcp-attacker', 'org-test', 'admin');
        $this->login('u-mcp-attacker', 'org-test', 'admin');   // an admin who does NOT own that credential
        $svc = new \Mcp_Service_Settings();
        $svc->revokeToken(['credential_id' => $credId]);

        $this->assertSame(0, (int) $svc->getResponse()->result, 'the call is refused, not silently "successful"');

        $policy = \Tiger_Mcp_Token::config($credId);
        $this->assertTrue((bool) $policy['read_only'],  "the owner's read_only restriction survives");
        $this->assertTrue((bool) $policy['org_scoped'], "the owner's org_scoped restriction survives");
        $this->assertSame(['cms'], array_values((array) $policy['modules']), "the owner's module allow-list survives");
    }

    #[Test]
    public function revoking_your_own_token_still_clears_its_policy(): void
    {
        // The positive control: the fix must not break ordinary owner-authorized cleanup.
        $owner  = 'u-mcp-self';
        $this->seedIdentityRows($owner, '', '');
        $cred   = (new \Tiger_Model_UserCredential())->createToken($owner);
        $credId = (string) $cred['credential_id'];
        \Tiger_Mcp_Token::saveConfig($credId, ['modules' => ['cms'], 'read_only' => true]);

        $this->login($owner, 'org-test', 'admin');
        $svc = new \Mcp_Service_Settings();
        $svc->revokeToken(['credential_id' => $credId]);

        $this->assertSame(1, (int) $svc->getResponse()->result);
        $this->assertFalse((bool) \Tiger_Mcp_Token::config($credId)['read_only'], 'policy is cleared on a real revoke');
    }
}
