<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Admin_Nav;

/**
 * Tiger_Admin_Nav dropdown support — a module can contribute a top-level nav item that is a TOGGLE
 * (a `children` array, no `href`), like the core Settings/Modules groups. This guards the
 * register()/items() contract that TigerServer's "TigerServer" dropdown relies on:
 *   - a childless item still requires an `href` (a leaf link);
 *   - a dropdown's `children` survive into items() for the recursive sidebar render;
 *   - the `children` key is present ONLY when there are children (the sidebar treats its mere
 *     presence as "this is a toggle", so a leaf must not carry an empty one).
 */
#[CoversClass(Tiger_Admin_Nav::class)]
final class NavDropdownTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Admin_Nav::clear();
        // Flip the discovery latch so items() won't glob real module navigation.ini files.
        (new ReflectionProperty(Tiger_Admin_Nav::class, '_loaded'))->setValue(null, true);
    }

    protected function tearDown(): void
    {
        Tiger_Admin_Nav::clear();
        parent::tearDown();
    }

    /** items() keyed by their `key`, for concise assertions. */
    private function byKey(): array
    {
        $out = [];
        foreach (Tiger_Admin_Nav::items() as $it) {
            $out[$it['key']] = $it;
        }
        return $out;
    }

    #[Test]
    public function a_dropdown_registers_with_children_and_no_href(): void
    {
        Tiger_Admin_Nav::register([
            'key'      => 'tigerserver',
            'label'    => 'server.nav.label',
            'icon'     => 'fa-server',
            'children' => [
                ['key' => 'server_dns', 'label' => 'server.nav.dns', 'href' => '/server/dns', 'resource' => 'Server_DnsController'],
            ],
        ]);

        $items = $this->byKey();
        $this->assertArrayHasKey('tigerserver', $items);
        $this->assertArrayHasKey('children', $items['tigerserver']);
        $this->assertCount(1, $items['tigerserver']['children']);
        $this->assertSame('server_dns', $items['tigerserver']['children'][0]['key']);
    }

    #[Test]
    public function a_leaf_needs_an_href_and_carries_no_children_key(): void
    {
        Tiger_Admin_Nav::register(['key' => 'logs', 'label' => 'core.nav.logs', 'href' => '/system/logs']);

        $items = $this->byKey();
        $this->assertArrayHasKey('logs', $items);
        // A stray `children` key would wrongly mark this leaf as a toggle in the sidebar render.
        $this->assertArrayNotHasKey('children', $items['logs']);
    }

    #[Test]
    public function an_item_with_neither_href_nor_children_is_rejected(): void
    {
        Tiger_Admin_Nav::register(['key' => 'nope', 'label' => 'x']);
        $this->assertArrayNotHasKey('nope', $this->byKey());
    }

    #[Test]
    public function key_and_label_are_required(): void
    {
        Tiger_Admin_Nav::register(['label' => 'x', 'href' => '/y']);   // no key
        Tiger_Admin_Nav::register(['key' => 'k', 'href' => '/y']);     // no label
        $this->assertSame([], Tiger_Admin_Nav::items());
    }
}
