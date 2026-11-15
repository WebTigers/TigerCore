<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\License;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Admin_Header;
use Tiger_Crypto_Signature;
use Tiger_License_Authority;
use Tiger_License_Checker;
use Tiger_License_Pass;
use Tiger_License_Seats;
use Tiger_License_Store;

/**
 * Tiger_License_Seats + Tiger_License_Authority::claim — per-domain license seats on a multi-domain install.
 *
 * Driven with an in-memory license store, a fake authority (the claim transport + the verify transport) and an
 * injected host list, so the whole model runs with no DB and no network. Pinned:
 *   - back-compat: an install holding only the old single record = only its main domain covered;
 *   - the claim client trusts only a reply signed by the pinned key, about the domain it asked for;
 *   - reconcile claims the missing seats and skips the main domain, seated domains and www/apex duplicates;
 *     a `no_seats` refusal or an untrusted reply stores nothing; it never throws;
 *   - the update gate stays on the main key — no seat / a lapsed seat never blocks an update;
 *   - the admin notice fires only for a mapped tenant domain without a valid seat.
 */
#[CoversClass(Tiger_License_Seats::class)]
#[CoversClass(Tiger_License_Authority::class)]
final class SeatsTest extends UnitTestCase
{
    private const AUTH = 'https://store.example/shop/authority';
    private const MAIN = 'main-1111-7000-8000-000000000000';

    /** @var Tiger_License_Store&object{rows:array} */
    private $store;
    private array $keys;
    /** @var array<int,array{url:string,payload:array}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Tiger_License_Checker::_reset();
        Tiger_License_Authority::_reset();
        Tiger_License_Seats::_reset();
        $this->store = new class implements Tiger_License_Store {
            public array $rows = [];
            public bool $failPut = false;
            public function get(string $slug): ?array { return $this->rows[$slug] ?? null; }
            public function put(string $slug, array $record): void
            {
                if ($this->failPut) { throw new RuntimeException('store down'); }
                $this->rows[$slug] = $record;
            }
            public function forget(string $slug): void { unset($this->rows[$slug]); }
        };
        Tiger_License_Checker::setStore($this->store);
        $this->keys = Tiger_Crypto_Signature::generateKeypair();
    }

    protected function tearDown(): void
    {
        Tiger_License_Checker::_reset();
        Tiger_License_Authority::_reset();
        Tiger_License_Seats::_reset();
        \Tiger_Log::reset();   // drop any logger built against this test's config
        parent::tearDown();
    }

    // ---- fixtures -------------------------------------------------------------------------

    /** The install's main TigerPASS record, pinned to main.com (as Checker::remember leaves it). */
    private function holdPass(string $domain = 'main.com'): void
    {
        $this->store->rows[Tiger_License_Pass::SLUG] = [
            'key' => self::MAIN, 'authority' => self::AUTH, 'vendor' => 'Acme/TigerVendor',
            'public_key' => $this->keys['public_key'], 'domain' => $domain,
        ];
    }

    private function hosts(array $hosts): void
    {
        Tiger_License_Seats::setHostSource(static fn() => $hosts);
    }

    private function sign(array $data, ?string $secret = null): array
    {
        $json = json_encode($data);
        return ['payload' => $json, 'signature' => Tiger_Crypto_Signature::sign($json, $secret ?? $this->keys['secret_key'])];
    }

    /** A fake authority /claim: hands out SEAT-<apex> for every domain in $grant, refuses the rest no_seats. */
    private function authorityGrants(array $grant): void
    {
        $self = $this;
        Tiger_License_Authority::setTransport(function ($url, $p) use ($grant, $self) {
            $self->calls[] = ['url' => $url, 'payload' => $p];
            $d = (string) $p['domain'];
            return in_array($d, $grant, true)
                ? $self->sign(['ok' => true, 'key' => 'SEAT-' . $d, 'domain' => $d, 'seats' => 5, 'in_use' => 2])
                : $self->sign(['ok' => false, 'domain' => $d, 'reason' => 'no_seats', 'seats' => 5, 'in_use' => 5]);
        });
    }

