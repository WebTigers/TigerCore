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

    /**
     * On the SPLIT layout the docroot gets a `_modules` link to <root>/public/_modules — where
     * Tiger_Module_Installer publishes — so module JS is served from ~/public_html (TIGER-123).
     */
    #[Test]
    public function on_a_split_layout_the_docroot_gets_a_modules_link(): void
    {
        $made = Tiger_Install::linkPublicAssets($this->webroot, $this->root, 'puma');

        $this->assertArrayHasKey('_modules', $made);
        $this->assertSame($this->root . '/public/_modules', $made['_modules']);
        $this->assertDirectoryExists($this->root . '/public/_modules', 'the publish target is created so the link has somewhere to point');
        $this->assertTrue(is_link($this->webroot . '/_modules') || is_dir($this->webroot . '/_modules'));
    }

    /** Co-located: the docroot IS <root>/public, so a `_modules` link there would point at itself. */
    #[Test]
    public function on_a_colocated_layout_no_self_referential_modules_link_is_made(): void
    {
        @mkdir($this->root . '/public', 0775, true);
        $made = Tiger_Install::linkPublicAssets($this->root . '/public', $this->root, 'puma');

        $this->assertArrayNotHasKey('_modules', $made);
        $this->assertFalse(is_link($this->root . '/public/_modules'), 'never a link to itself');
    }

    /**
     * An install set up before TIGER-123 has an UNMARKED `_modules` copy the old installer made. It is
     * ours but indistinguishable from a user's directory, so it is left alone — a throw here would break
     * link:assets and every core update on those hosts.
     */
    #[Test]
    public function a_legacy_unmarked_modules_directory_is_left_alone_not_thrown_on(): void
    {
        @mkdir($this->webroot . '/_modules/oldmod', 0775, true);
        file_put_contents($this->webroot . '/_modules/oldmod/x.js', '//');

        $made = Tiger_Install::linkPublicAssets($this->webroot, $this->root, 'puma');

        $this->assertArrayHasKey('_tiger', $made, 'the rest of the wiring still happens');
        $this->assertArrayNotHasKey('_modules', $made, 'the legacy copy was skipped, not replaced');
        $this->assertFileExists($this->webroot . '/_modules/oldmod/x.js', 'and not touched');
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

    /**
     * A theme's asset base (`public/_greymist`, put there by Tiger_Theme::activate()) — and anything
     * else Tiger publishes under <root>/public — must reach a SPLIT docroot, or the activated theme's
     * CSS 404s. Found on a real cPanel account: the theme rendered unstyled after a one-click install.
     */
    #[Test]
    public function it_mirrors_every_published_public_entry_into_a_split_docroot(): void
    {
        @mkdir($this->root . '/application/modules/theme-grey-mist/assets/css', 0775, true);
        file_put_contents($this->root . '/application/modules/theme-grey-mist/assets/css/grey.css', '.grey{}');
        @mkdir($this->root . '/public', 0775, true);
        symlink($this->root . '/application/modules/theme-grey-mist/assets', $this->root . '/public/_greymist');
        @mkdir($this->root . '/public/_other/x', 0775, true);                  // a module's own publish, a real dir
        file_put_contents($this->root . '/public/_other/x/o.js', '1');
        file_put_contents($this->root . '/public/notes.txt', 'ignored');       // not an _entry

        $made = Tiger_Install::linkPublicAssets($this->webroot, $this->root, 'puma');
        $this->assertArrayHasKey('_greymist', $made);
        $this->assertArrayHasKey('_other', $made);
        $this->assertFileExists($this->webroot . '/_greymist/css/grey.css');
        $this->assertFileExists($this->webroot . '/_other/x/o.js');
        $this->assertFileDoesNotExist($this->webroot . '/notes.txt');

        // Copy mode too — a real directory with the marker, refreshed on a second run.
        $copied = NoSymlinkInstall::linkPublicAssets($this->webroot, $this->root, 'puma');
        $this->assertArrayHasKey('_greymist', $copied);
        $this->assertFalse(is_link($this->webroot . '/_greymist'));
        $this->assertFileExists($this->webroot . '/_greymist/' . Tiger_Install::ASSET_COPY_MARKER);
    }

    #[Test]
    public function it_does_not_mirror_when_the_docroot_is_public_itself(): void
    {
        @mkdir($this->root . '/public/_greymist', 0775, true);
        $made = Tiger_Install::linkPublicAssets($this->root . '/public', $this->root, 'puma');
        $this->assertArrayNotHasKey('_greymist', $made, 'co-located: a link onto itself would loop');
    }

    #[Test]
    public function publish_one_links_or_copies_a_single_entry_and_respects_a_users_directory(): void
    {
        $target = $this->root . '/vendor/webtigers/tiger-core/themes/puma/assets';
        $this->assertTrue(Tiger_Install::publishOne($this->webroot, '_x', $target));
        $this->assertTrue(is_link($this->webroot . '/_x'));
        $this->assertFalse(NoSymlinkInstall::publishOne($this->webroot, '_x', $target), 'copied');
        $this->assertFileExists($this->webroot . '/_x/css/default.css');
        $this->assertFileExists($this->webroot . '/_x/' . Tiger_Install::ASSET_COPY_MARKER);
        @mkdir($this->webroot . '/_mine', 0775, true); file_put_contents($this->webroot . '/_mine/keep', '1');
        $this->expectException(RuntimeException::class);
        Tiger_Install::publishOne($this->webroot, '_mine', $target);
    }
}

/** Forces the copy path — the only way to reach it on a dev machine that allows symlinks. */
final class NoSymlinkInstall extends Tiger_Install
{
    protected static function _canSymlink() { return false; }
}
