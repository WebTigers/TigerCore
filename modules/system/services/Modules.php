<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Service_Modules — the /api service for the Module manager (activate / deactivate).
 *
 * Toggling flips the `module.active` flag; the activation gate
 * (Tiger_Application_Resource_Modules) picks it up on the NEXT request — a deactivated module
 * drops off routing + bootstrapping entirely. Gated to `superadmin`+ (managing modules is a
 * platform-admin privilege). A PROTECTED set can never be deactivated so you can't lock
 * yourself out of the manager, user admin, or core dispatch.
 *
 * @api
 */
class System_Service_Modules extends Tiger_Service_Service
{
    /** Modules that must always stay active. */
    const PROTECTED = ['default', 'system', 'access'];

    /**
     * The reserved license "slug" the install's ONE TigerPASS key is stored under. TigerPASS is not a
     * per-module license — one subscription key unlocks the whole WebTigers premium shelf — so every
     * PASS-covered module resolves its entitlement from this single record (not its own slug).
     */
    const PASS_SLUG = '__tigerpass__';

    /** The `owner/repo` trust anchor for WebTigers-published premium modules (the TigerPASS shelf). */
    const PASS_VENDOR = 'WebTigers/TigerVendor';

    /** How long dismissing the TigerPASS promo banner snoozes it (days) before it may reappear. */
    const NAG_SNOOZE_DAYS = 30;

    /** Per-user option keys for the TigerPASS nag (lazy `option` tier — never the eager config tier). */
    const NAG_DISMISSED_KEY = 'tiger.pass.nag.dismissed_at';   // UTC unix stamp of the last dismiss
    const NAG_DISABLED_KEY  = 'tiger.pass.nag.disabled';       // '1' = the user turned the banner off

    /**
     * Activate a module (by `slug`), publishing its assets.
     *
     * @param  array $params the /api payload (expects `slug`)
     * @return void
     */
    public function activate(array $params): void   { $this->_toggle($params, true); }

    /**
     * Deactivate a module (by `slug`), unpublishing its assets.
     *
     * @param  array $params the /api payload (expects `slug`)
     * @return void
     */
    public function deactivate(array $params): void { $this->_toggle($params, false); }

