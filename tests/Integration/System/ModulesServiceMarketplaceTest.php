<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Modules;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Module_Registry;
use Zend_Registry;

// System_Service_Modules resolves via the harness module autoloader (tests/bootstrap.php).

/**
 * System_Service_Modules — the Add-screen /api, superadmin+ per modules/system/configs/acl.ini. These
 * characterize the surfaces added for the marketplace federation + the TigerPASS funnel:
 *   - the "Connect a marketplace" CRUD (sources / connectSource / updateSource / removeSource), written
 *     to the config tier and read back LIVE (a just-connected source is visible in the same request);
 *   - the per-user TigerPASS nag preference (passInfo / snoozePassNag / setPassNag);
 *   - listing `availability` normalization (free|freemium|pass|paid) + the pass/nag blocks in `search`;
 *   - the activatePass input guards.
 * The genuine license-authority verify (network + signed reply) is live territory, left to the box.
 */
#[CoversClass(System_Service_Modules::class)]
final class ModulesServiceMarketplaceTest extends IntegrationTestCase
{
    private string $cacheDir = '';
    private array $wrote = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = rtrim(APPLICATION_ROOT, '/') . '/storage/cache';
        @mkdir($this->cacheDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->wrote as $f) { @unlink($f); }
        parent::tearDown();
    }

    /** Construct + dispatch the service on its message (routes on `action`), returning the response object. */
    private function dispatch(array $msg): object
    {
        return (new System_Service_Modules($msg))->getResponse();
    }

    /** Prime the Directory source's file cache so search() resolves with no network. */
    private function primeIndex(array $index): void
    {
        $f = $this->cacheDir . '/registry-index.json';
        file_put_contents($f, json_encode($index));
        $this->wrote[] = $f;
    }

    private function sourceById(array $sources, string $id): ?array
    {
        foreach ($sources as $s) { if (($s['id'] ?? '') === $id) { return $s; } }
        return null;
    }

    // ---- ACL -------------------------------------------------------------------------------------

    #[Test]
    public function the_shipped_acl_gates_the_service_to_superadmin(): void
    {
        $this->loginAs('superadmin');
        $acl = Zend_Registry::get('Zend_Acl');
        $this->assertTrue($acl->has('System_Service_Modules'), 'the acl.ini resource loaded');
        $this->assertTrue($acl->isAllowed('superadmin', 'System_Service_Modules'));
        $this->assertFalse($acl->isAllowed('admin', 'System_Service_Modules'), 'module management is superadmin+');
        $this->assertFalse($acl->isAllowed('guest', 'System_Service_Modules'));
    }

    #[Test]
    public function a_non_admin_is_denied_connecting_a_source(): void
    {
        $this->loginAs('user');
        $res = $this->dispatch(['action' => 'connectSource', 'label' => 'X', 'url' => 'https://x.test/i.json']);
        $this->assertSame(0, $res->result, 'a plain user cannot add a marketplace');
    }

    // ---- Connect-a-marketplace CRUD --------------------------------------------------------------

    #[Test]
    public function sources_lists_the_shipped_defaults(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatch(['action' => 'sources']);
        $this->assertSame(1, $res->result);
        $ids = array_column($res->data['sources'], 'id');
        $this->assertContains(Tiger_Module_Registry::SOURCE_DIRECTORY, $ids, 'the git Directory is always present');
        $this->assertContains(Tiger_Module_Registry::SOURCE_MARKETPLACE, $ids, 'the WebTigers marketplace slot is present');
    }

    #[Test]
    public function connect_then_update_then_remove_a_marketplace_round_trips(): void
    {
        $this->loginAs('superadmin');

        // connect
        $res = $this->dispatch(['action' => 'connectSource', 'label' => 'Acme Market', 'url' => 'https://acme.test/index.json', 'kind' => 'live-api']);
        $this->assertSame(1, $res->result, 'connect succeeds');
        $id = $res->data['id'];
        $row = $this->sourceById($res->data['sources'], $id);
        $this->assertNotNull($row, 'the connected source is visible in the SAME request (live config read)');
        $this->assertSame('connected', $row['origin']);
        $this->assertTrue((bool) $row['removable']);
        $this->assertSame('live-api', $row['kind']);

        // update priority + disable
        $res = $this->dispatch(['action' => 'updateSource', 'id' => $id, 'priority' => 3, 'enabled' => 0]);
        $this->assertSame(1, $res->result);
        $row = $this->sourceById($res->data['sources'], $id);
        $this->assertSame(3, (int) $row['priority']);
        $this->assertFalse((bool) $row['enabled'], 'the source can be disabled without removing it');

        // remove
        $res = $this->dispatch(['action' => 'removeSource', 'id' => $id]);
        $this->assertSame(1, $res->result);
        $this->assertNull($this->sourceById($res->data['sources'], $id), 'a removed marketplace is gone');
    }

    #[Test]
    public function a_shipped_default_source_cannot_be_removed_only_disabled(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatch(['action' => 'removeSource', 'id' => Tiger_Module_Registry::SOURCE_DIRECTORY]);
        $this->assertSame(0, $res->result, 'a built-in source is refused removal');
    }

    #[Test]
    public function connect_rejects_a_missing_label_or_a_non_http_url(): void
    {
        $this->loginAs('superadmin');
        $this->assertSame(0, $this->dispatch(['action' => 'connectSource', 'label' => '', 'url' => 'https://x.test/i.json'])->result);
        $this->assertSame(0, $this->dispatch(['action' => 'connectSource', 'label' => 'X', 'url' => 'not-a-url'])->result);
    }

    // ---- the TigerPASS nag preference (per-user) -------------------------------------------------

    #[Test]
    public function the_nag_shows_by_default_and_the_disable_switch_toggles_it(): void
    {
        $this->loginAs('superadmin');

        $res = $this->dispatch(['action' => 'passInfo']);
        $this->assertSame(1, $res->result);
        $this->assertFalse($res->data['pass']['has'], 'no PASS key on a fresh install');
        $this->assertTrue($res->data['nag']['show'], 'the promo banner shows by default');

        // the "Disable TigerPASS nag alert" switch hides it...
        $res = $this->dispatch(['action' => 'setPassNag', 'disabled' => 1]);
        $this->assertFalse($res->data['nag']['show']);
        $this->assertTrue($res->data['nag']['disabled']);

        // ...and turning it back on restores it (no snooze in this flow).
        $res = $this->dispatch(['action' => 'setPassNag', 'disabled' => 0]);
        $this->assertTrue($res->data['nag']['show']);
        $this->assertFalse($res->data['nag']['disabled']);
    }

    #[Test]
    public function snoozing_hides_the_nag_without_disabling_it(): void
    {
        $this->loginAs('superadmin');
        // Snooze is a time-boxed dismiss (not the on/off switch): it hides the banner while `disabled`
        // stays false, and self-corrects once the window elapses.
        $res = $this->dispatch(['action' => 'snoozePassNag']);
        $this->assertSame(1, $res->result);
        $this->assertFalse($res->data['nag']['show']);
        $this->assertFalse($res->data['nag']['disabled']);
    }

    // ---- availability normalization + the search payload -----------------------------------------

    #[Test]
    public function search_tags_each_listing_with_its_availability_and_carries_pass_and_nag(): void
    {
        $this->loginAs('superadmin');
        $this->primeIndex(['modules' => [
            ['module' => 'Free',  'slug' => 'free-mod',  'repository' => 'https://github.com/x/free',  'pricing' => ['model' => 'free']],
            ['module' => 'Freem', 'slug' => 'freem-mod', 'repository' => 'https://github.com/x/freem', 'pricing' => ['model' => 'freemium']],
            ['module' => 'Pass',  'slug' => 'pass-mod',  'repository' => 'https://github.com/x/pass',  'pricing' => ['model' => 'licensed', 'plan' => 'tigerpass', 'authority' => 'https://a.test', 'vendor' => 'W/V']],
            ['module' => 'PaidL', 'slug' => 'paidl-mod', 'repository' => 'https://github.com/x/paidl', 'pricing' => ['model' => 'licensed', 'authority' => 'https://other.test', 'vendor' => 'O/V']],
            ['module' => 'Paid',  'slug' => 'paid-mod',  'repository' => 'https://github.com/x/paid',  'pricing' => ['model' => 'paid']],
        ]]);

        $res = $this->dispatch(['action' => 'search', 'q' => '']);
        $this->assertSame(1, $res->result);

        $avail = [];
        foreach ($res->data['results'] as $m) { $avail[$m['slug']] = $m['availability']; }
        $this->assertSame('free', $avail['free-mod']);
        $this->assertSame('freemium', $avail['freem-mod']);
        $this->assertSame('pass', $avail['pass-mod'], 'a licensed module on the tigerpass plan is a PASS listing');
        $this->assertSame('paid', $avail['paidl-mod'], 'a licensed module WITHOUT the tigerpass plan is a per-vendor paid one');
        $this->assertSame('paid', $avail['paid-mod']);

        $this->assertArrayHasKey('pass', $res->data);
        $this->assertArrayHasKey('nag', $res->data);
        $this->assertFalse($res->data['pass']['has']);
    }

    // ---- activatePass guards ---------------------------------------------------------------------

    #[Test]
    public function activate_pass_rejects_a_malformed_key(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatch(['action' => 'activatePass', 'key' => 'not-a-key']);
        $this->assertSame(0, $res->result, 'a key that isn\'t a UUID is refused at the format gate, before any authority call');
    }

    #[Test]
    public function activate_pass_accepts_a_uuid_shaped_key_at_the_format_gate(): void
    {
        // The real TigerPASS key is the v7 UUID TigerLicense mints — it must CLEAR the format gate (not be
        // rejected like the old "TPASS-…" shape). With no pass authority configured here, a well-formed key
        // then fails for a DIFFERENT reason (not configured), so the two error messages must differ.
        $this->loginAs('superadmin');
        $malformed = $this->dispatch(['action' => 'activatePass', 'key' => 'not-a-key']);
        $uuid      = $this->dispatch(['action' => 'activatePass', 'key' => '019f88b1-7ce7-7467-95b3-db7a7433342c']);
        $this->assertSame(0, $malformed->result);
        $this->assertSame(0, $uuid->result);
        $this->assertNotSame(
            (string) ($malformed->messages[0]->message ?? 'a'),
            (string) ($uuid->messages[0]->message ?? 'b'),
            'a UUID passes the format check (fails later as not-configured); a malformed key fails AT the format check'
        );
    }
}
