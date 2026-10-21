<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git;
use Tiger_Git_Credential;
use Tiger_Git_Environment;
use Tiger_Git_Provider_GitHub;
use Tiger_Git_WorkingTree;

/**
 * Tiger_Git_Provider_GitHub — the GitHub adapter (TIGER-364): WHICH host, composing the two transports.
 *
 * The behaviour that makes the provider worth its own class is the ROUTING, so that's what's asserted
 * with no network and no real git: both a command-runner seam (shell) and an HTTP seam (API) are
 * injected, and each test checks which one a given operation reached. The load-bearing case —
 * release/archive operations go to the API EVEN on a shell connection — is pinned explicitly.
 */
#[CoversClass(Tiger_Git_Provider_GitHub::class)]
final class GitHubProviderTest extends UnitTestCase
{
    private string $dir;
    /** @var array<int,array> git commands the runner saw */
    private array $gitCalls = [];
    /** @var array<int,array> HTTP requests the API seam saw */
    private array $httpCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger-git-prov-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
        Tiger_Git_Environment::setProbe(fn () => true);   // keep detect() off the network
    }

    protected function tearDown(): void
    {
        Tiger_Git_Environment::setProbe(null);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function tree(string $transport): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->dir, 'acme', 'mod', 'main', 'main', $transport, 'cred-1', true);
    }

    private function provider(): Tiger_Git_Provider_GitHub
    {
        $creds = new class extends Tiger_Git_Credential {
            public function resolve(string $ref): ?string { return 'ghp_token'; }
        };

        // The shell runner answers just enough git for status(); it records every call.
        $runner = function (array $args, string $dir, array $env): array {
            $this->gitCalls[] = $args;
            if ($args === ['rev-parse', '--abbrev-ref', 'HEAD'])  { return ['code' => 0, 'out' => "main\n", 'err' => '']; }
            if ($args === ['status', '--porcelain'])              { return ['code' => 0, 'out' => '',       'err' => '']; }
            if (in_array('@{upstream}', $args, true))             { return ['code' => 1, 'out' => '', 'err' => 'no upstream']; }
            return ['code' => 0, 'out' => '', 'err' => ''];
        };

        // The API seam records every call and answers the few endpoints these tests reach.
        $http = function (string $method, string $url, array $headers, ?string $body): array {
            $this->httpCalls[] = ['method' => $method, 'url' => $url];
            if (str_contains($url, '/branches')) {
                return ['status' => 200, 'body' => json_encode([['name' => 'main'], ['name' => 'dev']])];
            }
            if (str_contains($url, '/releases')) {
                return ['status' => 200, 'body' => json_encode([['tag_name' => 'v1.0.0', 'target_commitish' => 'abc', 'created_at' => 'now', 'prerelease' => false]])];
            }
            return ['status' => 404, 'body' => '[]'];
        };

        return new Tiger_Git_Provider_GitHub($creds, $http, $runner);
    }

    #[Test]
    public function provider_key_is_github(): void
    {
        $this->assertSame('github', $this->provider()->provider());
    }

    #[Test]
    public function name_reports_the_detected_host_transport(): void
    {
        $this->assertSame(Tiger_Git_Environment::detect()['transport'], $this->provider()->name());
    }

    #[Test]
    public function a_git_op_on_a_shell_connection_uses_the_git_binary(): void
    {
        if (!Tiger_Git_Environment::execUsable()) {
            $this->markTestSkipped('proc_open unavailable — shell routing cannot be exercised');
        }
        $status = $this->provider()->status($this->tree(Tiger_Git::TRANSPORT_SHELL));

        $this->assertSame('main', $status['branch']);
        $this->assertTrue($status['clean']);
        $this->assertNotEmpty($this->gitCalls, 'the git binary was driven');
        $this->assertSame([], $this->httpCalls, 'no API call for a shell git op');
    }

    #[Test]
    public function a_git_op_on_an_api_connection_uses_the_api(): void
    {
        $branches = $this->provider()->branches($this->tree(Tiger_Git::TRANSPORT_API));

        $this->assertSame(['main', 'dev'], $branches);
        $this->assertNotEmpty($this->httpCalls, 'the API was called');
        $this->assertSame([], $this->gitCalls, 'the git binary was not touched for an API connection');
    }

    #[Test]
    public function releases_go_to_the_api_even_on_a_shell_connection(): void
    {
        // The whole reason the provider exists: a GitHub release is not a git concept, so a shell
        // connection still reaches it over the API — never the git binary.
        $releases = $this->provider()->listReleases($this->tree(Tiger_Git::TRANSPORT_SHELL));

        $this->assertSame('v1.0.0', $releases[0]['tag']);
        $this->assertNotEmpty($this->httpCalls, 'releases were fetched over the API');
        $this->assertStringContainsString('/releases', $this->httpCalls[0]['url']);
        $this->assertSame([], $this->gitCalls, 'the git binary is never used for releases');
    }
}
