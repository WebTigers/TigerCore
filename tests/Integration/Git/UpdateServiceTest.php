<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Git_Service_Base;
use Git_Service_Update;
use Tiger_Git;
use Tiger_Git_Credential;
use Tiger_Git_Provider_GitHub;
use Tiger_Model_GitCredential;
use Tiger_Model_GitRepoConnection;
use Tiger_Model_ModuleInstallPin;
use Tiger_Model_ModuleRelease;

/**
 * Git_Service_Update against a real migrated DB (TIGER-367).
 *
 * Proves the receive-updates flow: check reports a pending APPROVED release (and respects up-to-date +
 * the pinned channel); apply installs the latest approved and moves the pin (recording the rollback
 * anchor), refuses an unapproved or pinned version; setPolicy saves the channel; rollback restores the
 * previous version and pins the one it fled. The archive download (adapter) and the install step are
 * both behind seams, so there is no network and no touching of the modules dir.
 */
#[CoversClass(Git_Service_Update::class)]
#[CoversClass(Git_Service_Base::class)]
final class UpdateServiceTest extends IntegrationTestCase
{
    private static bool $gitSchema = false;
    private const ORG    = 'org-upd';
    private const MODULE = 'git';

    /** @var array<int,array> install-seam invocations */
    private array $installs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->applyGitSchema();
        $this->seedCrypto();
        $this->seedIdentityRows('u-admin', self::ORG, 'admin');
        \Zend_Registry::set('tiger.auth.stateless', true);

