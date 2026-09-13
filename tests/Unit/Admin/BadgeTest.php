<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Admin_Badge;
use Tiger_Admin_Nav;

/** The shared badge resolver (TIGER-112/114), and that Nav carries a badge like Header does. */
#[CoversClass(Tiger_Admin_Badge::class)]
#[CoversClass(Tiger_Admin_Nav::class)]
final class BadgeTest extends UnitTestCase
{
    #[Test]
    public function resolves_callables_lazily_and_ints_directly(): void
    {
        $calls = 0;
        $item = ['badge' => function () use (&$calls) { $calls++; return 4; }];
        $this->assertSame(0, $calls, 'building the item must not evaluate the badge');
        $this->assertSame(4, Tiger_Admin_Badge::resolve($item));
        $this->assertSame(9, Tiger_Admin_Badge::resolve(['badge' => 9]));
    }

    #[Test]
    public function absent_zero_negative_and_throwing_all_mean_no_badge(): void
    {
        $this->assertSame(0, Tiger_Admin_Badge::resolve([]));
        $this->assertSame(0, Tiger_Admin_Badge::resolve(['badge' => 0]));
        $this->assertSame(0, Tiger_Admin_Badge::resolve(['badge' => -3]));
        $this->assertSame(0, Tiger_Admin_Badge::resolve(['badge' => fn () => throw new \RuntimeException('no db')]),
            'a badge must never be the reason a menu fails to render');
    }

    #[Test]
    public function the_label_caps_so_a_runaway_count_cannot_widen_the_menu(): void
    {
        $this->assertSame('7', Tiger_Admin_Badge::label(7));
        $this->assertSame('99+', Tiger_Admin_Badge::label(250));
    }

    #[Test]
    public function nav_items_carry_the_badge_through_to_render(): void
    {
        Tiger_Admin_Nav::register(['key' => 'bt', 'label' => 'x', 'href' => '/x', 'badge' => fn () => 3]);
        foreach (Tiger_Admin_Nav::items() as $i) {
            if ($i['key'] === 'bt') { $this->assertSame(3, Tiger_Admin_Badge::resolve($i)); return; }
        }
        $this->fail('registered nav item not returned by items()');
    }
}
