<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_I18n;
use Zend_Registry;
use Zend_Translate;
use Zend_View;

/**
 * Tiger_View_Helper_I18n — the per-page string carrier for inline JS (the values-not-keys, no-global-dump
 * mechanism). It must: register aliased keys, translate them in the active locale, emit ONE hidden carrier
 * with attribute-safe JSON, derive aliases from a list, dedup, and emit NOTHING when nothing registered.
 */
#[CoversClass(Tiger_View_Helper_I18n::class)]
final class I18nHelperTest extends UnitTestCase
{
    private function view(array $map, string $locale = 'en'): Zend_View
    {
        $tr = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => $map, 'locale' => 'en', 'disableNotices' => true]);
        if ($locale !== 'en') {
            $tr->addTranslation(['content' => $map, 'locale' => $locale]);   // caller passes the right map
        }
        $tr->setLocale($locale);
        Zend_Registry::set('Zend_Translate', $tr);

        $view = new Zend_View();
        $view->addHelperPath(dirname(__DIR__, 3) . '/library/Tiger/View/Helper', 'Tiger_View_Helper');
        return $view;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Tiger_View_Helper_I18n::reset();
    }

    protected function tearDown(): void
    {
        Tiger_View_Helper_I18n::reset();
        if (Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
        parent::tearDown();
    }

    #[Test]
    public function registering_returns_nothing_and_the_carrier_holds_the_translated_values(): void
    {
        $view = $this->view(['cms.page.saved' => 'Page saved.', 'cms.page.del' => 'Delete this page?']);

        $this->assertSame('', $view->i18n(['saved' => 'cms.page.saved', 'confirmDel' => 'cms.page.del']), 'registering emits nothing');

        $carrier = $view->i18n();
        $this->assertStringContainsString('id="tiger-i18n"', $carrier);
        $this->assertStringContainsString('Page saved.', $carrier, 'the translated VALUE ships');
        $this->assertStringContainsString('&quot;saved&quot;', $carrier, 'aliased + attribute-escaped JSON');
        $this->assertStringNotContainsString('cms.page.saved', $carrier, 'the KEY is never exposed — only the alias + value');
    }

    #[Test]
    public function it_emits_nothing_when_no_page_registered_strings(): void
    {
        $view = $this->view([]);
        $this->assertSame('', $view->i18n(), 'no carrier at all when the page needs no JS strings');
    }

    #[Test]
    public function a_list_derives_the_alias_from_the_key_tail(): void
    {
        $view = $this->view(['cms.page.saved' => 'Page saved.']);
        $view->i18n(['cms.page.saved']);        // list form → alias "saved"
        $this->assertStringContainsString('&quot;saved&quot;', $view->i18n());
    }

    #[Test]
    public function the_carrier_is_script_break_out_safe(): void
    {
        // a translation value carrying markup / quotes must never break the attribute or inject a tag
        $view = $this->view(['x.evil' => '</script><b>"hi"</b>']);
        $view->i18n(['evil' => 'x.evil']);
        $carrier = $view->i18n();
        $this->assertStringNotContainsString('</script>', $carrier, 'no raw closing script tag');
        $this->assertStringNotContainsString('<b>', $carrier, 'markup is neutralized in the attribute');
    }

    #[Test]
    public function the_active_locale_decides_the_shipped_value(): void
    {
        $view = $this->view(['cms.page.saved' => 'Página guardada.'], 'es');
        $view->i18n(['saved' => 'cms.page.saved']);
        $this->assertStringContainsString('Página guardada.', $view->i18n(), 'ships the active-locale value');
    }
}
