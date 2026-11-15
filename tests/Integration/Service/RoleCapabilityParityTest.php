<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Acl_Acl;
use Tiger_Service_Authentication;
use Tiger_Session_SaveHandler_DbTable;
use Zend_Registry;

/**
 * TIGER-511 — two role LISTS became named ACL capabilities, with the SAME access role by role.
 *
 *  - `Tiger_Service_Authentication::homeFor()` used `ADMIN_HOME_ROLES = ['admin','superadmin','developer']`
 *    to pick `/admin` vs `/account`; it now asks `Tiger_Admin_Home` / `enter`.
 *  - `Tiger_Session_SaveHandler_DbTable` used `$_privileged` (the same three roles) to pick the short TTL
 *    tier; it now asks `Tiger_Session_Privileged` / `short_ttl`, and when the ACL isn't built yet it
 *    falls to the SHORTER tier rather than the long one.
 *
 * The parity table below is the old list, written out — every core role must land exactly where it did.
 */
#[CoversClass(Tiger_Service_Authentication::class)]
#[CoversClass(Tiger_Session_SaveHandler_DbTable::class)]
#[CoversClass(Tiger_Acl_Acl::class)]
final class RoleCapabilityParityTest extends IntegrationTestCase
{
    /** The roles the removed lists named — the ONLY ones that got /admin and the short session tier. */
    private const OLD_LIST = ['admin', 'superadmin', 'developer'];

    /** Whatever ACL the process had before — several cases unset it; put it back for later tests. */
    private $priorAcl = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorAcl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        // In-memory auth store from the start: several cases never log in, and the harness tearDown's
        // clearIdentity() on the DEFAULT (session-backed) store would start a real PHP session and leave
        // Zend_Session read-only for every later test in the process.
        \Zend_Auth::getInstance()->setStorage(new \Zend_Auth_Storage_NonPersistent());
    }

    protected function tearDown(): void
    {
        if ($this->priorAcl !== null) {
            Zend_Registry::set('Zend_Acl', $this->priorAcl);
        } elseif (Zend_Registry::isRegistered('Zend_Acl')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        }
        parent::tearDown();
    }

    public static function roles(): array
    {
        $out = [];
        foreach (['guest', 'user', 'manager', 'supermanager', 'admin', 'superadmin', 'developer'] as $r) {
            $out[$r] = [$r];
        }
        return $out;
    }

    private function handler(): Tiger_Session_SaveHandler_DbTable
    {
        return new Tiger_Session_SaveHandler_DbTable([
            'name'           => 'session',
            'primary'        => 'session_id',
            'modifiedColumn' => 'modified',
            'dataColumn'     => 'data',
            'lifetimeColumn' => 'lifetime',
        ]);
    }

    private function lifetimeFor(string $id): int
    {
        return (int) $this->db->fetchOne('SELECT lifetime FROM session WHERE session_id = ?', [$id]);
    }

    #[Test]
    #[DataProvider('roles')]
    public function home_for_lands_every_role_where_the_old_list_did(string $role): void
    {
        Zend_Registry::set('Zend_Acl', new Tiger_Acl_Acl());
        $expected = in_array($role, self::OLD_LIST, true) ? '/admin' : '/account';
        $this->assertSame($expected, Tiger_Service_Authentication::homeFor((object) ['role' => $role]), "role $role");
    }

    #[Test]
    #[DataProvider('roles')]
    public function the_session_tier_for_every_role_matches_the_old_list(string $role): void
    {
        if ($role === 'guest') {
            // An empty in-memory store = no identity. (Not logout(): clearing a default SESSION-backed
            // Zend_Auth store would start a real session and leave Zend_Session read-only for later tests.)
            \Zend_Auth::getInstance()->setStorage(new \Zend_Auth_Storage_NonPersistent());
            Zend_Registry::set('Zend_Acl', new Tiger_Acl_Acl());
            $expected = 86400;   // no signed-in user → the guest tier
        } else {
            $this->login('user-tier-' . $role, 'org-tier', $role);
            $expected = in_array($role, self::OLD_LIST, true) ? 28800 : 604800;
        }
        $id = substr('sesstier' . $role . str_repeat('0', 40), 0, 40);
        $this->handler()->write($id, 'x');
        $this->assertSame($expected, $this->lifetimeFor($id), "role $role");
    }

    #[Test]
    public function an_app_role_that_extends_admin_inherits_both_capabilities(): void
    {
        // The point of the conversion: an app-defined admin-class role needs NO code change.
        $acl = new Tiger_Acl_Acl();
        $acl->addRole(new \Zend_Acl_Role('tenantops'), 'admin');
        Zend_Registry::set('Zend_Acl', $acl);

        $this->assertSame('/admin', Tiger_Service_Authentication::homeFor((object) ['role' => 'tenantops']));
        $this->assertTrue(Tiger_Acl_Acl::roleIsAllowed('tenantops', Tiger_Session_SaveHandler_DbTable::RESOURCE_PRIVILEGED, Tiger_Session_SaveHandler_DbTable::PRIVILEGE_SHORT_TTL));
    }

    #[Test]
    public function with_no_acl_home_for_fails_to_the_account_surface(): void
    {
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        $this->assertSame('/account', Tiger_Service_Authentication::homeFor((object) ['role' => 'admin']),
            'cannot ask → the lower surface, never /admin by default');
    }

    #[Test]
    public function with_no_acl_an_authenticated_session_gets_the_shorter_tier(): void
    {
        // A session is written at the end of the request; on an early-failing request the ACL may not be
        // built. A plain member must NOT get the long 7d tier by default then — the shorter tier wins.
        $this->login('user-noacl', 'org-noacl', 'user');
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        $id = 'sessnoaclxxxxxxxxxxxxxxxxxxxxxxxxxxxx01';
        $this->handler()->write($id, 'x');
        $this->assertSame(28800, $this->lifetimeFor($id), 'min(privileged 8h, authed 7d)');

        // ...and a guest keeps the guest tier (there is no privilege question to ask).
        $this->logout();
        $gid = 'sessnoaclxxxxxxxxxxxxxxxxxxxxxxxxxxxx02';
        $this->handler()->write($gid, 'cart=1');
        $this->assertSame(86400, $this->lifetimeFor($gid));
    }

    #[Test]
    public function role_is_allowed_fails_closed(): void
    {
        Zend_Registry::set('Zend_Acl', new Tiger_Acl_Acl());
        $this->assertFalse(Tiger_Acl_Acl::roleIsAllowed('', 'Tiger_Admin_Home', 'enter'), 'empty role');
        $this->assertFalse(Tiger_Acl_Acl::roleIsAllowed('nosuchrole', 'Tiger_Admin_Home', 'enter'), 'unknown role');
        $this->assertFalse(Tiger_Acl_Acl::roleIsAllowed('developer', 'Nope_Not_A_Resource', 'x'), 'ungoverned resource');
        $this->assertTrue(Tiger_Acl_Acl::isAvailable());
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        $this->assertFalse(Tiger_Acl_Acl::isAvailable());
        $this->assertFalse(Tiger_Acl_Acl::roleIsAllowed('developer', 'Tiger_Admin_Home', 'enter'), 'no ACL');
    }
}