    // ---- naming + back-compat -------------------------------------------------------------

    #[Test]
    public function apexStripsWwwPortCaseAndTrailingDot(): void
    {
        $this->assertSame('example.com', Tiger_License_Seats::apex(' WWW.Example.com:8443 '));
        $this->assertSame('example.com', Tiger_License_Seats::apex('example.com.'));
        $this->assertSame('shop.example.com', Tiger_License_Seats::apex('shop.example.com'), 'a subdomain stays its own site');
        $this->assertSame(Tiger_License_Pass::SLUG . '@example.com', Tiger_License_Seats::seatSlug('www.example.com'));
        $long = str_repeat('a', 160) . '.com';
        $this->assertLessThanOrEqual(191 - strlen('tiger.license.'), strlen(Tiger_License_Seats::seatSlug($long)), 'fits the option key column');
    }

    #[Test]
    public function anInstallWithOnlyTheOldSingleRecordCoversOnlyItsMainDomain(): void
    {
        $this->holdPass('www.main.com');
        $this->hosts(['main.com', 'www.main.com', 'tenant.com']);

        $cov = Tiger_License_Seats::coverage();
        $this->assertTrue($cov['enabled']);
        $this->assertSame('main.com', $cov['main']);
        $this->assertSame(['main.com' => 'main', 'tenant.com' => 'uncovered'], $cov['domains']);
        $this->assertSame(1, $cov['covered']);
        $this->assertSame(2, $cov['total']);
        $this->assertSame(Tiger_License_Seats::MAIN, Tiger_License_Seats::stateFor('main.com'));
    }

    #[Test]
    public function noTigerPassMeansCoverageDoesNotApply(): void
    {
        $this->hosts(['tenant.com']);
        $this->assertFalse(Tiger_License_Seats::coverage()['enabled']);
        $this->assertSame(Tiger_License_Seats::NONE, Tiger_License_Seats::stateFor('tenant.com'));
        $this->assertFalse(Tiger_License_Seats::needsSeat('tenant.com'));
        $this->assertSame('no_pass', Tiger_License_Seats::reconcile()['skipped']);
    }

    // ---- the claim client -----------------------------------------------------------------

    #[Test]
    public function claimPostsTheMainKeyAndTheApexAndTrustsASignedReply(): void
    {
        $this->authorityGrants(['tenant.com']);
        $r = Tiger_License_Authority::claim(self::AUTH . '/', 'K', 'WWW.Tenant.com', $this->keys['public_key'], 'pass');

        $this->assertSame(self::AUTH . '/claim', $this->calls[0]['url']);
        $this->assertSame(['key' => 'K', 'domain' => 'tenant.com', 'product' => 'pass'], $this->calls[0]['payload']);
        $this->assertSame(['ok' => true, 'domain' => 'tenant.com', 'seats' => 5, 'in_use' => 2, 'key' => 'SEAT-tenant.com'], $r);
    }

    #[Test]
    public function aSignedRefusalCarriesItsReason(): void
    {
        $this->authorityGrants([]);
        $r = Tiger_License_Authority::claim(self::AUTH, 'K', 'tenant.com', $this->keys['public_key']);
        $this->assertFalse($r['ok']);
        $this->assertSame('no_seats', $r['reason']);
        $this->assertArrayNotHasKey('key', $r);
    }

    #[Test]
    public function aForgedUnsignedOrMisdirectedReplyIsUnknown(): void
    {
        $other = Tiger_Crypto_Signature::generateKeypair();
        $good  = ['ok' => true, 'key' => 'SEAT', 'domain' => 'tenant.com', 'seats' => 1, 'in_use' => 1];
        $cases = [
            'forged'         => $this->sign($good, $other['secret_key']),
            'unsigned'       => $good,
            'tampered'       => ['payload' => json_encode($good + ['x' => 1]), 'signature' => $this->sign($good)['signature']],
            'other domain'   => $this->sign(['domain' => 'evil.com'] + $good),
            'ok with no key' => $this->sign(['ok' => true, 'domain' => 'tenant.com', 'seats' => 1, 'in_use' => 1]),
            'unreachable'    => null,
        ];
        foreach ($cases as $label => $reply) {
            Tiger_License_Authority::setTransport(static fn($u, $p) => $reply);
            $this->assertNull(Tiger_License_Authority::claim(self::AUTH, 'K', 'tenant.com', $this->keys['public_key']), $label);
        }
    }

