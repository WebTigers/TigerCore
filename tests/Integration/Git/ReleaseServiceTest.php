<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Git_Service_Base;
use Git_Service_Release;
use Tiger_Git;
use Tiger_Git_Credential;
use Tiger_Git_Provider_GitHub;
use Tiger_Model_GitCredential;
use Tiger_Model_GitRepoConnection;
use Tiger_Model_ModuleRelease;

/**
 * Git_Service_Release against a real migrated DB (TIGER-367).
 *
 * Proves the owner-only capability gate (superadmin tags; an admin who can read releases cannot cut
 * one), the immutable-tag guard, the submit → decide QA flow, and semver enforcement. The connection is
 * pinned to the API transport so `tag` drives the GitHub Git Data API through the injected HTTP seam.
 */
#[CoversClass(Git_Service_Release::class)]
#[CoversClass(Git_Service_Base::class)]
final class ReleaseServiceTest extends IntegrationTestCase
{
    private static bool $gitSchema = false;
    private const ORG    = 'org-rel';
    private const MODULE = 'git';

    protected function setUp(): void
    {
        parent::setUp();
        $this->applyGitSchema();
        $this->seedCrypto();

        $this->seedIdentityRows('u-super', self::ORG, 'superadmin');
        $this->seedIdentityRows('u-admin', self::ORG, 'admin');
        \Zend_Registry::set('tiger.auth.stateless', true);

        Git_Service_Base::setAdapterFactory($this->seam($this->tagHttp()));
        $this->seedConnection();
    }

    protected function tearDown(): void
    {
        Git_Service_Base::setAdapterFactory(null);
        parent::tearDown();
    }

    // --- helpers ---------------------------------------------------------------------------------

    private function applyGitSchema(): void
    {
        if (self::$gitSchema) { return; }
        $this->db->rollBack();
        // Via the migrator so the version is recorded in the tiger_migration ledger — otherwise a later
        // installFromTarball()->_migrate() (which scans modules/git/migrations) re-runs it and collides
        // with these tables. Idempotent: an already-applied version is skipped.
        (new \Tiger_Db_Migrator($this->db, [dirname(__DIR__, 3) . '/modules/git/migrations']))->migrate();
        $this->db->beginTransaction();
        self::$gitSchema = true;
    }

    private function seedCrypto(): void
    {
        $cfg = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config') : new \Zend_Config([], true);
        if ($cfg->readOnly()) { $cfg = new \Zend_Config($cfg->toArray(), true); }
        $cfg->merge(new \Zend_Config(['tiger' => ['crypto' => ['key' => base64_encode(str_repeat("\x22", 32))]]], true));
        \Zend_Registry::set('Zend_Config', $cfg);
    }

    private function seedConnection(): void
    {
        $cred = (new Tiger_Git_Credential())->store(self::ORG, self::MODULE, Tiger_Model_GitCredential::TYPE_TOKEN, 'ghp_x', []);
        (new Tiger_Model_GitRepoConnection())->insert([
            'org_id'         => self::ORG,
            'module_key'     => self::MODULE,
            'provider'       => Tiger_Git_Provider_GitHub::PROVIDER,
            'repo_owner'     => 'acme',
            'repo_name'      => 'mod',
            'default_branch' => 'main',
            'transport'      => Tiger_Git::TRANSPORT_API,   // so tag drives the API through the seam
            'write_enabled'  => 1,
            'credential_ref' => $cred['ref'],
            'status'         => Tiger_Git::STATUS_CONNECTED,
        ]);
    }

    private function seam(callable $http): callable
    {
        return fn () => new Tiger_Git_Provider_GitHub(new Tiger_Git_Credential(), $http);
    }

