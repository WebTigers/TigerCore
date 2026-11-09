<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mcp;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Mcp_Scope;
use Zend_Acl;

/**
 * Tiger_Mcp_Scope — the MCP token scope registry (TIGER-470): the offerable set the mint UI draws from,
 * the ACL filter that hides a profile a caller can't mint, and the resolver that turns a chosen set into
 * a concrete token policy (and refuses the footgun inputs — empty, mixed-with-a-profile, unauthorized).
 *
 * Hermetic: every call passes an explicit `roots` ([] = scan nothing) so the real module tree is never
 * touched, and a hand-built Zend_Acl stands in for the registered one.
 */
#[CoversClass(Tiger_Mcp_Scope::class)]
final class McpScopeTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Mcp_Scope::resetRegistered();
    }

    protected function tearDown(): void
    {
        Tiger_Mcp_Scope::resetRegistered();
        parent::tearDown();
    }

    /** An ACL where `admin` may reach the ops console resource and `manager` may not. */
    private function acl(): Zend_Acl
    {
        $acl = new Zend_Acl();
        $acl->addRole('manager');
        $acl->addRole('admin');
        $acl->addResource('Ops_AdminController');
        $acl->allow('admin', 'Ops_AdminController');
        return $acl;
    }

    /** Register the reference observer profile (what a module's mcp-scope.ini would contribute). */
    private function registerObserver(): void
    {
        Tiger_Mcp_Scope::register([
            'key'       => 'ops.observer',
            'label'     => 'TigerOps — Observer',
            'module'    => 'ops',
            'role'      => 'observer',
            'read_only' => 1,
            'resources' => ['task' => 'report,list'],
            'resource'  => 'Ops_AdminController',
            'gated'     => 1,
        ]);
    }

    #[Test]
    public function the_base_set_includes_git_and_the_extras(): void
    {
        $all = Tiger_Mcp_Scope::all([]);
        // The drift the old hardcoded JS list had: git IS offerable; access/analytics are opt-in extras.
        $this->assertArrayHasKey('git', $all, 'git is a real default module and must be offerable');
        $this->assertTrue($all['cms']['default'], 'cms is in the curated default set');
        $this->assertFalse($all['access']['default'], 'access is an opt-in extra, not a default');
        $this->assertFalse($all['cms']['profile'], 'a plain module offer is not a profile');
    }

    #[Test]
    public function a_profile_is_offered_only_to_a_role_allowed_to_mint_it(): void
    {
        $this->registerObserver();
        $acl = $this->acl();

        $adminKeys = array_column(Tiger_Mcp_Scope::offers('admin', $acl, []), 'key');
        $mgrKeys   = array_column(Tiger_Mcp_Scope::offers('manager', $acl, []), 'key');

        $this->assertContains('ops.observer', $adminKeys, 'admin may reach Ops_AdminController → sees the profile');
        $this->assertNotContains('ops.observer', $mgrKeys, 'manager may not → the profile is hidden');
        $this->assertContains('cms', $mgrKeys, 'an ungated module offer is still available');
    }

    #[Test]
    public function resolving_a_module_set_keeps_the_callers_role(): void
    {
        $scope = Tiger_Mcp_Scope::resolve(['cms', 'media'], 'admin', false, $this->acl(), []);
        $this->assertSame(['cms', 'media'], $scope['modules']);
        $this->assertSame('', $scope['role'], 'a plain module token inherits the owner role');
        $this->assertSame([], $scope['resources']);
        $this->assertFalse($scope['read_only']);
    }

    #[Test]
    public function resolving_a_profile_sets_role_resources_and_forced_read_only(): void
    {
        $this->registerObserver();
        $scope = Tiger_Mcp_Scope::resolve(['ops.observer'], 'admin', false, $this->acl(), []);
        $this->assertSame('observer', $scope['role'], 'the token acts AS the profile role');
        $this->assertSame(['ops'], $scope['modules']);
        $this->assertSame(['task' => ['report', 'list']], $scope['resources'], 'the finer resource fence rides along');
        $this->assertTrue($scope['read_only'], 'the profile forces read-only even though the flag was off');
    }

    #[Test]
    public function an_empty_selection_is_refused_not_widened(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mcp.scope.error.empty');
        Tiger_Mcp_Scope::resolve([], 'admin', false, $this->acl(), []);
    }

    #[Test]
    public function a_profile_cannot_be_combined_with_other_offers(): void
    {
        $this->registerObserver();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mcp.scope.error.profile_exclusive');
        Tiger_Mcp_Scope::resolve(['ops.observer', 'cms'], 'admin', false, $this->acl(), []);
    }

    #[Test]
    public function minting_an_unauthorized_profile_is_refused(): void
    {
        $this->registerObserver();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mcp.scope.error.not_allowed');
        Tiger_Mcp_Scope::resolve(['ops.observer'], 'manager', false, $this->acl(), []);
    }

    #[Test]
    public function an_unknown_offer_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mcp.scope.error.unknown');
        Tiger_Mcp_Scope::resolve(['no.such.offer'], 'admin', false, $this->acl(), []);
    }

    #[Test]
    public function a_module_contributes_an_offer_via_its_configs_ini_with_no_core_change(): void
    {
        // The zero-code path: a module drops configs/mcp-scope.ini and the offer appears.
        $root = sys_get_temp_dir() . '/mcp-scope-' . uniqid();
        $dir  = $root . '/widgets/configs';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/mcp-scope.ini', <<<INI
[widgets.readonly]
label     = "Widgets — read-only"
module    = "widgets"
role      = "widget_viewer"
read_only = 1
resources.widget = "list,get"
INI);
        try {
            $all = Tiger_Mcp_Scope::all([$root]);
            $this->assertArrayHasKey('widgets.readonly', $all, 'the ini-declared offer is discovered');
            $o = $all['widgets.readonly'];
            $this->assertSame('widget_viewer', $o['role']);
            $this->assertTrue($o['profile'], 'a role-bearing offer is a profile');
            $this->assertSame(['widget' => ['list', 'get']], $o['resources']);
        } finally {
            @unlink($dir . '/mcp-scope.ini');
            @rmdir($dir);
            @rmdir($root . '/widgets');
            @rmdir($root);
        }
    }
}
