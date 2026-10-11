<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Crypto;
use Tiger_Secret;
use Tiger_Secret_Adapter_Interface;

/**
 * Tiger_Secret — the provider-neutral resolver. The load-bearing contract: a `scheme:path` reference is
 * fetched from its backend; anything else is plaintext passthrough; a recognized-but-unresolvable ref is
 * null (a missing credential), never a fatal. Plus the default-driver bare lookup, the per-process memo,
 * and custom-adapter registration.
 */
#[CoversClass(Tiger_Secret::class)]
final class SecretTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Secret::_reset();
    }

    protected function tearDown(): void
    {
        Tiger_Secret::_reset();
        parent::tearDown();
    }

    #[Test]
    public function plaintext_passes_through_unchanged(): void
    {
        $this->assertSame('just-a-plain-key', Tiger_Secret::resolve('just-a-plain-key'));
    }

    #[Test]
    public function an_empty_value_resolves_to_empty_string(): void
    {
        $this->assertSame('', Tiger_Secret::resolve(''));
        $this->assertSame('', Tiger_Secret::resolve(null));
    }

    #[Test]
    public function a_value_with_an_unknown_scheme_is_treated_as_plaintext(): void
    {
        // An https URL or a user:pass string must not be mistaken for a reference.
        $this->assertSame('https://store.example/x', Tiger_Secret::resolve('https://store.example/x'));
        $this->assertSame('user:pass@host', Tiger_Secret::resolve('user:pass@host'));
    }

    #[Test]
    public function enc_scheme_decrypts_an_inline_tiger_crypto_blob(): void
    {
        $this->setCryptoConfig();
        $blob = Tiger_Crypto::encrypt('s3cr3t-value');

        $this->assertSame('s3cr3t-value', Tiger_Secret::resolve('enc:' . $blob));
        // get() reaches the same backend explicitly.
        $this->assertSame('s3cr3t-value', Tiger_Secret::get('enc:' . $blob));
    }

    #[Test]
    public function enc_scheme_without_a_crypto_key_resolves_to_null_not_fatal(): void
    {
        // No crypto config in the registry -> Local adapter is unavailable.
        $this->assertNull(Tiger_Secret::resolve('enc:not-decryptable'));
    }

    #[Test]
    public function env_scheme_resolves_an_environment_variable(): void
    {
        putenv('TIGER_SECRET_TEST_ENV=from-env');
        try {
            $this->assertSame('from-env', Tiger_Secret::resolve('env:TIGER_SECRET_TEST_ENV'));
            $this->assertNull(Tiger_Secret::resolve('env:TIGER_SECRET_TEST_MISSING'));
        } finally {
            putenv('TIGER_SECRET_TEST_ENV');
        }
    }

    #[Test]
    public function file_scheme_reads_a_file_and_strips_a_trailing_newline(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tsec');
        file_put_contents($tmp, "file-secret\n");
        try {
            $this->assertSame('file-secret', Tiger_Secret::resolve('file:' . $tmp));
        } finally {
            @unlink($tmp);
        }
        $this->assertNull(Tiger_Secret::resolve('file:/no/such/path/tiger-secret'));
    }

    #[Test]
    public function a_bare_name_resolves_against_the_configured_default_driver(): void
    {
        $this->setConfig(['tiger' => ['secret' => ['driver' => 'env']]]);
        putenv('TIGER_SECRET_BARE=bare-value');
        try {
            $this->assertSame('bare-value', Tiger_Secret::get('TIGER_SECRET_BARE'));
        } finally {
            putenv('TIGER_SECRET_BARE');
        }
    }

    #[Test]
    public function a_registered_custom_adapter_handles_its_scheme(): void
    {
        Tiger_Secret::register('vault', $this->fixedAdapter('vault-answer'));

        $this->assertTrue(Tiger_Secret::has('vault'));
        $this->assertContains('vault', Tiger_Secret::schemes());
        $this->assertSame('vault-answer', Tiger_Secret::resolve('vault:secret/data/x'));
    }

    #[Test]
    public function successful_resolutions_are_memoized_per_process(): void
    {
        // An adapter that yields a different value each call proves the cache is used.
        $counter = new class implements Tiger_Secret_Adapter_Interface {
            public int $calls = 0;
            public function available(): bool { return true; }
            public function get(string $path): ?string { $this->calls++; return 'v' . $this->calls; }
        };
        Tiger_Secret::register('count', $counter);

        $this->assertSame('v1', Tiger_Secret::resolve('count:x'));
        $this->assertSame('v1', Tiger_Secret::resolve('count:x'));   // cached, not v2
        $this->assertSame(1, $counter->calls);

        // A new registration clears the cache.
        Tiger_Secret::register('count', $counter);
        $this->assertSame('v2', Tiger_Secret::resolve('count:x'));
        $this->assertSame(2, $counter->calls);
    }

    #[Test]
    public function an_adapter_that_throws_fails_soft_to_null(): void
    {
        $boom = new class implements Tiger_Secret_Adapter_Interface {
            public function available(): bool { return true; }
            public function get(string $path): ?string { throw new \RuntimeException('kaboom'); }
        };
        Tiger_Secret::register('boom', $boom);

        $this->assertNull(Tiger_Secret::resolve('boom:x'));
    }

    #[Test]
    public function an_unavailable_adapter_is_skipped(): void
    {
        $down = new class implements Tiger_Secret_Adapter_Interface {
            public function available(): bool { return false; }
            public function get(string $path): ?string { return 'should-not-be-reached'; }
        };
        Tiger_Secret::register('down', $down);

        $this->assertNull(Tiger_Secret::resolve('down:x'));
    }

    #[Test]
    public function get_on_an_empty_reference_is_null(): void
    {
        $this->assertNull(Tiger_Secret::get(''));
    }

    /** A trivial always-available adapter returning a fixed value. */
    private function fixedAdapter(string $value): Tiger_Secret_Adapter_Interface
    {
        return new class($value) implements Tiger_Secret_Adapter_Interface {
            public function __construct(private string $value) {}
            public function available(): bool { return true; }
            public function get(string $path): ?string { return $this->value; }
        };
    }
}
