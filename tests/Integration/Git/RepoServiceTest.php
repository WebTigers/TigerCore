<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Git_Service_Base;
use Git_Service_Repo;
use Tiger_Git;
use Tiger_Git_Credential;
use Tiger_Git_Environment;
use Tiger_Git_Provider_GitHub;
use Tiger_Model_GitCredential;
use Tiger_Model_GitRepoConnection;

/**
 * Git_Service_Repo against a real migrated DB (TIGER-367).
 *
 * Proves the connect → verify → record flow, that a connection carries no secret, that a bad credential
 * leaves nothing behind, that reconnect-after-disconnect is not blocked (the active-only unique), and
 * that the admin surface is ACL-gated. The GitHub provider is injected behind the Base seam so there is
 * no network: `listReleases` (the verification call — always the API) is the one the seam answers.
 */
#[CoversClass(Git_Service_Repo::class)]
#[CoversClass(Git_Service_Base::class)]
final class RepoServiceTest extends IntegrationTestCase
{
    private static bool $gitSchema = false;
    private const ORG    = 'org-git';
    private const MODULE = 'git';   // modules/git exists under TIGER_CORE_PATH, so _moduleDir resolves

    protected function setUp(): void
    {
        parent::setUp();
        $this->applyGitSchema();
        $this->seedCrypto();

        $this->seedIdentityRows('u-admin', self::ORG, 'admin');
        $this->seedIdentityRows('u-user',  self::ORG, 'user');
        \Zend_Registry::set('tiger.auth.stateless', true);

        Tiger_Git_Environment::setProbe(fn () => true);   // detect() without the network
        Git_Service_Base::setAdapterFactory($this->seam($this->okHttp()));
    }

    protected function tearDown(): void
    {
        Tiger_Git_Environment::setProbe(null);
        Git_Service_Base::setAdapterFactory(null);
        parent::tearDown();
    }

    // --- helpers ---------------------------------------------------------------------------------

