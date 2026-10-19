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

    #[Test]
    public function enableAllEnrollsCoreAndMergesExisting(): void
    {
        Tiger_Update_AutoUpdate::set('panel', true);        // a prior opt-in must survive
        $set = Tiger_Update_AutoUpdate::enableAll();
        $this->assertContains('tiger-core', $set, 'the platform is enrolled');
        $this->assertTrue(Tiger_Update_AutoUpdate::isOn('tiger-core'));
        $this->assertContains('panel', $set, 'enableAll merges with what was already on');

        // Idempotent — a second call keeps a single tiger-core entry.
        $again = Tiger_Update_AutoUpdate::enableAll();
        $this->assertSame(1, count(array_keys($again, 'tiger-core', true)), 'no duplicate core on re-run');
    }

    #[Test]
    public function enableAllCanSkipCore(): void
    {
        Tiger_Update_AutoUpdate::disableAll();
        $set = Tiger_Update_AutoUpdate::enableAll(false);   // --modules-only (control plane: core is deploy-driven)
        $this->assertNotContains('tiger-core', $set);
        $this->assertFalse(Tiger_Update_AutoUpdate::isOn('tiger-core'));
    }

    #[Test]
    public function disableAllClearsEverything(): void
    {
        Tiger_Update_AutoUpdate::set('panel', true);
        Tiger_Update_AutoUpdate::enableAll();
        $this->assertNotSame([], Tiger_Update_AutoUpdate::onSlugs());
        $this->assertSame([], Tiger_Update_AutoUpdate::disableAll());
        $this->assertSame([], Tiger_Update_AutoUpdate::onSlugs());
        $this->assertFalse(Tiger_Update_AutoUpdate::isOn('tiger-core'));
    }
}
