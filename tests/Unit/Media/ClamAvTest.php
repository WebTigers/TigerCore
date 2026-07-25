<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Scanner_ClamAv;

/**
 * Tiger_Media_Scanner_ClamAv — the ClamAV scanner's binary-absent contract.
 *
 * Neither `clamdscan` nor `clamscan` is installed on the test host (asserted, else skipped), so the
 * scanner's "no ClamAV" path is deterministic: `_which()` reports both binaries missing, `_run()`
 * returns null for each, and `scan()` reports a fail-open `error` verdict — NEVER throwing — which the
 * orchestrator (`Tiger_Media_Scan`) treats as "store but leave scan_status = skipped". The clean /
 * infected exit-code branches need a live clamd and belong to a live suite — see
 * WAVE7-FINDINGS-media.md.
 */
#[CoversClass(Tiger_Media_Scanner_ClamAv::class)]
final class ClamAvTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['clamdscan', 'clamscan'] as $bin) {
            $out = [];
            $code = 1;
            exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out)) {
                $this->markTestSkipped("ClamAV ($bin) is installed on this host — the absent path can't be forced.");
            }
        }
    }

    #[Test]
    public function scan_reports_an_error_verdict_when_no_clamav_binary_is_present(): void
    {
        $scanner = new Tiger_Media_Scanner_ClamAv();
        $r = $scanner->scan('/etc/hosts', 'text/plain');

        $this->assertIsArray($r);
        $this->assertSame('error', $r['status'], 'no binary → a fail-open error verdict, never a throw');
        $this->assertMatchesRegularExpression('/not installed/i', (string) $r['reason']);
        $this->assertArrayHasKey('meta', $r);
    }

    #[Test]
    public function scan_never_throws_even_for_a_nonexistent_path(): void
    {
        $scanner = new Tiger_Media_Scanner_ClamAv();
        $r = $scanner->scan('/no/such/file/anywhere.bin', null);
        $this->assertSame('error', $r['status']);
    }

    #[Test]
    public function which_is_false_for_an_absent_binary(): void
    {
        $scanner = new Tiger_Media_Scanner_ClamAv();
        $m = new ReflectionMethod($scanner, '_which');
        $this->assertFalse($m->invoke($scanner, 'clamdscan'));
        $this->assertFalse($m->invoke($scanner, 'definitely-not-a-real-binary-xyz'));
    }

    #[Test]
    public function run_returns_null_for_an_absent_binary(): void
    {
        $scanner = new Tiger_Media_Scanner_ClamAv();
        $m = new ReflectionMethod($scanner, '_run');
        $this->assertNull($m->invoke($scanner, 'clamdscan', '--fdpass', '/etc/hosts'));
    }
}
