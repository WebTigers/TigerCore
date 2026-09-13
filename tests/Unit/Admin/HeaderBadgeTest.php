<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Admin_Header;

/**
 * The header-bar badge (TIGER-114). A count pill on a registered icon — unread messages, pending
 * updates. It renders on EVERY admin page, so it has to be lazy, cheap, and unable to break the header.
 */
#[CoversClass(Tiger_Admin_Header::class)]
final class HeaderBadgeTest extends UnitTestCase
{
    #[Test]
    public function a_callable_badge_is_resolved_at_render_time(): void
    {
        $calls = 0;
        Tiger_Admin_Header::register(['key' => 'b1', 'label' => 'x', 'href' => '/x', 'badge' => function () use (&$calls) { $calls++; return 7; }]);
        $item = $this->item('b1');
        $this->assertSame(0, $calls, 'registering must not evaluate it — that is what makes it cheap to register');
        $this->assertSame(7, Tiger_Admin_Header::badgeCount($item));
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function a_plain_integer_works_too(): void
    {
        Tiger_Admin_Header::register(['key' => 'b2', 'label' => 'x', 'href' => '/x', 'badge' => 3]);
        $this->assertSame(3, Tiger_Admin_Header::badgeCount($this->item('b2')));
    }

    #[Test]
    public function no_badge_and_zero_both_mean_nothing_to_show(): void
    {
        Tiger_Admin_Header::register(['key' => 'b3', 'label' => 'x', 'href' => '/x']);
        Tiger_Admin_Header::register(['key' => 'b4', 'label' => 'x', 'href' => '/x', 'badge' => fn () => 0]);
        $this->assertSame(0, Tiger_Admin_Header::badgeCount($this->item('b3')));
        $this->assertSame(0, Tiger_Admin_Header::badgeCount($this->item('b4')));
    }

    /** A badge must never be the reason the header fails to render. */
    #[Test]
    public function a_throwing_badge_renders_nothing_rather_than_breaking_the_header(): void
    {
        Tiger_Admin_Header::register(['key' => 'b5', 'label' => 'x', 'href' => '/x', 'badge' => function () { throw new \RuntimeException('no db'); }]);
        $this->assertSame(0, Tiger_Admin_Header::badgeCount($this->item('b5')));
    }

    #[Test]
    public function a_negative_count_is_clamped(): void
    {
        Tiger_Admin_Header::register(['key' => 'b6', 'label' => 'x', 'href' => '/x', 'badge' => fn () => -4]);
        $this->assertSame(0, Tiger_Admin_Header::badgeCount($this->item('b6')));
    }

    private function item(string $key): array
    {
        foreach (Tiger_Admin_Header::items() as $i) { if ($i['key'] === $key) { return $i; } }
        $this->fail("item $key not registered");
    }
}
