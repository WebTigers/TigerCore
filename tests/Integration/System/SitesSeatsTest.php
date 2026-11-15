<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Modules;
use System_Service_Sites;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Authority;
use Tiger_License_Checker;
use Tiger_License_Pass;
use Tiger_License_Seats;
use Tiger_Model_Org;
use Tiger_Model_SiteDomain;
use Zend_Registry;

/**
 * Per-domain TigerPASS seats on the real stores (TIGER-505): the option-tier license store, the
 * `site_domain` map, and the System › Sites + Module Manager surfaces that report coverage.
 *
 * Pinned: the activeHosts() finder; the store's back-compat (only the old single record = only the main
 * domain covered); mapping a domain claims its seat after the commit, and a refused claim never undoes the
 * mapping; the Sites grid's coverage column; "Check seats" (ACL, no-PASS refusal, claims + coverage); and
 * the Module Manager's "N of M domains covered". The authority is a fake transport signing with a test key.
 */
#[CoversClass(System_Service_Sites::class)]
#[CoversClass(Tiger_License_Seats::class)]
#[CoversClass(Tiger_Model_SiteDomain::class)]
final class SitesSeatsTest extends IntegrationTestCase
{
    private const AUTH = 'https://store.example/shop/authority';
    private const MAIN = 'main-2222-7000-8000-000000000000';

    private array $keys;
    private array $claims = [];
    /** @var array<int,string> domains the fake authority will seat */
    private array $grant = [];

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // CSRF-immune API path (no session in CLI)
        Tiger_License_Checker::_reset();
        Tiger_License_Authority::_reset();
        Tiger_License_Seats::_reset();
        $this->keys = Tiger_Crypto_Signature::generateKeypair();

