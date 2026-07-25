<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Acl_Acl;
use Tiger_Model_AclResource;
use Tiger_Model_AclRole;
use Tiger_Model_AclRule;

/**
 * Tiger_Acl_Acl — the DB tier layered onto the ini policy. AclModelsTest pins the row LOADERS in
 * isolation; this builds an actual Tiger_Acl_Acl AFTER seeding `acl_role`/`acl_resource`/`acl_rule`, so
 * the engine's DB-tier construction paths run: a DB role (with AND without a parent) is added to the
 * graph, a DB resource is registered, and a DB rule is applied — the "DB loads last, so it wins" tier.
 *
 * Rows ride the per-test transaction (rolled back); the ACL reads them on the same connection.
 */
#[CoversClass(Tiger_Acl_Acl::class)]
final class AclDbTierTest extends IntegrationTestCase
{
    #[Test]
    public function db_roles_resources_and_rules_are_layered_onto_the_engine(): void
    {
        // A DB role WITH a parent (inherits from the ini `user`) and one WITHOUT a parent.
        (new Tiger_Model_AclRole())->insert(['role' => 'w7editor', 'parent_role' => 'user']);
        (new Tiger_Model_AclRole())->insert(['role' => 'w7loner',  'parent_role' => '']);

        // A DB resource + an allow rule granting the DB role access to it.
        (new Tiger_Model_AclResource())->insert(['resource' => 'W7_Service_Report']);
        (new Tiger_Model_AclRule())->insert([
            'role' => 'w7editor', 'resource' => 'W7_Service_Report', 'privilege' => 'view', 'permission' => 'allow',
        ]);

        $acl = new Tiger_Acl_Acl();

        $this->assertTrue($acl->hasRole('w7editor'), 'a DB role is added to the graph');
        $this->assertTrue($acl->hasRole('w7loner'), 'a parentless DB role is added too');
        $this->assertTrue($acl->has('W7_Service_Report'), 'a DB resource is registered');
        $this->assertTrue($acl->isAllowed('w7editor', 'W7_Service_Report', 'view'), 'the DB allow rule grants access');
        $this->assertFalse($acl->isAllowed('w7editor', 'W7_Service_Report', 'delete'), 'only the granted privilege is allowed');
        // The parent link resolved: w7editor inherits `user`, which the ini graph carries.
        $this->assertContains('user', $acl->getRoles(), 'the ini role graph is still present underneath the DB tier');
    }

    #[Test]
    public function a_db_deny_rule_overrides_an_inherited_allow(): void
    {
        // DB loads LAST, so a DB deny wins over an ini/base grant — the "wins on conflict" property.
        (new Tiger_Model_AclResource())->insert(['resource' => 'W7_Service_Thing']);
        (new Tiger_Model_AclRole())->insert(['role' => 'w7member', 'parent_role' => 'user']);
        (new Tiger_Model_AclRule())->insert([
            'role' => 'w7member', 'resource' => 'W7_Service_Thing', 'privilege' => '', 'permission' => 'allow',
        ]);
        (new Tiger_Model_AclRule())->insert([
            'role' => 'w7member', 'resource' => 'W7_Service_Thing', 'privilege' => 'secret', 'permission' => 'deny',
        ]);

        $acl = new Tiger_Acl_Acl();
        $this->assertTrue($acl->isAllowed('w7member', 'W7_Service_Thing', 'read'), 'the blanket allow grants general access');
        $this->assertFalse($acl->isAllowed('w7member', 'W7_Service_Thing', 'secret'), 'the scoped deny rule blocks that privilege');
    }
}
