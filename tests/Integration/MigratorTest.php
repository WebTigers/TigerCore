<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Db_Migrator;

/**
 * Tiger_Db_Migrator — the tiny, dependency-free schema runner. Verified against a real MariaDB with
 * THROWAWAY fixture migrations in a temp dir, so the core-migrated schema is never disturbed.
 *
 * The invariants under test are the ones the platform's whole schema lifecycle rests on:
 *   - version ordering (ascending, string-sorted zero-padded);
 *   - idempotency (a re-run applies nothing);
 *   - the `tiger_migration` ledger records exactly the applied versions;
 *   - APPLY-ONLY-AFTER-ALL-SUCCEED — a migration whose statements don't all succeed is left UNrecorded
 *     (so the next run retries it), never half-marked done;
 *   - rollback() runs the down statements and deletes the ledger row;
 *   - status() reflects applied vs pending.
 *
 * IMPORTANT ISOLATION NOTE: MySQL/MariaDB auto-commits DDL, which ends the base class's per-test
 * transaction — so these DDL-driven tests can't rely on the tearDown rollback for cleanup. This test
 * therefore uses out-of-range fixture versions (9xxx, far above any real migration) and distinctive
 * `zzz_mig_*` table names, and cleans BOTH up explicitly (drop tables + delete ledger rows).
 */
#[CoversClass(Tiger_Db_Migrator::class)]
final class MigratorTest extends IntegrationTestCase
{
    /** Fixture versions this test may create — purged from the ISOLATED ledger in tearDown. */
    private const FIXTURE_VERSIONS = ['9001', '9002', '9003', '9101', '9102', '9201', '9301', '9401', '9501', '9601', '9701'];

    /**
     * An ISOLATED ledger table so this test's fixtures never share the real `tiger_migration` set.
     * rollback() reverses the newest applied version GLOBALLY, so if a real (or another test's)
     * timestamp-versioned migration is committed to the shared ledger it would sort above the 9xxx
     * fixtures and be picked instead — a cross-test flake. A dedicated ledger makes rollback hermetic.
     */
    private const LEDGER = 'tiger_migration_test';

    /** Throwaway tables the fixtures may create — dropped in tearDown. */
    private const THROWAWAY_TABLES = [
        'zzz_mig_a', 'zzz_mig_b', 'zzz_mig_c', 'zzz_mig_p1', 'zzz_mig_p2', 'zzz_mig_r',
        'zzz_mig_dup_a', 'zzz_mig_dup_b', 'zzz_mig_renamed',
    ];

    /** A ledger created by an OLDER core (PRIMARY KEY on version alone) — dropped in tearDown. */
    private const LEGACY_LEDGER = 'tiger_migration_legacy_test';

