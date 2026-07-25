<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Module_Compat;

/**
 * Tiger_Module_Compat — the last two uncovered seams CompatTest leaves: the `runningVersion()` accessor
 * (TIGER_VERSION / the class constant), and `satisfies()`'s fail-open on an UNPARSEABLE constraint (no
 * leading digit after the operator) — advisory metadata never raises a false alarm, so a garbage
 * constraint is treated as "satisfied".
 */
#[CoversClass(Tiger_Module_Compat::class)]
final class CompatCoverageTest extends UnitTestCase
{
    #[Test]
    public function running_version_returns_a_non_empty_version_string(): void
    {
        $v = Tiger_Module_Compat::runningVersion();
        $this->assertIsString($v);
        $this->assertNotSame('', $v);
        // It's the canonical running version — TIGER_VERSION when defined, else the class constant.
        $expected = defined('TIGER_VERSION') ? TIGER_VERSION : \Tiger_Version::VERSION;
        $this->assertSame($expected, $v);
    }

    #[Test]
    public function satisfies_fails_open_on_an_unparseable_constraint(): void
    {
        // No digit for the matcher to anchor on → the regex misses → advisory "satisfied" (never a false block).
        $this->assertTrue(Tiger_Module_Compat::satisfies('1.0.0', 'not-a-version'));
        $this->assertTrue(Tiger_Module_Compat::satisfies('1.0.0', '>=garbage'));
    }
}
