<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Seo_Service_Head;
use Tiger\Tests\Support\IntegrationTestCase;
use Zend_Config;
use Zend_Controller_Request_Http;
use Zend_Controller_Request_Simple;
use Zend_Registry;
use Zend_View;

/**
 * Seo_Service_Head — the tail branches HeadServiceTest left open: an already-decoded array `meta` (no
 * JSON decode), a request without getScheme() (no self-referencing canonical), a view-less fallback, a
 * config-less read, and an article whose dates are blank (no article:*_time tags). Sibling to
 * HeadServiceTest (the happy paths) — this targets ONLY the still-uncovered arms.
 */
#[CoversClass(Seo_Service_Head::class)]
final class HeadServiceExtraTest extends IntegrationTestCase
{
    private ?Zend_View $view = null;
    private bool $hadConfig = false;
    private $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view = new Zend_View();
        $this->view->doctype('HTML5');
        Zend_Registry::set('Zend_View', $this->view);
        $this->view->headTitle()->getContainer()->exchangeArray([]);
        $this->view->headMeta()->getContainer()->exchangeArray([]);
        $this->view->headLink()->getContainer()->exchangeArray([]);

        $this->hadConfig   = Zend_Registry::isRegistered('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_View'))   { $reg->offsetUnset('Zend_View'); }
        if ($this->hadConfig) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger]));
    }

    private function request(string $uri = '/hello'): Zend_Controller_Request_Http
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['HTTPS']     = 'on';
        $r = new Zend_Controller_Request_Http();
        $r->setRequestUri($uri);
        return $r;
    }

    private function head(): string
    {
        return $this->view->headTitle()->toString() . "\n"
             . $this->view->headMeta()->toString() . "\n"
             . $this->view->headLink()->toString();
    }

    // ----- meta already an array (no JSON decode) -----------------------------------------------

    #[Test]
    public function a_page_whose_meta_is_already_an_array_is_read_without_decoding(): void
    {
        // A Zend_Db_Table_Row that already decoded its JSON hands the service a native array.
        $page = (object) [
            'meta'  => ['seo' => ['title' => 'Array Meta Title', 'description' => 'From an array.']],
            'title' => 'Row Title',
            'type'  => 'page',
        ];
        Seo_Service_Head::forRow($page, $this->request());
        $head = $this->head();
        $this->assertStringContainsString('Array Meta Title', $head, 'the array meta drives the title');
        $this->assertStringContainsString('From an array.', $head);
    }

    // ----- a request that cannot produce a scheme (no self-referencing canonical) ---------------

    #[Test]
    public function a_request_without_getScheme_yields_no_self_referencing_canonical(): void
    {
        $page = (object) ['meta' => json_encode([]), 'title' => 'No Scheme', 'type' => 'page'];
        // Zend_Controller_Request_Simple has no getScheme()/getHttpHost() — _currentUrl() returns ''.
        Seo_Service_Head::forRow($page, new Zend_Controller_Request_Simple());
        $head = $this->head();
        $this->assertStringNotContainsString('rel="canonical"', $head, 'no scheme → no canonical link');
        $this->assertStringNotContainsString('og:url', $head, 'no scheme → no og:url');
        // The render still succeeds: og:type/twitter:card are always emitted.
        $this->assertStringContainsString('og:type', $head);
    }

    // ----- view-less + config-less fallbacks ----------------------------------------------------

    #[Test]
    public function forRow_still_populates_when_no_view_and_no_config_are_registered(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_View'))   { $reg->offsetUnset('Zend_View'); }
        if ($reg->offsetExists('Zend_Config')) { $reg->offsetUnset('Zend_Config'); }

        $page = (object) ['meta' => json_encode(['seo' => ['title' => 'Fallback Title']]), 'title' => 'Row', 'type' => 'page'];
        Seo_Service_Head::forRow($page, $this->request());

        // Placeholder containers are a process-wide singleton — re-register a view to read them back.
        Zend_Registry::set('Zend_View', $this->view);
        $this->assertStringContainsString('Fallback Title', $this->head(), 'the fallback view wrote to the shared head containers');
    }

    // ----- an article with blank dates emits no article:*_time tags -----------------------------

    #[Test]
    public function an_article_with_blank_dates_emits_no_article_time_tags(): void
    {
        $this->config(['site' => ['name' => 'Acme']]);
        $page = (object) [
            'meta'         => json_encode([]),
            'title'        => 'Undated',
            'type'         => 'article',
            'published_at' => '',
            'updated_at'   => '',
        ];
        Seo_Service_Head::forRow($page, $this->request('/blog/undated'));
        $head = $this->head();
        $this->assertStringContainsString('og:type', $head);
        $this->assertStringContainsString('article', $head, 'an article page still tags og:type=article');
        $this->assertStringNotContainsString('article:published_time', $head, 'blank dates emit no published_time');
        $this->assertStringNotContainsString('article:modified_time', $head);
    }
}
