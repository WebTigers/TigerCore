<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Translations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_I18n_Catalog;
use Tiger_Model_Translation;
use Translations_Service_Translation;

/**
 * Translations_Service_Translation — the /api engine behind the Translations admin screen, plus its
 * file-tier reader Tiger_I18n_Catalog.
 *
 * Coverage: the catalog reads the shipped `languages/<lang>/*.php` files as key => value maps (the
 * base tier); the ACL gate (admin+); the DataTables feed (source + effective target value + the
 * overridden/missing flags, narrowable by search); the modal `entry` (a field per supported locale +
 * grep "where used"); the save (upsert a DB override when it diverges from the file, DROP it when it
 * equals the file or is blank — keeping the override tier lean); and `revert`.
 *
 * The module's OWN strings (`translations.*`) are deterministic fixtures — this suite asserts against
 * `translations.heading` ("Translations" / "Traducciones"), which the catalog will always find.
 * SUPPORTED_LANGS is pinned to en,es so the locale set is stable regardless of the harness config.
 */
#[CoversClass(Translations_Service_Translation::class)]
#[CoversClass(Tiger_I18n_Catalog::class)]
final class TranslationServiceTest extends IntegrationTestCase
{
    private const KEY = 'translations.heading';

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('SUPPORTED_LANGS')) {
            define('SUPPORTED_LANGS', ['en', 'es']);
        }
    }

    private function call(string $action, array $params = []): object
    {
        return (new Translations_Service_Translation(['action' => $action] + $params))->getResponse();
    }

    /** Normalize the response data to a plain array regardless of the envelope's internal shape. */
    private function data(object $res): array
    {
        return json_decode(json_encode($res->data), true) ?: [];
    }

    private function overrides(string $locale): array
    {
        return (new Tiger_Model_Translation())->getForLocale($locale, Tiger_Model_Translation::SCOPE_GLOBAL);
    }

    // ----- catalog --------------------------------------------------------------------------------

    #[Test]
    public function the_catalog_reads_shipped_keys_and_each_locales_value(): void
    {
        $en = Tiger_I18n_Catalog::keys('en');
        $this->assertArrayHasKey(self::KEY, $en, 'the canonical (en) key set includes the module string');
        $this->assertSame('Translations', $en[self::KEY]);

        $es = Tiger_I18n_Catalog::map('es');
        $this->assertSame('Traducciones', $es[self::KEY] ?? null, 'the es file value is read from languages/es');
    }

    // ----- ACL ------------------------------------------------------------------------------------

    #[Test]
    public function guest_and_plain_user_are_denied_admin_clears(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $this->assertSame(0, (int) $this->call('datatable', ['locale' => 'es'])->result, 'guest denied');

        $this->loginAs('user');
        $this->assertSame(0, (int) $this->call('datatable', ['locale' => 'es'])->result, 'plain user denied');

        $this->loginAs('admin');
        $this->assertSame(1, (int) $this->call('datatable', ['locale' => 'es'])->result, 'admin clears');
    }

    // ----- datatable ------------------------------------------------------------------------------

    #[Test]
    public function datatable_shows_source_and_target_value_and_flags(): void
    {
        $this->loginAs('admin');
        $rows = $this->data($this->call('datatable', ['locale' => 'es', 'search' => self::KEY]))['data'] ?? [];

        $row = null;
        foreach ($rows as $r) { if ($r['key'] === self::KEY) { $row = $r; } }

        $this->assertNotNull($row, 'the searched key is in the page');
        $this->assertSame('Translations', $row['source'], 'source column is the default-locale string');
        $this->assertSame('Traducciones', $row['value'], 'value column is the target-locale file string');
        $this->assertFalse($row['overridden'], 'no DB override yet');
        $this->assertFalse($row['missing'], 'the es string ships, so not missing');
    }

    // ----- entry (modal) --------------------------------------------------------------------------

    #[Test]
    public function entry_returns_source_a_field_per_locale_and_usage(): void
    {
        $this->loginAs('admin');
        $d = $this->data($this->call('entry', ['key' => self::KEY]));

        $this->assertSame('Translations', $d['source']);
        $this->assertSame('en', $d['default_locale']);

        $codes = array_column($d['locales'], 'code');
        $this->assertContains('en', $codes);
        $this->assertContains('es', $codes);

        // The key IS referenced by the screen's own view ($this->t('translations.heading')).
        $this->assertNotEmpty($d['usage'], 'grep finds where the key is used');
    }

    // ----- save / revert --------------------------------------------------------------------------

    #[Test]
    public function save_upserts_an_override_that_diverges_from_the_file(): void
    {
        $this->loginAs('admin');

        $res = $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'Traducciones (custom)']]);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('Traducciones (custom)', $this->overrides('es')[self::KEY] ?? null, 'override stored');
    }

    #[Test]
    public function save_drops_the_override_when_the_value_equals_the_shipped_file(): void
    {
        $this->loginAs('admin');
        (new Tiger_Model_Translation())->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY, 'stale');
        $this->assertArrayHasKey(self::KEY, $this->overrides('es'), 'precondition: an override exists');

        // Saving the exact shipped string means "use the default" → the row is removed, not stored.
        $this->call('save', ['key' => self::KEY, 'values' => ['es' => 'Traducciones']]);
        $this->assertArrayNotHasKey(self::KEY, $this->overrides('es'), 'override reverted to file');
    }

    #[Test]
    public function save_drops_the_override_when_blank(): void
    {
        $this->loginAs('admin');
        (new Tiger_Model_Translation())->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY, 'stale');

        $this->call('save', ['key' => self::KEY, 'values' => ['es' => '']]);
        $this->assertArrayNotHasKey(self::KEY, $this->overrides('es'), 'a blank value clears the override');
    }

    #[Test]
    public function revert_removes_the_override_for_a_locale(): void
    {
        $this->loginAs('admin');
        (new Tiger_Model_Translation())->set('es', Tiger_Model_Translation::SCOPE_GLOBAL, '', self::KEY, 'x');

        $res = $this->call('revert', ['key' => self::KEY, 'locale' => 'es']);
        $this->assertSame(1, (int) $res->result);
        $this->assertArrayNotHasKey(self::KEY, $this->overrides('es'), 'revert drops the DB override');
    }

    #[Test]
    public function a_missing_key_is_a_clean_error(): void
    {
        $this->loginAs('admin');
        $this->assertSame(0, (int) $this->call('save', ['values' => ['es' => 'x']])->result, 'no key → error');
        $this->assertSame(0, (int) $this->call('entry')->result);
    }
}
