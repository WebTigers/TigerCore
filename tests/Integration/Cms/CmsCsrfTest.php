<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Cms;

use Cms_Form_Page;
use Cms_Service_Menu;
use Cms_Service_Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Menu;
use Tiger_Model_Page;
use Zend_Registry;
use Zend_Session;
use Zend_View;

/**
 * TIGER-528: every CMS content write — page delete/restore/fork, the builder's design + block saves, and the
 * menu builder's item delete / reorder / whole-menu delete / revert / import — carries a CSRF token on the
 * browser (cookie-session) path. Before, these hand-validated `$params` (and the menu form opted out of CSRF
 * outright), so any page on the internet could make a signed-in admin's browser rewrite or delete content.
 */
#[CoversClass(Cms_Service_Page::class)]
#[CoversClass(Cms_Service_Menu::class)]
final class CmsCsrfTest extends IntegrationTestCase
{
    private bool $priorUnitTest = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTest = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        Zend_Registry::set('tiger.auth.stateless', false);   // cookie mode: CSRF applies
        $this->loginAs('admin');
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Zend_Session::$_unitTestEnabled = $this->priorUnitTest;
        parent::tearDown();
    }

    /** The token the page editor renders (Cms_Form_Page) — shared by every CMS content write. */
    private function pageToken(): string
    {
        $el = (new Cms_Form_Page())->getElement('_csrf');
        $el->setView(new Zend_View());
        $el->render();
        return (string) $el->getValue();
    }

    private function refused(object $res, string $what): void
    {
        $this->assertSame(0, (int) $res->result, "$what without a token is refused");
        $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$what: the CSRF refusal");
    }

    private function page(): string
    {
        return (string) (new Tiger_Model_Page())->save([
            'type' => Tiger_Model_Page::TYPE_PAGE, 'page_key' => 'csrf-target', 'slug' => 'csrf-target',
            'locale' => 'en', 'title' => 'Target', 'body' => '<p>Original</p>',
            'format' => Tiger_Model_Page::FORMAT_HTML, 'status' => Tiger_Model_Page::STATUS_PUBLISHED,
        ], null);
    }

    #[Test]
    public function every_page_write_refuses_a_tokenless_cross_site_request(): void
    {
        $id = $this->page();

        $calls = [
            'delete'     => ['page_id' => $id],
            'restore'    => ['page_id' => $id, 'version' => 1],
            'forkTheme'  => ['slug' => 'about'],
            'saveDesign' => ['page_id' => $id, 'html' => '<p>Defaced</p>', 'css' => ''],
            'saveBlock'  => ['name' => 'Evil', 'html' => '<p>x</p>'],
        ];
        foreach ($calls as $method => $args) {
            $svc = new Cms_Service_Page();
            $svc->$method($args);
            $this->refused($svc->getResponse(), "page/$method");
        }

        $row = (new Tiger_Model_Page())->findById($id);
        $this->assertNotNull($row, 'not deleted');
        $this->assertSame('<p>Original</p>', (string) $row->body, 'design not overwritten');
    }

    #[Test]
    public function the_editor_token_is_accepted_by_the_builder_save(): void
    {
        $id = $this->page();

        $svc = new Cms_Service_Page();
        $svc->saveDesign(['page_id' => $id, 'html' => '<p>New</p>', 'css' => '', '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result, json_encode($svc->getResponse()->messages));
        $this->assertStringContainsString('<p>New</p>', (string) (new Tiger_Model_Page())->findById($id)->body);
    }

    #[Test]
    public function every_menu_write_refuses_a_tokenless_cross_site_request(): void
    {
        $model = new Tiger_Model_Menu();
        $itemId = (string) $model->insert(['menu_key' => 'csrfmenu', 'org_id' => '', 'label' => 'Home',
                                           'url' => '/', 'sort_order' => 0, 'status' => 'published']);

        $calls = [
            'save'            => ['menu_key' => 'csrfmenu', 'label' => 'Injected', 'org_id' => ''],
            'delete'          => ['menu_id' => $itemId, 'menu_key' => 'csrfmenu', 'org_id' => ''],
            'deleteMenu'      => ['menu_key' => 'csrfmenu', 'org_id' => ''],
            'revertToTheme'   => ['menu_key' => 'csrfmenu', 'org_id' => ''],
            'reorder'         => ['menu_key' => 'csrfmenu', 'org_id' => '', 'tree' => json_encode([])],
            'importFromTheme' => ['org_id' => ''],
        ];
        foreach ($calls as $method => $args) {
            $svc = new Cms_Service_Menu();
            $svc->$method($args);
            $this->refused($svc->getResponse(), "menu/$method");
        }

        $this->assertNotNull($model->findById($itemId), 'the item survives');
        $this->assertCount(1, $model->itemsForEditor('csrfmenu', ''), 'nothing added, nothing removed');
    }

    #[Test]
    public function the_page_token_drives_a_menu_write_too(): void
    {
        $model = new Tiger_Model_Menu();
        $itemId = (string) $model->insert(['menu_key' => 'csrfmenu', 'org_id' => '', 'label' => 'Home',
                                           'url' => '/', 'sort_order' => 0, 'status' => 'published']);

        $svc = new Cms_Service_Menu();
        $svc->delete(['menu_id' => $itemId, 'menu_key' => 'csrfmenu', 'org_id' => '', '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result, 'one CMS salt: a rendered CMS token covers the menu builder');
        $this->assertNull($model->findById($itemId));
    }
}
