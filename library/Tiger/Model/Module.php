<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_Module — the module lifecycle registry (see migration 0023).
 *
 * The source of truth for which modules are ACTIVE, via an AREA-AWARE gate: CORE modules
 * (`tiger-core/modules`) are active UNLESS deactivated (opt-out — a base install needs no rows);
 * APP modules (`application/modules`) are inert UNLESS activated (opt-in — dropping one in does
 * nothing until Activate). `inactiveSlugs()` resolves that skip set; the boot-time gate
 * (Tiger_Application_Resource_Modules) + every module-config consumer (routes/nav/acl/…) call it to
 * strip non-active modules. `deactivatedSlugs()`/`activeSlugs()` are the raw row halves it composes.
 * Everything else feeds the Modules admin + the installer.
 *
 * @api
 */
class Tiger_Model_Module extends Tiger_Model_Table
{
    protected $_name    = 'module';
    protected $_primary = 'module_id';

    const SOURCE_REGISTRY   = 'registry';
    const SOURCE_URL        = 'url';
    const SOURCE_UPLOAD     = 'upload';
    const SOURCE_DISCOVERED = 'discovered';

    /** Per-request memo of the resolved skip set (inactiveSlugs) — reset on any activation change. */
    protected static $_skipCache;

    /**
     * The slugs to SKIP at boot — the AREA-AWARE "not active" gate every module-config consumer uses
     * (the bootstrap controller-map strip, routes, nav, acl, fields, schedule, …). Two rules, because
     * core and app modules opt in oppositely:
     *
     *   - **CORE** modules (`tiger-core/modules`, discovery area 'core'): active UNLESS explicitly
     *     deactivated (a row with `active=0`) — opt-OUT, so a base install needs no rows at all.
     *   - **APP** modules (`application/modules`, area 'app'): inactive UNLESS explicitly activated
     *     (a row with `active=1`) — opt-IN, so dropping a module in is inert until Activate writes the row.
     *
     * A slug in this set neither bootstraps its `Bootstrap.php` nor answers a URL nor contributes any
     * `configs/*.ini`. Result is memoized per request (reset by setActive/install). Fail-soft: on any
     * error (no DB / no `module` table on a fresh install) returns [] — every module stays active,
     * never a worse state than stock ZF1.
     *
     * @return array<int,string> the slugs of modules to skip
     */
    public function inactiveSlugs()
    {
        if (self::$_skipCache !== null) { return self::$_skipCache; }
        try {
            $deactivated = array_flip(array_map('strval', $this->deactivatedSlugs()));  // active = 0 rows
            $activated   = array_flip(array_map('strval', $this->activeSlugs()));        // active = 1 rows
            $discovered  = $this->_discovered();
        } catch (Throwable $e) {
            return [];   // broken/absent DB → skip nothing (all active), the safe fresh-boot default
        }
        $skip = [];
        foreach ($discovered as $slug => $d) {
            $slug = (string) $slug;
            if ($slug === '') { continue; }
            if (($d['area'] ?? 'app') === 'core') {
                if (isset($deactivated[$slug])) { $skip[] = $slug; }   // core: opt-out (skip only if deactivated)
            } else {
                if (!isset($activated[$slug]))  { $skip[] = $slug; }   // app:  opt-in  (skip unless activated)
            }
        }
        return self::$_skipCache = $skip;
    }

    /**
     * The discovered modules keyed by slug (each carries its `area` = 'core'|'app'). A seam so a test
     * can inject a known module layout without the real filesystem; production reads Tiger_Module_Discovery.
     *
     * @return array<string,array>
     */
    protected function _discovered()
    {
        return class_exists('Tiger_Module_Discovery') ? Tiger_Module_Discovery::all() : [];
    }

    /**
     * Slugs with an explicit `active = 0` row (deactivated). The raw query — the deactivation half of
     * the gate, and the input the admin uses to show "deactivated" state.
     *
     * @return array<int,string>
     */
    public function deactivatedSlugs()
    {
        $db = $this->getAdapter();
        return $db->fetchCol($db->select()->from($this->_name, ['slug'])->where('active = 0'));
    }

    /**
     * Slugs with an explicit `active = 1` row (activated). The opt-in half of the gate (app modules
     * are inert until one of these rows exists).
     *
     * @return array<int,string>
     */
    public function activeSlugs()
    {
        $db = $this->getAdapter();
        return $db->fetchCol($db->select()->from($this->_name, ['slug'])->where('active = 1'));
    }

    /**
     * One row by slug, or null.
     *
     * @param string $slug the module slug
     * @return Zend_Db_Table_Row_Abstract|null the module row, or null if none
     */
    public function bySlug($slug)
    {
        return $this->fetchRow($this->select()->where('slug = ?', (string) $slug));
    }

    /**
     * All rows keyed by slug — for overlaying state onto discovered modules.
     *
     * @return array<string,Zend_Db_Table_Row_Abstract> module rows keyed by slug
     */
    public function bySlugMap()
    {
        $out = [];
        foreach ($this->fetchAll($this->select()) as $r) {
            $out[$r->slug] = $r;
        }
        return $out;
    }

    /**
     * Set a module's active state (upsert). A discovered module gets a row the first time it's
     * toggled; an installer-managed row keeps its provenance. Returns the row id.
     *
     * @param string $slug   the module slug
     * @param bool   $active the desired active state
     * @param array  $meta   optional provenance for a new row (source, name, version)
     * @return string the module_id
     */
    public function setActive($slug, $active, array $meta = [])
    {
        self::$_skipCache = null;   // an activation change invalidates the memoized skip set
        $row  = $this->bySlug($slug);
        $data = [
            'active' => $active ? 1 : 0,
            'status' => $active ? 'active' : 'inactive',
        ];
        if ($row) {
            $this->update($data, $this->getAdapter()->quoteInto('slug = ?', (string) $slug));
            return $row->module_id;
        }
        $data['slug']    = (string) $slug;
        $data['source']  = $meta['source']  ?? self::SOURCE_DISCOVERED;
        $data['name']    = $meta['name']    ?? null;
        $data['version'] = $meta['version'] ?? null;
        return $this->insert($data);
    }

    /**
     * Record an installed (or updated) module with full provenance + active. Returns the id.
     *
     * @param string $slug the module slug
     * @param array  $meta provenance (name, version, repository, ref, source)
     * @return string the module_id
     */
    public function install($slug, array $meta)
    {
        self::$_skipCache = null;   // an install activates the module → invalidate the memoized skip set
        $data = [
            'name'       => $meta['name']       ?? null,
            'version'    => $meta['version']    ?? null,
            'repository' => $meta['repository'] ?? null,
            'ref'        => $meta['ref']        ?? null,
            'source'     => $meta['source']     ?? self::SOURCE_URL,
            // Taxonomy captured from the source (listing/manifest) so it's retained after install — the
            // same type/category Add Module showed (migration 0042). NULL when the source declared none.
            'type'       => $meta['type']       ?? null,
            'category'   => $meta['category']   ?? null,
            'active'     => 1,
            'status'     => 'active',
        ];
        $row = $this->bySlug($slug);
        if ($row) {
            $this->update($data, $this->getAdapter()->quoteInto('slug = ?', (string) $slug));
            return $row->module_id;
        }
        $data['slug'] = (string) $slug;
        return $this->insert($data);
    }

    /**
     * Drop a module's registry row (uninstall).
     *
     * @param string $slug the module slug
     * @return int the number of rows deleted
     */
    public function uninstall($slug)
    {
        return $this->delete($this->getAdapter()->quoteInto('slug = ?', (string) $slug));
    }
}