    private function applyGitSchema(): void
    {
        if (self::$gitSchema) { return; }
        $this->db->rollBack();   // DDL auto-commits; close the per-test txn around it
        if (!$this->tableExists('git_repo_connection')) {
            $m = require dirname(__DIR__, 3) . '/modules/git/migrations/20261021000001_create_git_tables.php';
            foreach ($m['up'] as $sql) { $this->db->query($sql); }
        }
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

    /** A provider factory wired to a canned HTTP seam (the real credential manager resolves the token). */
    private function seam(callable $http): callable
    {
        return fn () => new Tiger_Git_Provider_GitHub(new Tiger_Git_Credential(), $http);
    }

    private function okHttp(): callable
    {
        // Enough of the GitHub API for connect (releases/branches) AND the reconciler status
        // (ref → commit → an empty tree, so every on-disk module file reads as a new add).
        return function (string $method, string $url, array $headers, ?string $body): array {
            if (str_contains($url, '/releases'))          { return ['status' => 200, 'body' => '[]']; }
            if (str_contains($url, '/branches'))          { return ['status' => 200, 'body' => json_encode([['name' => 'main']])]; }
            if (str_contains($url, '/git/ref/heads/'))    { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => str_repeat('a', 40)]])]; }
            if (str_contains($url, '/git/commits/'))      { return ['status' => 200, 'body' => json_encode(['tree' => ['sha' => 'T0']])]; }
            if (str_contains($url, '/git/trees/'))        { return ['status' => 200, 'body' => json_encode(['tree' => []])]; }
            return ['status' => 200, 'body' => '{}'];
        };
    }

    private function call(string $method, array $params = []): array
    {
        $svc = new Git_Service_Repo();
        $svc->$method($params);
        $res  = $svc->getResponse();
        $msgs = [];
        foreach ((array) $res->messages as $m) { $msgs[] = (string) $m->message; }
        return ['result' => (int) $res->result, 'data' => json_decode(json_encode($res->data), true) ?: [], 'messages' => $msgs];
    }

    private function connect(array $over = []): array
    {
        return $this->call('connect', array_merge([
            'module_key' => self::MODULE, 'repo_owner' => 'acme', 'repo_name' => 'mod', 'token' => 'ghp_secret',
        ], $over));
    }

    // --- tests -----------------------------------------------------------------------------------

    #[Test]
    public function connect_records_the_connection_and_returns_no_secret(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $res = $this->connect();

        $this->assertSame(1, $res['result'], 'connect succeeded');
        $this->assertSame(self::MODULE, $res['data']['connection']['module_key']);
        $this->assertSame('acme/mod', $res['data']['connection']['slug']);
        $this->assertSame(Tiger_Git_Environment::detect()['transport'], $res['data']['connection']['transport']);

        $flat = json_encode($res['data']);
        $this->assertStringNotContainsString('ghp_secret', $flat, 'the token never leaves the service');
        $this->assertStringNotContainsString('credential_ref', $flat, 'the credential ref is not exposed');

        $conn = (new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE);
        $this->assertNotNull($conn, 'a connection row exists');
        $this->assertNotSame('', (string) $conn->credential_ref, 'it points at a stored credential');
        $this->assertNotNull((new Tiger_Model_GitCredential())->forModule(self::ORG, self::MODULE));
    }

    #[Test]
    public function a_bad_credential_is_rejected_and_leaves_nothing(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        Git_Service_Base::setAdapterFactory($this->seam(function (string $m, string $url): array {
            return str_contains($url, '/releases')
                ? ['status' => 401, 'body' => json_encode(['message' => 'Bad credentials'])]
                : ['status' => 200, 'body' => '{}'];
        }));

        $res = $this->connect();

        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.credential_invalid', $res['messages']);
        $this->assertNull((new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE), 'no connection was recorded');
        $this->assertNull((new Tiger_Model_GitCredential())->forModule(self::ORG, self::MODULE), 'the just-stored credential was revoked');
    }

    #[Test]
    public function reconnect_after_disconnect_is_not_blocked(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $this->assertSame(1, $this->connect()['result']);
        $this->assertSame(1, $this->call('disconnect', ['module_key' => self::MODULE])['result']);
        $this->assertNull((new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE));

        $this->assertSame(1, $this->connect()['result'], 'the active-only unique freed the pair');
        $this->assertNotNull((new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE));
    }

    #[Test]
    public function disconnect_revokes_the_credential(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $this->connect();
        $ref = (string) (new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE)->credential_ref;

        $this->assertSame(1, $this->call('disconnect', ['module_key' => self::MODULE])['result']);
        $this->assertNull((new Tiger_Git_Credential())->resolve($ref), 'the credential no longer resolves');
    }

    #[Test]
    public function status_reports_the_working_tree_over_the_api_reconciler(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $this->connect();
        // Pin the API transport: the no-.git reconciler compares the on-disk module to the remote tree.
        $conn = (new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE);
        $conn->transport = Tiger_Git::TRANSPORT_API;
        $conn->save();

        $res = $this->call('status', ['module_key' => self::MODULE]);
        $this->assertSame(1, $res['result']);
        $this->assertTrue($res['data']['status_available'], 'the API transport now does status');
        $this->assertSame('main', $res['data']['working']['branch']);
        $this->assertIsArray($res['data']['working']['changed']);
    }

    #[Test]
    public function list_and_log_reflect_the_connect(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $this->connect();

        $list = $this->call('list');
        $this->assertCount(1, $list['data']['connections']);

        $log = $this->call('log', ['module_key' => self::MODULE]);
        $ops = array_column($log['data']['entries'], 'operation');
        $this->assertContains('connect', $ops);
    }

    #[Test]
    public function a_non_admin_is_refused(): void
    {
        $this->login('u-user', self::ORG, 'user');
        $res = $this->connect();
        $this->assertSame(0, $res['result']);
        $this->assertSame('core.api.error.not_allowed', $res['messages'][0]);
        $this->assertNull((new Tiger_Model_GitRepoConnection())->forModule(self::ORG, self::MODULE));
    }
}
