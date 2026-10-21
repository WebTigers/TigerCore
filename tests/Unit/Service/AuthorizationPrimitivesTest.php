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

/** A concrete service that exposes the protected authorization primitives for testing. */
final class _AuthzFixtureService extends Tiger_Service_Service
{
    public function callerMay(string $privilege, ?string $resource = null): bool
    {
        return $this->_isAllowed($privilege, $resource);
    }

    public function roleMay(string $role, string $privilege, ?string $resource = null): bool
    {
        return $this->_roleIsAllowed($role, $privilege, $resource);
    }
}

/**
 * Tiger_Service_Service::_isAllowed() / _roleIsAllowed() — the ONE authorization primitive (TIGER-335).
 *
 * Pins the contract that replaces the three deprecated role comparers: a privilege-scoped rule admits
 * exactly the role it names (and no bare resource-level default exists to leak access), a role that
 * inherits admin and is then denied is refused, an ungoverned resource grants nothing, and every path
 * fails CLOSED when the ACL is not registered. None of this compares a role string.
 */
#[CoversClass(Tiger_Service_Service::class)]
final class AuthorizationPrimitivesTest extends UnitTestCase
{
    private const RES = 'Fixture_Res';

    private _AuthzFixtureService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new _AuthzFixtureService();
    }

    /** A small hand-built policy: user→read, admin→write, billing-admin inherits admin then loses write. */
    private function registerAcl(): void
    {
        $acl = new Zend_Acl();
        $acl->addRole('guest');
        $acl->addRole('user', 'guest');
        $acl->addRole('admin', 'user');
        $acl->addRole('billing-admin', 'admin');     // inherits admin's RULES...
        $acl->addResource(self::RES);
        $acl->allow('user', self::RES, 'read');      // privilege-scoped — not a blanket resource grant
        $acl->allow('admin', self::RES, 'write');
        $acl->deny('billing-admin', self::RES, 'write');   // ...then narrowed: NOT as senior as admin
        Zend_Registry::set('Zend_Acl', $acl);
    }

    private function identity(?string $role): void
    {
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
        if ($role === null) { $auth->clearIdentity(); return; }
        $auth->getStorage()->write((object) ['role' => $role]);
    }

    #[Test]
    public function a_privilege_scoped_rule_admits_exactly_the_role_it_names(): void
    {
        $this->registerAcl();
        $this->assertTrue($this->svc->roleMay('user', 'read', self::RES), 'the rule must not deny the role it names');
        $this->assertFalse($this->svc->roleMay('user', 'write', self::RES), 'user was not granted write');
    }

    #[Test]
    public function a_role_that_inherits_admin_then_is_denied_is_refused(): void
    {
        $this->registerAcl();
        $this->assertTrue($this->svc->roleMay('admin', 'write', self::RES));
        $this->assertFalse(
            $this->svc->roleMay('billing-admin', 'write', self::RES),
            'inheriting admin is not being "at least as senior" — the explicit deny wins (defect #3)'
        );
    }

    #[Test]
    public function an_ungoverned_resource_grants_nothing(): void
    {
        $this->registerAcl();
        $this->assertFalse($this->svc->roleMay('admin', 'read', 'Not_A_Resource'), 'has()-gate: unknown resource → false');
    }

    #[Test]
    public function an_empty_role_is_refused(): void
    {
        $this->registerAcl();
        $this->assertFalse($this->svc->roleMay('', 'read', self::RES));
    }

    #[Test]
    public function every_path_fails_closed_with_no_acl_registered(): void
    {
        // No registerAcl() — the registry is clean (UnitTestCase resets it). The old helpers had an
        // `admin → true` shortcut that fired BEFORE this check; the primitive has none (defect #4).
        $this->assertFalse($this->svc->roleMay('admin', 'read', self::RES));
        $this->identity('admin');
        $this->assertFalse($this->svc->callerMay('read', self::RES));
    }

    #[Test]
    public function the_caller_variant_resolves_the_identity_and_defaults_guest(): void
    {
        $this->registerAcl();

        $this->identity('user');
        $this->assertTrue($this->svc->callerMay('read', self::RES), 'the signed-in user holds read');
        $this->assertFalse($this->svc->callerMay('write', self::RES));

        // No identity → the caller is treated as `guest` (a real role that was granted nothing here).
        $this->identity(null);
        $this->assertFalse($this->svc->callerMay('read', self::RES), 'an anonymous caller defaults to guest and is refused');
    }
}
