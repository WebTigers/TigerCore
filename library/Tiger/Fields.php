<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Fields — the custom-fields registry: declarative field groups that attach to CMS content and
 * store in `page.meta`, versioned and org-cascaded for free (the same seam as `meta.seo.*`).
 *
 * The clean-vs-WordPress story: ACF stores each field as a `wp_postmeta` row (the EAV nightmare that
 * makes WP slow); Tiger puts a group's values in the page's **`meta` JSON** — one row, already loaded,
 * snapshotted by `page_version` (revisions for free), and tenant-cascaded like everything else in
 * `meta`. And because groups are **declared** (config/code), the Module Manager can show what fields a
 * module adds *before* install — the "declarative = inspectable" win ACF structurally can't match.
 *
 * A module contributes a group two equal ways (mirroring Tiger_Admin_Nav):
 *   - **Config** — a `configs/fields.php` returning `['groups' => [ <group>, ... ]]` (auto-discovered
 *     at bootstrap). PHP array, not .ini, because a group nests a list of fields.
 *   - **Code** — `Tiger_Fields::register($group)` from the module Bootstrap, for computed groups.
 *
 * A group:
 *   ['key' => 'listing', 'label' => 'Listing details', 'types' => ['page','article'], 'order' => 50,
 *    'fields' => [ ['key'=>'price','label'=>'Price','type'=>'number','required'=>true], ... ]]
 * `types` empty/absent = applies to every page type. Values live at `meta.fields.<group>.<field>`.
 *
 * @api
 */
class Tiger_Fields
{
    /** Supported field input types. */
    const TYPES = ['text', 'textarea', 'select', 'checkbox', 'number', 'url', 'date', 'media'];

    /** @var array<string,array> group key => normalized group definition */
    protected static $_groups = [];

    /** @var bool whether module configs/fields.php files have been discovered this request */
    protected static $_discovered = false;

    /**
     * Register (or replace, by key) a field group.
     *
     * @param  array $group key, label, and optional types[] / order / fields[]
     * @return void
     */
    public static function register(array $group)
    {
        $key = isset($group['key']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $group['key']) : '';
        if ($key === '' || empty($group['label'])) {
            return;
        }
        $fields = [];
        foreach ((array) ($group['fields'] ?? []) as $f) {
            $fk = isset($f['key']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $f['key']) : '';
            if ($fk === '') { continue; }
            $type = in_array(($f['type'] ?? 'text'), self::TYPES, true) ? $f['type'] : 'text';
            $fields[] = [
                'key'         => $fk,
                'label'       => (string) ($f['label'] ?? $fk),
                'type'        => $type,
                'required'    => !empty($f['required']),
                'options'     => array_values((array) ($f['options'] ?? [])),   // for select
                'help'        => (string) ($f['help'] ?? ''),
                'placeholder' => (string) ($f['placeholder'] ?? ''),
            ];
        }
        self::$_groups[$key] = [
            'key'    => $key,
            'label'  => (string) $group['label'],
            'types'  => array_values(array_filter(array_map('strval', (array) ($group['types'] ?? [])))),
            'order'  => (int) ($group['order'] ?? 100),
            'fields' => $fields,
        ];
    }

    /**
     * Every registered group, sorted by order then label (discovers config-declared groups first).
     *
     * @return array<int,array>
     */
    public static function all()
    {
        self::discover();
        $groups = array_values(self::$_groups);
        usort($groups, static function ($a, $b) {
            return [$a['order'], $a['label']] <=> [$b['order'], $b['label']];
        });
        return $groups;
    }

    /**
     * The groups that apply to a given page type (a group with no `types` applies to all).
     *
     * @param  string $pageType e.g. 'page' | 'article'
     * @return array<int,array>
     */
    public static function forType($pageType)
    {
        $pageType = (string) $pageType;
        return array_values(array_filter(self::all(), static function ($g) use ($pageType) {
            return empty($g['types']) || in_array($pageType, $g['types'], true);
        }));
    }

    /**
     * One group by key (or null).
     *
     * @param  string $key
     * @return array|null
     */
    public static function get($key)
    {
        self::discover();
        return self::$_groups[$key] ?? null;
    }

    /**
     * Read a stored field value from a page row: `Tiger_Fields::value($row, 'listing.price')`.
     *
     * @param  array|ArrayAccess $pageRow a page row (or its ->toArray())
     * @param  string            $path    "<group>.<field>"
     * @param  mixed             $default
     * @return mixed
     */
    public static function value($pageRow, $path, $default = null)
    {
        [$group, $field] = array_pad(explode('.', (string) $path, 2), 2, '');
        $meta = self::_meta($pageRow);
        return $meta['fields'][$group][$field] ?? $default;
    }

