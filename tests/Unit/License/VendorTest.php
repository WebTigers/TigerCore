<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\License;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Store;
use Tiger_License_Vendor;

/**
 * Tiger_License_Vendor — CONNECT + pin a paid module's vendor (its `[owner]/TigerVendor` repo): fetch +
 * validate the `tigervendor.json` manifest, produce a consent payload (fingerprint + pin/changed state),
 * pin/resolve the vendor's Ed25519 public key, and drop the pin. Fetch + store are injected — no network,
 * no DB.
 */
#[CoversClass(Tiger_License_Vendor::class)]
final class VendorTest extends UnitTestCase
{
    private string $pubA = '';
    private string $pubB = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->pubA = Tiger_Crypto_Signature::generateKeypair()['public_key'];
        $this->pubB = Tiger_Crypto_Signature::generateKeypair()['public_key'];
        Tiger_License_Vendor::setStore($this->memoryStore());
    }

    protected function tearDown(): void
    {
        Tiger_License_Vendor::_reset();
        parent::tearDown();
    }

    /** An in-memory Tiger_License_Store for isolation. */
    private function memoryStore(): Tiger_License_Store
    {
        return new class implements Tiger_License_Store {
            private array $rows = [];
            public function get(string $slug): ?array { return $this->rows[$slug] ?? null; }
            public function put(string $slug, array $record): void { $this->rows[$slug] = $record; }
            public function forget(string $slug): void { unset($this->rows[$slug]); }
        };
    }

    /** Point the fetch seam at a fixed manifest string (or null to simulate unreachable). */
    private function serveManifest(?string $json): void
    {
        Tiger_License_Vendor::setFetch(static function () use ($json) { return $json; });
    }

    #[Test]
    public function manifest_parses_a_valid_tigervendor(): void
    {
        $this->serveManifest(json_encode([
            'vendor'     => 'acme/TigerVendor',
            'api_base'   => 'https://store.acme.com/shop/authority/',
            'public_key' => $this->pubA,
            'catalog'    => 'https://store.acme.com/marketplace.json',
        ]));

        $m = Tiger_License_Vendor::manifest('acme/TigerVendor');
        $this->assertSame('acme/TigerVendor', $m['vendor']);
        $this->assertSame('https://store.acme.com/shop/authority', $m['api_base'], 'trailing slash trimmed');
        $this->assertSame($this->pubA, $m['public_key']);
        $this->assertSame('https://store.acme.com/marketplace.json', $m['catalog']);
    }

    #[Test]
    public function manifest_rejects_unusable_anchors(): void
    {
        // absent
        $this->serveManifest(null);
        $this->assertNull(Tiger_License_Vendor::manifest('acme/TigerVendor'));

        // not JSON
        $this->serveManifest('<html>nope</html>');
        $this->assertNull(Tiger_License_Vendor::manifest('acme/TigerVendor'));

        // http (not https) authority
        $this->serveManifest(json_encode(['api_base' => 'http://store.acme.com/a', 'public_key' => $this->pubA]));
        $this->assertNull(Tiger_License_Vendor::manifest('acme/TigerVendor'), 'http anchor refused');

        // bogus key (not 32 raw bytes)
        $this->serveManifest(json_encode(['api_base' => 'https://store.acme.com/a', 'public_key' => 'not-a-key']));
        $this->assertNull(Tiger_License_Vendor::manifest('acme/TigerVendor'), 'invalid key refused');

        // malformed vendor id
        $this->serveManifest(json_encode(['api_base' => 'https://store.acme.com/a', 'public_key' => $this->pubA]));
        $this->assertNull(Tiger_License_Vendor::manifest('not-owner-repo'));
    }

    #[Test]
    public function connect_reports_fingerprint_and_pin_state(): void
    {
        $this->serveManifest(json_encode(['api_base' => 'https://store.acme.com/a', 'public_key' => $this->pubA]));

        $c = Tiger_License_Vendor::connect('acme/TigerVendor');
        $this->assertSame(Tiger_Crypto_Signature::fingerprint($this->pubA), $c['fingerprint']);
        $this->assertFalse($c['pinned'], 'not connected yet');
        $this->assertFalse($c['changed']);

        Tiger_License_Vendor::pin('acme/TigerVendor', $c);
        $c2 = Tiger_License_Vendor::connect('acme/TigerVendor');
        $this->assertTrue($c2['pinned']);
        $this->assertFalse($c2['changed'], 'same key = not changed');

        // The vendor silently rotates its key -> takeover signal.
        $this->serveManifest(json_encode(['api_base' => 'https://store.acme.com/a', 'public_key' => $this->pubB]));
        $c3 = Tiger_License_Vendor::connect('acme/TigerVendor');
        $this->assertTrue($c3['pinned']);
        $this->assertTrue($c3['changed'], 'a different key must flag changed (re-consent)');
    }

    #[Test]
    public function pin_resolve_and_unpin_roundtrip(): void
    {
        $this->assertNull(Tiger_License_Vendor::publicKey('acme/TigerVendor'), 'unpinned = no key');

        Tiger_License_Vendor::pin('acme/TigerVendor', ['api_base' => 'https://store.acme.com/a', 'public_key' => $this->pubA]);
        $this->assertSame($this->pubA, Tiger_License_Vendor::publicKey('acme/TigerVendor'));
        $this->assertSame('https://store.acme.com/a', Tiger_License_Vendor::pinned('acme/TigerVendor')['api_base']);

        Tiger_License_Vendor::unpin('acme/TigerVendor');
        $this->assertNull(Tiger_License_Vendor::pinned('acme/TigerVendor'));
    }
}
