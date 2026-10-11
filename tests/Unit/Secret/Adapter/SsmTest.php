<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Secret_Adapter_Ssm;
use Tiger_Secret_Writer_Interface;

/**
 * Tiger_Secret_Adapter_Ssm — AWS Parameter Store. Tested with an INJECTED client (no SDK, no network):
 * prefix joining, absolute-path override, the write side, and fail-soft nulls. The capability gate
 * (dormant without the AWS SDK) is asserted when the SDK genuinely isn't present.
 */
#[CoversClass(Tiger_Secret_Adapter_Ssm::class)]
final class SsmTest extends UnitTestCase
{
    /** A minimal stand-in for Aws\Ssm\SsmClient recording its calls. */
    private function client(array $params = []): object
    {
        return new class($params) {
            public array $calls = [];
            public function __construct(private array $params) {}
            public function getParameter(array $args): array
            {
                $this->calls[] = ['getParameter', $args];
                $name = $args['Name'];
                return ['Parameter' => ['Value' => $this->params[$name] ?? '']];
            }
            public function putParameter(array $args): array { $this->calls[] = ['putParameter', $args]; return []; }
            public function deleteParameter(array $args): array { $this->calls[] = ['deleteParameter', $args]; return []; }
        };
    }

    #[Test]
    public function is_available_with_an_injected_client(): void
    {
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $this->client()]);
        $this->assertTrue($a->available());
        $this->assertInstanceOf(Tiger_Secret_Writer_Interface::class, $a);
    }

    #[Test]
    public function joins_a_relative_path_under_the_prefix(): void
    {
        $client = $this->client(['/tiger/prod/node/crypto_key' => 'the-key']);
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $client, 'prefix' => '/tiger/prod/node']);

        $this->assertSame('the-key', $a->get('crypto_key'));
        $this->assertSame('/tiger/prod/node/crypto_key', $client->calls[0][1]['Name']);
        $this->assertTrue($client->calls[0][1]['WithDecryption']);
    }

    #[Test]
    public function an_absolute_path_ignores_the_prefix(): void
    {
        $client = $this->client(['/other/abs/x' => 'abs-val']);
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $client, 'prefix' => '/tiger/prod/node']);

        $this->assertSame('abs-val', $a->get('/other/abs/x'));
    }

    #[Test]
    public function a_missing_or_empty_parameter_is_null(): void
    {
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $this->client()]);   // returns '' for anything
        $this->assertNull($a->get('nope'));
    }

    #[Test]
    public function put_stores_a_securestring_and_delete_removes_it(): void
    {
        $client = $this->client();
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $client, 'prefix' => '/tiger/prod']);

        $this->assertTrue($a->put('relay/opensrs_api_key', 'abc123'));
        $put = $client->calls[0][1];
        $this->assertSame('/tiger/prod/relay/opensrs_api_key', $put['Name']);
        $this->assertSame('abc123', $put['Value']);
        $this->assertSame('SecureString', $put['Type']);
        $this->assertTrue($put['Overwrite']);

        $this->assertTrue($a->delete('relay/opensrs_api_key'));
        $this->assertSame('deleteParameter', $client->calls[1][0]);
    }

    #[Test]
    public function a_throwing_client_fails_soft(): void
    {
        $boom = new class {
            public function getParameter(array $args): array { throw new \RuntimeException('aws down'); }
        };
        $a = new Tiger_Secret_Adapter_Ssm(['client' => $boom]);
        $this->assertNull($a->get('x'));
    }

    #[Test]
    public function is_dormant_without_the_aws_sdk(): void
    {
        if (class_exists('Aws\\Ssm\\SsmClient')) {
            $this->markTestSkipped('AWS SDK is installed in this environment; the dormant path cannot be shown.');
        }
        $a = new Tiger_Secret_Adapter_Ssm([]);   // no injected client, no SDK
        $this->assertFalse($a->available());
        $this->assertNull($a->get('anything'));
        $this->assertFalse($a->put('x', 'y'));
    }
}
