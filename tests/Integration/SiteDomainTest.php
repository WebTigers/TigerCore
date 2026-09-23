<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_SiteDomain;
use Tiger_Uuid;

/**
 * Tiger_Model_SiteDomain — the host → org map behind multi-site (one install, many sites).
 *
 * Proves the load-bearing lookup the bootstrap depends on: an active mapping resolves a request host
 * (normalized — lowercased, port stripped) to its org, while an unmapped, deleted, or non-active host
 * resolves to null so the install falls back to single-site behavior.
 */
#[CoversClass(Tiger_Model_SiteDomain::class)]
final class SiteDomainTest extends IntegrationTestCase
{
    private function seed(string $domain, string $orgId, string $status = 'active', int $deleted = 0): string
    {
        $id = Tiger_Uuid::v7();
        $this->db->insert('site_domain', [
            'site_domain_id' => $id,
            'domain'         => $domain,
            'org_id'         => $orgId,
            'status'         => $status,
            'deleted'        => $deleted,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
        return $id;
    }

    #[Test]
    public function migration_creates_the_table(): void
    {
        $this->assertTrue($this->tableExists('site_domain'));
    }

    #[Test]
    public function normalize_host_lowercases_and_strips_port(): void
    {
        $this->assertSame('example.com', Tiger_Model_SiteDomain::normalizeHost('Example.com'));
        $this->assertSame('example.com', Tiger_Model_SiteDomain::normalizeHost('EXAMPLE.COM:8443'));
        $this->assertSame('shop.example.com', Tiger_Model_SiteDomain::normalizeHost(' shop.example.com '));
        $this->assertSame('', Tiger_Model_SiteDomain::normalizeHost(''));
    }

    #[Test]
    public function resolves_an_active_host_to_its_org(): void
    {
        $org = Tiger_Uuid::v7();
        $this->seed('siteb.example', $org);

        $m = new Tiger_Model_SiteDomain();
        $this->assertSame($org, $m->orgForHost('siteb.example'));
        // normalization: uppercase + port still match the stored lowercase host
        $this->assertSame($org, $m->orgForHost('SiteB.Example:443'));
    }

    #[Test]
    public function unmapped_host_resolves_to_null(): void
    {
        $this->assertNull((new Tiger_Model_SiteDomain())->orgForHost('nothing-here.example'));
        $this->assertNull((new Tiger_Model_SiteDomain())->orgForHost(''));
    }

    #[Test]
    public function deleted_or_inactive_mapping_does_not_resolve(): void
    {
        $org = Tiger_Uuid::v7();
        $this->seed('gone.example', $org, 'active', 1);        // soft-deleted
        $this->seed('suspended.example', $org, 'suspended', 0); // not active

        $m = new Tiger_Model_SiteDomain();
        $this->assertNull($m->orgForHost('gone.example'));
        $this->assertNull($m->orgForHost('suspended.example'));
    }

    #[Test]
    public function all_for_org_lists_its_hosts(): void
    {
        $org   = Tiger_Uuid::v7();
        $other = Tiger_Uuid::v7();
        $this->seed('apex.example', $org);
        $this->seed('www.apex.example', $org);
        $this->seed('someone-else.example', $other);

        $hosts = [];
        foreach ((new Tiger_Model_SiteDomain())->allForOrg($org) as $row) {
            $hosts[] = $row->domain;
        }
        sort($hosts);
        $this->assertSame(['apex.example', 'www.apex.example'], $hosts);
    }
}
