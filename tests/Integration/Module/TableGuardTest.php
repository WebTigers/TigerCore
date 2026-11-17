<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Db_Migrator;
use Tiger_Module_TableGuard;

/**
 * TIGER-444 — a module whose migrations would create a table that already exists is refused BEFORE install, with a
 * named reason; a third-party module must also declare a branded prefix (configs/module.ini `tables.prefix`) that is
 * not generic, doesn't overlap another module's, and that every one of its tables uses. WebTigers' own modules are
 * exempt from the prefix rule only. An update re-shipping already-applied migrations is not a collision.
 */
#[CoversClass(Tiger_Module_TableGuard::class)]
final class TableGuardTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;
    private string $tmp = '';
    private array $installed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_guard_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->installed as $dir) { $this->rrmdir($dir); }
        $this->rrmdir($this->tmp);
        if (isset($this->db)) {
            $this->db->query("DELETE FROM tiger_migration WHERE name LIKE 'guardtest_%'");
            $this->db->query('DROP TABLE IF EXISTS acmepay_ledger');
        }
        parent::tearDown();
    }

    private function rrmdir(string $d): void
    {
        if (!is_dir($d)) { return; }
        foreach (scandir($d) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            is_dir("$d/$f") ? $this->rrmdir("$d/$f") : @unlink("$d/$f");
        }
        @rmdir($d);
    }

    /** Stage a package: tables => migration DDL, optional module.ini prefix. */
    private function package(string $name, array $tables, ?string $prefix = null, string $root = ''): string
    {
        $dir = $root !== '' ? $root : $this->tmp . '/' . $name;
        @mkdir($dir . '/migrations', 0775, true);
        $i = 1;
        foreach ($tables as $t) {
            file_put_contents(sprintf('%s/migrations/2026111712%04d_guardtest_%s.php', $dir, $i++, $t),
                "<?php return ['up' => ['CREATE TABLE `{$t}` (id INT PRIMARY KEY)'], 'down' => ['DROP TABLE `{$t}`']];");
        }
        if ($prefix !== null) {
            @mkdir($dir . '/configs', 0775, true);
            file_put_contents($dir . '/configs/module.ini', "[production]\ntables.prefix = \"{$prefix}\"\n");
        }
        return $dir;
    }

    private function refusal(callable $fn): ?RuntimeException
    {
        try { $fn(); return null; } catch (RuntimeException $e) { return $e; }
    }

    private const THIRD = ['author' => ['name' => 'Acme Software'], 'repository' => 'https://github.com/acme/AcmePay'];
    private const FIRST = ['author' => ['name' => 'WebTigers']];

    #[Test]
    public function a_table_that_already_exists_is_refused_by_name_even_for_a_first_party_module(): void
    {
        $dir = $this->package('wt', ['user']);   // core's `user` table exists in every install
        $e = $this->refusal(fn () => Tiger_Module_TableGuard::check('wtmod', $dir, self::FIRST, $this->db));
        $this->assertNotNull($e);
        $this->assertSame(Tiger_Module_TableGuard::E_TABLE_COLLISION, $e->getCode());
        $this->assertStringContainsString("'user'", $e->getMessage());
    }

    #[Test]
    public function a_first_party_module_needs_no_prefix(): void
    {
        $dir = $this->package('wt2', ['guardfresh']);
        $this->assertNull($this->refusal(fn () => Tiger_Module_TableGuard::check('wtmod2', $dir, self::FIRST, $this->db)));
        $viaRepo = ['repository' => 'https://github.com/WebTigers/TigerSomething'];
        $this->assertNull($this->refusal(fn () => Tiger_Module_TableGuard::check('wtmod3', $dir, $viaRepo, $this->db)));
    }

    #[Test]
    public function a_third_party_module_must_declare_a_branded_prefix_its_tables_use(): void
    {
        $none = $this->package('nopfx', ['acmepay_ledger']);
        $e = $this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $none, self::THIRD, $this->db));
        $this->assertSame(Tiger_Module_TableGuard::E_TABLE_PREFIX, $e?->getCode(), 'no prefix declared');

        foreach (['mail_' => 'generic', 'ab_' => 'too short', 'Acme_' => 'not lowercase', 'acme' => 'no underscore'] as $p => $why) {
            $d = $this->package('bad' . md5($p), ['acmepay_ledger'], $p);
            $e = $this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $d, self::THIRD, $this->db));
            $this->assertSame(Tiger_Module_TableGuard::E_TABLE_PREFIX, $e?->getCode(), "$p is refused ($why)");
        }

        $stray = $this->package('stray', ['acmepay_ledger', 'ledger'], 'acmepay_');
        $e = $this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $stray, self::THIRD, $this->db));
        $this->assertSame(Tiger_Module_TableGuard::E_TABLE_PREFIX, $e?->getCode());
        $this->assertStringContainsString("'ledger'", $e->getMessage(), 'names the table that skips the prefix');

        $good = $this->package('good', ['acmepay_ledger', 'acmepay_payout'], 'acmepay_');
        $this->assertNull($this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $good, self::THIRD, $this->db)));
    }

    #[Test]
    public function a_prefix_overlapping_an_installed_modules_prefix_is_refused(): void
    {
        // An installed third-party module that owns `acme_`.
        $installedDir = APPLICATION_PATH . '/modules/acmebase';
        $this->package('x', [], 'acme_', $installedDir);
        $this->installed[] = $installedDir;

        $d = $this->package('over', ['acmepay_ledger'], 'acmepay_');
        $this->assertNull($this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $d, self::THIRD, $this->db)),
            '`acmepay_` does not overlap `acme_` (different words)');

        $d2 = $this->package('over2', ['acme_pay_ledger'], 'acme_pay_');
        $e = $this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $d2, self::THIRD, $this->db));
        $this->assertSame(Tiger_Module_TableGuard::E_TABLE_PREFIX, $e?->getCode(), '`acme_pay_` sits inside `acme_`');
        $this->assertStringContainsString('acmebase', $e->getMessage());
    }

    #[Test]
    public function an_update_reshipping_an_applied_migration_is_not_a_collision(): void
    {
        $dir = $this->package('upd', ['acmepay_ledger'], 'acmepay_');
        (new Tiger_Db_Migrator($this->db, [$dir . '/migrations']))->migrate();   // the earlier version installed it
        $this->assertContains('acmepay_ledger', array_map('strtolower', $this->db->listTables()));

        $this->assertNull($this->refusal(fn () => Tiger_Module_TableGuard::check('acmepay', $dir, self::THIRD, $this->db)),
            'its own already-applied migration is not re-checked');
    }

    #[Test]
    public function the_installer_refuses_before_anything_is_placed(): void
    {
        // A real uploaded package: third-party, creates `guardloose` with no declared prefix.
        $slug = 'guardloose';
        $zipPath = $this->tmp . '/' . $slug . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString($slug . '/module.json', json_encode(['slug' => $slug, 'name' => 'Loose', 'version' => '1.0.0',
            'author' => ['name' => 'Acme Software']]));
        $zip->addFromString($slug . '/migrations/20261117120001_guardtest_loose.php',
            "<?php return ['up' => ['CREATE TABLE `guardloose` (id INT PRIMARY KEY)'], 'down' => ['DROP TABLE `guardloose`']];");
        $zip->close();

        $e = $this->refusal(fn () => \Tiger_Module_Installer::installFromUpload($zipPath));
        $this->installed[] = APPLICATION_PATH . '/modules/' . $slug;
        $this->assertSame(Tiger_Module_TableGuard::E_TABLE_PREFIX, $e?->getCode(), 'refused with the named reason');
        $this->assertDirectoryDoesNotExist(APPLICATION_PATH . '/modules/' . $slug, 'nothing placed');
        $this->assertNotContains('guardloose', array_map('strtolower', $this->db->listTables()), 'nothing migrated');
    }

    #[Test]
    public function table_names_are_read_from_text_and_the_prefix_from_module_ini(): void
    {
        $this->assertSame(['acmepay_a', 'acmepay_b'], Tiger_Module_TableGuard::createdTables(
            "CREATE TABLE `acmepay_a` (x INT); create table if not exists ACMEPAY_B (y INT)"));
        $d = $this->package('ini', [], 'Paysoft_');
        $this->assertSame('paysoft_', Tiger_Module_TableGuard::declaredPrefix($d));
    }
}
