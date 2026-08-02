<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Account_Nav;

/**
 * Tiger_Account_Nav — the "My Account" sidebar registry (the module hook for USER screens), the
 * account-surface twin of Tiger_Admin_Nav. A module contributes a self-service screen via
 * register() or a config-discovered navigation-account.ini, deduped by key, sorted by (order,
 * label), ACL-/activation-gated downstream in the view.
 *
 * The one shape difference from the admin registry: items() is never empty — it always bookends
 * the module items with the core "Overview" (→ /account) first and the "Admin" escape hatch
 * (→ /admin, carrying the AdminController resource so the view's deny-by-default ACL hides it for
 * non-admins) last. Discovery-suppressed tests flip the `_loaded` latch so items() returns only
 * what the test registered; a final test runs the real discover() to prove config auto-discovery
 * works (the profile module surfaces Profile + My Organization with zero code).
 */
#[CoversClass(Tiger_Account_Nav::class)]
final class NavTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Account_Nav::clear();
    }

    protected function tearDown(): void
    {
        Tiger_Account_Nav::clear();
        parent::tearDown();
    }

    /** Flip the discovery latch ON so items() won't glob real module *.ini files — pure isolation. */
    private function suppressDiscovery(): void
    {
        (new ReflectionProperty(Tiger_Account_Nav::class, '_loaded'))->setValue(null, true);
    }

    #[Test]
    public function items_always_bookend_overview_then_the_admin_escape_hatch(): void
    {
        $this->suppressDiscovery();
        $items = Tiger_Account_Nav::items();

        $this->assertCount(2, $items, 'with nothing registered, just the two core bookends');
        $this->assertSame('overview', $items[0]['key']);
        $this->assertSame('/account', $items[0]['href']);
        $this->assertNull($items[0]['resource'], 'Overview is open to any authenticated user');

        $last = $items[count($items) - 1];
        $this->assertSame('admin', $last['key']);
        $this->assertSame('/admin', $last['href']);
        $this->assertSame('AdminController', $last['resource'], 'the escape hatch is ACL-hidden for non-admins');
    }

    #[Test]
    public function register_shapes_a_module_item_and_seats_it_between_the_bookends(): void
    {
        $this->suppressDiscovery();
        Tiger_Account_Nav::register(['key' => 'membership', 'label' => 'My Membership', 'href' => '/member']);
        $items = Tiger_Account_Nav::items();

        $this->assertSame(['overview', 'membership', 'admin'], array_column($items, 'key'));
        $mid = $items[1];
        $this->assertSame('fa-circle', $mid['icon'], 'default icon');
        $this->assertSame('/member', $mid['match'], 'match defaults to href');
        $this->assertNull($mid['resource']);
    }

    #[Test]
    public function register_requires_key_label_and_href(): void
    {
        $this->suppressDiscovery();
        Tiger_Account_Nav::register(['label' => 'x', 'href' => '/x']);       // no key
        Tiger_Account_Nav::register(['key' => 'k', 'label' => 'x']);          // no href
        Tiger_Account_Nav::register(['key' => 'k', 'href' => '/x']);          // no label

        // Nothing valid registered → only the two core bookends remain.
        $this->assertSame(['overview', 'admin'], array_column(Tiger_Account_Nav::items(), 'key'));
    }

    #[Test]
    public function module_items_sort_by_order_then_label_within_the_bookends(): void
    {
        $this->suppressDiscovery();
        Tiger_Account_Nav::register(['key' => 'z', 'label' => 'Zed',   'href' => '/z', 'order' => 50]);
        Tiger_Account_Nav::register(['key' => 'a', 'label' => 'Alpha', 'href' => '/a', 'order' => 100]);
        Tiger_Account_Nav::register(['key' => 'b', 'label' => 'Beta',  'href' => '/b', 'order' => 100]);

        // order 50 first; then order 100 broken by label ASC — always inside the overview/admin frame.
        $this->assertSame(['overview', 'z', 'a', 'b', 'admin'], array_column(Tiger_Account_Nav::items(), 'key'));
    }

    #[Test]
    public function register_dedupes_by_key_last_registration_wins(): void
    {
        $this->suppressDiscovery();
        Tiger_Account_Nav::register(['key' => 'lic', 'label' => 'Licenses', 'href' => '/license']);
        Tiger_Account_Nav::register(['key' => 'lic', 'label' => 'My Licenses', 'href' => '/license']);

        $items = Tiger_Account_Nav::items();
        $this->assertSame(['overview', 'lic', 'admin'], array_column($items, 'key'));
        $this->assertSame('My Licenses', $items[1]['label']);
    }

    #[Test]
    public function discovery_reads_a_modules_navigation_account_ini_and_survives_a_dbless_boot(): void
    {
        // Latch OFF: items() runs the real discover(). tiger-core's `profile` module ships
        // modules/profile/configs/navigation-account.ini, and there's no DB in a unit run —
        // inactiveSlugs() throws and is swallowed (show everything), so the profile module's
        // account-menu items are discovered from config with zero code (the reference example).
        Tiger_Account_Nav::clear();
        $keys = array_column(Tiger_Account_Nav::items(), 'key');

        $this->assertContains('profile', $keys, 'the profile module surfaces "Profile" via config');
        $this->assertContains('org', $keys, 'and "My Organization"');
        $this->assertSame('overview', $keys[0], 'still bookended by Overview…');
        $this->assertSame('admin', $keys[count($keys) - 1], '…and the Admin escape hatch');
    }
}