    /**
     * The current values for one group on a page row (for editor prefill), keyed by field.
     *
     * @param  array|ArrayAccess $pageRow
     * @param  string            $groupKey
     * @return array
     */
    public static function valuesFor($pageRow, $groupKey)
    {
        $meta = self::_meta($pageRow);
        return (array) ($meta['fields'][(string) $groupKey] ?? []);
    }

    /**
     * Merge posted field values into a page's `meta` array (the save seam). Only DECLARED groups/fields
     * for this page type are accepted — stray posted keys are ignored — and each value is coerced by its
     * declared type. Mutates $meta in place under `meta.fields.<group>.<field>`.
     *
     * @param  array  $meta     the page's decoded meta (by reference)
     * @param  string $pageType the page's type (selects which groups apply)
     * @param  array  $posted   the posted `fields` payload: [group => [field => value]]
     * @return void
     */
    public static function applyToMeta(array &$meta, $pageType, array $posted)
    {
        foreach (self::forType($pageType) as $group) {
            $gk = $group['key'];
            $in = (array) ($posted[$gk] ?? []);
            foreach ($group['fields'] as $f) {
                if (!array_key_exists($f['key'], $in)) {
                    // checkbox absent from a submitted form means "unchecked"
                    if ($f['type'] === 'checkbox') { $meta['fields'][$gk][$f['key']] = false; }
                    continue;
                }
                $meta['fields'][$gk][$f['key']] = self::_coerce($f, $in[$f['key']]);
            }
        }
    }

    /**
     * Discover module-declared groups from each active module's configs/fields.php (idempotent).
     *
     * @return void
     */
    public static function discover()
    {
        if (self::$_discovered) {
            return;
        }
        self::$_discovered = true;

        $dirs = [];
        if (defined('TIGER_CORE_PATH'))  { $dirs[] = TIGER_CORE_PATH . '/modules'; }
        if (defined('APPLICATION_PATH')) { $dirs[] = APPLICATION_PATH . '/modules'; }

        $inactive = [];
        try {
            if (class_exists('Tiger_Model_Module')) { $inactive = (new Tiger_Model_Module())->inactiveSlugs(); }
        } catch (Throwable $e) {
            // DB not ready (install/CLI) — discover everything rather than nothing.
        }

        foreach ($dirs as $modsDir) {
            foreach (glob($modsDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
                if (in_array(basename($moduleDir), $inactive, true)) { continue; }
                $file = $moduleDir . '/configs/fields.php';
                if (!is_file($file)) { continue; }
                try {
                    $decl = include $file;
                    $groups = isset($decl['groups']) ? $decl['groups'] : (is_array($decl) ? $decl : []);
                    foreach ((array) $groups as $g) {
                        if (is_array($g)) { self::register($g); }
                    }
                } catch (Throwable $e) {
                    error_log('Tiger_Fields: fields.php failed to load: ' . $file . ' — ' . $e->getMessage());
                }
            }
        }
    }

    /** Reset the registry (tests). */
    public static function reset()
    {
        self::$_groups = [];
        self::$_discovered = false;
    }

    // ----- internals ---------------------------------------------------------

    /** Coerce a posted value to its declared field type. */
    protected static function _coerce(array $field, $value)
    {
        switch ($field['type']) {
            case 'checkbox':
                return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($value) ? $value + 0 : null;
            case 'url':
            case 'text':
            case 'date':
            case 'media':
                return trim((string) $value);
            case 'select':
                $v = (string) $value;
                return in_array($v, array_map('strval', $field['options']), true) ? $v : '';
            case 'textarea':
            default:
                return (string) $value;
        }
    }

    /** Decode a page row's meta to an array (handles a Zend row, a plain object, an array, or JSON). */
    protected static function _meta($pageRow)
    {
        $meta = null;
        if (is_array($pageRow)) {
            $meta = $pageRow['meta'] ?? null;
        } elseif (is_object($pageRow)) {
            if (method_exists($pageRow, 'toArray')) { $meta = $pageRow->toArray()['meta'] ?? null; }
            elseif (isset($pageRow->meta))          { $meta = $pageRow->meta; }
        }
        if (is_array($meta)) { return $meta; }
        if (is_string($meta) && $meta !== '') { $d = json_decode($meta, true); return is_array($d) ? $d : []; }
        return [];
    }
}
