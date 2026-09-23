<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Sites;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Org;
use Tiger_Model_SiteDomain;
use Zend_Registry;

/**
 * System_Service_Sites — the /api writer behind the multi-site host → org map (datatable/save/delete).
 *
 * Coverage: the ACL gate (admin+), the validate→write save (host normalized + unique install-wide, org
 * must exist), the soft-delete (host then resolves to null → single-site fallback), and the DataTables
 * envelope with the org name joined.
 */
#[CoversClass(System_Service_Sites::class)]
final class SitesServiceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // CSRF-immune API path (no session in CLI)
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new System_Service_Sites(['action' => $action] + $params))->getResponse();
    }

    private function makeOrg(string $name, string $slug): string
    {
        return (string) (new Tiger_Model_Org())->insert(['name' => $name, 'slug' => $slug, 'status' => 'active']);
    }

    #[Test]
    public function acl_gate_admin_only(): void
    {
        $this->loginAs('user');
        $this->assertSame(0, (int) $this->call('datatable')->result, 'plain user denied');

        $this->loginAs('admin');
        $this->assertSame(1, (int) $this->call('datatable', ['draw' => 1])->result, 'admin allowed');
    }

    #[Test]
    public function save_maps_a_host_to_an_org_normalized_and_unique(): void
    {
        $this->loginAs('admin');
        $org = $this->makeOrg('Site B Co', 'site-b-' . substr(md5(uniqid()), 0, 6));

        // Mixed case + surrounding space normalize to the stored host.
        $res = $this->call('save', ['domain' => '  ShopB.Example  ', 'org_id' => $org]);
        $this->assertSame(1, (int) $res->result, 'admin can map a host');

        $this->assertSame($org, (new Tiger_Model_SiteDomain())->orgForHost('shopb.example'), 'resolves normalized');

        // Same host again -> refused (unique install-wide).
        $dup = $this->call('save', ['domain' => 'shopb.example', 'org_id' => $org]);
        $this->assertSame(0, (int) $dup->result);
        $this->assertStringContainsString('host_taken', json_encode($dup->messages));
    }

    #[Test]
    public function save_rejects_a_nonexistent_org(): void
    {
        $this->loginAs('admin');
        // A syntactically valid host, but the org isn't in the form's option list -> form invalid.
        $res = $this->call('save', ['domain' => 'ghost.example', 'org_id' => 'no-such-org-id']);
        $this->assertSame(0, (int) $res->result);
        $this->assertNull((new Tiger_Model_SiteDomain())->orgForHost('ghost.example'));
    }

    #[Test]
    public function save_rejects_a_malformed_host(): void
    {
        $this->loginAs('admin');
        $org = $this->makeOrg('Bad Host Co', 'bad-host-' . substr(md5(uniqid()), 0, 6));
        $res = $this->call('save', ['domain' => 'not a hostname', 'org_id' => $org]);
        $this->assertSame(0, (int) $res->result, 'malformed host is refused by the form');
    }

    #[Test]
    public function delete_removes_the_mapping_and_host_falls_back(): void
    {
        $this->loginAs('admin');
        $org = $this->makeOrg('Del Co', 'del-co-' . substr(md5(uniqid()), 0, 6));
        $this->call('save', ['domain' => 'del.example', 'org_id' => $org]);
        $row = (new Tiger_Model_SiteDomain())->findByHost('del.example');
        $this->assertNotNull($row);

        $res = $this->call('delete', ['site_domain_id' => $row->site_domain_id]);
        $this->assertSame(1, (int) $res->result);
        $this->assertNull((new Tiger_Model_SiteDomain())->orgForHost('del.example'), 'soft-deleted -> single-site fallback');
    }

    #[Test]
    public function datatable_joins_the_org_name(): void
    {
        $this->loginAs('admin');
        $org = $this->makeOrg('Grid Site Co', 'grid-site-' . substr(md5(uniqid()), 0, 6));
        $this->call('save', ['domain' => 'grid.example', 'org_id' => $org]);

        $res = $this->call('datatable', ['draw' => 2, 'start' => 0, 'length' => 25, 'search' => 'grid.example']);
        $this->assertSame(1, (int) $res->data['recordsFiltered']);
        $row = $res->data['data'][0];
        $this->assertSame('grid.example', $row['domain']);
        $this->assertSame('Grid Site Co', $row['org_name']);
        $this->assertTrue($row['can_delete']);
    }
}
