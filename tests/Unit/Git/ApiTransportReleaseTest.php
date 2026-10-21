<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git_Credential;
use Tiger_Git_Exception;
use Tiger_Git_Transport_Api;
use Tiger_Git_WorkingTree;

/**
 * Tiger_Git_Transport_Api — the provider-API verbs (TIGER-364): listing releases and fetching a release
 * archive. These are the operations the GitHub adapter routes to the API even for a shell connection,
 * because a GitHub release is not a git concept. Asserted against canned responses (the HTTP seam) — no
 * network — including the tarball's 302→codeload capture and the host-allowlist guard on the redirect.
 */
#[CoversClass(Tiger_Git_Transport_Api::class)]
final class ApiTransportReleaseTest extends UnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger-git-rel-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/archive.tar.gz');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function tree(): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->dir, 'acme', 'mod', 'main', 'main', 'api', 'cred-1', true);
    }

    private function creds(?string $token): Tiger_Git_Credential
    {
        return new class($token) extends Tiger_Git_Credential {
            public function __construct(private ?string $token) {}
            public function resolve(string $ref): ?string { return $this->token; }
        };
    }

    /** @param array<int,array> $calls */
    private function http(array &$calls, callable $responder): callable
    {
        return function (string $method, string $url, array $headers, ?string $body) use (&$calls, $responder): array {
            $calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            return $responder($method, $url);
        };
    }

    #[Test]
    public function list_releases_maps_newest_first_and_skips_drafts(): void
    {
        $calls = [];
        $http = $this->http($calls, function (string $m, string $url): array {
            if (str_contains($url, '/releases')) {
                return ['status' => 200, 'body' => json_encode([
                    ['tag_name' => 'v1.1.0', 'name' => 'Spring release', 'target_commitish' => 'abc123', 'created_at' => '2026-03-01T00:00:00Z', 'prerelease' => false, 'draft' => false],
                    ['tag_name' => 'v1.1.0-rc1', 'name' => '',              'target_commitish' => 'main',   'created_at' => '2026-02-01T00:00:00Z', 'prerelease' => true,  'draft' => false],
                    ['tag_name' => 'wip',        'name' => 'Draft',         'target_commitish' => 'main',   'created_at' => '2026-02-15T00:00:00Z', 'prerelease' => false, 'draft' => true],
                ])];
            }
            return ['status' => 404, 'body' => '[]'];
        });
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);

        $releases = $api->listReleases($this->tree());

        $this->assertCount(2, $releases, 'the draft is excluded');
        $this->assertSame('v1.1.0', $releases[0]['tag']);
        $this->assertSame('Spring release', $releases[0]['name']);
        $this->assertSame('abc123', $releases[0]['sha']);
        $this->assertFalse($releases[0]['prerelease']);

        // Empty name falls back to the tag; the prerelease flag is carried.
        $this->assertSame('v1.1.0-rc1', $releases[1]['name']);
        $this->assertTrue($releases[1]['prerelease']);

        $this->assertStringContainsString('/repos/acme/mod/releases', $calls[0]['url']);
        $this->assertSame('Bearer ghp_token', $calls[0]['headers']['Authorization']);
    }

    #[Test]
    public function fetch_archive_captures_the_302_and_streams_from_codeload(): void
    {
        $calls = [];
        $http = $this->http($calls, function (string $m, string $url): array {
            if (str_contains($url, 'api.github.com') && str_contains($url, '/tarball/')) {
                return ['status' => 302, 'headers' => ['Location' => 'https://codeload.github.com/acme/mod/tar.gz/v1.0.0'], 'body' => ''];
            }
            if (str_contains($url, 'codeload.github.com')) {
                return ['status' => 200, 'body' => 'TARBALL-BYTES'];
            }
            return ['status' => 404, 'body' => 'nope'];
        });
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);

        $dest = $this->dir . '/archive.tar.gz';
        $path = $api->fetchArchive($this->tree(), 'v1.0.0', $dest);

        $this->assertSame($dest, $path);
        $this->assertSame('TARBALL-BYTES', file_get_contents($dest));

        // Two hops: the api.github.com tarball request (authenticated), then the CDN (NOT authenticated).
        $this->assertStringContainsString('api.github.com', $calls[0]['url']);
        $this->assertSame('Bearer ghp_token', $calls[0]['headers']['Authorization']);
        $this->assertStringContainsString('codeload.github.com', $calls[1]['url']);
        $this->assertArrayNotHasKey('Authorization', $calls[1]['headers'], 'the signed CDN URL is not sent our token');
    }

    #[Test]
    public function fetch_archive_refuses_a_redirect_off_the_allowlist(): void
    {
        $calls = [];
        $http = $this->http($calls, fn (string $m, string $url): array =>
            ['status' => 302, 'headers' => ['Location' => 'https://evil.example.com/acme/mod.tar.gz'], 'body' => '']
        );
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);

        try {
            $api->fetchArchive($this->tree(), 'v1.0.0', $this->dir . '/archive.tar.gz');
            $this->fail('expected HOST_NOT_ALLOWED');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::HOST_NOT_ALLOWED, $e->code());
        }
        $this->assertCount(1, $calls, 'the off-allowlist redirect is never followed');
        $this->assertFileDoesNotExist($this->dir . '/archive.tar.gz');
    }

    #[Test]
    public function fetch_archive_refuses_a_bad_tag_before_any_call(): void
    {
        $calls = [];
        $http = $this->http($calls, fn () => ['status' => 200, 'body' => '']);
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);

        try {
            $api->fetchArchive($this->tree(), '../../etc/passwd', $this->dir . '/x.tar.gz');
            $this->fail('expected INVALID_REF');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::INVALID_REF, $e->code());
        }
        $this->assertSame([], $calls, 'nothing is fetched for a rejected tag');
    }
}
