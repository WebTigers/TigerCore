<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
namespace Tiger\Tests\Integration\Cms;

use Cms_Form_Page;
use Cms_Service_Page;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Page;
use Zend_Registry;

/**
 * The per-page SEO/social fields the CMS editor writes — `seo_title` and `og_image_id` — end to end:
 * form declaration + validation, the merge into `page.meta.seo`, and the read back out by
 * Seo_Service_Head::forRow(). The merge semantics are the load-bearing part: a caller that OMITS a
 * field (an /api client or the AI agent sending a partial message) must not wipe an authored value,
 * while an explicit blank clears the override so the site-wide fallback applies again.
 */
#[CoversClass(Cms_Service_Page::class)]
final class SeoFieldsRoundTripTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);
        $this->loginAs('admin');
    }

    #[Test]
    public function form_declares_and_validates_the_new_fields(): void
    {
        $form = new Cms_Form_Page();
        $this->assertNotNull($form->getElement('seo_title'));
        $this->assertNotNull($form->getElement('og_image_id'));

        $uuid = '0192f3a4-5b6c-7d8e-9f01-23456789abcd';
        $this->assertTrue($form->isValid(['title' => 'T', 'seo_title' => 'S', 'og_image_id' => $uuid]));
        $v = $form->getValues();
        $this->assertSame('S', $v['seo_title']);
        $this->assertSame($uuid, $v['og_image_id']);

        // blank is allowed (not required)
        $form2 = new Cms_Form_Page();
        $this->assertTrue($form2->isValid(['title' => 'T', 'seo_title' => '', 'og_image_id' => '']));

        // garbage image ref refused
        $form3 = new Cms_Form_Page();
        $this->assertFalse($form3->isValid(['title' => 'T', 'og_image_id' => 'not a media id']));
    }

    #[Test]
    public function save_round_trips_into_meta_seo_and_never_wipes_on_omission(): void
    {
        $uuid = '0192f3a4-5b6c-7d8e-9f01-23456789abcd';
        $call = function (array $p) { return (new Cms_Service_Page(['action' => 'save'] + $p))->getResponse(); };
        $r = $call([
            'title' => 'RT Page', 'slug' => 'rt-page-seo', 'type' => 'page', 'format' => 'html',
            'status' => 'draft', 'locale' => 'en', 'body' => 'x',
            'seo_title' => 'Better Title', 'meta_description' => 'D', 'og_image_id' => $uuid,
        ]);
        $this->assertSame(1, (int) $r->result, json_encode($r->messages));
        $id = $r->data['page_id'];

        $meta = json_decode((string) (new Tiger_Model_Page())->findById($id)->meta, true);
        $this->assertSame('Better Title', $meta['seo']['title']);
        $this->assertSame($uuid, $meta['seo']['og_image_id']);
        $this->assertSame('D', $meta['seo']['description']);

        // A save that OMITS both fields must preserve them.
        $r2 = $call([
            'page_id' => $id, 'title' => 'RT Page', 'slug' => 'rt-page-seo', 'type' => 'page',
            'format' => 'html', 'status' => 'draft', 'locale' => 'en', 'body' => 'y',
        ]);
        $this->assertSame(1, (int) $r2->result, json_encode($r2->messages));
        $meta = json_decode((string) (new Tiger_Model_Page())->findById($id)->meta, true);
        $this->assertSame('Better Title', $meta['seo']['title']);
        $this->assertSame($uuid, $meta['seo']['og_image_id']);

        // A submitted BLANK clears the override.
        $r3 = $call([
            'page_id' => $id, 'title' => 'RT Page', 'slug' => 'rt-page-seo', 'type' => 'page',
            'format' => 'html', 'status' => 'draft', 'locale' => 'en', 'body' => 'z',
            'seo_title' => '', 'og_image_id' => '',
        ]);
        $this->assertSame(1, (int) $r3->result, json_encode($r3->messages));
        $meta = json_decode((string) (new Tiger_Model_Page())->findById($id)->meta, true);
        $this->assertArrayNotHasKey('title', $meta['seo']);
        $this->assertArrayNotHasKey('og_image_id', $meta['seo']);
    }

    #[Test]
    public function head_service_reads_what_we_wrote(): void
    {
        $page = (object) ['meta' => json_encode(['seo' => ['title' => 'Hello SEO', 'og_image_id' => 'https://example.com/x.png']]), 'title' => 'Raw'];
        $view = new \Zend_View();
        $view->doctype('HTML5');
        $view->headTitle()->getContainer()->exchangeArray([]);
        $view->headMeta()->getContainer()->exchangeArray([]);
        Zend_Registry::set('Zend_View', $view);
        \Seo_Service_Head::forRow($page);
        $this->assertStringContainsString('Hello SEO', $view->headTitle()->toString());
        $this->assertStringContainsString('https://example.com/x.png', $view->headMeta()->toString());
    }
}
