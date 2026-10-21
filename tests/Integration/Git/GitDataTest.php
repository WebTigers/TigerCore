<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Git_Credential;
use Tiger_Git_Exception;
use Tiger_Model_GitCredential;
use Tiger_Model_GitOperationLog;
use Tiger_Model_GitRepoConnection;
use Tiger_Model_ModuleInstallPin;
use Tiger_Model_ModuleRelease;

/**
 * Tiger_Git data model + the credential manager, against a real migrated DB (TIGER-334 Phase 1).
 *
 * Proves the load-bearing Phase-1 contracts:
 *   - the five tables build and the standard-column model finders work;
 *   - the connection/pin uniques are ACTIVE-ONLY — soft-deleting frees `(org_id, module_key)` so a
 *     reconnect is not blocked (the node-re-enroll bug class);
 *   - a credential is WRITE-ONLY: `store()` returns no secret, `resolve()` round-trips it, `revoke()`
 *     stops it resolving, and a bad/missing input fails safe.
 *
 * The git migration lives in the module dir (not core/migrations, which the harness runs), so we apply
 * it once per process here — DDL auto-commits, so we close + re-open the harness's per-test transaction
 * around it to keep the first test isolated.
 */
#[CoversClass(Tiger_Git_Credential::class)]
#[CoversClass(Tiger_Model_GitRepoConnection::class)]
#[CoversClass(Tiger_Model_GitCredential::class)]
#[CoversClass(Tiger_Model_GitOperationLog::class)]
#[CoversClass(Tiger_Model_ModuleRelease::class)]
#[CoversClass(Tiger_Model_ModuleInstallPin::class)]
final class GitDataTest extends IntegrationTestCase
{
    private static bool $gitSchema = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$gitSchema) {
            $this->db->rollBack();   // close the harness per-test txn; DDL would auto-commit it anyway
            if (!$this->tableExists('git_repo_connection')) {
                $m = require dirname(__DIR__, 3) . '/modules/git/migrations/20261021000001_create_git_tables.php';
                foreach ($m['up'] as $sql) { $this->db->query($sql); }
            }
            $this->db->beginTransaction();   // re-open so THIS test body still rolls back
            self::$gitSchema = true;
        }

        // Seed a crypto key so Tiger_Crypto::isConfigured() is true (the AgentMcpTest idiom).
        $cfg = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config') : new \Zend_Config([], true);
        if ($cfg->readOnly()) { $cfg = new \Zend_Config($cfg->toArray(), true); }
        $cfg->merge(new \Zend_Config(['tiger' => ['crypto' => ['key' => base64_encode(str_repeat("\x22", 32))]]], true));
        \Zend_Registry::set('Zend_Config', $cfg);
    }

    #[Test]
    public function the_five_tables_build(): void
    {
        foreach (['git_repo_connection', 'git_credential', 'git_operation_log', 'module_release', 'module_install_pin'] as $t) {
            $this->assertTrue($this->tableExists($t), "expected git table `$t`");
        }
    }

    // --- credential: write-only --------------------------------------------------------------

    #[Test]
    public function store_returns_no_secret_and_resolve_round_trips(): void
    {
        $cred = new Tiger_Git_Credential();
        $out  = $cred->store('o1', 'acme/mod', Tiger_Model_GitCredential::TYPE_TOKEN, 'tgr_the_secret', ['fingerprint' => 'fp1', 'scopes' => 'contents:read']);

        $this->assertArrayHasKey('ref', $out);
        $flat = json_encode($out['public']);
        $this->assertStringNotContainsString('tgr_the_secret', $flat, 'the public view must never carry the secret');
        $this->assertSame('fp1', $out['public']['fingerprint']);

        $this->assertSame('tgr_the_secret', $cred->resolve($out['ref']), 'the transport read path recovers the secret');
    }

    #[Test]
    public function resolve_of_an_unknown_ref_is_null_not_a_throw(): void
    {
        $this->assertNull((new Tiger_Git_Credential())->resolve('00000000-0000-0000-0000-000000000000'));
        $this->assertNull((new Tiger_Git_Credential())->resolve(''));
    }

    #[Test]
    public function revoke_stops_the_secret_resolving(): void
    {
        $cred = new Tiger_Git_Credential();
        $ref  = $cred->store('o1', 'acme/mod', Tiger_Model_GitCredential::TYPE_TOKEN, 's3cret')['ref'];

        $this->assertTrue($cred->revoke($ref));
        $this->assertNull($cred->resolve($ref), 'a revoked credential no longer resolves');
        $this->assertNull((new Tiger_Model_GitCredential())->findById($ref), 'the row is soft-deleted');
    }

    #[Test]
    public function an_empty_secret_is_refused(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        (new Tiger_Git_Credential())->store('o1', 'acme/mod', Tiger_Model_GitCredential::TYPE_TOKEN, '');
    }

    #[Test]
    public function rotate_stores_the_new_without_revoking_the_old(): void
    {
        $cred = new Tiger_Git_Credential();
        $old  = $cred->store('o1', 'acme/mod', Tiger_Model_GitCredential::TYPE_TOKEN, 'old')['ref'];
        $new  = $cred->rotate('o1', 'acme/mod', Tiger_Model_GitCredential::TYPE_TOKEN, 'new')['ref'];

        $this->assertNotSame($old, $new);
        $this->assertSame('old', $cred->resolve($old), 'the old credential survives until verification passes');
        $this->assertSame('new', $cred->resolve($new));
    }

    #[Test]
    public function a_tiger_secret_ref_is_not_ours_to_revoke(): void
    {
        $this->assertFalse((new Tiger_Git_Credential())->revoke('ssm://tiger/prod/node/ses_key'));
    }

    // --- connection: active-only unique + finders --------------------------------------------

    #[Test]
    public function reconnect_after_disconnect_is_not_blocked_by_the_unique(): void
    {
        $m = new Tiger_Model_GitRepoConnection();
        $row = ['org_id' => 'o1', 'module_key' => 'acme/mod', 'repo_owner' => 'acme', 'repo_name' => 'mod', 'default_branch' => 'main', 'transport' => 'api'];

        $first = $m->insert($row);
        $m->softDelete($m->getAdapter()->quoteInto('git_repo_connection_id = ?', $first));

        // Same (org_id, module_key) again — a plain UNIQUE would throw here; the active-only one frees it.
        $second = $m->insert($row);
        $this->assertNotSame($first, $second);

        $live = $m->forModule('o1', 'acme/mod');
        $this->assertNotNull($live);
        $this->assertSame($second, $live->git_repo_connection_id);
    }

    #[Test]
    public function repo_connection_finders(): void
    {
        $m = new Tiger_Model_GitRepoConnection();
        $m->insert(['org_id' => 'o2', 'module_key' => 'a/one', 'repo_owner' => 'a', 'repo_name' => 'one', 'default_branch' => 'main', 'transport' => 'api']);
        $m->insert(['org_id' => 'o2', 'module_key' => 'a/two', 'repo_owner' => 'a', 'repo_name' => 'two', 'default_branch' => 'main', 'transport' => 'shell']);

        $this->assertCount(2, $m->forOrg('o2'));
        $this->assertSame('shell', $m->forModule('o2', 'a/two')->transport);
        $this->assertNull($m->forModule('o2', 'a/missing'));
    }

    #[Test]
    public function release_finders_by_tag_and_approved(): void
    {
        $m = new Tiger_Model_ModuleRelease();
        $m->insert(['org_id' => 'o3', 'module_key' => 'a/mod', 'version' => '1.0.0', 'git_tag' => 'v1.0.0', 'commit_sha' => 'abc', 'status' => Tiger_Model_ModuleRelease::STATUS_APPROVED]);
        $m->insert(['org_id' => 'o3', 'module_key' => 'a/mod', 'version' => '1.1.0', 'git_tag' => 'v1.1.0', 'commit_sha' => 'def', 'status' => Tiger_Model_ModuleRelease::STATUS_SUBMITTED]);

        $this->assertSame('v1.0.0', $m->byTag('o3', 'a/mod', 'v1.0.0')->git_tag);
        $this->assertNull($m->byTag('o3', 'a/mod', 'v9.9.9'));
        $this->assertCount(1, $m->approvedForModule('o3', 'a/mod'), 'only the approved release is installable');
    }

    #[Test]
    public function operation_log_records_and_reads_back(): void
    {
        $log = new Tiger_Model_GitOperationLog();
        $log->record(['org_id' => 'o4', 'module_key' => 'a/mod', 'operation' => 'commit', 'branch' => 'main', 'ref_after' => 'deadbeef', 'result' => Tiger_Model_GitOperationLog::RESULT_OK]);
        $log->record(['org_id' => 'o4', 'module_key' => 'a/mod', 'operation' => 'push', 'result' => Tiger_Model_GitOperationLog::RESULT_ERROR, 'error_code' => Tiger_Git_Exception::NON_FAST_FORWARD]);

        $rows = $log->recentForModule('o4', 'a/mod');
        $this->assertCount(2, $rows);
        $this->assertSame('push', $rows[0]->operation, 'newest first');
    }

    #[Test]
    public function install_pin_finder(): void
    {
        $m = new Tiger_Model_ModuleInstallPin();
        $m->insert(['org_id' => 'o5', 'module_key' => 'a/mod', 'installed_version' => '1.0.0', 'channel' => 'notify']);
        $this->assertSame('1.0.0', $m->forModule('o5', 'a/mod')->installed_version);
        $this->assertNull($m->forModule('o5', 'a/none'));
    }
}
