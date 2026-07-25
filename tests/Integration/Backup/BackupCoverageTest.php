<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Backup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Backup;
use Tiger_Backup_Archive;
use Tiger_Log;
use Tiger_Model_Backup;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Backup — the create/prune/restore/destinations engine, past the /api service surface.
 *
 * The sibling BackupServiceTest drives the happy `run`/`remove` round trip through the service; this
 * suite characterizes the parts of the static engine the service test doesn't reach — always without
 * ever running a REAL destructive restore (which would overwrite the live DB + files):
 *
 *   - `prune()` retention math (oldest scheduled/unpinned removed, manual pinned, max<=0 no-op);
 *   - `runScheduled()` config gate (disabled = no-op; enabled = a real database backup + prune);
 *   - `disks()` enumerating local + configured cloud media disks;
 *   - `restore()` guards (missing archive / no manifest / nothing-to-restore) AND a SAFE end-to-end
 *     restore of a manifest whose components collect no files and whose archive carries no database.sql
 *     — exercising the safety-backup + extract + happy-return path with nothing destructive to apply;
 *   - the restore CATCH path via a database.sql the importer rejects (a harmless syntactically-bad SELECT);
 *   - `fetchToTemp()` local resolution + its missing-file throw;
 *   - the internal file helpers (`_walk` excludes/symlink/.DS_Store, `_copyTree`, `_rrmdir`, `_cfg`,
 *     `_notify` gate) via reflection against throwaway temp dirs.
 *
 * Catalog rows ride the per-test transaction (rolled back); any archive bytes a backup writes to the
 * local disk are tracked and removed in tearDown.
 */
