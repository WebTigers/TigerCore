<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Theme_Menus;

/**
 * Tiger_Theme_Menus — the base tier of the menu live-override: a theme's public menus read from
 * `configs/menus.ini`.
 *
 * Coverage: the parse produces the same node shape Tiger_Model_Menu::tree() yields — author-facing
 * fields mapped to DB columns (class→css_class, id→dom_id, target→link_target, rel→link_rel), items
 * ordered with a 0-based sort_order, nesting via children, page_key carried through; a labelless
 * item is skipped; and a theme dir with no menus.ini (or a bad path) yields [].
 */
#[CoversClass(Tiger_Theme_Menus::class)]
final class MenusTest extends UnitTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tigtheme-' . uniqid('', true);
        mkdir($this->dir . '/configs', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/configs/menus.ini');
        @rmdir($this->dir . '/configs');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function writeIni(string $ini): void
    {
        file_put_contents($this->dir . '/configs/menus.ini', $ini);
    }

    #[Test]
    public function reads_menus_maps_fields_orders_and_nests(): void
    {
        $this->writeIni(<<<INI
        [primary]
        items.home.label     = "Home"
        items.home.url        = "/"
        items.services.label  = "Services"
        items.services.class  = "has-mega"
        items.services.id     = "svc"
        items.services.children.res.label    = "Residential"
        items.services.children.res.page_key = "svc-res"
        items.contact.label   = "Contact"
        items.contact.url      = "/contact"

        [footer-social]
        items.tw.label  = "Twitter"
        items.tw.url    = "https://twitter.com/acme"
        items.tw.target = "_blank"
        items.tw.rel    = "me"
        INI);

        $all = Tiger_Theme_Menus::all($this->dir);
        $this->assertSame(['primary', 'footer-social'], array_keys($all), 'both menus, in file order');
        $this->assertSame(['primary', 'footer-social'], Tiger_Theme_Menus::keys($this->dir));

        $primary = Tiger_Theme_Menus::tree('primary', $this->dir);
        $this->assertCount(3, $primary, 'three top-level items');

        // order + 0-based sort_order
        $this->assertSame('Home', $primary[0]['label']);
        $this->assertSame(0, $primary[0]['sort_order']);
        $this->assertSame('Services', $primary[1]['label']);
        $this->assertSame(1, $primary[1]['sort_order']);
        $this->assertSame(2, $primary[2]['sort_order']);

        // author fields -> DB columns
        $this->assertSame('has-mega', $primary[1]['css_class']);
        $this->assertSame('svc', $primary[1]['dom_id']);

        // nesting + page_key
        $this->assertCount(1, $primary[1]['children']);
        $this->assertSame('Residential', $primary[1]['children'][0]['label']);
        $this->assertSame('svc-res', $primary[1]['children'][0]['page_key']);
        $this->assertSame(0, $primary[1]['children'][0]['sort_order']);

        // target/rel mapping
        $social = Tiger_Theme_Menus::tree('footer-social', $this->dir);
        $this->assertSame('_blank', $social[0]['link_target']);
        $this->assertSame('me', $social[0]['link_rel']);
    }

    #[Test]
    public function skips_a_labelless_item(): void
    {
        $this->writeIni(<<<INI
        [primary]
        items.ok.label   = "OK"
        items.bad.url     = "/no-label"
        INI);

        $primary = Tiger_Theme_Menus::tree('primary', $this->dir);
        $this->assertCount(1, $primary, 'the labelless item is dropped');
        $this->assertSame('OK', $primary[0]['label']);
    }

    #[Test]
    public function missing_manifest_yields_empty(): void
    {
        // no menus.ini written
        $this->assertSame([], Tiger_Theme_Menus::all($this->dir));
        $this->assertSame([], Tiger_Theme_Menus::tree('primary', $this->dir));
        $this->assertSame([], Tiger_Theme_Menus::keys($this->dir . '/does-not-exist'));
    }
}
