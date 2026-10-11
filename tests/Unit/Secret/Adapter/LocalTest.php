<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Crypto;
use Tiger_Secret_Adapter_Local;

/**
 * Tiger_Secret_Adapter_Local — decrypt an inline Tiger_Crypto blob (the default backend). Available only
 * when a crypto key is configured; a bad or missing blob fails soft to null.
 */
#[CoversClass(Tiger_Secret_Adapter_Local::class)]
final class LocalTest extends UnitTestCase
{
    #[Test]
    public function decrypts_a_blob_when_a_key_is_configured(): void
    {
        $this->setCryptoConfig();
        $blob = Tiger_Crypto::encrypt('hunter2');
        $a = new Tiger_Secret_Adapter_Local();

        $this->assertTrue($a->available());
        $this->assertSame('hunter2', $a->get($blob));
    }

    #[Test]
    public function is_unavailable_without_a_crypto_key(): void
    {
        $a = new Tiger_Secret_Adapter_Local();
        $this->assertFalse($a->available());
    }

    #[Test]
    public function a_malformed_or_empty_blob_is_null(): void
    {
        $this->setCryptoConfig();
        $a = new Tiger_Secret_Adapter_Local();

        $this->assertNull($a->get(''));
        $this->assertNull($a->get('not-valid-ciphertext'));
    }
}
