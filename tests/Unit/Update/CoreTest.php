<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PharData;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Update_Core;
use ZipArchive;

/**
 * Tiger_Update_Core — the no-shell TigerCore self-update (download a pre-resolved vendored release ZIP →
 * verify → atomically swap vendor/). The genuine `update()` swap operates on the LIVE `vendor/` at the
 * real app root (`_appRoot()` is early-bound `self::`, so it can't be redirected to a sandbox) — that
 * rename-swap + the `Tiger_Module_Github`/GitHub-API `resolveRelease()` path are the destructive/network
 * boundary and are NOT run here (see WAVE7-FINDINGS-netparse.md).
 *
 * What IS covered — everything up to the swap: the pure build/parse/io helpers that do the real work
 * (version normalization, human byte sizes, the file:// + dead-curl download branches, the zip extractor
 * WITH its zip-slip guard, the staging-tree vendor locator, the Version.php reader, the maintenance flag,
 * recursive rmdir), the host-capability probes (`possible()` / `maintenanceFlag()` / `_canExtract`), and
 * `update()`'s no-URL guard (which fails before touching the app root). All fixtures live in a temp sandbox.
 */
#[CoversClass(Tiger_Update_Core::class)]
final class CoreTest extends UnitTestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_updcore_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    // ---- pure comparators / formatters ----------------------------------------

    #[Test]
    public function norm_strips_a_leading_v_and_trims(): void
    {
        $this->assertSame('1.2.3', UpdateCoreProbe::norm('v1.2.3'));
        $this->assertSame('2', UpdateCoreProbe::norm('  V2  '));
        $this->assertSame('0.41.0-beta', UpdateCoreProbe::norm('0.41.0-beta'));
    }

    #[Test]
    public function hsize_formats_bytes_kb_and_mb(): void
    {
        $this->assertSame('512 B', UpdateCoreProbe::hsize(512));
        $this->assertSame('0 B', UpdateCoreProbe::hsize(0));
        $this->assertSame('2 KB', UpdateCoreProbe::hsize(2048));
        $this->assertSame('1.5 MB', UpdateCoreProbe::hsize((int) (1.5 * 1048576)));
    }

    #[Test]
    public function can_extract_is_true_when_a_zip_or_phar_extractor_is_present(): void
    {
        $this->assertTrue(UpdateCoreProbe::canExtract());   // ZipArchive is present in the harness
    }

    #[Test]
    public function app_root_resolves_to_a_real_directory(): void
    {
        $root = UpdateCoreProbe::appRoot();
        $this->assertIsString($root);
        $this->assertNotSame('', $root);
    }

    // ---- download branches -----------------------------------------------------

    #[Test]
    public function download_copies_a_file_url_to_the_destination(): void
    {
        $src = $this->tmp . '/src.bin';
        file_put_contents($src, 'payload-bytes');
        $dest = $this->tmp . '/dest.bin';

        $this->assertTrue(UpdateCoreProbe::download('file://' . $src, $dest));
        $this->assertSame('payload-bytes', file_get_contents($dest));
    }

    #[Test]
    public function download_reads_a_bare_absolute_path_too(): void
    {
        $src = $this->tmp . '/abs.bin';
        file_put_contents($src, 'abc');
        $dest = $this->tmp . '/abs-dest.bin';

        $this->assertTrue(UpdateCoreProbe::download($src, $dest));   // leading '/' → file_get_contents branch
        $this->assertSame('abc', file_get_contents($dest));
    }

    #[Test]
    public function download_returns_false_when_the_http_endpoint_is_unreachable(): void
    {
        // A dead local port → curl fails fast (connection refused), no real network.
        $this->assertFalse(UpdateCoreProbe::download('http://127.0.0.1:1/nope.zip', $this->tmp . '/x.zip'));
    }

    // ---- extract (+ zip-slip guard) -------------------------------------------

    #[Test]
    public function extract_unpacks_a_clean_zip(): void
    {
        $zip = $this->tmp . '/clean.zip';
        $za  = new ZipArchive();
        $za->open($zip, ZipArchive::CREATE);
        $za->addFromString('a/b.txt', 'hello');
        $za->close();

        $into = $this->tmp . '/out';
        @mkdir($into, 0775, true);
        $this->assertTrue(UpdateCoreProbe::extract($zip, $into));
        $this->assertSame('hello', file_get_contents($into . '/a/b.txt'));
    }

    #[Test]
    public function extract_refuses_a_zip_with_a_traversal_entry(): void
    {
        $zip = $this->tmp . '/evil.zip';
        $za  = new ZipArchive();
        $za->open($zip, ZipArchive::CREATE);
        $za->addFromString('../escape.txt', 'boom');   // zip-slip
        $za->close();

        $into = $this->tmp . '/evil-out';
        @mkdir($into, 0775, true);
        $this->assertFalse(UpdateCoreProbe::extract($zip, $into), 'a .. entry must be refused before extraction');
        $this->assertFileDoesNotExist($this->tmp . '/escape.txt');
    }

    // ---- staging-tree helpers --------------------------------------------------

    #[Test]
    public function locate_vendor_finds_a_direct_or_nested_vendor_dir(): void
    {
        $direct = $this->tmp . '/stage-direct';
        @mkdir($direct . '/vendor', 0775, true);
        $this->assertSame($direct . '/vendor', UpdateCoreProbe::locateVendor($direct));

        $nested = $this->tmp . '/stage-nested';
        @mkdir($nested . '/tiger-core-0.6.0/vendor', 0775, true);
        $this->assertSame($nested . '/tiger-core-0.6.0/vendor', UpdateCoreProbe::locateVendor($nested));

        $none = $this->tmp . '/stage-none';
        @mkdir($none, 0775, true);
        $this->assertNull(UpdateCoreProbe::locateVendor($none));
    }

    #[Test]
    public function version_in_reads_the_version_constant_from_the_staged_tree(): void
    {
        $vendor = $this->tmp . '/vendor';
        $dir    = $vendor . '/webtigers/tiger-core/library/Tiger';
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/Version.php', "<?php\nclass Tiger_Version { const VERSION = '9.9.9-beta'; }\n");

        $this->assertSame('9.9.9-beta', UpdateCoreProbe::versionIn($vendor));
        $this->assertNull(UpdateCoreProbe::versionIn($this->tmp . '/no-such-vendor'));
    }

    // ---- maintenance flag + recursive rmdir -----------------------------------

    // ---- post-swap staleness --------------------------------------------------
    //
    // The rename is atomic on disk; PHP is not. With opcache.validate_timestamps=1 and
    // revalidate_freq=N, workers keep running the PREVIOUS bytecode for up to N seconds — so the
    // health probe reads the old code, sees the maintenance page it was meant to be let through,
    // and rolls back a good update. Reproduced on a live cPanel host: probe at t+0 returned 503,
    // the identical request moments later returned 200.

    #[Test]
    public function resetting_the_opcode_cache_never_throws(): void
    {
        // Best effort by contract: absent when OPcache is off, and closed off by opcache.restrict_api
        // on plenty of shared hosts. It must report, never explode — an update is already mid-flight.
        $this->assertIsBool(UpdateCoreProbe::resetOpcache());
    }

    #[Test]
    public function a_failed_health_probe_is_retried_before_rolling_back(): void
    {
        // The load-bearing behaviour: one 'unhealthy' reading right after the swap is NOT trusted,
        // because that is exactly what stale bytecode looks like.
        RetryingUpdateProbe::$readings = [false, false, true];
        RetryingUpdateProbe::$pauses   = 0;

        $this->assertTrue(RetryingUpdateProbe::probeUntilSettled());
        $this->assertSame(2, RetryingUpdateProbe::$pauses, 'waited between attempts rather than hammering');
        $this->assertSame([], RetryingUpdateProbe::$readings, 'consumed exactly the readings it needed');
    }

    #[Test]
    public function a_healthy_first_probe_is_taken_immediately(): void
    {
        // A good update must not be slowed down by the retry machinery.
        RetryingUpdateProbe::$readings = [true, false, false];
        RetryingUpdateProbe::$pauses   = 0;

        $this->assertTrue(RetryingUpdateProbe::probeUntilSettled());
        $this->assertSame(0, RetryingUpdateProbe::$pauses);
    }

    #[Test]
    public function an_inconclusive_probe_is_not_retried(): void
    {
        // null means "could not reach the site" — already treated as passing; retrying buys nothing.
        RetryingUpdateProbe::$readings = [null, true];
        RetryingUpdateProbe::$pauses   = 0;

        $this->assertNull(RetryingUpdateProbe::probeUntilSettled());
        $this->assertSame(0, RetryingUpdateProbe::$pauses);
    }

    #[Test]
    public function a_genuinely_broken_build_still_fails_after_every_retry(): void
    {
        // The rail must keep working: retries must not turn a real failure into a pass.
        RetryingUpdateProbe::$readings = [false, false, false, false, false, false];
        RetryingUpdateProbe::$pauses   = 0;

        $this->assertFalse(RetryingUpdateProbe::probeUntilSettled());
        $this->assertSame(Tiger_Update_Core::HEALTH_RETRIES, RetryingUpdateProbe::$pauses);
    }

    #[Test]
    public function maintenance_mints_a_nonce_and_writes_it_beside_the_timestamp(): void
    {
        // The nonce is what lets the post-swap health probe past our own 503. Without it the probe
        // reads the maintenance page and every successful update gets rolled back.
        $work = $this->tmp . '/work-nonce';
        @mkdir($work, 0775, true);

        $nonce = UpdateCoreProbe::maintenance($work, true);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $nonce);

        $parts = explode(' ', (string) file_get_contents($work . '/.maintenance'));
        $this->assertCount(2, $parts, 'flag holds "<timestamp> <nonce>"');
        $this->assertNotSame('', $parts[0], 'timestamp kept — it drives the 120s auto-expiry');
        $this->assertSame($nonce, $parts[1]);

        UpdateCoreProbe::maintenance($work, false);
    }

    #[Test]
    public function each_update_mints_a_fresh_nonce(): void
    {
        // Per-update and never reused, so a nonce cannot be replayed against a later update.
        $work = $this->tmp . '/work-fresh';
        @mkdir($work, 0775, true);

        $a = UpdateCoreProbe::maintenance($work, true);
        $b = UpdateCoreProbe::maintenance($work, true);
        $this->assertNotSame($a, $b);

        UpdateCoreProbe::maintenance($work, false);
    }

    #[Test]
    public function clearing_maintenance_returns_no_nonce(): void
    {
        $work = $this->tmp . '/work-clear';
        @mkdir($work, 0775, true);
        UpdateCoreProbe::maintenance($work, true);
        $this->assertSame('', UpdateCoreProbe::maintenance($work, false));
    }

    #[Test]
    public function maintenance_writes_then_removes_the_flag(): void
    {
        $work = $this->tmp . '/work';
        @mkdir($work, 0775, true);
        $flag = $work . '/.maintenance';

        UpdateCoreProbe::maintenance($work, true);
        $this->assertFileExists($flag);

        UpdateCoreProbe::maintenance($work, false);
        $this->assertFileDoesNotExist($flag);
    }

    #[Test]
    public function rrmdir_removes_a_nested_tree(): void
    {
        $root = $this->tmp . '/tree';
        @mkdir($root . '/a/b', 0775, true);
        file_put_contents($root . '/a/b/leaf.txt', 'x');
        UpdateCoreProbe::rrmdir($root);
        $this->assertDirectoryDoesNotExist($root);
    }

    // ---- host-capability probes + the update() no-URL guard --------------------

    #[Test]
    public function possible_and_maintenance_flag_report_the_host_state(): void
    {
        $this->assertIsBool(Tiger_Update_Core::possible());
        $this->assertSame(UpdateCoreProbe::appRoot() . '/var/update/.maintenance', Tiger_Update_Core::maintenanceFlag());
    }

    #[Test]
    public function update_fails_before_touching_the_app_root_when_no_url_is_supplied(): void
    {
        $this->setConfig(['tiger' => ['log' => ['writer' => 'null']]]);   // the fail-step logs via Tiger_Log — silence it
        $res = Tiger_Update_Core::update([]);   // no 'url' → the earliest guard, no vendor/ is touched
        $this->assertFalse($res['ok']);
        $this->assertNotEmpty($res['log']);
        $last = end($res['log']);
        $this->assertSame('error', $last['step']);
        $this->assertStringContainsString('No release-ZIP URL', $last['detail']);
    }

    // ---- helpers ---------------------------------------------------------------

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            (is_dir($p) && !is_link($p)) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}

