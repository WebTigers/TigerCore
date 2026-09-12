<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Module_Installer;

/**
 * Purging a module removes the storage it owns (TIGER-101).
 *
 * The promise the UI makes is "this destroys data and cannot be undone". Before this, purge reached
 * only the module DIRECTORY — and a module cannot keep user data there, because an update renames
 * that directory away and deletes the backup. So everything real lived under `storage/<slug>/` and
 * survived a deletion the user was told was total.
 *
 * This function deletes a tree from a caller-supplied slug, which is the shape that goes wrong, so
 * the containment is tested harder than the happy path.
 */
#[CoversClass(Tiger_Module_Installer::class)]
final class PurgeStorageTest extends UnitTestCase
{
    /**
     * The REAL APPLICATION_ROOT, not a fixture one.
     *
     * The bootstrap already defines the constant, so redefining it is impossible — and a test that
     * silently skipped because of that would be worse than no test. Instead each case works inside
     * the real `storage/` under a unique slug, which also exercises the genuine path resolution
     * rather than a stand-in.
     */
    private string $root;
    private string $slug;

    protected function setUp(): void
    {
        $this->root = rtrim(APPLICATION_ROOT, '/');
        $this->slug = 'purgetest' . bin2hex(random_bytes(4));
        if (!is_dir($this->root . '/storage')) { mkdir($this->root . '/storage', 0775, true); }
    }

    protected function tearDown(): void
    {
        foreach ([$this->slug, $this->slug . '-sibling', $this->slug . '-link'] as $s) {
            $p = $this->root . '/storage/' . $s;
            if (is_link($p)) { @unlink($p); } elseif (is_dir($p)) { $this->rrm($p); }
        }
        $out = sys_get_temp_dir() . '/' . $this->slug . '-outside';
        if (is_dir($out)) { $this->rrm($out); }
    }

    private function rrm(string $d): void
    {
        foreach (scandir($d) ?: [] as $i) {
            if ($i === '.' || $i === '..') { continue; }
            $p = "$d/$i";
            is_dir($p) && !is_link($p) ? $this->rrm($p) : @unlink($p);
        }
        @rmdir($d);
    }

    /** storageDir() is protected — it is an internal guard, not a public API. */
    private function storageDir(string $slug)
    {
        $m = new ReflectionMethod(Tiger_Module_Installer::class, 'storageDir');
        return $m->invoke(null, $slug);
    }

    private function purgeStorage(string $slug): bool
    {
        $m = new ReflectionMethod(Tiger_Module_Installer::class, '_purgeStorage');
        return (bool) $m->invoke(null, $slug);
    }

    /** The reason the ticket exists. */
    #[Test]
    public function it_deletes_the_tree_a_module_owns(): void
    {
        $dir = $this->root . '/storage/' . $this->slug;
        mkdir($dir . '/2026/09', 0775, true);
        file_put_contents($dir . '/2026/09/a.png', 'bytes');

        $this->assertTrue($this->purgeStorage($this->slug));
        $this->assertDirectoryDoesNotExist($dir,
            'a user told "cannot be undone" must not still have their data on disk');
    }

    #[Test]
    public function a_module_with_no_storage_is_not_an_error(): void
    {
        $this->assertNull($this->storageDir($this->slug . 'nope'));
        $this->assertFalse($this->purgeStorage($this->slug . 'nope'));
    }

    /** It must not reach a SIBLING module's data. */
    #[Test]
    public function it_touches_only_its_own_slug(): void
    {
        mkdir($this->root . '/storage/' . $this->slug, 0775, true);
        mkdir($this->root . '/storage/' . $this->slug . '-sibling', 0775, true);
        file_put_contents($this->root . '/storage/' . $this->slug . '-sibling/keep.txt', 'not ours');

        $this->purgeStorage($this->slug);

        $this->assertDirectoryDoesNotExist($this->root . '/storage/' . $this->slug);
        $this->assertFileExists($this->root . '/storage/' . $this->slug . '-sibling/keep.txt',
            'another module\'s data is not ours to delete');
    }

    /**
     * Containment. _validSlug already rejects dots and slashes, so these cannot arrive in practice —
     * which is exactly why the guard is worth a test: it is the layer that holds when the one above
     * it changes.
     */
    #[Test]
    public function it_refuses_to_resolve_outside_storage(): void
    {
        foreach (['../application', '../../etc', '..', '.', '', '../public'] as $evil) {
            $this->assertNull($this->storageDir($evil), "must not resolve '$evil'");
            $this->assertFalse($this->purgeStorage($evil), "must not purge '$evil'");
        }
        // '.' resolving would have deleted storage/ itself, and '..' the root above it.
        $this->assertDirectoryExists($this->root . '/storage', 'storage/ itself must survive');
        $this->assertDirectoryExists($this->root . '/library', 'the tree above storage/ must survive');
    }

    /** A symlinked storage dir pointing outside is refused — realpath resolves it, containment catches it. */
    #[Test]
    public function it_refuses_a_symlink_escaping_storage(): void
    {
        $outside = sys_get_temp_dir() . '/' . $this->slug . '-outside';
        mkdir($outside, 0775, true);
        file_put_contents($outside . '/precious.txt', 'do not delete');
        if (!@symlink($outside, $this->root . '/storage/' . $this->slug . '-link')) {
            $this->markTestSkipped('symlink() unavailable');
        }

        $this->assertNull($this->storageDir($this->slug . '-link'), 'a symlink out of storage/ must not resolve');
        $this->assertFalse($this->purgeStorage($this->slug . '-link'));
        $this->assertFileExists($outside . '/precious.txt');
    }
}
