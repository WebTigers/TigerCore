<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_RateLimit;

/**
 * Tiger_RateLimit (TIGER-525) — a per-key fixed window that refuses past the limit, keeps keys apart, stores
 * no raw key, and fails OPEN when it cannot store anything.
 */
#[CoversClass(Tiger_RateLimit::class)]
final class RateLimitTest extends UnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger_rl_' . bin2hex(random_bytes(4));
        Tiger_RateLimit::setDir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', (array) glob($this->dir . '/*'));
        @rmdir($this->dir);
        Tiger_RateLimit::setDir(null);
        parent::tearDown();
    }

    #[Test]
    public function it_allows_up_to_the_limit_then_refuses(): void
    {
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k1', 3, 3600));
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k1', 3, 3600));
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k1', 3, 3600));
        $this->assertFalse(Tiger_RateLimit::hit('t.route', 'k1', 3, 3600), 'the 4th hit in the window is refused');
    }

    #[Test]
    public function keys_and_names_are_counted_separately(): void
    {
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'a', 1, 3600));
        $this->assertFalse(Tiger_RateLimit::hit('t.route', 'a', 1, 3600));
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'b', 1, 3600), 'another caller has its own window');
        $this->assertTrue(Tiger_RateLimit::hit('t.other', 'a', 1, 3600), 'another limit has its own window');
    }

    #[Test]
    public function a_new_window_starts_the_count_again(): void
    {
        // Age the stored bucket to a past window: the next hit is the first of a new one.
        Tiger_RateLimit::hit('t.route', 'k', 1, 3600);
        $this->assertFalse(Tiger_RateLimit::hit('t.route', 'k', 1, 3600));
        $file = (glob($this->dir . '/*.json') ?: [])[0];
        file_put_contents($file, json_encode(['b' => intdiv(time(), 3600) - 1, 'n' => 99]));
        $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k', 1, 3600));
    }

    #[Test]
    public function no_raw_key_is_written_to_disk(): void
    {
        Tiger_RateLimit::hit('t.route', '203.0.113.9', 5, 60);
        foreach ((array) glob($this->dir . '/*') as $f) {
            $this->assertStringNotContainsString('203.0.113.9', basename($f) . file_get_contents($f));
        }
        $this->assertNotSame('203.0.113.9', Tiger_RateLimit::ipKey());
    }

    #[Test]
    public function it_fails_open_when_it_cannot_store_and_zero_means_unlimited(): void
    {
        $file = $this->dir . '-file';
        file_put_contents($file, 'x');                       // a FILE where the directory should be
        Tiger_RateLimit::setDir($file . '/sub');
        for ($i = 0; $i < 5; $i++) { $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k', 1, 60)); }
        unlink($file);

        Tiger_RateLimit::setDir($this->dir);
        for ($i = 0; $i < 5; $i++) { $this->assertTrue(Tiger_RateLimit::hit('t.route', 'k', 0, 60)); }
    }
}