/** Test seam: expose Tiger_Update_Core's protected build/parse/io helpers (never the live swap). */
/**
 * Drives the retry loop with scripted probe readings and a sleep that does not sleep, so the
 * post-swap staleness behaviour is testable without a web server or a stopwatch.
 */
final class RetryingUpdateProbe extends Tiger_Update_Core
{
    /** @var array<int,bool|null> */
    public static array $readings = [];
    public static int $pauses = 0;

    protected static function _httpHealth($nonce = '')
    {
        return array_shift(self::$readings);
    }

    protected static function _pause($seconds)
    {
        self::$pauses++;
    }

    /** The exact loop update() runs, isolated. */
    public static function probeUntilSettled()
    {
        $http = static::_httpHealth('');
        for ($try = 1; $http === false && $try <= self::HEALTH_RETRIES; $try++) {
            static::_pause(self::HEALTH_RETRY_WAIT);
            $http = static::_httpHealth('');
        }
        return $http;
    }
}

final class UpdateCoreProbe extends Tiger_Update_Core
{
    public static function norm($v): string { return self::_norm($v); }
    public static function hsize($b): string { return self::_hsize($b); }
    public static function canExtract(): bool { return self::_canExtract(); }
    public static function appRoot(): string { return self::_appRoot(); }
    public static function download($u, $d): bool { return self::_download($u, $d); }
    public static function extract($a, $i): bool { return self::_extract($a, $i); }
    public static function locateVendor($s) { return self::_locateVendor($s); }
    public static function versionIn($d) { return self::_versionIn($d); }
    public static function maintenance($w, $on) { return self::_maintenance($w, $on); }
    public static function resetOpcache(): bool { return self::_resetOpcache(); }
    public static function rrmdir($d): void { self::_rrmdir($d); }
}