    /**
     * NUCLEAR delete of a NON-CORE module (by `slug`): drops its tables + data, then removes its files,
     * assets, and install row. **Irreversible.** Guarded four ways: superadmin-only (the service ACL);
     * never a bundled/core or PROTECTED module; a typed-confirmation `confirm` token that must match the
     * module's `<vendor>/<name>`; and a dependency gate — refused while another ACTIVE module requires it.
     *
     * @param  array $params the /api payload (expects `slug` + `confirm`)
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['slug'] ?? ''));
        if ($slug === '') { $this->_error('core.api.error.general'); return; }
        if (in_array($slug, self::PROTECTED, true)) { $this->_error('system.error.protected'); return; }

        $all = Tiger_Module_Discovery::all();
        if (!isset($all[$slug])) { $this->_error('system.error.unknown'); return; }
        $d = $all[$slug];

        // Only NON-CORE (app) modules are deletable — never a bundled platform module.
        if (($d['area'] ?? '') === 'core') { $this->_error('system.error.not_deletable'); return; }

        // Must be DEACTIVATED first — delete is the second, deliberate step after the non-destructive one.
        if ($this->_isModuleActive($slug, $d)) { $this->_error('system.error.delete_active'); return; }

        // The typed confirmation must match exactly (defense-in-depth; the UI enforces it too).
        if ((string) ($params['confirm'] ?? '') !== self::_deleteToken($d)) { $this->_error('system.error.delete_confirm'); return; }

        // Refuse while an ACTIVE module depends on this one (it would break). Deactivating/deleting it unblocks.
        $dependents = Tiger_Module_Dependency::dependents($slug);
        if ($dependents) {
            $names = [];
            foreach ($dependents as $s) { $names[] = self::_deleteToken($all[$s] ?? ['name' => $s]); }
            $this->_error('system.error.delete_dependents', ['dependents' => $names]);
            return;
        }

        try {
            // A deactivated theme keeps its asset symlink (inert, per THEMES.md §5a) — drop it on delete.
            if (($d['type'] ?? '') === 'theme') {
                $key  = (string) ($d['key'] ?? preg_replace('/^theme-/', '', $slug));
                $base = (string) ($d['asset_base'] ?? '');
                if ($base === '') { $base = '/_' . $key; }
                if (defined('PUBLIC_PATH')) { $link = PUBLIC_PATH . '/' . ltrim($base, '/'); if (is_link($link)) { @unlink($link); } }
            }
            Tiger_Module_Installer::purge($slug);
            $this->_success(['slug' => $slug, 'deleted' => true], 'system.module.deleted', '/system/modules');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** Whether a module is currently active — a theme by its `tiger.theme` config, else its registry flag. */
    private function _isModuleActive(string $slug, array $d): bool
    {
        if (($d['type'] ?? '') === 'theme') {
            $active = (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme');
            return $active === (string) ($d['key'] ?? $slug);
        }
        $row = (new Tiger_Model_Module())->bySlug($slug);
        return $row ? ((int) $row->active === 1) : true;
    }

    /** The delete-confirmation token a user must type: "<vendor>/<name>" (else just the name). */
    private static function _deleteToken(array $d): string
    {
        $vendor = trim((string) ($d['author'] ?? ''));
        $name   = trim((string) ($d['name'] ?? ''));
        return $vendor !== '' ? ($vendor . '/' . $name) : $name;
    }

    protected function _toggle(array $params, $on): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['slug'] ?? ''));
        if ($slug === '') { $this->_error('core.api.error.general'); return; }
        if (in_array($slug, self::PROTECTED, true)) { $this->_error('system.error.protected'); return; }

        $discovered = Tiger_Module_Discovery::all();
        if (!isset($discovered[$slug])) { $this->_error('system.error.unknown'); return; }

        try {
            $d = $discovered[$slug];

            // Mutual exclusion (module.json "conflict"): a module can't run alongside a declared
            // conflict — e.g. two cloud-SDK providers that each bundle their own copy of a shared HTTP
            // library. On activation, deactivate any ACTIVE conflict first, but only after an explicit
            // confirm so the admin knows exactly what's being turned off.
            if ($on) {
                $conflicts = Tiger_Module_Dependency::conflicts($slug);
                if ($conflicts) {
                    if ((string) ($params['confirm'] ?? '') !== '1') {
                        $this->_error('system.error.conflict', ['slug' => $slug, 'conflicts' => array_map(
                            fn ($c) => ['slug' => $c, 'name' => (string) ($discovered[$c]['name'] ?? $c)],
                            $conflicts
                        )]);
                        return;
                    }
                    $cm = new Tiger_Model_Module();
                    foreach ($conflicts as $c) {
                        $cm->setActive($c, false, ['name' => $discovered[$c]['name'] ?? $c, 'version' => $discovered[$c]['version'] ?? null]);
                        Tiger_Module_Installer::unpublishAssets($c);
                    }
                }
            }

            // Capability detection, not a declared type: activating anything that ships a
            // migrations/ folder applies its schema now (idempotent; no-op without one). This is
            // why `type` stays a mere label — a theme that owns tables migrates just like a module.
            if ($on) { Tiger_Module_Installer::migrateModule($slug); }

            // Themes activate differently (THEMES.md §5a): not the module.active flag, but the
            // `tiger.theme` config (one active per scope) + the asset-base symlink. No build/deploy.
            if (($d['type'] ?? 'module') === 'theme') {
                $this->_toggleTheme($slug, $d, $on);
                return;
            }

            $model = new Tiger_Model_Module();
            if ($on) {
                $model->setActive($slug, $on, ['name' => $d['name'], 'version' => $d['version']]);
                Tiger_Module_Installer::publishAssets($slug);   // symlink assets/ into public/_modules/<slug> if present
                // Convenience alert (non-blocking): required modules absent, inactive, or too old.
                $data = ['slug' => $slug, 'active' => $on, 'requires_missing' => Tiger_Module_Dependency::missingReport($slug)];
            } else {
                $data = ['slug' => $slug, 'active' => $on, 'dependents' => Tiger_Module_Dependency::dependents($slug)];
                $model->setActive($slug, $on, ['name' => $d['name'], 'version' => $d['version']]);
                Tiger_Module_Installer::unpublishAssets($slug);
            }
            $this->_success(
                $data,
                $on ? 'system.module.activated' : 'system.module.deactivated',
                '/system/modules'
            );
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Activate/deactivate a THEME (THEMES.md §5a). Activation writes `tiger.theme` (global scope —
     * one active theme per scope) and symlinks the theme's assets to its `assetBase`; deactivation
     * clears the config back to the platform base theme. No module.active flag, no build, no deploy.
     *
     * @param  string $slug the theme slug
     * @param  array  $d     its discovery row (type/asset_base/area)
     * @param  bool   $on    activate (true) or deactivate (false)
     * @return void
     */
    protected function _toggleTheme($slug, array $d, $on): void
    {
        $key = (string) ($d['key'] ?? preg_replace('/^theme-/', '', $slug));   // tiger.theme stores the KEY
        $cfg = new Tiger_Model_Config();
        if ($on) {
            $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme', $key);   // one active per scope
            $base = ((string) ($d['asset_base'] ?? '')) !== '' ? $d['asset_base'] : '/_' . $key;
            $this->_linkThemeAssets($slug, $base, (string) ($d['area'] ?? 'app'));
        } elseif ($cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme') === $key) {
            $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.theme', '');       // -> platform base theme
        }
        $this->_success(
            ['slug' => $slug, 'theme' => true, 'active' => (bool) $on],
            $on ? 'system.theme.activated' : 'system.theme.deactivated',
            '/system/modules'
        );
    }

    /** Symlink a theme's assets/ to public/<assetBase> (copy fallback where symlinks are blocked). */
    protected function _linkThemeAssets($slug, $base, $area): void
    {
        $root   = ($area === 'app' && defined('APPLICATION_PATH')) ? APPLICATION_PATH : TIGER_CORE_PATH;
        $assets = $root . '/modules/' . $slug . '/assets';
        if (!is_dir($assets)) { return; }
        $link = PUBLIC_PATH . '/' . ltrim((string) $base, '/');
        if (is_link($link)) { @unlink($link); }
        if (!@symlink($assets, $link) && !is_dir($link)) {
            Tiger_Module_Installer::publishAssets($slug);   // best-effort; symlink is the norm on cPanel
        }
    }

    /**
     * Search the Vendor Registry (empty + available=false when the registry isn't reachable). Each result
     * is annotated with its Add-screen `availability` (free|freemium|pass|paid) so the client renders the
     * right badge + button without re-interpreting the pricing block; the payload also carries this
     * install's TigerPASS `pass` state so PASS listings show "Get TigerPASS" vs a plain "Install".
     *
     * @param  array $params the /api payload (expects `q`; optional `sort`, `refresh`)
     * @return void
     */
    public function search(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $sort    = (string) ($params['sort'] ?? 'featured');
        $refresh = !empty($params['refresh']);   // "Refresh directory" — bypass the 3h cache

        $results = Tiger_Module_Registry::search((string) ($params['q'] ?? ''), $sort, $refresh);
        foreach ($results as &$m) { $m['availability'] = self::_availabilityOf($m); }
        unset($m);

        $this->_success([
            'results'   => $results,
            'available' => Tiger_Module_Registry::available(),   // reads the copy search() just refreshed
            'taxonomy'  => Tiger_Module_Registry::taxonomy(),    // data-driven type/category filters
            'sort'      => $sort,
            'pass'      => self::_passState(),                   // {has, state} — no network (cached verdict)
            'nag'       => self::_passNagState(),                // {show, disabled} — whether to show the promo banner
        ]);
    }

    /**
     * PASS status + nag preference for this user (the TigerPASS tab reads this directly, independent of a
     * registry search — so the tab works even when the registry is unreachable).
     *
     * @param  array $params the /api payload (no fields)
     * @return void
     */
    public function passInfo(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['pass' => self::_passState(), 'nag' => self::_passNagState()]);
    }

    /**
     * Snooze the TigerPASS promo banner for this user (NAG_SNOOZE_DAYS). Records `dismissed_at = now` in
     * the per-user `option` tier; the banner self-corrects — it reappears once the interval elapses, with
     * no reset logic. A brand-new subscription hides it anyway (see _passNagState).
     *
     * @param  array $params the /api payload (no fields)
     * @return void
     */
    public function snoozePassNag(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $uid = self::_currentUserId();
        if ($uid !== '') {
            (new Tiger_Model_Option())->set(Tiger_Model_Option::SCOPE_USER, $uid, self::NAG_DISMISSED_KEY, (string) time());
        }
        $this->_success(['nag' => self::_passNagState()], 'system.pass.nag_snoozed');
    }

    /**
     * Turn the TigerPASS promo banner off (or back on) for this user — the "Disable TigerPASS nag alert"
     * switch. A per-user preference in the `option` tier, so one admin's choice never blinds another.
     *
     * @param  array $params the /api payload (expects `disabled` = 1|0)
     * @return void
     */
    public function setPassNag(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $uid = self::_currentUserId();
        if ($uid === '') { $this->_error('core.api.error.general'); return; }
        $disabled = in_array((string) ($params['disabled'] ?? ''), ['1', 'true', 'on'], true);
        (new Tiger_Model_Option())->set(Tiger_Model_Option::SCOPE_USER, $uid, self::NAG_DISABLED_KEY, $disabled ? '1' : '0');
        $this->_success(['nag' => self::_passNagState()], 'system.pass.nag_updated');
    }

    // ---- marketplace sources (the "Connect a marketplace" surface) --------------------------------

    /** The config-tier subtree an admin-connected/overridden source lives under. */
    const SOURCE_KEY_PREFIX = 'tiger.modules.sources.';
    /** The per-source config fields the Connect UI writes/removes. */
    const SOURCE_FIELDS = ['kind', 'url', 'label', 'priority', 'enabled', 'removable', 'default'];

    /**
     * List every catalog source the Add screen aggregates — shipped defaults, module-contributed
     * (register()), and admin-connected — with provenance so the UI can show who owns each and what may be
     * removed vs. only disabled.
     *
     * @param  array $params the /api payload (no fields)
     * @return void
     */
    public function sources(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['sources' => self::_sourceList()]);
    }

    /**
     * Connect a new marketplace/directory: an admin pastes a label + an index URL (a `git-index` static
     * `index.json`, or a `live-api` endpoint). Stored in the config tier so it survives updates and the
     * admin owns it. The source just has to return `{modules, taxonomy}` — everything else is automatic.
     *
     * @param  array $params the /api payload (expects `label`, `url`; optional `kind`, `priority`)
     * @return void
     */
    public function connectSource(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $label = trim((string) ($params['label'] ?? ''));
        $url   = trim((string) ($params['url'] ?? ''));
        if ($label === '') { $this->_error('system.source.err_label'); return; }
        if (!preg_match('#^https?://#i', $url)) { $this->_error('system.source.err_url'); return; }

        $kind     = ($params['kind'] ?? '') === Tiger_Module_Source::KIND_GIT_INDEX
            ? Tiger_Module_Source::KIND_GIT_INDEX : Tiger_Module_Source::KIND_LIVE_API;
        $priority = max(0, (int) ($params['priority'] ?? 5));

        // A fresh id from the label; never collide with an existing source (default/module/connected).
        $existing = [];
        foreach (Tiger_Module_Registry::sources() as $s) { $existing[$s->id] = true; }
        $base = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower($label)), '-') ?: 'source';
        $id   = $base; $n = 2;
        while (isset($existing[$id])) { $id = $base . '-' . $n++; }

        try {
            $cfg = new Tiger_Model_Config();
            $write = [
                'kind' => $kind, 'url' => $url, 'label' => $label,
                'priority' => (string) $priority, 'enabled' => '1', 'removable' => '1', 'default' => '0',
            ];
            foreach ($write as $field => $value) {
                $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', self::SOURCE_KEY_PREFIX . $id . '.' . $field, $value);
            }
            $this->_success(['id' => $id, 'sources' => self::_sourceList(true)], 'system.source.connected');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Enable/disable or reorder any source (works for defaults + module sources too — it writes a config
     * override that wins over what shipped/registered). Only the fields present are changed.
     *
     * @param  array $params the /api payload (expects `id`; optional `enabled`, `priority`)
     * @return void
     */
    public function updateSource(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $id = self::_slugId((string) ($params['id'] ?? ''));
        if ($id === '' || !self::_sourceExists($id)) { $this->_error('system.source.err_unknown'); return; }

        try {
            $cfg = new Tiger_Model_Config();
            if (array_key_exists('enabled', $params)) {
                $on = in_array((string) $params['enabled'], ['1', 'true', 'on'], true);
                $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', self::SOURCE_KEY_PREFIX . $id . '.enabled', $on ? '1' : '0');
            }
            if (array_key_exists('priority', $params) && $params['priority'] !== '') {
                $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', self::SOURCE_KEY_PREFIX . $id . '.priority', (string) max(0, (int) $params['priority']));
            }
            $this->_success(['sources' => self::_sourceList(true)], 'system.source.updated');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove a CONNECTED marketplace (deletes its config subtree). A shipped default or a module-provided
     * source can't be removed here — disable it instead (or deactivate the owning module).
     *
     * @param  array $params the /api payload (expects `id`)
     * @return void
     */
    public function removeSource(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $id = self::_slugId((string) ($params['id'] ?? ''));
        $src = null;
        foreach (Tiger_Module_Registry::sources() as $s) { if ($s->id === $id) { $src = $s; break; } }
        if (!$src) { $this->_error('system.source.err_unknown'); return; }
        if ($src->origin !== 'connected') { $this->_error('system.source.err_not_removable'); return; }

        try {
            $cfg = new Tiger_Model_Config();
            foreach (self::SOURCE_FIELDS as $field) {
                $cfg->forget(Tiger_Model_Config::SCOPE_GLOBAL, '', self::SOURCE_KEY_PREFIX . $id . '.' . $field);
            }
            // Drop its per-source cache too, so a re-connect with the same id starts clean.
            $cache = defined('APPLICATION_ROOT') ? APPLICATION_ROOT . '/storage/cache/registry-' . $id . '.json' : '';
            if ($cache && is_file($cache)) { @unlink($cache); }
            $this->_success(['sources' => self::_sourceList(true)], 'system.source.removed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** The resolved source list as plain arrays (optionally refreshing the merged index so counts are live). */
    protected static function _sourceList(bool $refresh = false): array
    {
        if ($refresh) { Tiger_Module_Registry::available(true); }   // re-fetch so a just-connected source is live
        $out = [];
        foreach (Tiger_Module_Registry::sources() as $s) { $out[] = $s->toArray(); }
        return $out;
    }

    /** Whether a source id currently exists (any origin). */
    protected static function _sourceExists(string $id): bool
    {
        foreach (Tiger_Module_Registry::sources() as $s) { if ($s->id === $id) { return true; } }
        return false;
    }

    /** Reduce an id to the `[a-z0-9-]` shape a source id is allowed to take. */
    protected static function _slugId(string $id): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $id)), '-');
    }

    /**
     * Activate a TigerPASS subscription key on this install. Validates the key shape, remembers it under
     * the reserved pass slug, and verifies it against the pass authority. NAG-NEVER-DISABLE: activation is
     * refused ONLY on a definitive, reached-home `lapsed` verdict; an unreachable authority yields
     * `unknown` and is accepted (assume-current — an authority outage must never block a paying customer).
     * The heavy commerce (buy/renew) lives on webtigers.com; this endpoint only accepts the resulting key.
     *
     * @param  array $params the /api payload (expects `key`)
     * @return void
     */
    public function activatePass(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        // A TigerPASS key is the license key TigerLicense mints — a lowercase v7 UUID (not a branded
        // "TPASS-…" string). Normalize + shape-check as a UUID before any authority call.
        $key = strtolower(trim((string) ($params['key'] ?? '')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $key)) {
            $this->_error('system.pass.invalid_format'); return;
        }

        $authority = self::_passAuthority();
        if ($authority === '') { $this->_error('system.pass.not_configured'); return; }

        try {
            Tiger_License_Checker::remember(self::PASS_SLUG, [
                'key'        => $key,
                'authority'  => $authority,
                'vendor'     => self::PASS_VENDOR,
                'public_key' => self::_passPublicKey(),
            ]);
            $verdict = Tiger_License_Checker::verify(self::PASS_SLUG);   // the one deliberate network check
            if ($verdict['state'] === Tiger_License_Checker::LAPSED) {
                Tiger_License_Checker::forget(self::PASS_SLUG);          // don't keep a proven-lapsed key
                $this->_error('system.pass.lapsed'); return;
            }
            $this->_success(['pass' => self::_passState()], 'system.pass.activated');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove the stored TigerPASS key from this install (a manage/troubleshoot action; the subscription
     * itself is cancelled on webtigers.com — this only forgets the key locally).
     *
     * @param  array $params the /api payload (no fields)
     * @return void
     */
    public function deactivatePass(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        Tiger_License_Checker::forget(self::PASS_SLUG);
        $this->_success(['pass' => self::_passState()], 'system.pass.removed');
    }

    /**
     * This install's TigerPASS entitlement, read from the CACHED verdict (no network — safe to call on
     * every search). `has` is true when we hold a key and home has not definitively said `lapsed`
     * (`valid` = confirmed, `unknown` = couldn't reach home → assume-current, the fail-open rule).
     *
     * @return array{has:bool,state:string}
     */
    protected static function _passState(): array
    {
        $verdict = Tiger_License_Checker::status(self::PASS_SLUG);
        $state   = (string) $verdict['state'];
        $has     = !in_array($state, [Tiger_License_Checker::UNLICENSED, Tiger_License_Checker::LAPSED], true);
        return ['has' => $has, 'state' => $state];
    }

    /**
     * Whether to show the TigerPASS promo banner for the current user, and whether they've disabled it.
     * An active PASS suppresses it outright (nothing to promote); otherwise it's hidden while the user's
     * "disable" switch is on OR their dismiss is still inside the NAG_SNOOZE_DAYS window.
     *
     * @return array{show:bool,disabled:bool}
     */
    protected static function _passNagState(): array
    {
        if (self::_passState()['has']) { return ['show' => false, 'disabled' => false]; }   // subscribers never see it

        $uid = self::_currentUserId();
        if ($uid === '') { return ['show' => true, 'disabled' => false]; }

        $opt      = new Tiger_Model_Option();
        $disabled = (string) $opt->get(Tiger_Model_Option::SCOPE_USER, $uid, self::NAG_DISABLED_KEY) === '1';
        if ($disabled) { return ['show' => false, 'disabled' => true]; }

        $dismissedAt = (int) $opt->get(Tiger_Model_Option::SCOPE_USER, $uid, self::NAG_DISMISSED_KEY);
        $snoozed     = $dismissedAt > 0 && (time() - $dismissedAt) < (self::NAG_SNOOZE_DAYS * 86400);
        return ['show' => !$snoozed, 'disabled' => false];
    }

    /** The authenticated user's id (for per-user option scoping), or '' when there's no identity. */
    protected static function _currentUserId(): string
    {
        if (class_exists('Zend_Auth') && Zend_Auth::getInstance()->hasIdentity()) {
            $id = Zend_Auth::getInstance()->getIdentity();
            return (string) ($id->user_id ?? '');
        }
        return '';
    }

    /**
     * The Add-screen acquisition path for a listing: free | freemium | pass | paid. `pass` = a `licensed`
     * module covered by the TigerPASS subscription (marked by `pricing.plan = "tigerpass"` or a
     * `pricing.authority` matching this install's configured pass authority); any other `licensed` module
     * is a per-vendor paid one.
     *
     * @param  array $m a registry listing
     * @return string one of free|freemium|pass|paid
     */
    protected static function _availabilityOf(array $m): string
    {
        $p     = Tiger_Module_Pricing::of($m);
        $model = $p['model'];
        if ($model === Tiger_Module_Pricing::FREE)     { return 'free'; }
        if ($model === Tiger_Module_Pricing::FREEMIUM) { return 'freemium'; }
        if ($model === Tiger_Module_Pricing::LICENSED) {
            $pricing = (isset($m['pricing']) && is_array($m['pricing'])) ? $m['pricing'] : [];
            $plan    = strtolower((string) ($pricing['plan'] ?? ''));
            if ($plan === 'tigerpass' || self::_isPassAuthority((string) $p['authority'])) { return 'pass'; }
            return 'paid';   // a generic per-vendor licensed module (its own authority/key)
        }
        return 'paid';
    }

    /** The configured TigerPASS authority URL (`tiger.pass.authority`), or '' when unset. */
    protected static function _passAuthority(): string
    {
        return self::_passConfig('authority');
    }

    /** The pinned Ed25519 public key for the pass authority (`tiger.pass.public_key`), or '' (unsigned/dev). */
    protected static function _passPublicKey(): string
    {
        return self::_passConfig('public_key');
    }

    /** Whether an authority URL is this install's configured TigerPASS authority. */
    protected static function _isPassAuthority(string $authority): bool
    {
        $pa = self::_passAuthority();
        return $pa !== '' && rtrim($authority, '/') === rtrim($pa, '/');
    }

    /** Read a `tiger.pass.<key>` value from the resolved config, or '' when absent. */
    protected static function _passConfig(string $key): string
    {
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t    = $cfg ? $cfg->get('tiger') : null;
        $pass = $t ? $t->get('pass') : null;
        return $pass ? (string) $pass->get($key) : '';
    }

    /**
     * Preview a module before install: pull module.json + TIGER.md from the public repo and
     * return the manifest + rendered description. No side effects — the "review before you
     * install" step.
     *
     * @param  array $params the /api payload (expects `url`, optional `ref`)
     * @return void
     */
    public function inspect(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $r = Tiger_Module_Github::parseRepo((string) ($params['url'] ?? ''));
        if (!$r) { $this->_error('That doesn\'t look like a GitHub repository URL.'); return; }

        $ref = trim((string) ($params['ref'] ?? ''));
        if ($ref === '') { $ref = Tiger_Module_Github::latestRef($r['org'], $r['repo']); }
        if (!$ref) { $this->_error('Couldn\'t resolve a release — is the repo public?'); return; }

        // A code module ships module.json; a theme ships theme.json (slug = 'theme-' + key).
        $mj = Tiger_Module_Github::fetchRaw($r['org'], $r['repo'], $ref, 'module.json');
        if ($mj !== null) {
            $m = json_decode($mj, true);
            if (!is_array($m) || empty($m['slug'])) { $this->_error('That repo\'s module.json is invalid.'); return; }
        } else {
            $tj = Tiger_Module_Github::fetchRaw($r['org'], $r['repo'], $ref, 'theme.json');
            if ($tj === null) { $this->_error('No module.json or theme.json found (or the repo isn\'t public).'); return; }
            $t = json_decode($tj, true);
            if (!is_array($t) || empty($t['key'])) { $this->_error('That repo\'s theme.json is invalid.'); return; }
            $m = [
                'slug'        => 'theme-' . $t['key'],
                'name'        => $t['name'] ?? $t['key'],
                'version'     => $t['version'] ?? null,
                'author'      => $t['vendor'] ?? '',
                'license'     => $t['license'] ?? '',
                'description' => $t['description'] ?? '',
                'requires'    => $t['requires'] ?? new stdClass(),
                'type'        => 'theme',
            ];
        }

        $tigerMd  = Tiger_Module_Github::fetchRaw($r['org'], $r['repo'], $ref, 'TIGER.md');
        $descHtml = '';
        if ($tigerMd !== null) {
            try { $descHtml = $this->_scrub((new Tiger_Cms_Renderer())->renderBody($tigerMd, 'markdown')); } catch (Throwable $e) {}
        }

        // "Installed" = recorded by the installer OR simply present on disk (discovered) — the
        // latter covers a theme/module placed manually or activated without an installer row.
        $row        = (new Tiger_Model_Module())->bySlug($m['slug']);
        $discovered = Tiger_Module_Discovery::all();
        $present    = $row || isset($discovered[$m['slug']]);
        $instVer    = $row ? $row->version : ($discovered[$m['slug']]['version'] ?? null);
        $author     = $m['author'] ?? '';
        if (is_array($author)) { $author = $author['name'] ?? ''; }

        $this->_success([
            'repo'             => "https://github.com/{$r['org']}/{$r['repo']}",
            'ref'              => $ref,
            'manifest'         => [
                'slug'        => $m['slug'],
                'name'        => $m['name'] ?? $m['slug'],
                'version'     => $m['version'] ?? null,
                'author'      => (string) $author,
                'license'     => $m['license'] ?? '',
                'description' => $m['description'] ?? '',
                'requires'    => $m['requires'] ?? new stdClass(),
                'pricing'     => $m['pricing']['model'] ?? null,
                // Advisory: has this module been tested for the Tiger version running here? (never blocks)
                'compat'      => Tiger_Module_Compat::check($m),
            ],
            'description_html' => $descHtml,
            'installed'        => (bool) $present,
            'installed_version'=> $instVer,
        ]);
    }

    /** Strip active content from untrusted vendor markdown (the TIGER.md preview). */
    protected function _scrub($html)
    {
        $html = (string) $html;
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta|base)\b[^>]*>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>]*\2#i', '$1=$2#$2', $html);
        return $html;
    }

    /**
     * Install (or update, with force) a module from a public GitHub URL.
     *
     * @param  array $params the /api payload (expects `url`, optional `ref`, `force`)
     * @return void
     */
    public function install(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $url = (string) ($params['url'] ?? '');
        if (!Tiger_Module_Github::parseRepo($url)) { $this->_error('That doesn\'t look like a GitHub repository URL.'); return; }
        $ref = trim((string) ($params['ref'] ?? ''));

        try {
            $r = Tiger_Module_Installer::installFromUrl($url, $ref !== '' ? $ref : null, ['force' => !empty($params['force'])]);
            $this->_success($r, 'system.module.installed', '/system/modules');
        } catch (Throwable $e) {
            $this->_error('Install failed — ' . $e->getMessage());
        }
    }

    /**
     * Install a module from an uploaded .zip. Multipart POST to /api; the archive rides in
     * $_FILES['archive'] (not the JSON message body), so we read it directly. Same extract →
     * validate → place → migrate → publish → record path as a URL install (source=upload).
     *
     * @param  array $params the /api payload (optional `force` to update in place)
     * @return void
     */
    public function upload(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $f = $_FILES['archive'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->_error('Choose a module .zip to upload.'); return;
        }
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            $this->_error(($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE)
                ? 'That file is larger than the server allows.' : 'Upload failed — please try again.');
            return;
        }
        if (empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) { $this->_error('Invalid upload.'); return; }
        if (!preg_match('/\.zip$/i', (string) ($f['name'] ?? ''))) { $this->_error('Upload a .zip archive.'); return; }

        try {
            $r = Tiger_Module_Installer::installFromUpload($f['tmp_name'], ['force' => !empty($params['force'])]);
            $this->_success($r, 'system.module.installed', '/system/modules');
        } catch (Throwable $e) {
            $this->_error('Install failed — ' . $e->getMessage());
        }
    }
}
