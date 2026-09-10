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
use Tiger_Backup_Database;
use Zend_Config;
use Zend_Registry;

/** A Tiger_Backup whose safety backup fails on demand (restore() calls static::create()). */
final class FailingSafetyBackup extends Tiger_Backup
{
    public static array $created = [];
    public static string $root   = '';

    /**
     * Point the destructive destination at a sandbox.
     *
     * Without this the test writes into the REAL checkout whenever the guard under test fails —
     * which is exactly what happened while mutation-testing: with the safety gate removed, restore()
     * ran for real and dropped a file into public/_media/ of the working tree. A test for a
     * destructive path must not be able to perform the destruction it is asserting against.
     */
    protected static function _root() { return self::$root !== '' ? self::$root : parent::_root(); }
    public static function create(array $components, array $opts = [])
    {
        self::$created[] = $components;
        // create() returns a backup_id on its ERROR path too — the shape that fooled restore().
        return ['status' => 'error', 'backup_id' => 'bk-failed-1', 'error' => 'disk full'];
    }
}

/**
 * The backup/restore safety fixes: TIGER-83 through 87.
 *
 * These are destructive-path guarantees, so each is written as "what must NOT happen": restore must not
 * proceed without a recovery point, must not touch components nobody selected, must not report success
 * over a failed write, and must not let two archives share a name.
 */
#[CoversClass(Tiger_Backup::class)]
#[CoversClass(Tiger_Backup_Database::class)]
final class BackupSafetyTest extends IntegrationTestCase
{
    private string $sandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $base = Zend_Registry::isRegistered('Zend_Config')
            ? new Zend_Config(Zend_Registry::get('Zend_Config')->toArray(), true)
            : new Zend_Config([], true);
        $base->merge(new Zend_Config(['tiger' => ['log' => ['writer' => 'null']]], true));
        Zend_Registry::set('Zend_Config', $base);
        if (class_exists('Tiger_Log')) { \Tiger_Log::reset(); }

