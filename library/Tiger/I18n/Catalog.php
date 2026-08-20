<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_I18n_Catalog — read the SHIPPED translation files as key => value maps.
 *
 * The file tier of i18n (the base under the `translation` DB override tier) is a set of PHP
 * array files scattered across core, first-party modules, app modules, and the app global —
 * exactly the set `Tiger_Application_Bootstrap::_initTranslate` loads to build the request
 * translator. This class reads that same set WITHOUT booting a translator, so a tool (the
 * Translations admin screen) can enumerate every key, show each locale's shipped string, and
 * diff it against a DB override.
 *
 * It is the file-tier twin of `Tiger_Model_Translation` (the DB tier): the model answers
 * "what has been overridden?", this answers "what shipped, and where is it defined?". The
 * canonical key set is the DEFAULT locale's union of keys — every string the platform emits
 * has an `en` entry, so `keys()` is the authoritative list to translate FROM.
 *
 * @api
 * @see Tiger_Model_Translation  the DB override tier this sits under
 */
class Tiger_I18n_Catalog
{
    /**
     * The language files that contribute to a locale, in the SAME merge order the bootstrap
     * uses (core → first-party modules → app modules → app global; later wins). Mirrors
     * `Tiger_Application_Bootstrap::_languageFiles` so the catalog can't drift from the runtime.
     *
     * @param  string $locale language-only locale (en, es)
     * @return string[]        absolute paths to existing files, in load order
     */
    public static function files($locale)
    {
        $locale = self::_safe($locale);
        $files  = [];
        $files[] = TIGER_CORE_PATH . '/core/languages/' . $locale . '/core.php';
        foreach (glob(TIGER_CORE_PATH . '/modules/*/languages/' . $locale . '/*.php') ?: [] as $f) { $files[] = $f; }
        foreach (glob(APPLICATION_PATH . '/modules/*/languages/' . $locale . '/*.php') ?: [] as $f) { $files[] = $f; }
        $files[] = APPLICATION_PATH . '/languages/' . $locale . '/app.php';

        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * The whole file tier for a locale as one key => string map (later file wins on a collision,
     * matching the runtime translator).
     *
     * @param  string $locale language-only locale (en, es)
     * @return array<string,string>
     */
    public static function map($locale)
    {
        $map = [];
        foreach (self::files($locale) as $file) {
            $data = include $file;   // each file returns [key => string]
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_string($k) && $k !== '') { $map[$k] = (string) $v; }
                }
            }
        }
        return $map;
    }

    /**
     * The canonical key set + source strings: the DEFAULT locale's map. Every emitted string
     * has an entry here, so this is the authoritative list of keys to translate FROM.
     *
     * @param  string $default the source/default locale (en)
     * @return array<string,string> key => source string
     */
    public static function keys($default = 'en')
    {
        return self::map($default);
    }

    /**
     * Which shipped file DEFINES each key for a locale (last definition wins, matching `map()`).
     * Used to attribute a key to its owning module/area for the "where is this used" context.
     *
     * @param  string $locale language-only locale (en, es)
     * @return array<string,string> key => absolute file path
     */
    public static function sources($locale = 'en')
    {
        $src = [];
        foreach (self::files($locale) as $file) {
            $data = include $file;
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_string($k) && $k !== '') { $src[$k] = $file; }
                }
            }
        }
        return $src;
    }

    /** Strip a locale to a bare language-ish token so it can never escape the languages dir. */
    protected static function _safe($locale)
    {
        return preg_replace('/[^a-zA-Z_-]/', '', (string) $locale);
    }
}
