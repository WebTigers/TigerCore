<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations_Service_Translation — the /api engine behind the Translations admin screen.
 *
 * i18n has two tiers: the SHIPPED files (`languages/<lang>/*.php`, read by Tiger_I18n_Catalog)
 * and the DB OVERRIDE tier (`translation` rows, Tiger_Model_Translation) that wins at request
 * time with no deploy. This service lets an admin browse every key, see each locale's effective
 * string (file value, overridden by DB), and edit/override/revert it — the live-override pattern
 * (AGENTS.md) given a UI. It changes NO menu, form, or view: menus and views already run their
 * labels through the translator (a label is a key OR literal), so overriding a key here re-labels
 * everything that emits it, next request.
 *
 * Two optional AI conveniences reuse the in-app agent's provider (TigerAgent, BYO key): `translate`
 * (draft a locale value from the source string) and `context` (explain where/how a key is used, to
 * orient the translator). Both degrade cleanly when no provider is connected — the screen still works
 * fully by hand.
 *
 * Deny-by-default: admin+ (configs/acl.ini). Writes go through validate → transaction.
 *
 * @api
 * @see Tiger_I18n_Catalog     the shipped-file (base) tier this reads
 * @see Tiger_Model_Translation the DB override tier this writes
 */
class Translations_Service_Translation extends Tiger_Service_Service
{
    /** Cap on grep "where used" hits fed back to the UI / the AI context. */
    const USAGE_MAX = 12;

    /** Display names for the language-only locales (fallback = the code, upper-cased). */
    protected static $_names = [
        'en' => 'English',  'es' => 'Español', 'pt' => 'Português',
        'de' => 'Deutsch',  'fr' => 'Français', 'hi' => 'हिन्दी',
        'it' => 'Italiano', 'nl' => 'Nederlands', 'ja' => '日本語', 'zh' => '中文',
    ];

    // ------------------------------------------------------------------ list

    /**
     * Server-side DataTables feed: one row per translation key, showing the source (default-locale)
     * string and the effective value for a chosen target locale, plus whether it's overridden.
     *
     * @param  array $params DataTables params + `locale` (target) + optional `filter` (all|missing|overridden)
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt      = $this->_dtParams($params);
        $default = $this->_default();
        $locale  = $this->_targetLocale($params['locale'] ?? '');
        $filter  = in_array(($params['filter'] ?? ''), ['missing', 'overridden'], true) ? (string) $params['filter'] : '';

        $source    = Tiger_I18n_Catalog::keys($default);                 // key => source string (canonical set)
        $fileVals  = ($locale === $default) ? $source : Tiger_I18n_Catalog::map($locale);
        $overrides = $this->_overrides($locale);

        // Build the working rows.
        $rows = [];
        foreach ($source as $key => $src) {
            $hasOverride = array_key_exists($key, $overrides);
            $value       = $hasOverride ? $overrides[$key] : ($fileVals[$key] ?? '');
            $rows[] = [
                'key'        => $key,
                'source'     => $src,
                'value'      => $value,
                'overridden' => $hasOverride,
                'missing'    => (trim((string) $value) === ''),
            ];
        }

        $total = count($rows);

        // Filter (toolbar) then search (box) — mirrors the CMS grid split.
        if ($filter === 'missing')    { $rows = array_values(array_filter($rows, fn($r) => $r['missing'])); }
        if ($filter === 'overridden') { $rows = array_values(array_filter($rows, fn($r) => $r['overridden'])); }

        $needle = mb_strtolower($dt['search']);
        if ($needle !== '') {
            $rows = array_values(array_filter($rows, function ($r) use ($needle) {
                return mb_strpos(mb_strtolower($r['key'] . ' ' . $r['source'] . ' ' . $r['value']), $needle) !== false;
            }));
        }
        $filtered = count($rows);

        // Sort: 0=key, 1=source, 2=value; default key asc.
        $col = isset($dt['order'][0]) ? (int) $dt['order'][0]['column'] : 0;
        $dir = isset($dt['order'][0]) ? $dt['order'][0]['dir'] : 'ASC';
        $field = [0 => 'key', 1 => 'source', 2 => 'value'][$col] ?? 'key';
        usort($rows, function ($a, $b) use ($field, $dir) {
            $c = strnatcasecmp((string) $a[$field], (string) $b[$field]);
            return $dir === 'DESC' ? -$c : $c;
        });

        $page     = array_slice($rows, $dt['start'], $dt['length']);
        $canEdit  = $this->_isAdmin(static::class, 'save');

        $data = [];
        foreach ($page as $r) {
            $r['can_edit'] = $canEdit;
            $data[] = $r;
        }

        $this->_dtResponse($dt['draw'], $total, $filtered, $data);
    }

    // ------------------------------------------------------------------ modal

    /**
     * Everything the edit modal needs for one key: the source string, an editable field per
     * supported locale (effective value + whether it's a DB override), and the grep "where used"
     * hits that orient the translator.
     *
     * @param  array $params `key`
     * @return void
     */
    public function entry(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $key = trim((string) ($params['key'] ?? ''));
        if ($key === '') { $this->_error('translations.error.no_key'); return; }

        $default = $this->_default();
        $source  = Tiger_I18n_Catalog::keys($default)[$key] ?? '';

        $locales = [];
        foreach ($this->_supported() as $code) {
            $file      = Tiger_I18n_Catalog::map($code)[$key] ?? '';
            $override  = $this->_overrides($code)[$key] ?? null;
            $locales[] = [
                'code'       => $code,
                'name'       => $this->_localeName($code),
                'is_default' => ($code === $default),
                'file'       => $file,
                'value'      => $override !== null ? $override : $file,
                'overridden' => ($override !== null),
            ];
        }

        $this->_success([
            'key'            => $key,
            'source'         => $source,
            'default_locale' => $default,
            'locales'        => $locales,
            'usage'          => $this->_usage($key),
            'ai'             => Tiger_Agent::isConnected(),
        ]);
    }

