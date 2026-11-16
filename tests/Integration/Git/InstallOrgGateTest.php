<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use Git_Service_Base;
use Git_Service_Release;
use Git_Service_Repo;
use Git_Service_Update;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Admin_Nav;
use Tiger_Git;
use Tiger_Model_GitRepoConnection;
use Tiger_Model_Org;
use Tiger_Model_Table;
use Zend_Registry;

/**
 * TigerGit is INSTALL-LEVEL (TIGER-511): only the install's own (main) org may use it.
 *
 * Every Git_Service_* method refuses an admin of any other org (not_allowed, nothing written), while the
 * install org's admin is served; the `install_only` sidebar item shows only to the install org. The
 * gate is the ORG half of the rule — the role half is the module's acl.ini, unchanged.
 */
#[CoversClass(Git_Service_Base::class)]
#[CoversClass(Tiger_Git::class)]
#[CoversClass(Tiger_Admin_Nav::class)]
final class InstallOrgGateTest extends IntegrationTestCase
{
    private const INSTALL_ORG = 'org-git-install';
    private const OTHER_ORG   = 'org-git-tenant';

    private static bool $gitSchema = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (!self::$gitSchema) {
            $this->db->rollBack();   // DDL auto-commits; close the per-test txn around it
            (new \Tiger_Db_Migrator($this->db, [dirname(__DIR__, 3) . '/modules/git/migrations']))->migrate();
            $this->db->beginTransaction();
            self::$gitSchema = true;
        }
        $this->asInstallOrg(self::INSTALL_ORG);
        Zend_Registry::set('tiger.auth.stateless', true);
        // No network: any provider call answers an empty list.
        Git_Service_Base::setAdapterFactory(fn () => new \Tiger_Git_Provider_GitHub(
            new \Tiger_Git_Credential(),
            fn () => ['status' => 200, 'body' => '[]']
        ));
    }

    protected function tearDown(): void
    {
        Git_Service_Base::setAdapterFactory(null);
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Admin_Nav::clear();
        parent::tearDown();
    }

    /** Every public git method, with plausible params. */
    public static function methods(): array
    {
        $mk = ['module_key' => 'git'];
        return [
            'repo.connect'      => [Git_Service_Repo::class,    'connect',    $mk + ['repo_owner' => 'acme', 'repo_name' => 'mod', 'token' => 'ghp_x']],
            'repo.status'       => [Git_Service_Repo::class,    'status',     $mk],
            'repo.recheck'      => [Git_Service_Repo::class,    'recheck',    $mk],
            'repo.list'         => [Git_Service_Repo::class,    'list',       []],
            'repo.log'          => [Git_Service_Repo::class,    'log',        $mk],
            'repo.disconnect'   => [Git_Service_Repo::class,    'disconnect', $mk],
            'release.tag'       => [Git_Service_Release::class, 'tag',        $mk + ['version' => '1.0.0']],
            'release.submit'    => [Git_Service_Release::class, 'submit',     $mk + ['version' => '1.0.0', 'commit_sha' => str_repeat('a', 40)]],
            'release.decide'    => [Git_Service_Release::class, 'decide',     ['release_id' => 'x', 'decision' => 'approved']],
            'release.list'      => [Git_Service_Release::class, 'list',       $mk],
            'update.check'      => [Git_Service_Update::class,  'check',      []],
            'update.apply'      => [Git_Service_Update::class,  'apply',      $mk],
            'update.setPolicy'  => [Git_Service_Update::class,  'setPolicy',  $mk + ['policy' => 'notify']],
            'update.rollback'   => [Git_Service_Update::class,  'rollback',   $mk],
        ];
    }

    private function call(string $class, string $method, array $params): array
    {
        $svc = new $class();
        $svc->$method($params);
        $res  = $svc->getResponse();
        $msgs = [];
        foreach ((array) $res->messages as $m) { $msgs[] = (string) $m->message; }
        return ['result' => (int) $res->result, 'messages' => $msgs];
    }

    #[Test]
    #[DataProvider('methods')]
    public function another_orgs_superadmin_is_refused_every_method(string $class, string $method, array $params): void
    {
        $this->login('u-tenant', self::OTHER_ORG, 'superadmin');   // the role passes; the org does not
        $res = $this->call($class, $method, $params);

        $this->assertSame(0, $res['result'], "$class::$method must refuse a non-install org");
        $this->assertContains('core.api.error.not_allowed', $res['messages']);
        $this->assertSame([], (new Tiger_Model_GitRepoConnection())->forOrg(self::OTHER_ORG)->toArray(), 'nothing written');
    }

    #[Test]
    public function the_install_orgs_admin_is_served(): void
    {
        $this->login('u-install', self::INSTALL_ORG, 'admin');
        $this->assertSame(1, $this->call(Git_Service_Repo::class, 'list', [])['result']);
        $this->assertSame(1, $this->call(Git_Service_Update::class, 'check', [])['result']);
        $this->assertSame(1, $this->call(Git_Service_Release::class, 'list', ['module_key' => 'git'])['result']);
    }

    #[Test]
    public function only_the_install_org_may_use_git(): void
    {
        $this->assertTrue(Tiger_Git::orgMayUse(self::INSTALL_ORG));
        $this->assertFalse(Tiger_Git::orgMayUse(self::OTHER_ORG));
        $this->assertFalse(Tiger_Git::orgMayUse(''));
        $this->assertTrue(Tiger_Model_Org::isInstallOrg(self::INSTALL_ORG));
    }

    #[Test]
    public function the_sidebar_item_shows_only_to_the_install_org(): void
    {
        Tiger_Admin_Nav::clear();
        $keys = function (): array { return array_column(Tiger_Admin_Nav::items(), 'key'); };

        Tiger_Model_Table::setOrg(self::INSTALL_ORG);
        $this->assertContains('git', $keys(), 'the install org sees Git');

        Tiger_Model_Table::setOrg(self::OTHER_ORG);
        $this->assertNotContains('git', $keys(), 'another org never sees it');

        Tiger_Model_Table::setOrg('');
        $this->assertNotContains('git', $keys());
    }
}