    // ---- reconcile ------------------------------------------------------------------------

    #[Test]
    public function reconcileClaimsMissingSeatsAndSkipsMainSeatedAndDuplicates(): void
    {
        $this->holdPass('main.com');
        $this->store->rows[Tiger_License_Seats::seatSlug('seated.com')] = [
            'key' => 'SEAT-OLD', 'authority' => self::AUTH, 'public_key' => $this->keys['public_key'], 'domain' => 'seated.com',
        ];
        $this->hosts(['main.com', 'www.main.com', 'a.com', 'www.a.com', 'seated.com', 'shop.a.com']);
        $this->authorityGrants(['a.com', 'shop.a.com']);

        $out = Tiger_License_Seats::reconcile();

        $this->assertSame(['a.com', 'shop.a.com'], $out['claimed']);
        $this->assertSame(['a.com', 'shop.a.com'], array_map(static fn($c) => $c['payload']['domain'], $this->calls),
            'one claim per missing apex: not the main domain, not the seated one, not www twice');
        foreach ($this->calls as $c) { $this->assertSame(self::MAIN, $c['payload']['key'], 'always claimed with the MAIN key'); }

        $seat = $this->store->rows[Tiger_License_Seats::seatSlug('a.com')];
        $this->assertSame('SEAT-a.com', $seat['key']);
        $this->assertSame('a.com', $seat['domain'], 'pinned to its own domain');
        $this->assertSame(self::AUTH, $seat['authority'], 'the same authority as the payer');
        $this->assertSame($this->keys['public_key'], $seat['public_key']);
        $this->assertSame('SEAT-OLD', $this->store->rows[Tiger_License_Seats::seatSlug('seated.com')]['key'], 'untouched');

        // Idempotent: a second run has nothing to claim.
        $this->calls = [];
        $again = Tiger_License_Seats::reconcile();
        $this->assertSame([], $again['claimed']);
        $this->assertSame([], $this->calls);
    }