    /** @var string[] temp dirs created this test, removed in tearDown */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        // DDL auto-committed past the test transaction, so undo the side effects by hand.
        foreach (self::THROWAWAY_TABLES as $t) {
            try { $this->db->query("DROP TABLE IF EXISTS `$t`"); } catch (\Throwable $e) {}
        }
        foreach (self::FIXTURE_VERSIONS as $v) {
            try { $this->db->delete(self::LEDGER, $this->db->quoteInto('version = ?', $v)); } catch (\Throwable $e) {}
        }
        try { $this->db->query('DROP TABLE IF EXISTS `' . self::LEGACY_LEDGER . '`'); } catch (\Throwable $e) {}
        foreach ($this->tmpDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($dir);
        }
        parent::tearDown();
    }

    /** Make a temp migrations dir seeded with the given [filename => php-source] fixtures. */
    private function fixtureDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/tiger_mig_' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;
        foreach ($files as $name => $src) {
            file_put_contents($dir . '/' . $name, $src);
        }
        return $dir;
    }

    /** A migration file body that CREATEs then DROPs a throwaway table. */
    private static function createDropMigration(string $table): string
    {
        return "<?php return ['up' => [\"CREATE TABLE `$table` (id INT)\"], 'down' => [\"DROP TABLE IF EXISTS `$table`\"]];";
    }

    private function ledgerHas(string $version): bool
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM ' . self::LEDGER . ' WHERE version = ?', [$version]) > 0;
    }

    #[Test]
    public function migrate_applies_pending_versions_in_ascending_order_and_records_the_ledger(): void
    {
        // Deliberately write the higher version FIRST to prove discovery sorts, not filesystem order.
        $dir = $this->fixtureDir([
            '9002_create_b.php' => self::createDropMigration('zzz_mig_b'),
            '9001_create_a.php' => self::createDropMigration('zzz_mig_a'),
        ]);
        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER);

        $applied = $migrator->migrate();

        // PHP casts numeric-string array keys to int, so the discovered version map is keyed by int.
        $this->assertSame([9001, 9002], array_keys($applied), 'applied in ascending version order');
        $this->assertSame(['create_a', 'create_b'], array_values($applied), 'name parsed from the filename');
        $this->assertTrue($this->tableExists('zzz_mig_a'), 'the up statement ran');
        $this->assertTrue($this->tableExists('zzz_mig_b'));
        $this->assertTrue($this->ledgerHas('9001'), 'applied version is recorded in tiger_migration');
        $this->assertTrue($this->ledgerHas('9002'));
    }

    #[Test]
    public function migrate_is_idempotent_a_second_run_applies_nothing(): void
    {
        $dir = $this->fixtureDir(['9001_create_a.php' => self::createDropMigration('zzz_mig_a')]);
        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER);

        $this->assertSame(['9001' => 'create_a'], $migrator->migrate(), 'first run applies it');
        $this->assertSame([], $migrator->migrate(), 'a second run skips the already-applied version');
    }

    #[Test]
    public function status_reports_applied_versus_pending(): void
    {
        $dir = $this->fixtureDir([
            '9001_create_a.php' => self::createDropMigration('zzz_mig_a'),
            '9002_create_b.php' => self::createDropMigration('zzz_mig_b'),
        ]);
        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER);

        // Apply only 9001 by hand-recording it, then let status() classify both.
        $migrator->migrate();                                  // applies both
        $this->db->delete(self::LEDGER, $this->db->quoteInto('version = ?', '9002')); // pretend 9002 pending

        $status = $migrator->status();
        $this->assertTrue($status['9001']['applied'], '9001 is applied');
        $this->assertFalse($status['9002']['applied'], '9002 reads as pending');
        $this->assertSame('create_a', $status['9001']['name']);
    }

    #[Test]
    public function a_migration_that_fails_midway_is_left_unrecorded_and_stays_pending(): void
    {
        // 9101 is clean; 9102's SECOND statement is invalid SQL. The runner throws while applying 9102,
        // so 9102 must NOT be recorded — leaving it pending to be retried on the next run.
        $dir = $this->fixtureDir([
            '9101_create_p1.php' => self::createDropMigration('zzz_mig_p1'),
            '9102_partial.php'   => "<?php return ['up' => ["
                . "\"CREATE TABLE IF NOT EXISTS `zzz_mig_p2` (id INT)\", "
                . "\"THIS IS NOT VALID SQL\""
                . "], 'down' => [\"DROP TABLE IF EXISTS `zzz_mig_p2`\"]];",
        ]);
        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER);

        $threw = false;
        try {
            $migrator->migrate();
        } catch (\Throwable $e) {
            $threw = true;                 // the invalid statement surfaces as an exception
        }
        $this->assertTrue($threw, 'an invalid statement aborts the run');

        // The clean earlier migration IS recorded; the failing one is NOT.
        $this->assertTrue($this->ledgerHas('9101'), 'the migration before the failure committed + recorded');
        $this->assertFalse($this->ledgerHas('9102'), 'apply-only-after-all-succeed: the failed migration is unrecorded');

        // A subsequent run still sees it as pending (retried, never silently marked done).
        $this->assertFalse($migrator->status()['9102']['applied'], '9102 remains pending after a failed run');
    }

    #[Test]
    public function rollback_runs_down_statements_and_deletes_the_ledger_row(): void
    {
        $dir = $this->fixtureDir(['9201_create_r.php' => self::createDropMigration('zzz_mig_r')]);
        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER);

        $migrator->migrate();
        $this->assertTrue($this->tableExists('zzz_mig_r'), 'precondition: up created the table');
        $this->assertTrue($this->ledgerHas('9201'), 'precondition: recorded');

        $rolled = $migrator->rollback(1);

        $this->assertSame(['9201' => 'create_r'], $rolled, 'rollback reports what it reversed');
        $this->assertFalse($this->tableExists('zzz_mig_r'), 'the down statement dropped the table');
        $this->assertFalse($this->ledgerHas('9201'), 'the ledger row is removed so it can be re-applied');
    }

    // ---- TIGER-477: shared version numbers across modules ------------------------------------------

    private function ledgerRows(string $version, string $ledger = self::LEDGER): array
    {
        return $this->db->fetchCol('SELECT name FROM `' . $ledger . '` WHERE version = ? ORDER BY name', [$version]);
    }

    #[Test]
    public function two_modules_sharing_a_version_both_run_and_are_recorded_separately(): void
    {
        // Two module dirs that independently picked the same version number.
        $alpha = $this->fixtureDir(['9301_create_alpha.php' => self::createDropMigration('zzz_mig_dup_a')]);
        $beta = $this->fixtureDir(['9301_create_beta.php' => self::createDropMigration('zzz_mig_dup_b')]);
        $migrator = new Tiger_Db_Migrator($this->db, [$alpha, $beta], self::LEDGER, [$alpha, $beta]);

        $ran = $migrator->migrate();

        $this->assertSame(['9301_create_alpha' => 'create_alpha', '9301_create_beta' => 'create_beta'], $ran,
            'both run, ordered by name within the shared version, keyed version_name');
        $this->assertTrue($this->tableExists('zzz_mig_dup_a'), 'the first module\'s schema exists');
        $this->assertTrue($this->tableExists('zzz_mig_dup_b'), 'the second is NOT silently skipped');
        $this->assertSame(['create_alpha', 'create_beta'], $this->ledgerRows('9301'));
        $this->assertSame([], $migrator->migrate(), 'idempotent');

        $status = $migrator->status();
        $this->assertTrue($status['9301_create_alpha']['applied']);
        $this->assertCount(2, $status['9301_create_alpha']['duplicates'], 'status flags the shared version');
        $this->assertCount(1, $migrator->duplicates());
    }

    #[Test]
    public function an_install_that_already_skipped_the_shadowed_migration_gets_it_on_the_next_run(): void
    {
        // The old core recorded the version under ONE name and silently skipped the other module's file.
        $alpha = $this->fixtureDir(['9401_create_alpha.php' => self::createDropMigration('zzz_mig_dup_a')]);
        $beta = $this->fixtureDir(['9401_create_beta.php' => self::createDropMigration('zzz_mig_dup_b')]);
        (new Tiger_Db_Migrator($this->db, [$alpha], self::LEDGER, [$alpha]))->migrate();   // alpha applied first
        $this->assertSame(['create_alpha'], $this->ledgerRows('9401'));

        $ran = (new Tiger_Db_Migrator($this->db, [$alpha, $beta], self::LEDGER, [$alpha, $beta]))->migrate();

        $this->assertSame(['9401_create_beta' => 'create_beta'], $ran, 'only the skipped one runs — the heal');
        $this->assertTrue($this->tableExists('zzz_mig_dup_b'));
    }

    #[Test]
    public function a_scoped_module_install_never_mistakes_another_modules_row_for_a_rename(): void
    {
        // Module A applied 9501 earlier; module B (same version) is installed later by a migrator SCOPED to B's
        // dir (Tiger_Module_Installer::migrateModule). A's file still exists on the install, so A's ledger row is
        // NOT an orphan and must not make B look already-applied.
        $a = $this->fixtureDir(['9501_create_a.php' => self::createDropMigration('zzz_mig_dup_a')]);
        $b = $this->fixtureDir(['9501_create_b.php' => self::createDropMigration('zzz_mig_dup_b')]);
        (new Tiger_Db_Migrator($this->db, [$a], self::LEDGER, [$a, $b]))->migrate();

        $ran = (new Tiger_Db_Migrator($this->db, [$b], self::LEDGER, [$a, $b]))->migrate();

        $this->assertSame(['9501' => 'create_b'], $ran);
        $this->assertTrue($this->tableExists('zzz_mig_dup_b'));
    }

    #[Test]
    public function a_file_renamed_after_it_ran_is_still_applied(): void
    {
        // A module renamed a migration file (same version, new name) after installs had already applied it.
        $dir = $this->fixtureDir(['9601_create_renamed.php' => self::createDropMigration('zzz_mig_renamed')]);
        $this->db->insert(self::LEDGER, ['version' => '9601', 'name' => 'create_old_name', 'applied_at' => date('Y-m-d H:i:s')]);

        $migrator = new Tiger_Db_Migrator($this->db, [$dir], self::LEDGER, [$dir]);

        $this->assertSame([], $migrator->migrate(), 'the orphan row at its version means it already ran');
        $this->assertTrue($migrator->status()['9601']['applied']);
        $this->assertFalse($this->tableExists('zzz_mig_renamed'), 'its up never re-ran');
    }

    #[Test]
    public function a_scoped_purge_rolls_back_only_its_own_module_at_a_shared_version(): void
    {
        // Installer::remove/purge rolls back over the WHOLE ledger with a migrator scoped to one module.
        $a = $this->fixtureDir(['9701_create_a.php' => self::createDropMigration('zzz_mig_dup_a')]);
        $b = $this->fixtureDir(['9701_create_b.php' => self::createDropMigration('zzz_mig_dup_b')]);
        (new Tiger_Db_Migrator($this->db, [$a, $b], self::LEDGER, [$a, $b]))->migrate();

        (new Tiger_Db_Migrator($this->db, [$a], self::LEDGER, [$a, $b]))->rollback(1000000);

        $this->assertFalse($this->tableExists('zzz_mig_dup_a'), 'A\'s down ran');
        $this->assertTrue($this->tableExists('zzz_mig_dup_b'), 'B\'s schema untouched');
        $this->assertSame(['create_b'], $this->ledgerRows('9701'), 'B\'s ledger row kept');
    }

    #[Test]
    public function a_legacy_version_keyed_ledger_is_upgraded_in_place_keeping_its_rows(): void
    {
        $this->db->query('CREATE TABLE `' . self::LEGACY_LEDGER . '` (
            `version` VARCHAR(64) NOT NULL, `name` VARCHAR(191) NOT NULL, `applied_at` DATETIME NOT NULL,
            PRIMARY KEY (`version`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $this->db->insert(self::LEGACY_LEDGER, ['version' => '9301', 'name' => 'create_alpha', 'applied_at' => date('Y-m-d H:i:s')]);

        $alpha = $this->fixtureDir(['9301_create_alpha.php' => self::createDropMigration('zzz_mig_dup_a')]);
        $beta = $this->fixtureDir(['9301_create_beta.php' => self::createDropMigration('zzz_mig_dup_b')]);
        $ran = (new Tiger_Db_Migrator($this->db, [$alpha, $beta], self::LEGACY_LEDGER, [$alpha, $beta]))->migrate();

        $cols = $this->db->fetchCol(
            'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? ORDER BY ORDINAL_POSITION', [self::LEGACY_LEDGER, 'PRIMARY']);
        $this->assertSame(['version', 'name'], $cols, 'primary key upgraded to (version, name)');
        $this->assertSame(['9301_create_beta' => 'create_beta'], $ran, 'the existing row is kept, the shadowed one runs');
        $this->assertSame(['create_alpha', 'create_beta'], $this->ledgerRows('9301', self::LEGACY_LEDGER));
    }
}
