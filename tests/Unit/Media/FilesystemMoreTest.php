<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Storage_Filesystem;

/**
 * Tiger_Media_Storage_Filesystem — the surface Wave 1's FilesystemTest left uncovered:
 *   - `stream()` opens a real read handle (and throws cleanly on a missing file);
 *   - the write-failure arms of `put()` (an unreadable source) and the dir-creation guard;
 *   - `size()` reports 0 for a missing key;
 *   - `_absolute()` resolves a RELATIVE configured root under APPLICATION_ROOT, and an all-defaults
 *     config lands on the documented public/private roots + public URL.
 */
#[CoversClass(Tiger_Media_Storage_Filesystem::class)]
final class FilesystemMoreTest extends UnitTestCase
{
    private string $sandbox;
    private string $publicRoot;
    private string $privateRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sandbox     = sys_get_temp_dir() . '/tiger-fsm-' . bin2hex(random_bytes(6));
        $this->publicRoot  = $this->sandbox . '/public/_media';
        $this->privateRoot = $this->sandbox . '/storage/media';
        mkdir($this->publicRoot, 0777, true);
        mkdir($this->privateRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->sandbox);
        parent::tearDown();
    }

    private function adapter(): Tiger_Media_Storage_Filesystem
    {
        return new Tiger_Media_Storage_Filesystem([
            'public_root'  => $this->publicRoot,
            'private_root' => $this->privateRoot,
            'public_url'   => '/_media',
        ]);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            @unlink($dir);
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $path = $dir . '/' . $e;
            is_dir($path) && !is_link($path) ? $this->rmrf($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    // ---- stream() -------------------------------------------------------------------------------

    #[Test]
    public function stream_opens_a_readable_handle_over_the_stored_bytes(): void
    {
        $a = $this->adapter();
        $a->write('org-1/a.txt', 'hello stream', 'private');

        $fh = $a->stream('org-1/a.txt', 'private');
        $this->assertIsResource($fh);
        $this->assertSame('hello stream', stream_get_contents($fh));
        fclose($fh);
    }

    #[Test]
    public function stream_throws_on_a_missing_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');
        $this->adapter()->stream('org-1/missing.txt', 'private');
    }

    // ---- put() write-failure arm ----------------------------------------------------------------

    #[Test]
    public function put_throws_when_the_source_cannot_be_copied(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not store/i');
        $this->adapter()->put('org-1/a.jpg', '/no/such/source/file.bin', 'public');
    }

    #[Test]
    public function write_throws_when_the_target_directory_cannot_be_created(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('running as root — permission-based write failure cannot be forced.');
        }
        // Point the private root at a read-only directory so _ensureDir() can't mkdir a subdir under it.
        $locked = $this->sandbox . '/locked';
        mkdir($locked, 0500, true);
        $a = new Tiger_Media_Storage_Filesystem(['private_root' => $locked, 'public_root' => $this->publicRoot]);
        try {
            $a->write('sub/dir/a.txt', 'bytes', 'private');
            @chmod($locked, 0700);
            $this->markTestSkipped('the filesystem allowed the write despite 0500 — cannot force the failure here.');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/could not (create|write)/i', $e->getMessage());
        } finally {
            @chmod($locked, 0700);
        }
    }

    // ---- size() ---------------------------------------------------------------------------------

    #[Test]
    public function size_is_zero_for_a_missing_key(): void
    {
        $this->assertSame(0, $this->adapter()->size('org-1/missing.bin', 'public'));
    }

    // ---- _absolute() root resolution ------------------------------------------------------------

    #[Test]
    public function a_relative_configured_root_hangs_off_the_application_root(): void
    {
        $a = new Tiger_Media_Storage_Filesystem(['public_root' => 'var/media-pub', 'private_root' => 'var/media-priv']);
        $pub  = (new ReflectionProperty($a, '_publicRoot'))->getValue($a);
        $priv = (new ReflectionProperty($a, '_privateRoot'))->getValue($a);
        $this->assertSame(rtrim(APPLICATION_ROOT, '/') . '/var/media-pub', $pub);
        $this->assertSame(rtrim(APPLICATION_ROOT, '/') . '/var/media-priv', $priv);
    }

    #[Test]
    public function an_all_defaults_config_lands_on_the_documented_roots(): void
    {
        $a = new Tiger_Media_Storage_Filesystem([]);
        $base = rtrim(APPLICATION_ROOT, '/');
        $this->assertSame($base . '/public/_media', (new ReflectionProperty($a, '_publicRoot'))->getValue($a));
        $this->assertSame($base . '/storage/media', (new ReflectionProperty($a, '_privateRoot'))->getValue($a));
        $this->assertSame('/_media', (new ReflectionProperty($a, '_publicUrl'))->getValue($a));
    }
}