    #[Test]
    public function noSeatsLeavesTheDomainUncoveredAndStoresNothing(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'tenant.com']);
        $this->authorityGrants([]);
        $before = $this->store->rows;

        $out = Tiger_License_Seats::reconcile();
        $this->assertSame(['tenant.com' => 'no_seats'], $out['refused']);
        $this->assertSame($before, $this->store->rows);
        $this->assertSame(Tiger_License_Seats::UNCOVERED, Tiger_License_Seats::stateFor('tenant.com'));
    }

    #[Test]
    public function aBadSignatureIsUnknownAndStoresNothing(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'tenant.com']);
        $other = Tiger_Crypto_Signature::generateKeypair();
        $self  = $this;
        Tiger_License_Authority::setTransport(static fn($u, $p) => $self->sign(
            ['ok' => true, 'key' => 'EVIL', 'domain' => 'tenant.com', 'seats' => 1, 'in_use' => 1], $other['secret_key']));
        $before = $this->store->rows;

        $out = Tiger_License_Seats::reconcile();
        $this->assertSame(['tenant.com'], $out['unknown']);
        $this->assertSame($before, $this->store->rows);
    }

    #[Test]
    public function reconcileNeverThrows(): void
    {
        $this->setConfig(['tiger' => ['log' => ['writer' => 'null']]]);   // the failure paths log — silence it
        \Tiger_Log::reset();
        $this->holdPass();
        $this->hosts(['main.com', 'tenant.com']);
        Tiger_License_Authority::setTransport(static function () { throw new RuntimeException('boom'); });
        $this->assertSame(['tenant.com'], Tiger_License_Seats::reconcile()['unknown']);

        $this->authorityGrants(['tenant.com']);
        $this->store->failPut = true;   // the store itself fails mid-reconcile
        $out = Tiger_License_Seats::reconcile();
        $this->assertSame([], $out['claimed']);

        Tiger_License_Seats::setHostSource(static function () { throw new RuntimeException('db down'); });
        $this->assertIsArray(Tiger_License_Seats::reconcile());
        $this->assertFalse(Tiger_License_Seats::needsSeat('tenant.com'));
        $this->assertFalse(Tiger_License_Seats::coverage()['enabled']);
    }

    #[Test]
    public function reconcileWaitsForTheMainKeyToPinItsDomain(): void
    {
        $this->holdPass('');
        $this->hosts(['main.com', 'tenant.com']);
        $this->authorityGrants(['main.com', 'tenant.com']);
        $this->assertSame('no_main_domain', Tiger_License_Seats::reconcile()['skipped']);
        $this->assertSame([], $this->calls, 'never claims a seat for what may be the main domain itself');
    }

    #[Test]
    public function aLapsedSeatIsReclaimed(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'tenant.com']);
        $this->store->rows[Tiger_License_Seats::seatSlug('tenant.com')] = [
            'key' => 'SEAT-REVOKED', 'authority' => self::AUTH, 'public_key' => $this->keys['public_key'], 'domain' => 'tenant.com',
            'verdict' => ['state' => Tiger_License_Checker::LAPSED, 'can_update' => false, 'latest_version' => null, 'checked_at' => 1, 'expires_at' => 2],
        ];
        $this->assertSame(Tiger_License_Seats::LAPSED, Tiger_License_Seats::stateFor('tenant.com'));
        $this->authorityGrants(['tenant.com']);

        $this->assertSame(['tenant.com'], Tiger_License_Seats::reconcile()['claimed']);
        $this->assertSame('SEAT-tenant.com', $this->store->rows[Tiger_License_Seats::seatSlug('tenant.com')]['key']);
        $this->assertSame(Tiger_License_Seats::COVERED, Tiger_License_Seats::stateFor('tenant.com'));
    }

    // ---- per-domain verify + the update gate ----------------------------------------------

    #[Test]
    public function eachSeatIsVerifiedForItsOwnDomain(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'a.com', 'b.com']);
        foreach (['a.com', 'b.com'] as $d) {
            $this->store->rows[Tiger_License_Seats::seatSlug($d)] = ['key' => 'SEAT-' . $d, 'authority' => self::AUTH,
                'public_key' => $this->keys['public_key'], 'domain' => $d];
        }
        $seen = [];
        $self = $this;
        Tiger_License_Checker::setTransport(function ($a, $p) use (&$seen, $self) {
            $seen[$p['key']] = $p['domain'];
            return $self->sign(['valid' => $p['domain'] === 'a.com', 'ttl' => 3600]);
        });

        $out = Tiger_License_Seats::verifyAll(1000);
        $this->assertSame(['SEAT-a.com' => 'a.com', 'SEAT-b.com' => 'b.com'], $seen, 'each seat checked for ITS domain');
        $this->assertSame(['a.com' => 'valid', 'b.com' => 'lapsed'], $out);
        $this->assertSame(Tiger_License_Seats::COVERED, Tiger_License_Seats::stateFor('a.com'));
        $this->assertSame(Tiger_License_Seats::LAPSED, Tiger_License_Seats::stateFor('b.com'));
        $this->assertArrayNotHasKey('verdict', $this->store->rows[Tiger_License_Pass::SLUG], 'the main key is not touched');
    }

    #[Test]
    public function anUncoveredOrLapsedTenantNeverBlocksAnUpdate(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'lapsed.com', 'none.com']);
        $this->store->rows[Tiger_License_Seats::seatSlug('lapsed.com')] = ['key' => 'SEAT-L', 'authority' => self::AUTH,
            'public_key' => $this->keys['public_key'], 'domain' => 'lapsed.com',
            'verdict' => ['state' => 'lapsed', 'can_update' => false, 'latest_version' => null, 'checked_at' => 1, 'expires_at' => PHP_INT_MAX]];
        $self = $this;
        Tiger_License_Checker::setTransport(fn($a, $p) => $self->sign(['valid' => $p['key'] === self::MAIN, 'ttl' => 3600]));

        $manifest = ['pricing' => ['model' => 'licensed', 'authority' => self::AUTH, 'vendor' => 'Acme/TigerVendor', 'plan' => 'tigerpass']];
        // The update path: the PASS module resolves to the MAIN key, and the gate is open.
        $lic = Tiger_License_Pass::licenseFor('shop', $manifest);
        $this->assertSame(self::MAIN, $lic['key'], 'a PASS module updates under the main key, never a seat');
        $this->assertFalse(Tiger_License_Checker::gate($manifest, Tiger_License_Pass::SLUG)['blocked']);
        $this->assertTrue(Tiger_License_Checker::canUpdate(Tiger_License_Pass::SLUG));
        $this->assertSame(Tiger_License_Seats::LAPSED, Tiger_License_Seats::stateFor('lapsed.com'));
        $this->assertSame(Tiger_License_Seats::UNCOVERED, Tiger_License_Seats::stateFor('none.com'));
    }

    // ---- the admin notice + the "add a seat" link -----------------------------------------

    #[Test]
    public function theNoticeFiresOnlyForAMappedTenantWithoutAValidSeat(): void
    {
        $this->holdPass();
        $this->hosts(['main.com', 'www.main.com', 'none.com', 'ok.com', 'gone.com']);
        $this->store->rows[Tiger_License_Seats::seatSlug('ok.com')] = ['key' => 'S', 'authority' => self::AUTH, 'domain' => 'ok.com'];
        $this->store->rows[Tiger_License_Seats::seatSlug('gone.com')] = ['key' => 'S2', 'authority' => self::AUTH, 'domain' => 'gone.com',
            'verdict' => ['state' => 'lapsed', 'can_update' => false, 'latest_version' => null, 'checked_at' => 1, 'expires_at' => 2]];

        $this->assertTrue(Tiger_License_Seats::needsSeat('none.com'));
        $this->assertTrue(Tiger_License_Seats::needsSeat('www.none.com:443'));
        $this->assertTrue(Tiger_License_Seats::needsSeat('gone.com'), 'a lapsed seat is not covered');
        $this->assertFalse(Tiger_License_Seats::needsSeat('ok.com'), 'a held seat (verdict unknown = assume current)');
        $this->assertFalse(Tiger_License_Seats::needsSeat('www.main.com'), 'the main domain is the payer');
        $this->assertFalse(Tiger_License_Seats::needsSeat('unmapped.net'), 'an unmapped alias is not a tenant');
        $this->assertFalse(Tiger_License_Seats::needsSeat(''));
    }

    #[Test]
    public function theAddASeatLinkIsConfigElseTheAuthorityDashboard(): void
    {
        $this->holdPass();
        $this->assertSame('https://store.example/license', Tiger_License_Seats::manageUrl());
        $this->setConfig(['tiger' => ['pass' => ['manage_url' => 'https://seller.example/account/licenses']]]);
        $this->assertSame('https://seller.example/account/licenses', Tiger_License_Seats::manageUrl());
    }

    #[Test]
    public function aHeaderItemsVisibleCallableDecidesAndFailsSoft(): void
    {
        $this->assertTrue(Tiger_Admin_Header::isVisible(['key' => 'x']));
        $this->assertTrue(Tiger_Admin_Header::isVisible(['visible' => static fn() => true]));
        $this->assertFalse(Tiger_Admin_Header::isVisible(['visible' => static fn() => false]));
        $this->assertFalse(Tiger_Admin_Header::isVisible(['visible' => static function () { throw new RuntimeException('x'); }]));
    }
}
