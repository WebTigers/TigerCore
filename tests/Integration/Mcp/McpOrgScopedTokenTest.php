<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_ServerController;
use Mcp_Service_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Mcp_Scope;
use Tiger_Mcp_Token;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-511 — org-scoped MCP tokens are re-checked on EVERY request, and can't be app-wide.
 *
 *  - the token's owner must still hold an ACTIVE membership in the TOKEN'S org; removed / suspended →
 *    refused (403), never a fall back to the owner's own identity;
 *  - the role comes from THAT membership — not the owner's first membership in some other org;
 *  - a role holding `Tiger_Tenancy_AllOrgs` can't be org-scoped: refused at mint, and refused per
 *    request (catches a member promoted after minting);
 *  - a least-privilege PROFILE role is kept, while the membership is active.
 */
#[CoversClass(Mcp_ServerController::class)]
#[CoversClass(Mcp_Service_Settings::class)]
final class McpOrgScopedTokenTest extends ControllerTestCase
{
    private $origConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $this->origConfig ? $this->origConfig->toArray() : [];
        $arr['tiger']['mcp']['enabled'] = 1;
        $arr['tiger']['log']['writer']  = 'null';   // dispatch/refusal log lines → null sink (strict output)
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));
        \Tiger_Log::reset();
        Zend_Registry::set('Zend_Acl', new \Tiger_Acl_Acl());
        FakeOrgMcpController::$body = '';
        // The minting calls run as a Bearer/API call does — CSRF is a cookie-mode defence (TIGER-528).
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        if ($this->origConfig !== null) { Zend_Registry::set('Zend_Config', $this->origConfig); }
        \Tiger_Log::reset();
        Tiger_Mcp_Scope::resetRegistered();
        unset($_SERVER['HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    /** A real user (user_credential FKs to user) with a membership per [org => role]. */
    private function seedMember(array $memberships): string
    {
        $uid = (string) (new Tiger_Model_User())->insert([
            'email' => 'org-tok-' . bin2hex(random_bytes(3)) . '@test.local',
            'username' => 'orgtok' . bin2hex(random_bytes(3)), 'status' => 'active',
        ]);
        foreach ($memberships as $org => $role) {
            $this->seedIdentityRows($uid, $org, $role);
        }
        return $uid;
    }

    private function setMembershipStatus(string $uid, string $org, string $status, int $deleted = 0): void
    {
        $this->db->update('org_user', ['status' => $status, 'deleted' => $deleted],
            $this->db->quoteInto('user_id = ?', $uid) . ' AND ' . $this->db->quoteInto('org_id = ?', $org));
    }

    private function orgIdentity(string $uid, array $config)
    {
        $c = (new \ReflectionClass(FakeOrgMcpController::class))->newInstanceWithoutConstructor();
        return $c->pubEffective((object) ['user_id' => $uid, 'org_id' => 'org-first', 'role' => 'user'],
            $config + ['org_scoped' => true, 'role' => '', 'profile' => false, 'modules' => ['cms'], 'read_only' => false]);
    }

    // ---- the per-request membership re-check ----------------------------------------------------

    #[Test]
    public function the_role_comes_from_the_token_orgs_membership_not_the_first_one(): void
    {
        $uid = $this->seedMember(['org-first' => 'user', 'org-tok' => 'manager']);
        $id  = $this->orgIdentity($uid, ['org_id' => 'org-tok']);

        $this->assertIsObject($id);
        $this->assertSame('org-tok', $id->org_id);
        $this->assertSame('manager', $id->role, "the token org's membership role, not the Bearer identity's 'user'");
        $this->assertNull($id->user_id, 'still acts AS THE ORG');
    }

    #[Test]
    public function a_removed_or_suspended_member_is_refused(): void
    {
        $uid = $this->seedMember(['org-first' => 'user', 'org-tok' => 'manager']);

        $this->setMembershipStatus($uid, 'org-tok', 'suspended');
        $this->assertFalse($this->orgIdentity($uid, ['org_id' => 'org-tok']), 'suspended → refused');

        $this->setMembershipStatus($uid, 'org-tok', 'active', 1);
        $this->assertFalse($this->orgIdentity($uid, ['org_id' => 'org-tok']), 'removed (soft-deleted) → refused');

        $this->assertFalse($this->orgIdentity($uid, ['org_id' => 'org-never']), 'never a member → refused');
        $this->assertFalse($this->orgIdentity($uid, ['org_id' => '']), 'a token with no org → refused');
    }

    #[Test]
    public function an_app_wide_role_in_the_token_org_is_refused(): void
    {
        // Promoted to admin AFTER minting: admin holds Tiger_Tenancy_AllOrgs, so it is not org-scoped.
        $uid = $this->seedMember(['org-tok' => 'admin']);
        $this->assertFalse($this->orgIdentity($uid, ['org_id' => 'org-tok']));
    }

    #[Test]
    public function a_profile_role_is_kept_while_the_membership_is_active(): void
    {
        $uid = $this->seedMember(['org-tok' => 'admin']);
        $id  = $this->orgIdentity($uid, ['org_id' => 'org-tok', 'role' => 'observer', 'profile' => true]);
        $this->assertIsObject($id, 'a narrow profile role is org-bound even for an admin owner');
        $this->assertSame('observer', $id->role);

        $this->setMembershipStatus($uid, 'org-tok', 'suspended');
        $this->assertFalse($this->orgIdentity($uid, ['org_id' => 'org-tok', 'role' => 'observer', 'profile' => true]),
            'the profile does not outlive the membership');
    }

    #[Test]
    public function over_the_wire_a_removed_member_gets_403_not_their_own_identity(): void
    {
        $uid = $this->seedMember(['org-tok' => 'manager']);
        $tok = (new Tiger_Model_UserCredential())->createToken($uid);
        Tiger_Mcp_Token::saveConfig($tok['credential_id'], [
            'modules' => ['cms'], 'read_only' => true, 'org_scoped' => true, 'role' => '', 'org_id' => 'org-tok',
        ]);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $tok['token'];

        [$code] = $this->post(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
        $this->assertSame(200, $code, 'an active member is served');

        $this->setMembershipStatus($uid, 'org-tok', 'active', 1);
        [$code, $out] = $this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $this->assertSame(403, $code, 'the token died with the membership');
        $this->assertSame(-32003, $out['error']['code']);
        $this->assertArrayNotHasKey('result', $out);
    }

    // ---- mint ------------------------------------------------------------------------------------

    #[Test]
    public function minting_an_org_scoped_token_for_an_app_wide_role_is_refused(): void
    {
        $uid = $this->seedMember(['org-test' => 'admin']);
        $this->login($uid, 'org-test', 'admin');

        $r = (new Mcp_Service_Settings(['action' => 'mintToken', 'scopes' => 'cms', 'org_scoped' => 1]))->getResponse();
        $this->assertSame(0, (int) $r->result, 'an admin-role org token would reach every org — a contradiction');
        $this->assertStringContainsString('org_scoped_app_wide', json_encode($r->messages));
        $this->assertSame([], (new Tiger_Model_UserCredential())->tokensFor($uid), 'nothing was minted');

        // The same admin may still mint a plain (not org-scoped) token...
        $plain = (new Mcp_Service_Settings(['action' => 'mintToken', 'scopes' => 'cms']))->getResponse();
        $this->assertSame(1, (int) $plain->result);
    }

    #[Test]
    public function minting_an_org_scoped_token_against_a_narrow_profile_is_allowed(): void
    {
        $uid = $this->seedMember(['org-test' => 'admin']);
        $this->login($uid, 'org-test', 'admin');
        Tiger_Mcp_Scope::register([
            'key' => 'test.observer', 'label' => 'Test observer', 'module' => 'cms',
            'role' => 'observer', 'read_only' => 1, 'resources' => ['page' => 'datatable'],
        ]);

        $r = (new Mcp_Service_Settings(['action' => 'mintToken', 'scopes' => 'test.observer', 'org_scoped' => 1]))->getResponse();
        $this->assertSame(1, (int) $r->result, json_encode($r->messages));
        $c = Tiger_Mcp_Token::config($r->data['credential_id']);
        $this->assertTrue($c['org_scoped']);
        $this->assertSame('org-test', $c['org_id']);
    }

    private function post(array $msg): array
    {
        FakeOrgMcpController::$body = json_encode($msg);
        $res = $this->dispatchAction(FakeOrgMcpController::class, 'index', [], 'POST');
        return [$res->getHttpResponseCode(), json_decode($this->echoed, true)];
    }
}

/** Test double: inject the JSON-RPC body and expose the effective-identity decision. */
class FakeOrgMcpController extends Mcp_ServerController
{
    public static $body = '';

    protected function _rawBody()
    {
        return self::$body;
    }

    public function pubEffective($identity, array $config)
    {
        return $this->_effectiveIdentity($identity, $config);
    }
}