        $self = $this;
        Tiger_License_Authority::setTransport(function ($url, $p) use ($self) {
            $self->claims[] = (string) $p['domain'];
            $d  = (string) $p['domain'];
            $ok = in_array($d, $self->grant, true);
            $data = $ok
                ? ['ok' => true, 'key' => 'SEAT-' . $d, 'domain' => $d, 'seats' => 3, 'in_use' => 2]
                : ['ok' => false, 'domain' => $d, 'reason' => 'no_seats', 'seats' => 3, 'in_use' => 3];
            $json = json_encode($data);
            return ['payload' => $json, 'signature' => Tiger_Crypto_Signature::sign($json, $self->keys['secret_key'])];
        });
    }

    protected function tearDown(): void
    {
        Tiger_License_Checker::_reset();
        Tiger_License_Authority::_reset();
        Tiger_License_Seats::_reset();
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    // ---- helpers ---------------------------------------------------------------------------

    private function sites(string $action, array $params = []): object
    {
        return (new System_Service_Sites(['action' => $action] + $params))->getResponse();
    }

    private function org(): string
    {
        $s = substr(md5(uniqid('', true)), 0, 8);
        return (string) (new Tiger_Model_Org())->insert(['name' => 'Seat Co ' . $s, 'slug' => 'seat-co-' . $s, 'status' => 'active']);
    }

    /** Hold the install's main TigerPASS key exactly as "Activate TigerPASS" leaves it (option tier). */
    private function holdPass(string $domain): void
    {
        Tiger_License_Checker::remember(Tiger_License_Pass::SLUG, [
            'key' => self::MAIN, 'authority' => self::AUTH, 'vendor' => 'Acme/TigerVendor',
            'public_key' => $this->keys['public_key'], 'domain' => $domain,
        ]);
    }

    private function mapHost(string $host, string $org, string $status = 'active'): string
    {
        return (string) (new Tiger_Model_SiteDomain())->insert(['domain' => $host, 'org_id' => $org, 'status' => $status]);
    }

    // ---- the finder + the store ------------------------------------------------------------

    #[Test]
    public function activeHostsListsOnlyActiveUndeletedMappings(): void
    {
        $org   = $this->org();
        $model = new Tiger_Model_SiteDomain();
        $this->mapHost('live.seat505.test', $org);
        $this->mapHost('paused.seat505.test', $org, 'suspended');
        $gone = $this->mapHost('gone.seat505.test', $org);
        $model->softDelete($model->getAdapter()->quoteInto('site_domain_id = ?', $gone));

        $hosts = array_values(array_filter($model->activeHosts(), static fn($h) => str_ends_with($h, '.seat505.test')));
        $this->assertSame(['live.seat505.test'], $hosts);
    }

    #[Test]
    public function theOldSingleRecordOnTheRealStoreCoversOnlyTheMainDomain(): void
    {
        $org = $this->org();
        $this->holdPass('main.seat505.test');
        $this->mapHost('main.seat505.test', $org);
        $this->mapHost('tenant.seat505.test', $org);

        $this->assertSame(Tiger_License_Seats::MAIN, Tiger_License_Seats::stateFor('main.seat505.test'));
        $this->assertSame(Tiger_License_Seats::UNCOVERED, Tiger_License_Seats::stateFor('tenant.seat505.test'));
        $this->assertNull(Tiger_License_Checker::get(Tiger_License_Seats::seatSlug('tenant.seat505.test')));
    }

    // ---- mapping a domain claims its seat ---------------------------------------------------

    #[Test]
    public function mappingADomainClaimsItsSeatWithTheMainKey(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->holdPass('main.seat505.test');
        $this->grant = ['shop.seat505.test'];

        $res = $this->sites('save', ['domain' => 'shop.seat505.test', 'org_id' => $org]);
        $this->assertSame(1, (int) $res->result);
        $this->assertContains('shop.seat505.test', $res->data['seats']['claimed']);

        $seat = Tiger_License_Checker::get(Tiger_License_Seats::seatSlug('shop.seat505.test'));
        $this->assertSame('SEAT-shop.seat505.test', $seat['key'], 'stored in the option-tier store');
        $this->assertSame('shop.seat505.test', $seat['domain']);
        $this->assertSame(Tiger_License_Seats::COVERED, Tiger_License_Seats::stateFor('shop.seat505.test'));
    }

    #[Test]
    public function aRefusedClaimNeverUndoesTheMapping(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->holdPass('main.seat505.test');
        $this->grant = [];

        $res = $this->sites('save', ['domain' => 'full.seat505.test', 'org_id' => $org]);
        $this->assertSame(1, (int) $res->result, 'the domain is mapped regardless');
        $this->assertSame('no_seats', $res->data['seats']['refused']['full.seat505.test']);
        $this->assertSame($org, (new Tiger_Model_SiteDomain())->orgForHost('full.seat505.test'));
        $this->assertSame(Tiger_License_Seats::UNCOVERED, Tiger_License_Seats::stateFor('full.seat505.test'));
    }

    #[Test]
    public function withoutTigerPassMappingADomainClaimsNothing(): void
    {
        $this->loginAs('admin');
        $res = $this->sites('save', ['domain' => 'plain.seat505.test', 'org_id' => $this->org()]);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('no_pass', $res->data['seats']['skipped']);
        $this->assertSame([], $this->claims);
    }

    // ---- the grid + "Check seats" -----------------------------------------------------------

    #[Test]
    public function theSitesGridCarriesEachDomainsCoverage(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->mapHost('main.seat505.test', $org);
        $this->mapHost('grid.seat505.test', $org);

        $rows = fn() => array_column($this->sites('datatable', ['draw' => 1, 'start' => 0, 'length' => 100, 'search' => 'seat505'])->data['data'], null, 'domain');
        $this->assertSame('none', $rows()['grid.seat505.test']['coverage'], 'no TigerPASS → coverage does not apply');

        $this->holdPass('main.seat505.test');
        Tiger_License_Seats::_reset();
        $r = $rows();
        $this->assertSame('main', $r['main.seat505.test']['coverage']);
        $this->assertSame('uncovered', $r['grid.seat505.test']['coverage']);
        $this->assertSame('https://store.example/license', $r['grid.seat505.test']['manage_url'], 'where to add a seat');
    }

    #[Test]
    public function checkSeatsIsAdminOnlyAndNeedsTigerPass(): void
    {
        $this->loginAs('user');
        $this->assertSame(0, (int) $this->sites('checkSeats')->result);

        $this->loginAs('admin');
        $res = $this->sites('checkSeats');
        $this->assertSame(0, (int) $res->result);
        $this->assertMatchesRegularExpression('/no_pass|TigerPASS/', json_encode($res->messages, JSON_UNESCAPED_UNICODE));
    }

    #[Test]
    public function checkSeatsClaimsWhatIsMissingAndReportsCoverage(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->holdPass('main.seat505.test');
        $this->mapHost('main.seat505.test', $org);
        $this->mapHost('www.main.seat505.test', $org);
        $this->mapHost('a.seat505.test', $org);
        $this->mapHost('www.a.seat505.test', $org);
        $this->mapHost('b.seat505.test', $org);
        $this->grant = ['a.seat505.test'];

        $res = $this->sites('checkSeats');
        $this->assertSame(1, (int) $res->result);
        $mine = array_values(array_filter($this->claims, static fn($d) => str_ends_with($d, '.seat505.test')));
        sort($mine);
        $this->assertSame(['a.seat505.test', 'b.seat505.test'], $mine, 'main + www duplicates skipped');
        $cov = $res->data['coverage'];
        $this->assertSame('main', $cov['domains']['main.seat505.test']);
        $this->assertSame('covered', $cov['domains']['a.seat505.test']);
        $this->assertSame('uncovered', $cov['domains']['b.seat505.test']);
    }

    #[Test]
    public function theModuleManagerReportsNOfMDomainsCovered(): void
    {
        $this->loginAs('superadmin');
        $org = $this->org();
        $this->holdPass('main.seat505.test');
        $this->mapHost('main.seat505.test', $org);
        $this->mapHost('x.seat505.test', $org);
        $this->grant = ['x.seat505.test'];
        Tiger_License_Seats::reconcile();

        $res = (new System_Service_Modules(['action' => 'passInfo']))->getResponse();
        $this->assertSame(1, (int) $res->result);
        $seats = $res->data['seats'];
        $this->assertTrue($seats['enabled']);
        $this->assertSame('covered', $seats['domains']['x.seat505.test']);
        $this->assertSame($seats['total'], count($seats['domains']));
        $this->assertGreaterThanOrEqual(2, $seats['covered']);
    }
}
