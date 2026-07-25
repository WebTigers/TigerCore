<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\License;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Checker;
use Tiger_License_Store;

/**
 * Tiger_License_Checker — two branches the main CheckerTest doesn't reach:
 *   - the default option-backed store lazy-initializes when NO store is injected (status()/verify() for
 *     an unlicensed slug never touches an authority);
 *   - a reply whose signature VERIFIES but whose payload isn't a JSON object is untrusted → `unknown`
 *     (fail-OPEN). This is the subtle sibling of the forged-signature case: the signature is genuine, but
 *     a non-object payload can't be a verdict, so a malformed-but-signed reply still can't nag.
 */
#[CoversClass(Tiger_License_Checker::class)]
final class CheckerExtraTest extends IntegrationTestCase
{
    private const SLUG = 'acme-extra';

    protected function tearDown(): void
    {
        Tiger_License_Checker::_reset();   // drop any injected store/transport
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

    #[Test]
    public function status_uses_the_default_option_store_when_none_is_injected(): void
    {
        // No setStore() → the checker lazily constructs its default Tiger_License_Store_Option; an
        // unrecorded slug reports unlicensed with no network hit.
        $verdict = Tiger_License_Checker::status('never-installed-' . bin2hex(random_bytes(3)));
        $this->assertSame(Tiger_License_Checker::UNLICENSED, $verdict['state']);
        $this->assertTrue($verdict['can_update']);
    }

    #[Test]
    public function a_correctly_signed_reply_with_a_non_object_payload_is_untrusted(): void
    {
        Tiger_License_Checker::setStore($this->memoryStore());

        $keys = Tiger_Crypto_Signature::generateKeypair();
        // A payload that is valid JSON but decodes to a STRING, not an object → cannot be a verdict.
        $payload   = json_encode('not-a-verdict-object');
        $signature = Tiger_Crypto_Signature::sign($payload, $keys['secret_key']);

        Tiger_License_Checker::remember(self::SLUG, [
            'key'        => 'LIC-XYZ',
            'authority'  => 'https://store.example/authority',
            'vendor'     => 'acme/TigerVendor',
            'public_key' => $keys['public_key'],
        ]);
        Tiger_License_Checker::setTransport(
            static fn(string $authority, array $payloadIn): ?array => ['payload' => $payload, 'signature' => $signature]
        );

        $verdict = Tiger_License_Checker::verify(self::SLUG);
        $this->assertSame(Tiger_License_Checker::UNKNOWN, $verdict['state'], 'a signed-but-malformed payload is untrusted, not lapsed');
        $this->assertTrue($verdict['can_update'], 'fail-OPEN: it never withholds an update');
    }
}