        $this->sandbox = sys_get_temp_dir() . '/bk-safety-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0777, true);
        FailingSafetyBackup::$created = [];
        FailingSafetyBackup::$root    = '';
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->sandbox);
        if (class_exists('Tiger_Log')) { \Tiger_Log::reset(); }
        parent::tearDown();
    }

    private function rrmdir(string $d): void
    {
        if (!is_dir($d)) { return; }
        foreach (scandir($d) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = "$d/$f";
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($d);
    }

    private static function invoke(string $method, array $args)
    {
        return (new ReflectionMethod(Tiger_Backup::class, $method))->invokeArgs(null, $args);
    }

    // ---- TIGER-83: no recovery point, no destruction ----------------------------------------------

    #[Test]
    public function restore_aborts_when_its_safety_backup_fails(): void
    {
        // A real archive, so the ONLY reason to stop is the failed safety backup.
        $zip = $this->sandbox . '/src.zip';
        $za  = new \ZipArchive();
        $za->open($zip, \ZipArchive::CREATE);
        $za->addFromString('manifest.json', json_encode(['components' => ['media'], 'version' => '1']));
        $za->addFromString('files/public/_media/x.txt', 'restored');
        $za->close();

        $live = $this->sandbox . '/live-root';
        mkdir($live, 0777, true);
        FailingSafetyBackup::$root = $live;

        $res = FailingSafetyBackup::restore($zip, ['media']);

        $this->assertSame('error', $res['status'], 'restore refuses to run');
        $this->assertStringContainsString('Safety backup failed', $res['error']);
        $this->assertNotEmpty(FailingSafetyBackup::$created, 'it did attempt the safety backup first');
        $this->assertSame([], $res['restored'] ?? [], 'nothing was restored');
        $this->assertFalse(
            is_file($live . '/public/_media/x.txt'),
            'and nothing was written to the destination — the abort happened BEFORE extraction'
        );
    }

    // ---- TIGER-84: only what was asked for --------------------------------------------------------

    #[Test]
    public function a_partial_restore_leaves_unselected_components_untouched(): void
    {
        // A staged tree holding BOTH a media file and a platform file — the mixed archive from the bug.
        $stage = $this->sandbox . '/files';
        mkdir($stage . '/public/_media', 0777, true);
        mkdir($stage . '/application/configs', 0777, true);
        file_put_contents($stage . '/public/_media/photo.jpg', 'NEW-MEDIA');
        file_put_contents($stage . '/application/configs/local.ini', 'NEW-PLATFORM');

        // A live root where BOTH already exist with current content.
        $live = $this->sandbox . '/live';
        mkdir($live . '/public/_media', 0777, true);
        mkdir($live . '/application/configs', 0777, true);
        file_put_contents($live . '/public/_media/photo.jpg', 'OLD-MEDIA');
        file_put_contents($live . '/application/configs/local.ini', 'CURRENT-PLATFORM');

        // Restore MEDIA only.
        $paths  = ['include' => ['public/_media'], 'exclude' => []];
        $failed = [];
        $n = self::invoke('_copyTree', [$stage, $live, $paths, '', &$failed]);

        $this->assertSame('NEW-MEDIA', file_get_contents($live . '/public/_media/photo.jpg'), 'media restored');
        $this->assertSame(
            'CURRENT-PLATFORM',
            file_get_contents($live . '/application/configs/local.ini'),
            'platform config was NOT selected and must be untouched — this is the whole bug'
        );
        $this->assertSame(1, $n, 'exactly one file copied');
        $this->assertSame([], $failed);
    }

    #[Test]
    public function a_sibling_inside_a_traversed_directory_is_not_restored(): void
    {
        // The case the directory gate alone cannot catch, and the reason a FILE-level gate exists:
        // selecting `application/modules` means walking THROUGH application/ to reach it — but a file
        // sitting directly in application/, or in a sibling like application/configs, was never
        // selected and must not be written. Found by mutation testing: deleting the file gate left
        // every other test green.
        $stage = $this->sandbox . '/s5';
        mkdir($stage . '/application/modules', 0777, true);
        mkdir($stage . '/application/configs', 0777, true);
        file_put_contents($stage . '/application/modules/m.txt', 'NEW-MODULE');
        file_put_contents($stage . '/application/configs/local.ini', 'NEW-SECRETS');
        file_put_contents($stage . '/application/Bootstrap.php', 'NEW-BOOTSTRAP');

        $live = $this->sandbox . '/l5';
        mkdir($live . '/application/modules', 0777, true);
        mkdir($live . '/application/configs', 0777, true);
        file_put_contents($live . '/application/modules/m.txt', 'OLD-MODULE');
        file_put_contents($live . '/application/configs/local.ini', 'CURRENT-SECRETS');
        file_put_contents($live . '/application/Bootstrap.php', 'CURRENT-BOOTSTRAP');

        $failed = [];
        $n = self::invoke('_copyTree', [$stage, $live, ['include' => ['application/modules'], 'exclude' => []], '', &$failed]);

        $this->assertSame('NEW-MODULE', file_get_contents($live . '/application/modules/m.txt'), 'the selected component IS restored');
        $this->assertSame('CURRENT-SECRETS', file_get_contents($live . '/application/configs/local.ini'), 'a sibling directory is untouched');
        $this->assertSame('CURRENT-BOOTSTRAP', file_get_contents($live . '/application/Bootstrap.php'), 'a file in the traversed parent is untouched');
        $this->assertSame(1, $n, 'exactly one file copied');
    }

    #[Test]
    public function selecting_platform_still_reaches_nested_paths(): void
    {
        // Positive control: scoping must not become "restore nothing". application/ has to be walked
        // to reach application/configs beneath it.
        $stage = $this->sandbox . '/s2';
        mkdir($stage . '/application/configs', 0777, true);
        file_put_contents($stage . '/application/configs/app.ini', 'NEW');
        $live = $this->sandbox . '/l2';
        mkdir($live . '/application/configs', 0777, true);
        file_put_contents($live . '/application/configs/app.ini', 'OLD');

        $failed = [];
        self::invoke('_copyTree', [$stage, $live, ['include' => ['application'], 'exclude' => []], '', &$failed]);

        $this->assertSame('NEW', file_get_contents($live . '/application/configs/app.ini'));
    }

    #[Test]
    public function excluded_paths_are_never_written(): void
    {
        $stage = $this->sandbox . '/s3';
        mkdir($stage . '/public/_media', 0777, true);
        file_put_contents($stage . '/public/_media/m.txt', 'NEW');
        $live = $this->sandbox . '/l3';
        mkdir($live . '/public/_media', 0777, true);
        file_put_contents($live . '/public/_media/m.txt', 'OLD');

        $failed = [];
        self::invoke('_copyTree', [$stage, $live, ['include' => ['public'], 'exclude' => ['public/_media']], '', &$failed]);

        $this->assertSame('OLD', file_get_contents($live . '/public/_media/m.txt'), 'an excluded path wins over its including parent');
    }

    // ---- TIGER-85: a failed write is never a success ----------------------------------------------

    #[Test]
    public function a_copy_that_cannot_be_written_is_reported_not_swallowed(): void
    {
        $stage = $this->sandbox . '/s4';
        mkdir($stage . '/application', 0777, true);
        file_put_contents($stage . '/application/f.txt', 'DATA');

        $live = $this->sandbox . '/l4';
        mkdir($live . '/application', 0777, true);
        if (!@chmod($live . '/application', 0555) || is_writable($live . '/application')) {
            $this->markTestSkipped('cannot make a directory read-only here (running as root?)');
        }
        $failed = [];
        self::invoke('_copyTree', [$stage, $live, ['include' => ['application'], 'exclude' => []], '', &$failed]);
        @chmod($live . '/application', 0777);

        $this->assertNotEmpty($failed, 'an unwritable destination surfaces as a failure');
        $this->assertContains('application/f.txt', $failed);
    }

    #[Test]
    public function a_short_write_in_the_dump_throws(): void
    {
        // fwrite() reports a short write by return value rather than raising, so an unchecked call turns
        // a full disk into a silently truncated archive. A closed handle is the deterministic stand-in.
        $path = $this->sandbox . '/x.sql';
        file_put_contents($path, '');
        $fh = fopen($path, 'rb');            // open for READING — every write to it fails
        $this->assertIsResource($fh);

        $m = new ReflectionMethod(Tiger_Backup_Database::class, '_w');
        try {
            @$m->invokeArgs(null, [$fh, 'some bytes', $path]);
            fclose($fh);
            $this->fail('a failed write must throw, not be swallowed');
        } catch (RuntimeException $e) {
            fclose($fh);
            $this->assertStringContainsString('short write', $e->getMessage());
        }
    }

    // ---- TIGER-86: archives never share a name ----------------------------------------------------

    #[Test]
    public function archive_names_are_unique_even_within_the_same_second(): void
    {
        $names = [];
        for ($i = 0; $i < 50; $i++) { $names[] = self::invoke('_archiveName', []); }

        $this->assertCount(50, array_unique($names), '50 names generated back-to-back are all distinct');
        foreach ($names as $n) {
            $this->assertMatchesRegularExpression('/^TigerBackup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}-[0-9a-f]{6}\.zip$/', $n);
        }
    }

    // ---- TIGER-87: the snapshot, and the guard on it ----------------------------------------------

    #[Test]
    public function the_dump_records_whether_it_got_a_consistent_snapshot(): void
    {
        // Inside the harness's per-test transaction, dump() must DEFER its own snapshot rather than
        // issue START TRANSACTION — which MySQL treats as an implicit commit and would silently commit
        // the caller's work. It records the fact instead of pretending.
        $path = $this->sandbox . '/d.sql';
        $res  = Tiger_Backup_Database::dump($path);

        $this->assertArrayHasKey('snapshot', $res);
        $this->assertFalse($res['snapshot'], 'deferred, because this test already holds a transaction');
        $this->assertStringContainsString('consistent_snapshot: no', (string) file_get_contents($path));
        $this->assertGreaterThan(0, $res['tables']);
    }

    #[Test]
    public function the_dump_does_not_commit_the_callers_transaction(): void
    {
        // The regression that leaked rows into the shared test database: an unguarded
        // START TRANSACTION committed the harness's wrapping transaction.
        $pdo = $this->db->getConnection();
        $this->assertTrue($pdo->inTransaction(), 'the harness holds a transaction');

        Tiger_Backup_Database::dump($this->sandbox . '/d2.sql');

        $this->assertTrue($pdo->inTransaction(), 'and it is STILL open after the dump');
    }
}
