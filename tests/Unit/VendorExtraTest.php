<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Vendor;
use Tiger_Vendor_Environment;

/**
 * Tiger_Vendor — the remaining no-network provisioner branches VendorTest/VendorProvisionTest don't reach.
 *
 * Deliberately steers clear of anything that would touch the process-static registry-index cache (a
 * different index would poison VendorProvisionTest) and anything that would actually shell out to
 * Composer. What's covered here is the pure error/edge surface, all with local `file://` inputs:
 *
 *   - installTarball(): a failed download, and an archive that isn't a valid tarball (extract fails);
 *   - installAsset(): the same two failure modes into a module dir;
 *   - the `^0.0.x` caret upper bound (the smallest-segment branch of _caretUpper);
 *   - installedVersion() reading Composer's real `vendor/composer/installed.json` for an installed package;
 *   - ensure()'s Tier-3 source-tarball path generating an autoloader that includes an `autoload.files` entry.
 *
 * Every write lands in the real shared store; tearDown removes ONLY the test slugs this suite created.
 */
#[CoversClass(Tiger_Vendor::class)]
final class VendorExtraTest extends UnitTestCase
{
    private string $tmp = '';
    private bool $storePreExisted = false;
    private array $slugs = ['test-vex-auto', 'test-vex-dl', 'test-vex-bad'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_vex_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
        $this->storePreExisted = is_dir(Tiger_Vendor_Environment::storeDir());
    }

    protected function tearDown(): void
    {
        $store = Tiger_Vendor_Environment::storeDir();
        if (!$this->storePreExisted && is_dir($store)) {
            $this->rrmdir($store);
        } else {
            foreach ($this->slugs as $slug) { $this->rrmdir($store . '/' . $slug); }
        }
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    // ---- installTarball(): failure branches ------------------------------------

    #[Test]
    public function install_tarball_reports_a_failed_download(): void
    {
        $r = Tiger_Vendor::installTarball('file://' . $this->tmp . '/does-not-exist.tar.gz', 'test/vex-dl');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Download failed', $r['message']);
        $this->assertDirectoryDoesNotExist(Tiger_Vendor_Environment::storeDir() . '/test-vex-dl');
    }

    #[Test]
    public function install_tarball_reports_an_archive_it_cannot_extract(): void
    {
        // A downloadable file that is NOT a valid tar.gz → download succeeds, extract fails.
        $junk = $this->tmp . '/not-a-tar.tar.gz';
        file_put_contents($junk, 'this is definitely not a gzip tar archive');

        $r = Tiger_Vendor::installTarball('file://' . $junk, 'test/vex-bad');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('extract', strtolower($r['message']));
    }

    // ---- installAsset(): failure branches --------------------------------------

    #[Test]
    public function install_asset_reports_a_failed_download(): void
    {
        $moduleDir = $this->tmp . '/mod-dl';
        @mkdir($moduleDir, 0775, true);
        $r = Tiger_Vendor::installAsset(
            ['name' => 'w', 'tarball' => 'file://' . $this->tmp . '/nope.tar.gz', 'target' => 'assets', 'include' => ['x.js']],
            $moduleDir
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Download failed', $r['message']);
    }

    #[Test]
    public function install_asset_reports_an_archive_it_cannot_extract(): void
    {
        $junk = $this->tmp . '/asset-junk.tar.gz';
        file_put_contents($junk, 'not an archive');
        $moduleDir = $this->tmp . '/mod-bad';
        @mkdir($moduleDir, 0775, true);

        $r = Tiger_Vendor::installAsset(
            ['name' => 'w', 'tarball' => 'file://' . $junk, 'target' => 'assets', 'include' => ['x.js']],
            $moduleDir
        );
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('extract', strtolower($r['message']));
    }

    // ---- the semver matcher's smallest-segment caret ---------------------------

    #[Test]
    public function caret_on_a_double_zero_version_pins_the_patch(): void
    {
        // ^0.0.3 ⇒ >=0.0.3 <0.0.4 — the _caretUpper branch where both major and minor are zero.
        $this->assertTrue(Tiger_Vendor::satisfies('0.0.3', '^0.0.3'));
        $this->assertFalse(Tiger_Vendor::satisfies('0.0.4', '^0.0.3'), 'a 0.0.x caret pins the patch');
    }

    // ---- installedVersion() via Composer's installed.json ----------------------

    #[Test]
    public function installed_version_reads_composers_installed_json(): void
    {
        // Pick a package genuinely present in the app's vendor/composer/installed.json.
        $installed = Tiger_Vendor_Environment::appRoot() . '/vendor/composer/installed.json';
        $this->assertFileExists($installed, 'the test run has a Composer install to read');
        $j = json_decode((string) file_get_contents($installed), true);
        $packages = $j['packages'] ?? $j;
        $name = $packages[0]['name'] ?? null;
        $this->assertNotNull($name, 'installed.json lists at least one package');

        $version = Tiger_Vendor::installedVersion($name);
        $this->assertNotNull($version, 'the installed version is resolved from installed.json');
        $this->assertMatchesRegularExpression('/\d/', (string) $version);
    }

    // ---- ensure() Tier-3 tarball → generated autoloader with a files entry -----

    #[Test]
    public function ensure_generates_an_autoloader_that_includes_an_autoload_files_entry(): void
    {
        // A raw source tarball (no constraint → NO registry lookup) carrying a composer.json whose autoload
        // block declares BOTH psr-4 and files → the generated autoload.php requires the files entry.
        $tar = $this->makeLibTarGz('test-vex-auto', [
            'psr-4' => ['VexLib\\' => 'src/'],
            'files' => ['helper.php'],
        ]);

        $r = Tiger_Vendor::ensure(['name' => 'test/vex-auto', 'tarball' => 'file://' . $tar]);
        $this->assertTrue($r['ok'], $r['message'] ?? '');
        $this->assertSame('tarball', $r['tier']);

        $auto = Tiger_Vendor_Environment::storeDir() . '/test-vex-auto/autoload.php';
        $this->assertFileExists($auto);
        $gen = (string) file_get_contents($auto);
        $this->assertStringContainsString('helper.php', $gen, 'the files[] entry is wired into the generated autoloader');
    }

    // ---- helpers ---------------------------------------------------------------

    private function makeLibTarGz(string $top, array $autoload): string
    {
        $base = $this->tmp . '/' . $top . '_' . bin2hex(random_bytes(3)) . '.tar';
        $phar = new \PharData($base);
        $phar->addFromString($top . '/src/Widget.php', "<?php\nnamespace VexLib;\nclass Widget {}\n");
        $phar->addFromString($top . '/helper.php', "<?php\n// a global helper file\n");
        $phar->addFromString($top . '/composer.json', json_encode(['autoload' => $autoload]));
        $phar->compress(\Phar::GZ);
        unset($phar);
        return $base . '.gz';
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $p = $dir . '/' . $item;
            (is_dir($p) && !is_link($p)) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
