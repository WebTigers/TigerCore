<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Secret_Adapter_Env;

/**
 * Tiger_Secret_Adapter_Env — resolve from an environment variable. Always available; getenv first, then
 * $_ENV / $_SERVER; unset or empty is null.
 */
#[CoversClass(Tiger_Secret_Adapter_Env::class)]
final class EnvTest extends UnitTestCase
{
    #[Test]
    public function reads_from_getenv(): void
    {
        putenv('TIGER_SEC_ENVTEST=yes');
        try {
            $a = new Tiger_Secret_Adapter_Env();
            $this->assertTrue($a->available());
            $this->assertSame('yes', $a->get('TIGER_SEC_ENVTEST'));
        } finally {
            putenv('TIGER_SEC_ENVTEST');
        }
    }

    #[Test]
    public function falls_back_to_the_server_superglobal(): void
    {
        $_SERVER['TIGER_SEC_SRVTEST'] = 'srv';
        try {
            $this->assertSame('srv', (new Tiger_Secret_Adapter_Env())->get('TIGER_SEC_SRVTEST'));
        } finally {
            unset($_SERVER['TIGER_SEC_SRVTEST']);
        }
    }

    #[Test]
    public function an_unset_or_empty_variable_is_null(): void
    {
        $a = new Tiger_Secret_Adapter_Env();
        $this->assertNull($a->get('TIGER_SEC_DEFINITELY_UNSET'));
        $this->assertNull($a->get(''));
    }
}
