<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_T;
use Zend_Registry;
use Zend_Translate;

/**
 * Tiger_View_Helper_T — the `$this->t('key')` view translator. It must resolve a key against the shared
 * Zend_Translate, fail SOFT (return the key when untranslated — visible, never blank), interpolate args,
 * and survive with no translator registered (early boot / CLI).
 */
#[CoversClass(Tiger_View_Helper_T::class)]
final class THelperTest extends UnitTestCase
{
    private function helper(): Tiger_View_Helper_T
    {
        return new Tiger_View_Helper_T();
    }

    private function registerTranslator(array $map, string $locale = 'en'): void
    {
        $tr = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => $map, 'locale' => $locale, 'disableNotices' => true]);
        Zend_Registry::set('Zend_Translate', $tr);
    }

    protected function tearDown(): void
    {
        if (Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
        parent::tearDown();
    }

    #[Test]
    public function it_translates_a_registered_key(): void
    {
        $this->registerTranslator(['cms.page.saved' => 'Page saved.']);
        $this->assertSame('Page saved.', $this->helper()->t('cms.page.saved'));
    }

    #[Test]
    public function it_returns_the_key_verbatim_when_untranslated(): void
    {
        $this->registerTranslator(['cms.page.saved' => 'Page saved.']);
        $this->assertSame('cms.page.missing', $this->helper()->t('cms.page.missing'), 'a missing key is visible + greppable, never blank');
    }

    #[Test]
    public function it_returns_the_key_when_no_translator_is_registered(): void
    {
        // no Zend_Translate in the registry (early boot / CLI) — must not fatal
        $this->assertSame('agent.error.empty', $this->helper()->t('agent.error.empty'));
    }

    #[Test]
    public function it_interpolates_sprintf_args(): void
    {
        $this->registerTranslator(['cms.page.count' => 'You have %d pages in %s.']);
        $this->assertSame('You have 5 pages in English.', $this->helper()->t('cms.page.count', 5, 'English'));
    }

    #[Test]
    public function a_mismatched_format_string_never_fatals(): void
    {
        $this->registerTranslator(['x.y' => 'plain, no placeholders']);
        // extra args with no placeholders → returns the text unharmed, no exception
        $this->assertSame('plain, no placeholders', $this->helper()->t('x.y', 'unused'));
    }

    #[Test]
    public function the_active_locale_decides_the_value(): void
    {
        $tr = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => ['cms.page.saved' => 'Page saved.'], 'locale' => 'en', 'disableNotices' => true]);
        $tr->addTranslation(['content' => ['cms.page.saved' => 'Página guardada.'], 'locale' => 'es']);
        $tr->setLocale('es');
        Zend_Registry::set('Zend_Translate', $tr);
        $this->assertSame('Página guardada.', $this->helper()->t('cms.page.saved'), 'resolves in the active locale');
    }
}