        Git_Service_Base::setAdapterFactory(fn () => new Tiger_Git_Provider_GitHub(new Tiger_Git_Credential(), $this->archiveHttp()));
        $this->installs = [];
        Git_Service_Base::setInstaller(function (string $tar, array $prov, array $opts): array {
            $this->installs[] = ['tar' => $tar, 'prov' => $prov, 'opts' => $opts];
            return ['slug' => self::MODULE, 'version' => $prov['ref'] ?? null, 'updated' => true];
        });
        $this->seedConnection();
        $this->login('u-admin', self::ORG, 'admin');
    }

    protected function tearDown(): void
    {
        Git_Service_Base::setAdapterFactory(null);
        Git_Service_Base::setInstaller(null);
        parent::tearDown();
    }

    // --- helpers ---------------------------------------------------------------------------------

    private function applyGitSchema(): void
    {
        if (self::$gitSchema) { return; }
        $this->db->rollBack();
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

    private function seedConnection(): void
    {
        $cred = (new Tiger_Git_Credential())->store(self::ORG, self::MODULE, Tiger_Model_GitCredential::TYPE_TOKEN, 'ghp_x', []);
        (new Tiger_Model_GitRepoConnection())->insert([
            'org_id' => self::ORG, 'module_key' => self::MODULE, 'provider' => Tiger_Git_Provider_GitHub::PROVIDER,
            'repo_owner' => 'acme', 'repo_name' => 'mod', 'default_branch' => 'main',
            'transport' => Tiger_Git::TRANSPORT_API, 'write_enabled' => 1, 'credential_ref' => $cred['ref'],
            'status' => Tiger_Git::STATUS_CONNECTED,
        ]);
    }

    private function approve(string $version, string $sha): void
    {
        (new Tiger_Model_ModuleRelease())->insert([
            'org_id' => self::ORG, 'module_key' => self::MODULE, 'version' => $version, 'git_tag' => $version,
            'commit_sha' => $sha, 'status' => Tiger_Model_ModuleRelease::STATUS_APPROVED,
            'submitted_at' => gmdate('Y-m-d H:i:s'), 'decided_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function pin(array $fields): void
    {
        (new Tiger_Model_ModuleInstallPin())->insert(array_merge([
            'org_id' => self::ORG, 'module_key' => self::MODULE, 'channel' => Tiger_Git::POLICY_NOTIFY, 'status' => 'active',
        ], $fields));
    }

    private function archiveHttp(): callable
    {
        return function (string $method, string $url, array $headers, ?string $body): array {
            if (str_contains($url, 'api.github.com') && str_contains($url, '/tarball/')) {
                return ['status' => 302, 'headers' => ['Location' => 'https://codeload.github.com/acme/mod/tar.gz/x'], 'body' => ''];
            }
            if (str_contains($url, 'codeload.github.com')) { return ['status' => 200, 'body' => 'TARBALL']; }
            return ['status' => 404, 'body' => '{}'];
        };
    }

    private function call(string $method, array $params = []): array
    {
        $svc = new Git_Service_Update();
        $svc->$method($params);
        $res  = $svc->getResponse();
        $msgs = [];
        foreach ((array) $res->messages as $m) { $msgs[] = (string) $m->message; }
        return ['result' => (int) $res->result, 'data' => json_decode(json_encode($res->data), true) ?: [], 'messages' => $msgs];
    }

    private function freshPin(): ?object
    {
        return (new Tiger_Model_ModuleInstallPin())->forModule(self::ORG, self::MODULE);
    }

    // --- check -----------------------------------------------------------------------------------

    #[Test]
    public function check_reports_a_pending_approved_update(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.0.0']);

        $res = $this->call('check', ['module_key' => self::MODULE]);
        $this->assertSame(1, $res['result']);
        $u = $res['data']['updates'][0];
        $this->assertTrue($u['pending']);
        $this->assertSame('1.2.0', $u['latest_approved_version']);
        $this->assertSame('1.0.0', $u['installed_version']);
    }

    #[Test]
    public function check_is_not_pending_when_up_to_date(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.2.0']);
        $this->assertFalse($this->call('check')['data']['updates'][0]['pending']);
    }

    #[Test]
    public function check_respects_the_pinned_channel(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.0.0', 'channel' => Tiger_Git::POLICY_PINNED]);
        $this->assertFalse($this->call('check')['data']['updates'][0]['pending'], 'a pinned module is never pending');
    }

    // --- apply -----------------------------------------------------------------------------------

    #[Test]
    public function apply_installs_the_latest_approved_and_moves_the_pin(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.0.0']);

        $res = $this->call('apply', ['module_key' => self::MODULE]);
        $this->assertSame(1, $res['result']);
        $this->assertSame('1.2.0', $res['data']['version']);
        $this->assertSame('1.0.0', $res['data']['previous_version']);

        $pin = $this->freshPin();
        $this->assertSame('1.2.0', (string) $pin->installed_version);
        $this->assertSame('1.0.0', (string) $pin->previous_version);

        $this->assertCount(1, $this->installs, 'the installer ran once');
        $this->assertTrue($this->installs[0]['opts']['force'], 'it updates in place');
        $this->assertSame('1.2.0', $this->installs[0]['prov']['ref']);
    }

    #[Test]
    public function apply_refuses_an_unapproved_version(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.0.0']);

        $res = $this->call('apply', ['module_key' => self::MODULE, 'version' => '9.9.9']);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.not_approved', $res['messages']);
        $this->assertCount(0, $this->installs, 'nothing was installed');
    }

    #[Test]
    public function apply_refuses_the_pinned_version(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.0.0', 'channel' => Tiger_Git::POLICY_PINNED, 'pinned_version' => '1.2.0']);

        $res = $this->call('apply', ['module_key' => self::MODULE, 'version' => '1.2.0']);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.version_pinned', $res['messages']);
    }

    // --- setPolicy -------------------------------------------------------------------------------

    #[Test]
    public function set_policy_saves_the_channel(): void
    {
        $this->pin(['installed_version' => '1.0.0']);
        $res = $this->call('setPolicy', ['module_key' => self::MODULE, 'channel' => Tiger_Git::POLICY_PINNED, 'pinned_version' => '1.0.0']);
        $this->assertSame(1, $res['result']);
        $this->assertSame(Tiger_Git::POLICY_PINNED, (string) $this->freshPin()->channel);
        $this->assertSame('1.0.0', (string) $this->freshPin()->pinned_version);
    }

    #[Test]
    public function set_policy_refuses_an_unknown_channel(): void
    {
        $res = $this->call('setPolicy', ['module_key' => self::MODULE, 'channel' => 'whenever']);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.invalid_channel', $res['messages']);
    }

    // --- rollback --------------------------------------------------------------------------------

    #[Test]
    public function rollback_restores_the_previous_version_and_pins_the_bad_one(): void
    {
        $this->approve('1.0.0', 'sha10');
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.2.0', 'previous_version' => '1.0.0']);

        $res = $this->call('rollback', ['module_key' => self::MODULE]);
        $this->assertSame(1, $res['result']);
        $this->assertSame('1.0.0', $res['data']['version']);
        $this->assertSame('1.2.0', $res['data']['pinned_version']);

        $pin = $this->freshPin();
        $this->assertSame('1.0.0', (string) $pin->installed_version);
        $this->assertSame(Tiger_Git::POLICY_PINNED, (string) $pin->channel);
        $this->assertSame('1.2.0', (string) $pin->pinned_version, 'the fled version is pinned so auto-update skips it');
        $this->assertCount(1, $this->installs);
    }

    #[Test]
    public function rollback_without_a_previous_version_is_refused(): void
    {
        $this->approve('1.2.0', 'sha12');
        $this->pin(['installed_version' => '1.2.0']);
        $res = $this->call('rollback', ['module_key' => self::MODULE]);
        $this->assertSame(0, $res['result']);
        $this->assertContains('git.error.no_previous', $res['messages']);
    }
}
