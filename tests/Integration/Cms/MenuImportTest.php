<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Cms;

use Cms_Service_Menu;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Menu;
use Tiger_Model_Menu;
use Zend_Registry;

/**
 * Cms_Service_Menu::importFromTheme + the Tiger_Menu live-override fallback.
 *
 * The theme's configs/menus.ini is the base tier: Tiger_Menu renders it when the DB has no rows,
 * and "Import from theme" clones it into editable DB rows (idempotent — a key with rows is skipped),
 * after which the DB wins. Here a fixture theme dir (Tiger_ThemeDir registry) carries a two-menu
 * menus.ini; we assert the base-tier render (getData with an empty table), the admin import creating
 * the rows with correct nesting/order/scope, the ACL gate, and idempotent re-runs.
 */
#[CoversClass(Cms_Service_Menu::class)]
final class MenuImportTest extends IntegrationTestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tigthememenu-' . uniqid('', true);
        mkdir($this->dir . '/configs', 0777, true);
        file_put_contents($this->dir . '/configs/menus.ini', <<<INI
        [primary]
        items.home.label     = "Home"
        items.home.url        = "/"
        items.services.label  = "Services"
        items.services.children.res.label = "Residential"
        items.services.children.res.url   = "/services/residential"
        items.contact.label  = "Contact"
        items.contact.url     = "/contact"

        [footer-social]
        items.tw.label  = "Twitter"
        items.tw.url    = "https://twitter.com/acme"
        items.tw.target = "_blank"
        INI);
        Zend_Registry::set('Tiger_ThemeDir', $this->dir);
    }

    protected function tearDown(): void
    {
        if (Zend_Registry::isRegistered('Tiger_ThemeDir')) {
            Zend_Registry::getInstance()->offsetUnset('Tiger_ThemeDir');
        }
        @unlink($this->dir . '/configs/menus.ini');
        @rmdir($this->dir . '/configs');
        @rmdir($this->dir);
        try { $this->db->query('DELETE FROM menu'); } catch (\Throwable $e) { /* ignore */ }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new Cms_Service_Menu(['action' => $action] + $params))->getResponse();
    }

    private function countRows(string $menuKey): int
    {
        return (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM menu WHERE menu_key = ? AND org_id = ? AND deleted = 0',
            [$menuKey, '']
        );
    }

    #[Test]
    public function tiger_menu_renders_the_theme_ini_when_the_db_is_empty(): void
    {
        // No DB rows: the base tier (menus.ini) supplies the tree.
        $tree = Tiger_Menu::getData('primary');
        $this->assertCount(3, $tree, 'three top-level items from the theme .ini');
        $this->assertSame('Home', $tree[0]['label']);
        $this->assertSame('/services/residential', $tree[1]['children'][0]['href'], 'nested href resolved');
        $this->assertSame(0, $this->countRows('primary'), 'nothing written — it was a live fallback');
    }

    #[Test]
    public function guest_and_user_are_denied_admin_imports(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $this->assertStringContainsString('not_allowed', json_encode($this->call('importFromTheme')->messages));

        $this->loginAs('user');
        $this->assertSame(0, (int) $this->call('importFromTheme')->result, 'plain user denied');
        $this->assertSame(0, $this->countRows('primary'), 'nothing imported');
    }

    #[Test]
    public function admin_import_clones_the_theme_menus_with_nesting_and_order(): void
    {
        $this->loginAs('admin');
        $res = $this->call('importFromTheme');

        $this->assertSame(1, (int) $res->result);
        $this->assertEqualsCanonicalizing(['primary', 'footer-social'], $res->data['imported']);
        $this->assertSame(4, $this->countRows('primary'), '3 top-level + 1 nested');
        $this->assertSame(1, $this->countRows('footer-social'));

        // The nested "Residential" carries the Services item's id as its parent, at the right order.
        $tree     = (new Tiger_Model_Menu())->tree('primary', '', false);
        $services = $tree[1];
        $this->assertSame('Services', $services['label']);
        $this->assertSame(1, (int) $services['sort_order']);
        $this->assertCount(1, $services['children']);
        $this->assertSame('Residential', $services['children'][0]['label']);
        $this->assertSame($services['menu_id'], $services['children'][0]['parent_id'], 'child parent_id mapped');

        // The DB now overrides the file: getData reads the imported rows, not the .ini.
        $this->assertSame('_blank', (new Tiger_Model_Menu())->tree('footer-social', '', false)[0]['link_target']);
    }

    #[Test]
    public function import_is_idempotent_skips_existing_menus(): void
    {
        $this->loginAs('admin');
        $this->call('importFromTheme');
        $before = $this->countRows('primary');

        $res = $this->call('importFromTheme');
        $this->assertSame(1, (int) $res->result);
        $this->assertSame([], $res->data['imported'], 'nothing new imported');
        $this->assertEqualsCanonicalizing(['primary', 'footer-social'], $res->data['skipped']);
        $this->assertSame($before, $this->countRows('primary'), 'no duplicate rows');
    }
}
