<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Service_Service;
use Zend_Acl;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Registry;

/** A concrete service exposing the protected org-scope primitives. */
final class _OrgScopeFixtureService extends Tiger_Service_Service
{
    public function scope(): ?array { return $this->_orgScope(); }
    public function inScope(string $orgId): bool { return $this->_inOrgScope($orgId); }
    public function org(): string { return $this->_orgId(); }
}

/**
 * Tiger_Service_Service::_orgScope() / _inOrgScope() — the one place a read's tenant scope is decided
 * (ACL.md §1a).
 *
 * The contract under test is the standard itself: a tenant-scoped role reads its own org plus the
 * shared `''` tier, an APP-WIDE role is not scoped AT ALL (null, meaning "apply no org predicate"),
 * and the difference is decided by a CAPABILITY grant rather than by comparing role strings — so an
 * app can move the cut line in acl.ini without touching a service.
 */
#[CoversClass(Tiger_Service_Service::class)]
final class OrgScopeTest extends UnitTestCase
{
    private const ORG_A = 'org-aaaa';
    private const ORG_B = 'org-bbbb';

    private _OrgScopeFixtureService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new _OrgScopeFixtureService();
    }

    /** The shipped shape: the capability is granted at `admin`, so admin-class roles inherit it. */
    private function registerAcl(): void
    {
        $acl = new Zend_Acl();
        $acl->addRole('guest');
        $acl->addRole('user', 'guest');
        $acl->addRole('manager', 'user');
        $acl->addRole('supermanager', 'manager');
        $acl->addRole('admin', 'supermanager');
        $acl->addRole('superadmin', 'admin');
        $acl->addResource(Tiger_Service_Service::RESOURCE_ALL_ORGS);
        $acl->allow('admin', Tiger_Service_Service::RESOURCE_ALL_ORGS, 'read');
        Zend_Registry::set('Zend_Acl', $acl);
    }

    private function identity(?string $role, string $orgId = self::ORG_A): void
    {
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
        if ($role === null) { $auth->clearIdentity(); return; }
        $auth->getStorage()->write((object) ['role' => $role, 'org_id' => $orgId]);
    }

    #[Test]
    public function a_tenant_role_reads_its_own_org_plus_the_shared_tier(): void
    {
        $this->registerAcl();
        $this->identity('manager');
        $this->assertSame([self::ORG_A, ''], $this->svc->scope());
    }

    #[Test]
    public function supermanager_is_still_tenant_scoped(): void
    {
        // The cut line sits BETWEEN supermanager and admin. supermanager inherits manager's rules,
        // which is not the same as holding the app-wide capability.
        $this->registerAcl();
        $this->identity('supermanager');
        $this->assertSame([self::ORG_A, ''], $this->svc->scope());
    }

    #[Test]
    public function an_app_wide_role_is_not_scoped_at_all(): void
    {
        $this->registerAcl();
        $this->identity('admin');
        $this->assertNull($this->svc->scope(), 'app-wide means NO org predicate, not a wider list');
    }

    #[Test]
    public function app_wide_reach_is_inherited_upward(): void
    {
        $this->registerAcl();
        $this->identity('superadmin');
        $this->assertNull($this->svc->scope());
    }

    #[Test]
    public function a_tenant_role_may_act_on_its_own_org_and_the_shared_tier_only(): void
    {
        $this->registerAcl();
        $this->identity('manager');
        $this->assertTrue($this->svc->inScope(self::ORG_A), 'own org');
        $this->assertTrue($this->svc->inScope(''), "the shared '' tier is everyone's");
        $this->assertFalse($this->svc->inScope(self::ORG_B), "another tenant's row");
    }

    #[Test]
    public function an_app_wide_role_may_act_on_any_org(): void
    {
        $this->registerAcl();
        $this->identity('admin');
        $this->assertTrue($this->svc->inScope(self::ORG_B), 'administering orgs is the job');
    }

    #[Test]
    public function the_cut_line_is_DATA_not_code(): void
    {
        // The whole point of expressing this as a capability: an app re-grants the resource and the
        // boundary moves, with no service change. Here supermanager is granted app-wide reach, and
        // the same unmodified service stops scoping it. A role-string compare could not do this.
        $acl = new Zend_Acl();
        $acl->addRole('guest');
        $acl->addRole('user', 'guest');
        $acl->addRole('manager', 'user');
        $acl->addRole('supermanager', 'manager');
        $acl->addResource(Tiger_Service_Service::RESOURCE_ALL_ORGS);
        $acl->allow('supermanager', Tiger_Service_Service::RESOURCE_ALL_ORGS, 'read');
        Zend_Registry::set('Zend_Acl', $acl);

        $this->identity('supermanager');
        $this->assertNull($this->svc->scope(), 'the grant moved, so the scope moved');
        $this->identity('manager');
        $this->assertSame([self::ORG_A, ''], $this->svc->scope(), 'manager still bounded');
    }

    #[Test]
    public function it_fails_closed_with_no_acl_registered(): void
    {
        // _isAllowed fails CLOSED with no ACL, so the caller is treated as tenant-scoped. A missing
        // ACL must never resolve to "read every org".
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        $this->identity('admin');
        $this->assertSame([self::ORG_A, ''], $this->svc->scope(), 'no ACL must not mean app-wide');
    }

    #[Test]
    public function a_caller_with_no_membership_scopes_to_the_shared_tier_only(): void
    {
        $this->registerAcl();
        $this->identity('user', '');
        $this->assertSame([''], $this->svc->scope());
        $this->assertSame('', $this->svc->org());
    }

    #[Test]
    public function an_anonymous_caller_is_not_app_wide(): void
    {
        $this->registerAcl();
        $this->identity(null);
        $this->assertSame([''], $this->svc->scope());
    }
}
