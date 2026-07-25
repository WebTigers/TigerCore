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
}

/** Test seam: expose Tiger_Update_Composer's protected process/parse helpers (never a real update). */
final class UpdateComposerProbe extends Tiger_Update_Composer
{
    public static function run($cmd, $cwd, $to): array { return self::_run($cmd, $cwd, $to); }
    public static function versionIn($f) { return self::_versionIn($f); }
    public static function tail($s, $m): string { return self::_tail($s, $m); }
}