    private function tagHttp(): callable
    {
        return function (string $method, string $url, array $headers, ?string $body): array {
            if ($method === 'GET'  && str_contains($url, '/git/ref/heads/')) { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => 'HEADSHA']])]; }
            if ($method === 'POST' && str_contains($url, '/git/tags'))        { return ['status' => 201, 'body' => json_encode(['sha' => 'TAGOBJ'])]; }
            if ($method === 'POST' && str_contains($url, '/git/refs'))        { return ['status' => 201, 'body' => json_encode(['ref' => 'refs/tags/x'])]; }
            if (str_contains($url, '/releases'))                              { return ['status' => 200, 'body' => '[]']; }
            return ['status' => 404, 'body' => '{}'];
        };
    }

    private function call(string $method, array $params = []): array
    {
        $svc = new Git_Service_Release();
        $svc->$method($params);
        $res  = $svc->getResponse();
        $msgs = [];
        foreach ((array) $res->messages as $m) { $msgs[] = (string) $m->message; }
        return ['result' => (int) $res->result, 'data' => json_decode(json_encode($res->data), true) ?: [], 'messages' => $msgs];
    }

    // --- tests -----------------------------------------------------------------------------------

    #[Test]
    public function superadmin_can_tag(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        $res = $this->call('tag', ['module_key' => self::MODULE, 'version' => '1.0.0']);

        $this->assertSame(1, $res['result'], 'the owner tagged');
        $this->assertSame('1.0.0', $res['data']['tag']);
        $this->assertSame('HEADSHA', $res['data']['sha']);
    }

    #[Test]
    public function an_admin_cannot_tag(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $res = $this->call('tag', ['module_key' => self::MODULE, 'version' => '1.0.0']);

        $this->assertSame(0, $res['result'], 'an ordinary admin is refused the release capability');
        $this->assertContains('git.error.not_allowed', $res['messages']);
    }

    #[Test]
    public function tag_refuses_a_non_semver_version(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        $res = $this->call('tag', ['module_key' => self::MODULE, 'version' => 'not-a-version']);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.invalid_version', $res['messages']);
    }

    #[Test]
    public function tag_refuses_a_version_already_released(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        (new Tiger_Model_ModuleRelease())->insert([
            'org_id' => self::ORG, 'module_key' => self::MODULE, 'version' => '2.0.0', 'git_tag' => '2.0.0',
            'commit_sha' => 'deadbeef', 'status' => Tiger_Model_ModuleRelease::STATUS_SUBMITTED, 'submitted_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $res = $this->call('tag', ['module_key' => self::MODULE, 'version' => '2.0.0']);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.tag_exists', $res['messages']);
    }

    #[Test]
    public function submit_records_a_release_for_qa(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        $res = $this->call('submit', ['module_key' => self::MODULE, 'version' => '1.2.0', 'commit_sha' => 'abc1234']);

        $this->assertSame(1, $res['result']);
        $this->assertSame(Tiger_Model_ModuleRelease::STATUS_SUBMITTED, $res['data']['status']);
        $row = (new Tiger_Model_ModuleRelease())->byTag(self::ORG, self::MODULE, '1.2.0');
        $this->assertNotNull($row);
        $this->assertSame('abc1234', (string) $row->commit_sha);
    }

    #[Test]
    public function decide_approves_a_submitted_release(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        $this->call('submit', ['module_key' => self::MODULE, 'version' => '1.3.0', 'commit_sha' => 'feedface']);
        $id = (string) (new Tiger_Model_ModuleRelease())->byTag(self::ORG, self::MODULE, '1.3.0')->module_release_id;

        $this->login('u-admin', self::ORG, 'admin');   // QA review is admin-level
        $res = $this->call('decide', ['module_release_id' => $id, 'decision' => 'approve']);

        $this->assertSame(1, $res['result']);
        $this->assertSame(Tiger_Model_ModuleRelease::STATUS_APPROVED, $res['data']['status']);
        $approved = (new Tiger_Model_ModuleRelease())->approvedForModule(self::ORG, self::MODULE);
        $this->assertCount(1, $approved);
    }

    #[Test]
    public function decide_rejects_and_list_reflects_it(): void
    {
        $this->login('u-super', self::ORG, 'superadmin');
        $this->call('submit', ['module_key' => self::MODULE, 'version' => '1.4.0', 'commit_sha' => 'cafebabe']);
        $id = (string) (new Tiger_Model_ModuleRelease())->byTag(self::ORG, self::MODULE, '1.4.0')->module_release_id;

        $this->login('u-admin', self::ORG, 'admin');
        $this->assertSame(1, $this->call('decide', ['module_release_id' => $id, 'decision' => 'reject'])['result']);

        $list = $this->call('list', ['module_key' => self::MODULE]);
        $byId = [];
        foreach ($list['data']['releases'] as $r) { $byId[$r['module_release_id']] = $r['status']; }
        $this->assertSame(Tiger_Model_ModuleRelease::STATUS_REJECTED, $byId[$id] ?? null);
    }
}
