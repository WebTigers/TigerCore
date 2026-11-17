<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Module_TableGuard — refuse, BEFORE a module is installed, a package whose migrations would create a database
 * table that collides with one already there (TIGER-444).
 *
 * Two checks, run on the staged package before it is moved into place, so a refusal leaves nothing behind:
 *
 *  1. **Live tables (every module).** Each table a not-yet-applied migration creates must not already exist —
 *     whoever owns it (core, the app, another module). Only migrations the ledger hasn't applied are considered:
 *     an update re-ships its old migrations, and their tables legitimately exist.
 *  2. **A branded prefix (third-party modules).** The vendor declares its table prefix in the module's
 *     `configs/module.ini` (`tables.prefix = "paysoft_"`): lowercase, at least three letters/digits then `_`, not a
 *     generic or reserved word (`user_`, `mail_`, `tiger_`…), not overlapping another installed module's prefix in
 *     either direction (`shop_` vs `shop_pro_`), and actually used by every table its migrations create.
 *     WebTigers' own modules are exempt — first-party modules own their plain names, as core does. MODULE_VENDORS.md
 *     is the vendor-facing rulebook.
 *
 * Table names are read from the migration files as TEXT (`CREATE TABLE …`), never by executing them. The bare
 * `CREATE TABLE` (no IF NOT EXISTS) in a migration stays as the database's own backstop.
 *
 * @api
 * @since 1.68.0
 */
class Tiger_Module_TableGuard
{
    /** Refused: a migration would create a table that already exists. */
    const E_TABLE_COLLISION = 4440;
    /** Refused: a third-party module has no valid branded prefix, or a table doesn't use it. */
    const E_TABLE_PREFIX = 4441;

    /** A prefix: a lowercase word of 3+ letters/digits (more words joined by `_` allowed), ending in `_`. */
    const PREFIX_PATTERN = '/^[a-z][a-z0-9]{2,}(?:_[a-z0-9]+)*_$/';

    /** Generic or platform words a vendor may not claim as a prefix — the names a collision is likeliest on. */
    const RESERVED_PREFIXES = ['tiger_', 'core_', 'zend_', 'app_', 'user_', 'users_', 'org_', 'auth_', 'mail_',
        'email_', 'page_', 'config_', 'option_', 'site_', 'module_', 'data_', 'test_', 'tmp_', 'temp_', 'admin_',
        'api_', 'log_', 'cache_', 'session_', 'acl_', 'media_', 'file_', 'shop_', 'pay_', 'blog_', 'cms_'];

    /**
     * Refuse the staged package if it would collide. Throws with a named, actionable reason; returns quietly when
     * the install may proceed.
     *
     * @param  string                         $slug     the module slug
     * @param  string                         $root     the staged package directory (holds module.json, migrations/)
     * @param  array                          $manifest the parsed module.json / theme.json
     * @param  Zend_Db_Adapter_Abstract|null  $db       the app database (null = no DB to check against: skip)
     * @param  array                          $provenance where it came from ({repository} when known)
     * @return void
     * @throws RuntimeException E_TABLE_COLLISION / E_TABLE_PREFIX
     */
    public static function check($slug, $root, array $manifest, ?Zend_Db_Adapter_Abstract $db, array $provenance = [])
    {
        $tables = self::pendingTables($root, $db);   // tables the migrations that WILL run create
        if (!$tables) { return; }

        if (!self::isFirstParty($manifest, $provenance)) {
            $prefix = self::declaredPrefix($root);
            if ($prefix === '') {
                throw new RuntimeException("Module '{$slug}' creates database tables but declares no table prefix. "
                    . 'Add `tables.prefix = "yourbrand_"` to configs/module.ini (see MODULE_VENDORS.md).', self::E_TABLE_PREFIX);
            }
            if (!preg_match(self::PREFIX_PATTERN, $prefix)) {
                throw new RuntimeException("Module '{$slug}' declares the table prefix '{$prefix}', which is not valid: use a branded, "
                    . 'lowercase prefix of at least three letters or digits ending in "_", e.g. "paysoft_".', self::E_TABLE_PREFIX);
            }
            if (in_array($prefix, self::RESERVED_PREFIXES, true)) {
                throw new RuntimeException("Module '{$slug}' declares the table prefix '{$prefix}', which is generic or reserved. "
                    . 'Use a prefix branded to your company or product, e.g. "paysoft_".', self::E_TABLE_PREFIX);
            }
            foreach (self::installedPrefixes($slug) as $other => $theirs) {
                if (strpos($prefix, $theirs) === 0 || strpos($theirs, $prefix) === 0) {
                    throw new RuntimeException("Module '{$slug}' declares the table prefix '{$prefix}', which overlaps "
                        . "'{$theirs}' used by the installed module '{$other}'. Choose a different prefix.", self::E_TABLE_PREFIX);
                }
            }
            foreach ($tables as $t) {
                if (strpos($t, $prefix) !== 0) {
                    throw new RuntimeException("Module '{$slug}' creates the table '{$t}', which does not use its declared prefix "
                        . "'{$prefix}'. Every table a module creates must start with its prefix.", self::E_TABLE_PREFIX);
                }
            }
        }

        if ($db === null) { return; }
        $existing = array_flip(array_map('strtolower', (array) $db->listTables()));
        foreach ($tables as $t) {
            if (isset($existing[$t])) {
                throw new RuntimeException("Module '{$slug}' would create the table '{$t}', which already exists in this database "
                    . '(owned by core, the app, or another module). Installing it would collide; nothing was installed.',
                    self::E_TABLE_COLLISION);
            }
        }
    }

