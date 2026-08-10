<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Modules;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Store;
use Tiger_License_Vendor;

/**
 * System_Service_Modules — the per-vendor paid module actions (single-module direct-buy): connectVendor
 * (fetch the consent payload), pinVendor (trust the vendor's key), and installLicensed (install from the
 * authority with a bought key). The vendor fetch + pin store are injected so no network/DB is touched;
 * the happy-path install (installFromAuthority → real download) is proven separately against a fixture.
 */
#[CoversClass(System_Service_Modules::class)]
final class ModulesLicensedTest extends IntegrationTestCase
{
    private string $pub = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->pub = Tiger_Crypto_Signature::generateKeypair()['public_key'];
        Tiger_License_Vendor::setStore($this->memoryStore());
    }

    protected function tearDown(): void
    {
        Tiger_License_Vendor::_reset();
        parent::tearDown();
    }

    private function memoryStore(): Tiger_License_Store
    {
        return new class implements Tiger_License_Store {
            private array $rows = [];
            public function get(string $slug): ?array { return $this->rows[$slug] ?? null; }
            public function put(string $slug, array $record): void { $this->rows[$slug] = $record; }
            public function forget(string $slug): void { unset($this->rows[$slug]); }
        };
    }

    private function serveManifest(?string $json): void
    {
        Tiger_License_Vendor::setFetch(static function () use ($json) { return $json; });
    }

    private function call(string $action, array $params = []): object
    {
        return (new System_Service_Modules(['action' => $action] + $params))->getResponse();
    }

    #[Test]
    public function guest_is_denied(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $this->assertStringContainsString('not_allowed', json_encode($this->call('connectVendor', ['vendor' => 'acme/TigerVendor'])->messages));
    }

    #[Test]
    public function connect_then_pin_a_vendor(): void
    {
        $this->loginAs('admin');
        $this->serveManifest(json_encode(['api_base' => 'https://store.acme.com/shop/authority', 'public_key' => $this->pub]));

        $c = $this->call('connectVendor', ['vendor' => 'acme/TigerVendor']);
        $this->assertSame(1, (int) $c->result);
        $this->assertSame(Tiger_Crypto_Signature::fingerprint($this->pub), $c->data['fingerprint']);
        $this->assertFalse($c->data['pinned'], 'connect must not auto-pin');
        $this->assertNull(Tiger_License_Vendor::pinned('acme/TigerVendor'), 'nothing trusted yet');

        $p = $this->call('pinVendor', ['vendor' => 'acme/TigerVendor']);
        $this->assertSame(1, (int) $p->result);
        $this->assertSame($this->pub, Tiger_License_Vendor::publicKey('acme/TigerVendor'), 'server pins the re-fetched key');
    }

    #[Test]
    public function connect_fails_on_an_unreadable_vendor(): void
    {
        $this->loginAs('admin');
        $this->serveManifest(null);
        $this->assertStringContainsString('unreachable', json_encode($this->call('connectVendor', ['vendor' => 'acme/TigerVendor'])->messages));
    }

    #[Test]
    public function install_licensed_requires_a_connected_vendor(): void
    {
        $this->loginAs('admin');
        $res = $this->call('installLicensed', ['vendor' => 'acme/TigerVendor', 'product' => 'acme-widget', 'key' => 'a-license-key']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_connected', json_encode($res->messages), 'must connect+pin the vendor first');
    }

    #[Test]
    public function install_licensed_needs_vendor_product_and_key(): void
    {
        $this->loginAs('admin');
        $res = $this->call('installLicensed', ['vendor' => 'acme/TigerVendor']);   // no product, no key
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('incomplete', json_encode($res->messages));
    }
}
