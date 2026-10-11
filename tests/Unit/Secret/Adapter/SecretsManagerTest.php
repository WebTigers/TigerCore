<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Secret_Adapter_SecretsManager;

/**
 * Tiger_Secret_Adapter_SecretsManager — AWS Secrets Manager. Tested with an injected client: read,
 * create-or-update write, delete, and the dormant-without-SDK gate.
 */
#[CoversClass(Tiger_Secret_Adapter_SecretsManager::class)]
final class SecretsManagerTest extends UnitTestCase
{
    /** A stand-in for Aws\SecretsManager\SecretsManagerClient. $existing = ids that already hold a value. */
    private function client(array $existing = []): object
    {
        return new class($existing) {
            public array $calls = [];
            public function __construct(private array $existing) {}
            public function getSecretValue(array $args): array
            {
                $this->calls[] = ['getSecretValue', $args];
                $id = $args['SecretId'];
                if (!array_key_exists($id, $this->existing)) { throw new \RuntimeException('ResourceNotFound'); }
                return ['SecretString' => $this->existing[$id]];
            }
            public function putSecretValue(array $args): array
            {
                $this->calls[] = ['putSecretValue', $args];
                if (!array_key_exists($args['SecretId'], $this->existing)) { throw new \RuntimeException('ResourceNotFound'); }
                $this->existing[$args['SecretId']] = $args['SecretString'];
                return [];
            }
            public function createSecret(array $args): array
            {
                $this->calls[] = ['createSecret', $args];
                $this->existing[$args['Name']] = $args['SecretString'];
                return [];
            }
            public function deleteSecret(array $args): array { $this->calls[] = ['deleteSecret', $args]; return []; }
        };
    }

    #[Test]
    public function reads_an_existing_secret_string(): void
    {
        $client = $this->client(['tiger/prod/stripe' => 'sk_live_x']);
        $a = new Tiger_Secret_Adapter_SecretsManager(['client' => $client, 'prefix' => 'tiger/prod']);
        $this->assertSame('sk_live_x', $a->get('stripe'));
    }

    #[Test]
    public function a_missing_secret_is_null(): void
    {
        $a = new Tiger_Secret_Adapter_SecretsManager(['client' => $this->client()]);
        $this->assertNull($a->get('absent'));
    }

    #[Test]
    public function put_creates_when_absent_then_updates_when_present(): void
    {
        $client = $this->client();
        $a = new Tiger_Secret_Adapter_SecretsManager(['client' => $client, 'prefix' => 'tiger/prod']);

        // Absent -> putSecretValue throws -> falls back to createSecret.
        $this->assertTrue($a->put('stripe', 'v1'));
        $this->assertSame('createSecret', $client->calls[1][0]);
        $this->assertSame('v1', $a->get('stripe'));

        // Present -> putSecretValue succeeds.
        $this->assertTrue($a->put('stripe', 'v2'));
        $this->assertSame('v2', $a->get('stripe'));
    }

    #[Test]
    public function delete_removes_the_secret(): void
    {
        $client = $this->client(['tiger/prod/x' => 'y']);
        $a = new Tiger_Secret_Adapter_SecretsManager(['client' => $client, 'prefix' => 'tiger/prod']);
        $this->assertTrue($a->delete('x'));
        $this->assertSame('deleteSecret', $client->calls[0][0]);
    }

    #[Test]
    public function is_dormant_without_the_aws_sdk(): void
    {
        if (class_exists('Aws\\SecretsManager\\SecretsManagerClient')) {
            $this->markTestSkipped('AWS SDK present; dormant path not demonstrable.');
        }
        $a = new Tiger_Secret_Adapter_SecretsManager([]);
        $this->assertFalse($a->available());
        $this->assertNull($a->get('x'));
    }
}
