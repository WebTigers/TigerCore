<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Update_AutoUpdate;

/**
 * Tiger_Update_AutoUpdate — per-module auto-update opt-in, stored as a single global option (the slug
 * list), like WordPress's auto_update_plugins. Needs the option store, so it's an integration test.
 */
#[CoversClass(Tiger_Update_AutoUpdate::class)]
final class AutoUpdateTest extends IntegrationTestCase
{
    #[Test]
    public function offByDefault(): void
    {
        $this->assertFalse(Tiger_Update_AutoUpdate::isOn('panel'));
        $this->assertSame([], Tiger_Update_AutoUpdate::onSlugs());
    }

    #[Test]
    public function optInThenOptOutRoundTrips(): void
    {
        $this->assertTrue(Tiger_Update_AutoUpdate::set('panel', true));
        $this->assertTrue(Tiger_Update_AutoUpdate::isOn('panel'));
        $this->assertContains('panel', Tiger_Update_AutoUpdate::onSlugs());

        // A second module, and idempotent re-enable (no duplicate).
        Tiger_Update_AutoUpdate::set('billing', true);
        Tiger_Update_AutoUpdate::set('panel', true);
        $slugs = Tiger_Update_AutoUpdate::onSlugs();
        $this->assertSame(1, count(array_keys($slugs, 'panel', true)), 'no duplicate on re-enable');
        $this->assertContains('billing', $slugs);

        // Opt out.
        $this->assertFalse(Tiger_Update_AutoUpdate::set('panel', false));
        $this->assertFalse(Tiger_Update_AutoUpdate::isOn('panel'));
        $this->assertTrue(Tiger_Update_AutoUpdate::isOn('billing'), 'turning one off leaves the other on');
    }

    #[Test]
    public function ignoresABlankSlug(): void
    {
        $this->assertFalse(Tiger_Update_AutoUpdate::set('', true));
        $this->assertFalse(Tiger_Update_AutoUpdate::isOn(''));
    }
}