#[CoversClass(Tiger_Backup::class)]
final class BackupCoverageTest extends IntegrationTestCase
{
    /** @var string[] absolute paths of archive bytes / temp files to remove in tearDown. */
    private array $artifacts = [];
    private ?Zend_Config $priorConfig = null;
    private string $sandbox = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $this->setBackupConfig([]);   // a null log sink baseline; individual tests layer more on top
        Tiger_Log::reset();
        $this->sandbox = sys_get_temp_dir() . '/tiger-bk-' . bin2hex(random_bytes(6));
        @mkdir($this->sandbox, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->artifacts as $f) { @unlink($f); }
        $this->artifacts = [];
        $this->rmrf($this->sandbox);
        if ($this->priorConfig !== null) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif (Zend_Registry::isRegistered('Zend_Config')) {
            Zend_Registry::set('Zend_Config', new Zend_Config([]));
        }
        Tiger_Log::reset();
        parent::tearDown();
    }

    /** Install a Zend_Config with a null log sink plus whatever `tiger` overrides a test needs. */
    private function setBackupConfig(array $tiger): void
    {
        $tiger['log'] = ['writer' => 'null'];
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger] + $this->mediaSlice($tiger)));
    }

    /** Pull an optional 'media' top-level slice out of a caller's overrides (disks live under media, not tiger). */
    private function mediaSlice(array &$tiger): array
    {
        if (isset($tiger['__media'])) {
            $media = $tiger['__media'];
            unset($tiger['__media']);
            return ['media' => $media];
        }
        return [];
    }

    private function localBackupDir(): string
    {
        return APPLICATION_ROOT . '/storage/backups';
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) { @unlink($dir); return; }
        foreach (scandir($dir) ?: [] as $e) {
            if ($e === '.' || $e === '..') { continue; }
            $p = $dir . '/' . $e;
            is_dir($p) && !is_link($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    /** Invoke a protected static Tiger_Backup method; $args may carry references (e.g. _walk's &$files). */
    private static function invoke(string $method, array $args)
    {
        $m = new ReflectionMethod(Tiger_Backup::class, $method);   // PHP 8.1+: protected is invocable without setAccessible
        return $m->invokeArgs(null, $args);
    }

    /** Seed a finished scheduled backup catalog row (rides the per-test txn). */
    private function seedScheduled(): string
    {
        $model = new Tiger_Model_Backup();
        $id = $model->begin('TigerBackup-sched-' . bin2hex(random_bytes(3)) . '.zip', 'local', ['database'], 'scheduled');
        $model->finish($id, 'ok', ['storage_key' => 'nonexistent-' . $id . '.zip', 'size_bytes' => 1]);
        return $id;
    }

    // ---- prune -----------------------------------------------------------------------------------

    #[Test]
    public function prune_removes_the_oldest_scheduled_backups_over_the_retention_max(): void
    {
        $this->setBackupConfig(['backup' => ['retention' => ['max' => '2']]]);
        for ($i = 0; $i < 4; $i++) { $this->seedScheduled(); usleep(1000); }

        $model = new Tiger_Model_Backup();
        $this->assertCount(4, $model->prunable());

        $removed = Tiger_Backup::prune();
        $this->assertSame(2, $removed, 'the two oldest scheduled/unpinned backups are pruned to the max of 2');
        $this->assertCount(2, $model->prunable(), 'exactly the max survives');
    }

    #[Test]
    public function prune_is_a_noop_when_the_retention_max_is_zero_or_negative(): void
    {
        $this->setBackupConfig(['backup' => ['retention' => ['max' => '0']]]);
        $this->seedScheduled();
        $this->assertSame(0, Tiger_Backup::prune(), 'a max of 0 disables retention entirely');
    }

    // ---- runScheduled ----------------------------------------------------------------------------

    #[Test]
    public function run_scheduled_is_a_noop_when_the_schedule_is_disabled(): void
    {
        $this->setBackupConfig(['backup' => ['schedule' => ['enabled' => '0']]]);
        $before = count((new Tiger_Model_Backup())->recent(100));

        Tiger_Backup::runScheduled();

        $after = count((new Tiger_Model_Backup())->recent(100));
        $this->assertSame($before, $after, 'nothing runs when tiger.backup.schedule.enabled is off');
    }

    #[Test]
    public function run_scheduled_creates_a_real_database_backup_when_enabled(): void
    {
        $this->setBackupConfig(['backup' => [
            'schedule'   => ['enabled' => '1'],
            'components' => 'database',
            'disk'       => 'local',
        ]]);

        Tiger_Backup::runScheduled();

        // The scheduled ok row is present (rides the txn); its archive bytes are on the local disk.
        $rows = array_values(array_filter(
            (new Tiger_Model_Backup())->recent(100),
            fn($r) => $r['source'] === 'scheduled' && $r['outcome'] === 'ok'
        ));
        $this->assertNotEmpty($rows, 'an enabled scheduled run produced a completed backup');
        $archive = $this->localBackupDir() . '/' . $rows[0]['filename'];
        $this->artifacts[] = $archive;
        $this->assertFileExists($archive);
    }

    // ---- disks -----------------------------------------------------------------------------------

    #[Test]
    public function disks_lists_local_plus_only_the_configured_cloud_disks(): void
    {
        $this->setBackupConfig(['__media' => ['disks' => [
            'local'    => ['adapter' => 'filesystem'],   // the local FS disk — never a "cloud" destination
            'archive'  => ['adapter' => 'local'],        // also excluded (local synonym)
            's3backup' => ['adapter' => 's3'],           // a real cloud disk — included
        ]]]);

        $disks = Tiger_Backup::disks();
        $names = array_column($disks, 'name');

        $this->assertContains('local', $names, 'the local server is always a destination');
        $this->assertContains('s3backup', $names, 'a configured cloud disk is offered');
        $this->assertNotContains('archive', $names, 'a filesystem/local-adapter disk is not a cloud destination');
        // the label reflects the adapter, upper-cased.
        $s3 = $disks[array_search('s3backup', $names, true)];
        $this->assertStringContainsString('S3', $s3['label']);
    }

    // ---- restore guards --------------------------------------------------------------------------

    #[Test]
    public function restore_errors_when_the_archive_file_is_missing(): void
    {
        $res = Tiger_Backup::restore($this->sandbox . '/nope.zip');
        $this->assertSame('error', $res['status']);
        $this->assertStringContainsString('not found', strtolower($res['error']));
    }

    #[Test]
    public function restore_errors_when_the_archive_has_no_manifest(): void
    {
        $zip = $this->sandbox . '/no-manifest.zip';
        Tiger_Backup_Archive::build($zip, [['name' => 'random.txt', 'data' => 'x']]);

        $res = Tiger_Backup::restore($zip);
        $this->assertSame('error', $res['status']);
        $this->assertStringContainsString('manifest', strtolower($res['error']));
    }

    #[Test]
    public function restore_errors_when_no_requested_component_is_present_in_the_archive(): void
    {
        $zip = $this->manifestOnlyArchive(['database']);
        $res = Tiger_Backup::restore($zip, ['media']);   // ask for media; archive only has database
        $this->assertSame('error', $res['status']);
        $this->assertStringContainsString('nothing', strtolower($res['error']));
    }

    // ---- restore: a SAFE full pass (nothing destructive to apply) --------------------------------

    #[Test]
    public function restore_runs_end_to_end_and_takes_a_safety_backup(): void
    {
        // A manifest declaring only DATABASE, but no database.sql in the archive → the restore walks the
        // whole guarded path (maintenance flag → safety backup → extract → nothing to import → ok) with
        // nothing to actually overwrite. This exercises the destructive machinery, non-destructively.
        $zip = $this->manifestOnlyArchive(['database']);

        $res = Tiger_Backup::restore($zip, [], ['safety' => true]);

        $this->assertSame('ok', $res['status'], json_encode($res));
        $this->assertSame([], $res['restored'], 'no component had bytes to restore');
        $this->assertNotNull($res['safety_id'], 'a pre-restore safety backup was taken');

        // Clean up the real safety-backup archive the pass wrote to the local disk.
        $row = (new Tiger_Model_Backup())->findById($res['safety_id']);
        if ($row && !empty($row['filename'])) { $this->artifacts[] = $this->localBackupDir() . '/' . $row['filename']; }
    }

    #[Test]
    public function restore_returns_an_error_when_the_database_import_is_rejected(): void
    {
        // Craft an archive whose database.sql carries the required token header but a syntactically broken
        // statement, so Tiger_Backup_Database::import() throws and restore() reports the failure. The bad
        // statement is a harmless (failed) SELECT — no schema or data is touched.
        $token = 'abc123';
        $sep   = "\n-- @" . $token . "@\n";
        $sql   = "-- TigerBackup dump\n-- TIGER_STMT_TOKEN: {$token}\n" . $sep . "SELECT bad ) syntax here;" . $sep;

        $zip = $this->sandbox . '/bad-db.zip';
        Tiger_Backup_Archive::build($zip, [
            ['name' => 'manifest.json', 'data' => json_encode(['components' => ['database']])],
            ['name' => 'database.sql', 'data' => $sql],
        ]);

        $res = Tiger_Backup::restore($zip, ['database'], ['safety' => false]);
        $this->assertSame('error', $res['status'], 'a failed DB import surfaces as an error');
        $this->assertNotEmpty($res['error']);
    }

    // ---- fetchToTemp -----------------------------------------------------------------------------

    #[Test]
    public function fetch_to_temp_returns_a_local_archive_in_place(): void
    {
        $dir = $this->localBackupDir();
        @mkdir($dir, 0775, true);
        $fn = 'TigerBackup-fetch-' . bin2hex(random_bytes(4)) . '.zip';
        $path = $dir . '/' . $fn;
        file_put_contents($path, 'zip-bytes');
        $this->artifacts[] = $path;

        $got = Tiger_Backup::fetchToTemp(['disk' => 'local', 'storage_key' => $fn, 'filename' => $fn]);
        $this->assertSame($path, $got, 'a local backup is returned in place, not copied');
    }

    #[Test]
    public function fetch_to_temp_throws_when_a_local_archive_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        Tiger_Backup::fetchToTemp(['disk' => 'local', 'storage_key' => 'not-here-' . bin2hex(random_bytes(4)) . '.zip', 'filename' => 'x.zip']);
    }

    // ---- internal helpers (reflection, throwaway temp dirs) --------------------------------------

    #[Test]
    public function walk_maps_files_honoring_excludes_symlinks_and_ds_store(): void
    {
        $root = $this->sandbox;
        mkdir($root . '/keep', 0777, true);
        mkdir($root . '/skipme', 0777, true);
        file_put_contents($root . '/keep/a.txt', 'a');
        file_put_contents($root . '/keep/.DS_Store', 'junk');   // must be skipped
        file_put_contents($root . '/skipme/b.txt', 'b');        // excluded dir
        file_put_contents($root . '/top.txt', 'c');
        if (!@symlink($root . '/keep/a.txt', $root . '/link.txt')) { /* symlinks may be unavailable; fine */ }

        $files = [];
        $args = [$root, $root, [$root . '/skipme'], &$files];
        self::invoke('_walk', $args);

        $keys = array_keys($files);
        $this->assertContains('files/keep/a.txt', $keys);
        $this->assertContains('files/top.txt', $keys);
        $this->assertNotContains('files/keep/.DS_Store', $keys, '.DS_Store is skipped');
        $this->assertNotContains('files/skipme/b.txt', $keys, 'an excluded directory is skipped');
        $this->assertNotContains('files/link.txt', $keys, 'symlinks are never followed');
    }

    #[Test]
    public function copy_tree_mirrors_a_directory_recursively(): void
    {
        $src = $this->sandbox . '/src';
        $dst = $this->sandbox . '/dst';
        mkdir($src . '/sub', 0777, true);
        mkdir($dst, 0777, true);   // _copyTree copies src/* INTO an existing dst (as restore() sets up)
        file_put_contents($src . '/root.txt', 'r');
        file_put_contents($src . '/sub/child.txt', 'c');

        self::invoke('_copyTree', [$src, $dst]);

        $this->assertSame('r', file_get_contents($dst . '/root.txt'));
        $this->assertSame('c', file_get_contents($dst . '/sub/child.txt'));
    }

    #[Test]
    public function copy_tree_is_a_noop_for_a_missing_source(): void
    {
        self::invoke('_copyTree', [$this->sandbox . '/does-not-exist', $this->sandbox . '/out']);
        $this->assertDirectoryDoesNotExist($this->sandbox . '/out');
    }

    #[Test]
    public function rrmdir_removes_a_whole_tree(): void
    {
        $dir = $this->sandbox . '/tree';
        mkdir($dir . '/a/b', 0777, true);
        file_put_contents($dir . '/a/x.txt', 'x');
        file_put_contents($dir . '/a/b/y.txt', 'y');

        self::invoke('_rrmdir', [$dir]);
        $this->assertDirectoryDoesNotExist($dir);
    }

    #[Test]
    public function cfg_returns_the_default_for_a_non_scalar_or_absent_node(): void
    {
        $this->setBackupConfig(['backup' => ['retention' => ['max' => '9']]]);
        // A section node (non-scalar) → default; a scalar leaf → its string value; an absent key → default.
        $this->assertSame('fallback', self::invoke('_cfg', ['tiger.backup.retention', 'fallback']));
        $this->assertSame('9', self::invoke('_cfg', ['tiger.backup.retention.max', 'x']));
        $this->assertSame('def', self::invoke('_cfg', ['tiger.backup.nope.missing', 'def']));
    }

    #[Test]
    public function notify_returns_early_when_enabled_but_no_recipient_is_configured(): void
    {
        // notify explicitly on (via opts), but the email list is empty → the send body is skipped, no mail.
        $this->setBackupConfig(['backup' => ['notify' => ['email' => '']]]);
        self::invoke('_notify', [true, 'TigerBackup-x.zip', ['size' => 1, 'components' => ['database']], ['notify' => true]]);
        $this->assertTrue(true, 'no recipient → _notify is a safe no-op (no exception, no mail)');
    }

    // ---- helpers ---------------------------------------------------------------------------------

    /** Build a valid TigerBackup archive holding ONLY a manifest.json declaring $components. */
    private function manifestOnlyArchive(array $components): string
    {
        $zip = $this->sandbox . '/manifest-only-' . bin2hex(random_bytes(3)) . '.zip';
        Tiger_Backup_Archive::build($zip, [
            ['name' => 'manifest.json', 'data' => json_encode(['components' => $components, 'created_at' => date('c')])],
        ]);
        return $zip;
    }
}
