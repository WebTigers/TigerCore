<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Translations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_I18n_Overrides;
use Tiger_Model_Org;
use Tiger_Model_Translation;
use Translations_Service_Translation;
use Zend_Registry;
use Zend_Translate;

/**
 * Translation overrides are ORG-LEVEL with a GLOBAL fallback (TIGER-511).
 *
 * The invariants: the Translations screen writes at the caller's org and never another's; an org sees
 * the install-wide (global) value as its inherited fallback until it sets its own; one org's wording
 * never reaches another org; the request translator folds global then the SITE org's tier (public
 * paths read the site org, not the signed-in visitor's); and an admin re-fold swaps the org tier
 * cleanly — none of the previous org's strings survive.
 */
#[CoversClass(Translations_Service_Translation::class)]
#[CoversClass(Tiger_I18n_Overrides::class)]
final class TranslationOrgTierTest extends IntegrationTestCase
{
    private const KEY   = 'translations.heading';   // en "Translations", es "Traducciones"
    private const ORG_A = 'org-tr-a';
    private const ORG_B = 'org-tr-b';

    private $translateBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('SUPPORTED_LANGS')) {
            define('SUPPORTED_LANGS', ['en', 'es']);
        }
        $this->translateBefore = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        Tiger_I18n_Overrides::reset();
        // CSRF is a cookie-mode defence (TIGER-528); a service-level test runs as a Bearer call does.
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_I18n_Overrides::reset();
        if ($this->translateBefore !== null) {
            Zend_Registry::set('Zend_Translate', $this->translateBefore);
        } elseif (Zend_Registry::isRegistered('Zend_Translate')) {
            Zend_Registry::getInstance()->offsetUnset('Zend_Translate');
        }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new Translations_Service_Translation(['action' => $action] + $params))->getResponse();
    }

    private function data(object $res): array
    {
        return json_decode(json_encode($res->data), true) ?: [];
    }

    private function orgTier(string $org, string $locale = 'es'): array
    {
        return (new Tiger_Model_Translation())->getForLocale($locale, Tiger_Model_Translation::SCOPE_ORG, $org);
    }

    /** The es field of the entry modal for KEY, as the signed-in caller sees it. */
    private function esField(): array
    {
        $d = $this->data($this->call('entry', ['key' => self::KEY]));
        foreach ($d['locales'] as $loc) {
            if ($loc['code'] === 'es') { return $loc; }
        }
        $this->fail('no es field');
    }

    // ----- the admin screen: writes at the caller's org, global as the inherited fallback -----------

    #[Test]
    public function a_save_lands_on_the_callers_org_and_never_reaches_another(): void
    {
        $this->login('u-a', self::ORG_A, 'admin');
        $res = $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'Traducciones de A']]);
        $this->assertSame(1, (int) $res->result);

        $this->assertSame('Traducciones de A', $this->orgTier(self::ORG_A)[self::KEY] ?? null);
        $this->assertSame([], (new Tiger_Model_Translation())->getForLocale('es', Tiger_Model_Translation::SCOPE_GLOBAL), 'nothing install-wide');
        $this->assertArrayNotHasKey(self::KEY, $this->orgTier(self::ORG_B));

        // Org B's admin sees the file value, not A's wording, and B's revert cannot touch A's row.
        $this->login('u-b', self::ORG_B, 'admin');
        $b = $this->esField();
        $this->assertSame('Traducciones', $b['value']);
        $this->assertFalse($b['overridden']);
        $this->call('revert', ['key' => self::KEY, 'locale' => 'es']);
        $this->assertSame('Traducciones de A', $this->orgTier(self::ORG_A)[self::KEY] ?? null, 'B\'s revert never reaches A');
    }

    #[Test]
    public function an_org_inherits_the_install_wide_value_until_it_overrides_it(): void
    {
        (new Tiger_Model_Translation())->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY, 'Traducciones (instalación)');

        $this->login('u-a', self::ORG_A, 'admin');
        $f = $this->esField();
        $this->assertSame('Traducciones (instalación)', $f['value'], 'the global value is the inherited fallback');
        $this->assertSame('Traducciones (instalación)', $f['inherited']);
        $this->assertFalse($f['overridden']);

        $row = array_values(array_filter(
            $this->data($this->call('datatable', ['locale' => 'es', 'search' => ['value' => self::KEY]]))['data'] ?? [],
            fn ($r) => $r['key'] === self::KEY
        ))[0] ?? null;
        $this->assertNotNull($row);
        $this->assertTrue($row['inherited']);
        $this->assertFalse($row['overridden']);

        // Saving the inherited value pins nothing; saving something else overrides it for A only.
        $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'Traducciones (instalación)']]);
        $this->assertArrayNotHasKey(self::KEY, $this->orgTier(self::ORG_A), 'the inherited value is not copied into the org');
        $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'Propia']]);
        $this->assertSame('Propia', $this->esField()['value']);

        // A revert drops A's override → back to the inherited install-wide value (the global row is untouched).
        $this->call('revert', ['key' => self::KEY, 'locale' => 'es']);
        $this->assertSame('Traducciones (instalación)', $this->esField()['value']);
        $this->assertSame('Traducciones (instalación)',
            (new Tiger_Model_Translation())->get('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY));
    }

    #[Test]
    public function a_caller_with_no_org_cannot_write(): void
    {
        $this->login('u-none', '', 'admin');
        $this->assertSame(0, (int) $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'X']])->result);
        $this->assertSame(0, (int) $this->call('revert', ['key' => self::KEY])->result);
        $this->assertSame([], (new Tiger_Model_Translation())->getForLocale('es', Tiger_Model_Translation::SCOPE_GLOBAL));
    }

    // ----- the request translator ------------------------------------------------------------------

    #[Test]
    public function overrides_fold_global_then_the_org_on_top(): void
    {
        $m = new Tiger_Model_Translation();
        $m->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY, 'Global');
        $m->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', 'translations.save', 'Guardar (global)');
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_A, self::KEY, 'De A');
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_B, self::KEY, 'De B');

        $a = Tiger_I18n_Overrides::forLocale('es', self::ORG_A);
        $this->assertSame('De A', $a[self::KEY], 'the org wins over global');
        $this->assertSame('Guardar (global)', $a['translations.save'], 'global fills what the org did not set');
        $this->assertSame('De B', Tiger_I18n_Overrides::forLocale('es', self::ORG_B)[self::KEY], 'never another org\'s');
        $this->assertSame('Global', Tiger_I18n_Overrides::forLocale('es', '')[self::KEY], 'no org = global only');
    }

    #[Test]
    public function public_paths_fold_the_site_org_not_the_signed_in_visitors(): void
    {
        $m = new Tiger_Model_Translation();
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_A, self::KEY, 'Sitio A');
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_B, self::KEY, 'Visitante B');

        Tiger_Model_Org::setSiteOrgId(self::ORG_A);        // the host maps to org A
        $this->login('u-b', self::ORG_B, 'user');           // a member of org B is browsing it

        $this->assertSame(self::ORG_A, Tiger_I18n_Overrides::requestOrgId(), 'the request folds the site org');
        $t = $this->translator(Tiger_I18n_Overrides::requestOrgId());
        $this->assertSame('Sitio A', $t->translate(self::KEY, 'es'));

        // An admin screen folds the caller's org instead (the tier the Translations screen edits).
        $this->assertSame(self::ORG_B, Tiger_I18n_Overrides::adminOrgId());
    }

    #[Test]
    public function an_admin_refold_swaps_the_org_tier_cleanly(): void
    {
        $m = new Tiger_Model_Translation();
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_A, self::KEY, 'Sitio A');
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_A, 'translations.save', 'Guardar A');
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_B, self::KEY, 'Admin B');
        // A key no file ships — only org A's override defines it, so only a CLEARED rebuild drops it.
        $m->set('es', Tiger_Model_Translation::SCOPE_ORG, self::ORG_A, 'app.tiger511.only_a', 'Solo A');

        $t = $this->translator(self::ORG_A);
        $this->assertSame('Solo A', $t->translate('app.tiger511.only_a', 'es'));
        Zend_Registry::set('Zend_Translate', $t);
        $this->assertSame('Guardar A', $t->translate('translations.save', 'es'));

        $this->assertFalse(Tiger_I18n_Overrides::refold(self::ORG_A), 'same org → no rebuild');
        $this->assertTrue(Tiger_I18n_Overrides::refold(self::ORG_B));
        $this->assertSame(self::ORG_B, Tiger_I18n_Overrides::foldedOrgId());
        $this->assertSame('Admin B', $t->translate(self::KEY, 'es'), 'B\'s tier is folded');
        $this->assertSame('Guardar', $t->translate('translations.save', 'es'), 'no string of A survives — back to the file');
        $this->assertSame('app.tiger511.only_a', $t->translate('app.tiger511.only_a', 'es'), 'even a key only A defined is gone');
        $this->assertSame('Translations', $t->translate(self::KEY, 'en'), 'other locales are rebuilt from files too');
    }

    /** A translator built the way the bootstrap builds it: files per locale, then the override fold. */
    private function translator(string $orgId): Zend_Translate
    {
        $t = new Zend_Translate(['adapter' => Zend_Translate::AN_ARRAY, 'content' => [], 'locale' => 'en', 'disableNotices' => true]);
        foreach (['en', 'es'] as $lang) {
            foreach (\Tiger_I18n_Catalog::files($lang) as $file) {
                $data = include $file;
                if (is_array($data) && $data) { $t->addTranslation(['content' => $data, 'locale' => $lang]); }
            }
            Tiger_I18n_Overrides::fold($t, $lang, $orgId);
        }
        return $t;
    }
}