    // ------------------------------------------------------------------ write

    /**
     * Save the overrides for one key across locales. A value equal to the shipped file string (or
     * blank) drops the override (revert to file); anything else upserts a DB override. Keeps the
     * override tier lean — only genuine divergences are stored.
     *
     * @param  array $params `key` + `values[<locale>]`
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $key    = trim((string) ($params['key'] ?? ''));
        $values = (array) ($params['values'] ?? []);
        if ($key === '') { $this->_error('translations.error.no_key'); return; }

        $supported = $this->_supported();
        $model     = new Tiger_Model_Translation();

        try {
            $this->_transaction(function () use ($key, $values, $supported, $model) {
                foreach ($supported as $code) {
                    if (!array_key_exists($code, $values)) { continue; }
                    $value = trim((string) $values[$code]);
                    $file  = Tiger_I18n_Catalog::map($code)[$key] ?? '';
                    if ($value === '' || $value === $file) {
                        $model->forget($code, Tiger_Model_Translation::SCOPE_GLOBAL, '', $key);   // revert to file
                    } else {
                        $model->set($code, Tiger_Model_Translation::SCOPE_GLOBAL, '', $key, $value);
                    }
                }
                return true;
            });
            $this->_success(['key' => $key], 'translations.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Revert one key to its shipped file value for one locale (or all supported locales when
     * `locale` is omitted) — drops the DB override(s).
     *
     * @param  array $params `key` + optional `locale`
     * @return void
     */
    public function revert(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $key = trim((string) ($params['key'] ?? ''));
        if ($key === '') { $this->_error('translations.error.no_key'); return; }

        $one     = (string) ($params['locale'] ?? '');
        $locales = ($one !== '' && in_array($one, $this->_supported(), true)) ? [$one] : $this->_supported();
        $model   = new Tiger_Model_Translation();

        try {
            $this->_transaction(function () use ($key, $locales, $model) {
                foreach ($locales as $code) {
                    $model->forget($code, Tiger_Model_Translation::SCOPE_GLOBAL, '', $key);
                }
                return true;
            });
            $this->_success(['key' => $key], 'translations.reverted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    // ------------------------------------------------------------------ AI helpers

    /**
     * Draft a translation of the source string into a target locale with the connected AI provider
     * (BYO key; TigerAgent). The client fills the field — it's a draft, saved only on Save.
     *
     * @param  array $params `source`, `locale`, optional `key`
     * @return void
     */
    public function translate(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        if (!Tiger_Agent::isConnected()) { $this->_error('translations.error.no_ai'); return; }

        $source = (string) ($params['source'] ?? '');
        $locale = (string) ($params['locale'] ?? '');
        if (trim($source) === '' || !in_array($locale, $this->_supported(), true)) {
            $this->_error('translations.error.bad_translate'); return;
        }

        $system = 'You are a professional software-localization translator. Translate the UI string from English into '
            . $this->_localeName($locale) . ' (locale "' . $locale . '"). Rules: preserve ALL placeholders and markup '
            . 'EXACTLY — printf specifiers (%s, %d, %1$s), brace tokens ({name}), and HTML tags/entities '
            . '(<code>…</code>, &lt;) must appear unchanged and in a natural position. Match the tone of concise product UI. '
            . 'Return ONLY the translated string — no quotes, no explanation, no trailing period unless the source has one.';

        $out = $this->_ai($system, $source);
        if ($out === null) { $this->_error('translations.error.ai_failed'); return; }

        $this->_success(['text' => trim($out), 'locale' => $locale]);
    }

    /**
     * Explain where/how a key is used, to orient the translator: greps the owning area for the key,
     * then asks the AI for a one/two-sentence summary. Cached per key (the codebase is static-ish),
     * so a re-open is free; the grep hits are always returned live.
     *
     * @param  array $params `key`
     * @return void
     */
    public function context(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $key = trim((string) ($params['key'] ?? ''));
        if ($key === '') { $this->_error('translations.error.no_key'); return; }

        $usage   = $this->_usage($key);
        $cacheKey = $this->_contextCacheFile($key);
        $cached   = ($cacheKey !== '' && is_file($cacheKey)) ? (string) @file_get_contents($cacheKey) : '';

        if ($cached !== '') {
            $this->_success(['summary' => $cached, 'usage' => $usage, 'cached' => true]);
            return;
        }
        if (!Tiger_Agent::isConnected()) { $this->_error('translations.error.no_ai'); return; }

        $default = $this->_default();
        $src     = Tiger_I18n_Catalog::keys($default)[$key] ?? '';
        $lines   = array_map(fn($u) => $u['file'] . ':' . $u['line'] . '  ' . $u['snippet'], $usage);

        $system = 'You explain, in ONE or TWO short sentences, how a UI translation string is used in a web app, '
            . 'to help a human translator pick the right wording. Be concrete about the surface (button, page heading, '
            . 'error message, menu label, email…) and any constraints (length, formality, placeholders). No preamble.';
        $user = "Translation key: {$key}\nEnglish source: \"{$src}\"\n\nReferences (file:line):\n"
            . ($lines ? implode("\n", $lines) : '(no direct code references found; it may be used dynamically)');

        $out = $this->_ai($system, $user);
        if ($out === null) { $this->_error('translations.error.ai_failed'); return; }

        $summary = trim($out);
        if ($cacheKey !== '') { @mkdir(dirname($cacheKey), 0775, true); @file_put_contents($cacheKey, $summary); }

        $this->_success(['summary' => $summary, 'usage' => $usage, 'cached' => false]);
    }

    // ------------------------------------------------------------------ internals

    /** One-shot AI completion via the connected provider (BYO key). Returns the text, or null on failure. */
    protected function _ai($system, $user)
    {
        try {
            $adapter = Tiger_Agent_Provider_Factory::make(Tiger_Agent::provider());
            $reply   = $adapter->complete($system, [['role' => 'user', 'content' => (string) $user]], Tiger_Agent::model(), Tiger_Agent::apiKey());
            $text    = is_array($reply) ? (string) ($reply['text'] ?? '') : (string) $reply;
            return $text === '' ? null : $text;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Current DB overrides for a locale (global scope) as key => value. */
    protected function _overrides($locale)
    {
        try {
            return (new Tiger_Model_Translation())->getForLocale($locale, Tiger_Model_Translation::SCOPE_GLOBAL);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * "Where used" — grep the area that OWNS a key (derived from its owner prefix) for literal
     * references. Narrowing by prefix (core.* → core/library, <module>.* → that module) keeps it fast
     * and portable (a bounded PHP scan, no shell), and accurate enough to orient a translator.
     *
     * @param  string $key
     * @return array<int,array{file:string,line:int,snippet:string}>
     */
    protected function _usage($key)
    {
        $prefix = strtok($key, '.');
        $roots  = [];
        if ($prefix === 'core') {
            $roots[] = TIGER_CORE_PATH . '/core';
            $roots[] = TIGER_CORE_PATH . '/library';
        } elseif ($prefix === 'app') {
            $roots[] = APPLICATION_PATH;
        } else {
            $roots[] = TIGER_CORE_PATH . '/modules/' . $prefix;
            $roots[] = APPLICATION_PATH . '/modules/' . $prefix;
            $roots[] = TIGER_CORE_PATH . '/themes';   // themes reference menu/label keys too
        }

        $hits = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) { continue; }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS)
            );
            foreach ($it as $file) {
                if (count($hits) >= self::USAGE_MAX) { break 2; }
                $ext = strtolower($file->getExtension());
                if ($ext !== 'php' && $ext !== 'phtml' && $ext !== 'ini') { continue; }
                $path = $file->getPathname();
                // Skip the language files themselves (they DEFINE the key, they don't USE it).
                if (strpos($path, '/languages/') !== false) { continue; }
                $lines = @file($path, FILE_IGNORE_NEW_LINES);
                if (!$lines) { continue; }
                foreach ($lines as $n => $line) {
                    if (strpos($line, $key) === false) { continue; }
                    $hits[] = [
                        'file'    => $this->_relPath($path),
                        'line'    => $n + 1,
                        'snippet' => trim(mb_substr($line, 0, 160)),
                    ];
                    if (count($hits) >= self::USAGE_MAX) { break 2; }
                }
            }
        }
        return $hits;
    }

    /** Trim an absolute path to a repo-relative one for display. */
    protected function _relPath($path)
    {
        foreach ([TIGER_CORE_PATH => 'tiger-core', APPLICATION_PATH => 'app'] as $base => $label) {
            if (strpos($path, $base) === 0) { return $label . substr($path, strlen($base)); }
        }
        return $path;
    }

    /** The supported language-only locales (from LocalePrefix's resolved set, else config). */
    protected function _supported()
    {
        if (defined('SUPPORTED_LANGS') && is_array(SUPPORTED_LANGS) && SUPPORTED_LANGS) {
            return array_values(SUPPORTED_LANGS);
        }
        $i18n = $this->_i18n();
        $list = array_values(array_filter(array_map('trim', explode(',', (string) ($i18n['locales'] ?? '')))));
        return $list ?: ['en'];
    }

    /** The default/source locale (tiger.i18n.default), clamped to the supported set. */
    protected function _default()
    {
        $i18n = $this->_i18n();
        $sup  = $this->_supported();
        $d    = (string) ($i18n['default'] ?? '');
        return ($d !== '' && in_array($d, $sup, true)) ? $d : $sup[0];
    }

    /** A requested target locale, clamped to the supported set (defaults to the first non-default locale). */
    protected function _targetLocale($requested)
    {
        $sup = $this->_supported();
        if (in_array($requested, $sup, true)) { return (string) $requested; }
        $default = $this->_default();
        foreach ($sup as $code) { if ($code !== $default) { return $code; } }
        return $default;
    }

    /** The tiger.i18n config subtree as a plain array. */
    protected function _i18n()
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return []; }
        $cfg   = Zend_Registry::get('Zend_Config');
        $tiger = $cfg->get('tiger');
        $i18n  = $tiger ? $tiger->get('i18n') : null;
        return $i18n ? $i18n->toArray() : [];
    }

    /** Display name for a locale (fallback = upper-cased code). */
    protected function _localeName($code)
    {
        return self::$_names[$code] ?? strtoupper((string) $code);
    }

    /** Per-key cache file for the AI context summary (repo-static, so cache aggressively). */
    protected function _contextCacheFile($key)
    {
        return defined('APPLICATION_ROOT')
            ? APPLICATION_ROOT . '/var/cache/i18n-context/' . md5($key) . '.txt'
            : '';
    }
}
