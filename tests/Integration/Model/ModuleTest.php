<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Module;

/**
 * Tiger_Model_Module — the module lifecycle registry and, more importantly, THE boot gate.
 *
 * `inactiveSlugs()` is what Tiger_Application_Resource_Modules calls to strip modules from the
 * controller-directory map before dispatch. Its contract is now AREA-AWARE (and resolved only among
 * DISCOVERED modules): a CORE module is skipped only if it has an `active=0` row (opt-out); an APP
 * module is skipped UNLESS it has an `active=1` row (opt-in). The raw row halves it composes are
 * `deactivatedSlugs()` (active=0) and `activeSlugs()` (active=1). The lifecycle writers (`setActive`
 * upsert, `install`, `uninstall`) put rows into / take them out of that gate.
 */
#[CoversClass(Tiger_Model_Module::class)]
final class ModuleTest extends IntegrationTestCase
{
    private Tiger_Model_Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        $this->module = new Tiger_Model_Module();
        $this->resetSkipCache();
    }

    /** The area-aware skip set memoizes per request in a static; clear it so each read sees fresh rows. */
    private function resetSkipCache(): void
    {
        $p = new \ReflectionProperty(Tiger_Model_Module::class, '_skipCache');
        $p->setValue(null, null);   // PHP 8.1+: no setAccessible() needed
    }

    /** A model whose discovered-module layout (slug => 'core'|'app') is injected, so the area-aware
     *  gate can be exercised without depending on the real modules on disk. */
    private function modelSeeing(array $areasBySlug): Tiger_Model_Module
    {
        return new class ($areasBySlug) extends Tiger_Model_Module {
            /** @var array<string,string> slug => area */
            public array $fakeAreas;
            public function __construct(array $areas)
            {
                $this->fakeAreas = $areas;
                parent::__construct();
            }
            protected function _discovered()
            {
                $out = [];
                foreach ($this->fakeAreas as $slug => $area) { $out[$slug] = ['area' => $area]; }
                return $out;
            }
        };
    }

    private function skipOf(Tiger_Model_Module $m): array
    {
        $this->resetSkipCache();
        $s = $m->inactiveSlugs();
        sort($s);
        return $s;
    }

    #[Test]
    public function inactive_slugs_is_area_aware_core_opt_out_app_opt_in(): void
    {
        // Six modules across the two areas × three row states.
        $m = $this->modelSeeing([
            'coremod'  => 'core',   // core, no row   → ACTIVE  (opt-out)
            'coredead' => 'core',   // core, active=0 → skipped
            'coreon'   => 'core',   // core, active=1 → ACTIVE
            'appmod'   => 'app',    // app,  no row   → skipped (opt-in)
            'appon'    => 'app',    // app,  active=1 → ACTIVE
            'appdead'  => 'app',    // app,  active=0 → skipped
        ]);
        $mk = fn (string $slug, int $active) =>
            $m->insert(['slug' => $slug, 'active' => $active, 'source' => Tiger_Model_Module::SOURCE_DISCOVERED]);
        $mk('coredead', 0);
        $mk('coreon', 1);
        $mk('appon', 1);
        $mk('appdead', 0);
        // coremod + appmod: no row

        $skip = $this->skipOf($m);

        // CORE = opt-out: live unless explicitly deactivated.
        $this->assertNotContains('coremod', $skip, 'core module with no row is active by default');
        $this->assertNotContains('coreon', $skip);
        $this->assertContains('coredead', $skip, 'core module with an active=0 row is skipped');

        // APP = opt-in: inert unless explicitly activated.
        $this->assertContains('appmod', $skip, 'app module with no row is inert (must be activated)');
        $this->assertContains('appdead', $skip);
        $this->assertNotContains('appon', $skip, 'app module with an active=1 row is live');
    }

    #[Test]
    public function raw_row_halves_return_exactly_their_active_flag(): void
    {
        // deactivatedSlugs()/activeSlugs() are pure row queries (no discovery), the inputs the gate composes.
        $this->module->insert(['slug' => 'blog',  'active' => 1, 'source' => Tiger_Model_Module::SOURCE_DISCOVERED]);
        $this->module->insert(['slug' => 'forum', 'active' => 0, 'source' => Tiger_Model_Module::SOURCE_DISCOVERED]);
        $this->module->insert(['slug' => 'wiki',  'active' => 0, 'source' => Tiger_Model_Module::SOURCE_DISCOVERED]);

        // contains/not-contains (the shared test DB may hold other module rows from sibling tests).
        $deact = $this->module->deactivatedSlugs();
        $this->assertContains('forum', $deact);
        $this->assertContains('wiki', $deact);
        $this->assertNotContains('blog', $deact, 'an active=1 row is never in deactivatedSlugs');
        $active = $this->module->activeSlugs();
        $this->assertContains('blog', $active);
        $this->assertNotContains('forum', $active, 'an active=0 row is never in activeSlugs');
        $this->assertNotContains('wiki', $active);
    }

    #[Test]
    public function set_active_creates_a_discovered_row_the_first_time_then_upserts(): void
    {
        // First toggle of a never-seen module MINTS a row (discovered provenance) …
        $id = $this->module->setActive('gallery', false);
        $row = $this->module->bySlug('gallery');
        $this->assertNotNull($row, 'setActive creates a row for a discovered module');
        $this->assertSame($id, $row->module_id, 'setActive returns the row id it created');
        $this->assertSame(0, (int) $row->active);
        $this->assertSame('inactive', $row->status, 'status tracks the active flag');
        $this->assertSame(Tiger_Model_Module::SOURCE_DISCOVERED, $row->source);
        $this->assertContains('gallery', $this->module->deactivatedSlugs(), 'a freshly-deactivated module has an active=0 row');

        // … a second toggle UPSERTS the SAME row (no duplicate) and flips the flags back.
        $id2 = $this->module->setActive('gallery', true);
        $this->assertSame($id, $id2, 'setActive upserts — same module_id, no new row');
        $row2 = $this->module->bySlug('gallery');
        $this->assertSame(1, (int) $row2->active);
        $this->assertSame('active', $row2->status);
        $this->assertNotContains('gallery', $this->module->deactivatedSlugs(), 're-activated → no longer an active=0 row');
        $this->assertContains('gallery', $this->module->activeSlugs());
        $this->assertCount(1, $this->module->fetchAll($this->module->select()->where('slug = ?', 'gallery')), 'exactly one row survives the upsert');
    }

    #[Test]
    public function install_forces_active_and_records_provenance(): void
    {
        $id = $this->module->install('shop', [
            'name'       => 'Tiger Shop',
            'version'    => '0.1.0-beta',
            'repository' => 'WebTigers/TigerShop',
            'ref'        => 'v0.1.0-beta',
            'source'     => Tiger_Model_Module::SOURCE_URL,
        ]);

        $row = $this->module->bySlug('shop');
        $this->assertNotNull($row);
        $this->assertSame($id, $row->module_id);
        $this->assertSame(1, (int) $row->active, 'install always forces active=1');
        $this->assertSame('active', $row->status);
        $this->assertSame('Tiger Shop', $row->name);
        $this->assertSame('0.1.0-beta', $row->version);
        $this->assertSame('WebTigers/TigerShop', $row->repository);
        $this->assertSame('v0.1.0-beta', $row->ref);
        $this->assertSame(Tiger_Model_Module::SOURCE_URL, $row->source);
        $this->assertContains('shop', $this->module->activeSlugs(), 'an installed module has an active=1 row');
    }

    #[Test]
    public function install_reactivates_and_updates_a_previously_deactivated_module(): void
    {
        // A module the admin had turned off, then (re)installs, must come back ACTIVE — install
        // forces active=1 on the existing row rather than minting a duplicate.
        $first = $this->module->setActive('forum', false);
        $this->assertContains('forum', $this->module->deactivatedSlugs());

        $second = $this->module->install('forum', ['name' => 'Forum', 'version' => '1.2.3']);
        $this->assertSame($first, $second, 'install upserts the same row');
        $row = $this->module->bySlug('forum');
        $this->assertSame(1, (int) $row->active, 'install re-activates a deactivated module');
        $this->assertSame('1.2.3', $row->version, 'provenance is refreshed on the existing row');
        $this->assertContains('forum', $this->module->activeSlugs());
    }

    #[Test]
    public function uninstall_hard_deletes_the_row(): void
    {
        // The module table is NOT soft-deleted: uninstall removes the row entirely.
        $this->module->install('temp', ['name' => 'Temp']);
        $this->assertNotNull($this->module->bySlug('temp'));

        $deleted = $this->module->uninstall('temp');
        $this->assertSame(1, $deleted, 'uninstall reports one row removed');
        $this->assertNull($this->module->bySlug('temp'), 'the row is hard-deleted, not soft-deleted');
    }

    #[Test]
    public function uninstalling_a_deactivated_module_removes_its_row(): void
    {
        // Hard-delete makes an active=0 row vanish rather than lingering.
        $this->module->setActive('doomed', false);
        $this->assertContains('doomed', $this->module->deactivatedSlugs());

        $this->module->uninstall('doomed');
        $this->assertNotContains('doomed', $this->module->deactivatedSlugs(), 'an uninstalled module leaves no row');
    }
}
