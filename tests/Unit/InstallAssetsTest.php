<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Install;

/**
 * Asset publishing — `_tiger` / `_theme` into the webroot.
 *
 * A symlink is preferred because it self-updates, but `symlink()` is commonly disabled on shared
 * cPanel hosting, which is a first-class Tiger target. Before the copy fallback existed the web
 * installer threw there and left an unstyled site. Copy mode is therefore a SUPPORTED install
 * mode, and the behaviours that matter are: it produces real assets, it is detectable (because a
 * copy goes stale where a symlink does not), it is refreshable, and it still refuses to eat a
 * directory it did not create.
 */
#[CoversClass(Tiger_Install::class)]
final class InstallAssetsTest extends UnitTestCase
{
    private string $tmp;
    private string $root;
    private string $webroot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp     = sys_get_temp_dir() . '/tiger-assets-' . bin2hex(random_bytes(6));
        $this->root    = $this->tmp . '/app';
        $this->webroot = $this->tmp . '/public_html';

        $core = $this->root . '/vendor/webtigers/tiger-core';
        @mkdir($core . '/public/css', 0775, true);
        @mkdir($core . '/themes/puma/assets/css', 0775, true);
        @mkdir($this->webroot, 0775, true);
        file_put_contents($core . '/public/css/core.css', '.core{}');
        file_put_contents($core . '/themes/puma/assets/css/default.css', '.puma{}');
    }

    protected function tearDown(): void
    {
        self::rmrf($this->tmp);
        parent::tearDown();
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) { @unlink($dir); return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            (is_dir($p) && !is_link($p)) ? self::rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    #[Test]
    public function it_symlinks_when_the_host_allows_it(): void
    {
        Tiger_Install::linkPublicAssets($this->webroot, $this->root, 'puma');

        $this->assertTrue(is_link($this->webroot . '/_tiger'));
        $this->assertTrue(is_link($this->webroot . '/_theme'));
        $this->assertFalse(Tiger_Install::assetsAreCopied($this->webroot));
        $this->assertSame('.puma{}', file_get_contents($this->webroot . '/_theme/css/default.css'));
    }

    #[Test]
    public function it_copies_the_assets_when_symlink_is_unavailable(): void
    {
        NoSymlinkInstall::linkPublicAssets($this->webroot, $this->root, 'puma');

        // Real directories, not links — and the assets are actually served.
        $this->assertFalse(is_link($this->webroot . '/_theme'));
        $this->assertDirectoryExists($this->webroot . '/_theme');
        $this->assertSame('.puma{}', file_get_contents($this->webroot . '/_theme/css/default.css'));
        $this->assertSame('.core{}', file_get_contents($this->webroot . '/_tiger/css/core.css'));
    }

    #[Test]
    public function a_copied_install_is_detectable_and_a_symlinked_one_is_not(): void
    {
        NoSymlinkInstall::linkPublicAssets($this->webroot, $this->root, 'puma');
        $this->assertTrue(Tiger_Install::assetsAreCopied($this->webroot));
        $this->assertFileExists($this->webroot . '/_theme/' . Tiger_Install::ASSET_COPY_MARKER);
    }

    #[Test]
    public function republishing_refreshes_stale_copied_assets_after_an_update(): void
    {
        NoSymlinkInstall::linkPublicAssets($this->webroot, $this->root, 'puma');

        // Simulate `composer update` / the vendor swap changing the shipped asset.
        file_put_contents(
            $this->root . '/vendor/webtigers/tiger-core/themes/puma/assets/css/default.css',
            '.puma{updated}'
        );
        $this->assertSame('.puma{}', file_get_contents($this->webroot . '/_theme/css/default.css'),
            'the copy is stale until it is re-published — this is why the update path must call it');

        $r = NoSymlinkInstall::republishAssets($this->root, $this->webroot, 'puma');

        $this->assertSame('copy', $r['mode']);
        $this->assertTrue($r['republished']);
        $this->assertNull($r['error']);
        $this->assertSame('.puma{updated}', file_get_contents($this->webroot . '/_theme/css/default.css'));
    }

    #[Test]
    public function republishing_a_symlinked_install_is_a_no_op(): void
    {
        Tiger_Install::linkPublicAssets($this->webroot, $this->root, 'puma');
        $r = Tiger_Install::republishAssets($this->root, $this->webroot, 'puma');

        $this->assertSame('symlink', $r['mode']);
        $this->assertFalse($r['republished'], 'a symlink already points at the new files');
        $this->assertNull($r['error']);
    }

    #[Test]
    public function it_refuses_to_replace_a_real_directory_it_did_not_publish(): void
    {
        @mkdir($this->webroot . '/_theme', 0775, true);
        file_put_contents($this->webroot . '/_theme/mine.txt', 'user content');

        $this->expectException(RuntimeException::class);
        try {
            NoSymlinkInstall::linkPublicAssets($this->webroot, $this->root, 'puma');
        } finally {
            $this->assertSame('user content', file_get_contents($this->webroot . '/_theme/mine.txt'));
        }
    }
}

/** Forces the copy path — the only way to reach it on a dev machine that allows symlinks. */
final class NoSymlinkInstall extends Tiger_Install
{
    protected static function _canSymlink() { return false; }
}