    /**
     * The tables created by the package's migrations that the ledger has NOT applied yet (lowercased, unique).
     * With no DB, every migration counts as pending.
     *
     * @param  string                        $root the staged package directory
     * @param  Zend_Db_Adapter_Abstract|null $db
     * @return string[]
     */
    public static function pendingTables($root, ?Zend_Db_Adapter_Abstract $db)
    {
        $dir = rtrim((string) $root, '/') . '/migrations';
        if (!is_dir($dir)) { return []; }
        $applied = [];
        if ($db !== null) {
            try {
                foreach ((new Tiger_Db_Migrator($db, [$dir]))->status() as $m) {
                    if (!empty($m['applied'])) { $applied[$m['version'] . '_' . $m['name']] = true; }
                }
            } catch (Throwable $e) {
                $applied = [];   // no ledger yet → every migration is pending
            }
        }
        $out = [];
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            if (isset($applied[basename($file, '.php')])) { continue; }
            foreach (self::createdTables((string) @file_get_contents($file)) as $t) { $out[$t] = true; }
        }
        return array_keys($out);
    }

    /**
     * The table names a migration file's text creates — `CREATE TABLE [IF NOT EXISTS] [`]name[`]`, case-insensitive.
     *
     * @param  string $sql the migration file's contents
     * @return string[] lowercased table names
     */
    public static function createdTables($sql)
    {
        preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', (string) $sql, $m);
        return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
    }

    /**
     * The prefix a package declares in `configs/module.ini` (`tables.prefix`, the [production] section or the top
     * level), or ''.
     *
     * @param  string $root a module directory
     * @return string
     */
    public static function declaredPrefix($root)
    {
        $ini = rtrim((string) $root, '/') . '/configs/module.ini';
        if (!is_file($ini)) { return ''; }
        $data = @parse_ini_file($ini, true, INI_SCANNER_RAW);
        if (!is_array($data)) { return ''; }
        $val = $data['production']['tables.prefix'] ?? ($data['tables.prefix'] ?? '');
        return strtolower(trim((string) $val, " \t\"'"));
    }

    /**
     * Every other installed module's declared prefix (slug => prefix), read live from the installed files.
     *
     * @param  string $exceptSlug the module being installed (its own older copy is not a rival)
     * @return array<string,string>
     */
    public static function installedPrefixes($exceptSlug = '')
    {
        $out = [];
        foreach (self::_moduleDirs() as $dir) {
            $slug = basename($dir);
            if ($slug === $exceptSlug) { continue; }
            $p = self::declaredPrefix($dir);
            if ($p !== '') { $out[$slug] = $p; }
        }
        return $out;
    }

    /**
     * Is this a WebTigers (first-party) module — exempt from the vendor prefix rule? Known by where it came from (a
     * repository under github.com/WebTigers/) or what it says it is (`author.name` "WebTigers"). The live-table
     * check below still applies to it; the prefix rule is a naming convention, not the safety net.
     *
     * @param  array $manifest
     * @param  array $provenance
     * @return bool
     */
    public static function isFirstParty(array $manifest, array $provenance = [])
    {
        $repo = strtolower((string) ($provenance['repository'] ?? ($manifest['repository'] ?? '')));
        if (preg_match('#^(https?://)?(www\.)?github\.com/webtigers/#', $repo) || strpos($repo, 'webtigers/') === 0) { return true; }
        $author = $manifest['author'] ?? '';
        $name   = is_array($author) ? (string) ($author['name'] ?? '') : (string) $author;
        return strcasecmp(trim($name), 'WebTigers') === 0;
    }

    /** Installed module directories (app modules + the bundled core ones). */
    protected static function _moduleDirs()
    {
        $dirs = [];
        foreach ([defined('APPLICATION_PATH') ? APPLICATION_PATH . '/modules' : null,
                  defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH . '/modules' : null] as $base) {
            if ($base && is_dir($base)) { $dirs = array_merge($dirs, glob($base . '/*', GLOB_ONLYDIR) ?: []); }
        }
        return $dirs;
    }
}
