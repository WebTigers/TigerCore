<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Update_Composer;

/**
 * Tiger_Update_Composer — the shell/VPS counterpart to the no-shell ZIP swap: run
 * `composer update <package>` in-process, then re-read Version.php from disk. The real
 * `composer update` (an unbounded proc_open against the LIVE app root, with process-env side effects)
 * is NOT run here — that's the destructive boundary (see WAVE7-FINDINGS-netparse.md). What's covered:
 * the `possible()` capability verdict, `update()`'s no-package guard (which returns before any spawn),
 * and the pure helpers that surround the spawn — the process runner `_run()` (driven with a harmless
 * `echo` / non-zero `exit` so the pipe-plumbing + exit-code path runs without Composer), the disk
 * Version.php reader, and the output tail-truncator.
 */
#[CoversClass(Tiger_Update_Composer::class)]
final class ComposerTest extends UnitTestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_updcomposer_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmp)) {
            foreach (scandir($this->tmp) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') { @unlink($this->tmp . '/' . $f); }
            }
            @rmdir($this->tmp);
        }
        parent::tearDown();
    }

    #[Test]
    public function possible_is_a_boolean_verdict(): void
    {
        $this->assertIsBool(Tiger_Update_Composer::possible());
    }

    #[Test]
    public function update_without_a_package_fails_before_spawning_composer(): void
    {
        $this->setConfig(['tiger' => ['log' => ['writer' => 'null']]]);   // the fail-step logs via Tiger_Log — silence it
        $res = Tiger_Update_Composer::update([]);   // no 'package' → earliest guard, no proc_open
        $this->assertFalse($res['ok']);
        $last = end($res['log']);
        $this->assertSame('error', $last['step']);
        $this->assertStringContainsString('No package to update', $last['detail']);
    }

    #[Test]
    public function run_captures_stdout_and_a_zero_exit(): void
    {
        [$code, $out] = UpdateComposerProbe::run('echo tiger-run-ok', $this->tmp, 10);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('tiger-run-ok', $out);
    }

    #[Test]
    public function run_reports_a_non_zero_exit_code(): void
    {
        [$code, $out] = UpdateComposerProbe::run('exit 3', $this->tmp, 10);
        $this->assertSame(3, $code);
        $this->assertIsString($out);
    }

    #[Test]
    public function version_in_reads_the_constant_or_null(): void
    {
        $file = $this->tmp . '/Version.php';
        file_put_contents($file, "<?php\nclass Tiger_Version { const VERSION = '1.2.3-beta'; }\n");
        $this->assertSame('1.2.3-beta', UpdateComposerProbe::versionIn($file));
        $this->assertNull(UpdateComposerProbe::versionIn($this->tmp . '/missing.php'));
    }

    #[Test]
    public function tail_truncates_only_when_longer_than_the_limit(): void
    {
        $this->assertSame('short', UpdateComposerProbe::tail('  short  ', 100));

        $long = str_repeat('x', 50);
        $tail = UpdateComposerProbe::tail($long, 10);
        $this->assertStringStartsWith('…', $tail);
        $this->assertSame(11, mb_strlen($tail), 'the ellipsis + the last 10 chars');
    }

    // ---- atomicity helpers (the deep preflight + rollback that keep a failed update from breaking the site)

    #[Test]
    public function first_unwritable_dir_is_null_when_the_whole_tree_is_writable(): void
    {
        @mkdir($this->tmp . '/a/b', 0775, true);
        $this->assertNull(UpdateComposerProbe::firstUnwritableDir($this->tmp));
        $this->assertNull(UpdateComposerProbe::firstUnwritableDir($this->tmp . '/does-not-exist'));
    }

    #[Test]
    public function first_unwritable_dir_finds_a_locked_nested_dir(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores permission bits, so the unwritable check cannot be exercised as root');
        }
        @mkdir($this->tmp . '/pkg/.github/workflows', 0775, true);
        @chmod($this->tmp . '/pkg/.github/workflows', 0500);   // r-x: cannot delete files in it (the real failure)
        $hit = UpdateComposerProbe::firstUnwritableDir($this->tmp);
        $this->assertSame($this->tmp . '/pkg/.github/workflows', $hit);
        @chmod($this->tmp . '/pkg/.github/workflows', 0775);   // let tearDown clean it
    }

    #[Test]
    public function package_intact_requires_a_composer_json(): void
    {
        $pkg = $this->tmp . '/somepkg';
        @mkdir($pkg, 0775, true);
        $this->assertFalse(UpdateComposerProbe::packageIntact($pkg, $pkg . '/library/Tiger/Version.php'));
        file_put_contents($pkg . '/composer.json', '{}');
        $this->assertTrue(UpdateComposerProbe::packageIntact($pkg, $pkg . '/library/Tiger/Version.php'));
    }

    #[Test]
    public function tiger_core_intact_needs_version_and_functions(): void
    {
        $pkg = $this->tmp . '/tiger-core';
        @mkdir($pkg . '/library/Tiger', 0775, true);
        file_put_contents($pkg . '/composer.json', '{}');
        $ver = $pkg . '/library/Tiger/Version.php';
        // composer.json alone is NOT enough for tiger-core (the half-extracted fatal is a missing functions.php)
        $this->assertFalse(UpdateComposerProbe::packageIntact($pkg, $ver));
        file_put_contents($ver, "<?php class Tiger_Version { const VERSION='1.0.0'; }");
        $this->assertFalse(UpdateComposerProbe::packageIntact($pkg, $ver));
        file_put_contents($pkg . '/functions.php', "<?php\n");
        $this->assertTrue(UpdateComposerProbe::packageIntact($pkg, $ver));
    }

    #[Test]
    public function restore_puts_the_backup_back_over_a_broken_dir(): void
    {
        $pkg    = $this->tmp . '/tiger-core';
        $backup = $this->tmp . '/rollback';
        @mkdir($backup, 0775, true);
        file_put_contents($backup . '/functions.php', "<?php // the good one\n");
        // a half-extracted package sits where the real one should be
        @mkdir($pkg, 0775, true);
        file_put_contents($pkg . '/partial.tmp', 'junk');

        $this->assertTrue(UpdateComposerProbe::restore($pkg, $backup));
        $this->assertFileExists($pkg . '/functions.php');
        $this->assertFileDoesNotExist($pkg . '/partial.tmp');   // the broken tree was replaced
        $this->assertDirectoryDoesNotExist($backup);            // backup consumed by the rename
        $this->assertFalse(UpdateComposerProbe::restore($pkg, null));   // nothing to restore
    }
}

/** Test seam: expose Tiger_Update_Composer's protected process/parse/atomicity helpers (never a real update). */
final class UpdateComposerProbe extends Tiger_Update_Composer
{
    public static function run($cmd, $cwd, $to): array { return self::_run($cmd, $cwd, $to); }
    public static function versionIn($f) { return self::_versionIn($f); }
    public static function tail($s, $m): string { return self::_tail($s, $m); }
    public static function firstUnwritableDir($d) { return self::_firstUnwritableDir($d); }
    public static function packageIntact($p, $v): bool { return self::_packageIntact($p, $v); }
    public static function restore($p, $b): bool { return self::_restore($p, $b); }
}
