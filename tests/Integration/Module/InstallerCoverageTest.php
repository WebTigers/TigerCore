<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Module_Installer;
use Tiger_Vendor_Environment;

/**
 * Tiger_Module_Installer — the reachable branches the lifecycle/unit suites don't cover, all offline:
 *   - installFromUrl()'s non-GitHub-URL guard (rejected before any network hop);
 *   - installFromTarball() over a package with a single top dir but NO manifest (the _findModuleRoot
 *     single-child unwrap + the "no valid module.json/theme.json" throw);
 *   - _provisionDependencies(): a manifest declaring BOTH a `dependencies.php` library and a
 *     `dependencies.asset` front-end package, each resolved from a local file:// tarball (Tier-3 / asset
 *     install — no network), so both provisioning loops run and the per-dep statuses come back on the result.
 *
 * The `module` row rides the per-test transaction; the on-disk module dir + published link + the shared
 * vendor-libs slug this test writes are cleaned in tearDown.
 */
#[CoversClass(Tiger_Module_Installer::class)]
final class InstallerCoverageTest extends IntegrationTestCase
{
    private string $tmp = '';
    private array $installed = [];
    private array $storeSlugs = [];
    private bool $storePreExisted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_instcov_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
        $this->storePreExisted = is_dir(Tiger_Vendor_Environment::storeDir());
    }

    protected function tearDown(): void
    {
        foreach ($this->installed as $slug) {
            $this->rrmdir(APPLICATION_PATH . '/modules/' . $slug);
            $link = APPLICATION_ROOT . '/public/_modules/' . $slug;
            if (is_link($link)) { @unlink($link); } elseif (is_dir($link)) { $this->rrmdir($link); }
        }
        @rmdir(APPLICATION_PATH . '/modules');
        @rmdir(APPLICATION_ROOT . '/public/_modules');
        $store = Tiger_Vendor_Environment::storeDir();
        if (!$this->storePreExisted && is_dir($store)) {
            $this->rrmdir($store);
        } else {
            foreach ($this->storeSlugs as $slug) { $this->rrmdir($store . '/' . $slug); }
        }
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function install_from_url_rejects_a_non_github_url(): void
    {
        // parseRepo() fails on a non-repo string BEFORE any network hop — a clean, offline guard.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not a GitHub repository URL.');
        Tiger_Module_Installer::installFromUrl('this is not a repository at all');
    }

    #[Test]
    public function install_from_tarball_unwraps_a_single_top_dir_then_rejects_a_missing_manifest(): void
    {
        // One top dir, no module.json/theme.json → _findModuleRoot returns the single child, _readManifest
        // finds nothing → the install refuses the package.
        $zip = $this->tmp . '/no-manifest.zip';
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFromString('somepkg/README.md', "# not a module\n");
        $z->addFromString('somepkg/src/Thing.php', "<?php\n");
        $z->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no valid module.json or theme.json');
        Tiger_Module_Installer::installFromTarball($zip, [], []);
    }

    #[Test]
    public function install_provisions_declared_php_and_asset_dependencies(): void
    {
        // A .tar.gz PHP library (Tier-3 source) + a .tar.gz front-end asset package, both served locally.
        $libTar   = $this->makeLibTarGz('deplib');
        $assetTar = $this->makeAssetTarGz();

        $manifest = [
            'name'         => 'W7 Deps',
            'version'      => '1.0.0',
            'dependencies' => [
                'php'   => [['name' => 'test/deplib', 'tarball' => 'file://' . $libTar]],
                'asset' => [[
                    'name'     => 'widget',
                    'tarball'  => 'file://' . $assetTar,
                    'target'   => 'assets/vendor',
                    'include'  => ['dist/widget.js'],
                    'optional' => true,
                ]],
            ],
        ];
        $this->storeSlugs[] = 'test-deplib';

        $zip = $this->makeModuleZip('w7deps', $manifest);
        $r = Tiger_Module_Installer::installFromUpload($zip);
        $this->installed[] = 'w7deps';

        $this->assertSame('w7deps', $r['slug']);
        $this->assertArrayHasKey('dependencies', $r);
        $this->assertCount(2, $r['dependencies'], 'both the php lib and the asset were provisioned');

        $names = array_column($r['dependencies'], 'name');
        $this->assertContains('test/deplib', $names, 'the PHP library dependency was resolved');
        $this->assertContains('widget', $names, 'the front-end asset dependency was resolved');

        // The php dep landed in the shared store; the asset landed inside the module.
        $this->assertDirectoryExists(Tiger_Vendor_Environment::storeDir() . '/test-deplib');
        $this->assertFileExists(APPLICATION_PATH . '/modules/w7deps/assets/vendor/widget.js');

        // The php dep is required (no `optional`), the asset is flagged optional.
        $byName = [];
        foreach ($r['dependencies'] as $d) { $byName[$d['name']] = $d; }
        $this->assertTrue($byName['test/deplib']['required'], 'a php dep with no optional flag is required');
        $this->assertFalse($byName['widget']['required'], 'the asset declared optional → not required');
    }

    // ---- helpers ---------------------------------------------------------------

    private function makeModuleZip(string $slug, array $manifest): string
    {
        $manifest += ['slug' => $slug, 'name' => ucfirst($slug)];
        $manifest['slug'] = $slug;
        $path = $this->tmp . '/' . $slug . '_' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString($slug . '/module.json', json_encode($manifest));
        $zip->addFromString($slug . '/README.md', "# {$slug}\n");
        $zip->close();
        return $path;
    }

    private function makeLibTarGz(string $top): string
    {
        $base = $this->tmp . '/' . $top . '_' . bin2hex(random_bytes(3)) . '.tar';
        $phar = new \PharData($base);
        $phar->addFromString($top . '/src/Dep.php', "<?php\nnamespace W7DepLib;\nclass Dep {}\n");
        $phar->addFromString($top . '/composer.json', json_encode(['autoload' => ['psr-4' => ['W7DepLib\\' => 'src/']]]));
        $phar->compress(\Phar::GZ);
        unset($phar);
        return $base . '.gz';
    }

    private function makeAssetTarGz(): string
    {
        $base = $this->tmp . '/asset_' . bin2hex(random_bytes(3)) . '.tar';
        $phar = new \PharData($base);
        $phar->addFromString('pkg/dist/widget.js', "console.log('w');\n");
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
