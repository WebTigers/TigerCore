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
 * Tiger_Git_Transport_Api — the commit spike (TIGER-364): create a commit through the GitHub Git Data
 * API with no local `.git` (blob → tree → commit → fast-forward ref). The HTTP layer is a seam, so the
 * whole four-step dance is asserted against canned responses — no network — including the payloads and
 * the NON_FAST_FORWARD / missing-credential / nothing-to-commit failure paths.
 */
#[CoversClass(Tiger_Git_Transport_Api::class)]
final class ApiTransportCommitTest extends UnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger-git-api-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/src', 0700, true);
        file_put_contents($this->dir . '/src/a.php', 'hello world');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/src/a.php');
        @rmdir($this->dir . '/src');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function tree(): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->dir, 'acme', 'mod', 'main', 'main', 'api', 'cred-1', true);
    }

    /** A credential manager that resolves to a fixed token (or null) — no DB. */
    private function creds(?string $token): Tiger_Git_Credential
    {
        return new class($token) extends Tiger_Git_Credential {
            public function __construct(private ?string $token) {}
            public function resolve(string $ref): ?string { return $this->token; }
        };
    }

    /**
     * A GitHub API fake. Records every call into $calls (by ref) and returns canned happy-path responses;
     * $override lets a test replace the response for a matched (method, url-substring).
     *
     * @param array<int,array> $calls
     */
    private function http(array &$calls, array $override = []): callable
    {
        return function (string $method, string $url, array $headers, ?string $body) use (&$calls, $override): array {
            $calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'json' => $body !== null ? json_decode($body, true) : null];
            foreach ($override as [$m, $needle, $resp]) {
                if ($method === $m && str_contains($url, $needle)) { return $resp; }
            }
            if ($method === 'GET'   && str_contains($url, '/git/ref/heads/'))  { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => 'HEAD1']])]; }
            if ($method === 'GET'   && str_contains($url, '/git/commits/'))    { return ['status' => 200, 'body' => json_encode(['tree' => ['sha' => 'TREE0']])]; }
            if ($method === 'POST'  && str_contains($url, '/git/blobs'))       { return ['status' => 201, 'body' => json_encode(['sha' => 'BLOB1'])]; }
            if ($method === 'POST'  && str_contains($url, '/git/trees'))       { return ['status' => 201, 'body' => json_encode(['sha' => 'TREE1'])]; }
            if ($method === 'POST'  && str_contains($url, '/git/commits'))     { return ['status' => 201, 'body' => json_encode(['sha' => 'NEW1'])]; }
            if ($method === 'POST'  && str_contains($url, '/git/refs'))        { return ['status' => 201, 'body' => json_encode(['ref' => 'refs/heads/new'])]; }
            if ($method === 'PATCH' && str_contains($url, '/git/refs/heads/')) { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => 'NEW1']])]; }
            return ['status' => 404, 'body' => json_encode(['message' => 'not found'])];
        };
    }

    #[Test]
    public function commit_runs_the_blob_tree_commit_ref_dance_and_returns_the_new_sha(): void
    {
        $calls = [];
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($calls));

        // Commit one modified file (on disk) and one deleted file (not on disk).
        $sha = $api->commit($this->tree(), 'Add feature', ['src/a.php', 'old.php']);

        $this->assertSame('NEW1', $sha);

        // The four-step sequence, in order.
        $seq = array_map(fn ($c) => $c['method'] . ' ' . parse_url($c['url'], PHP_URL_PATH), $calls);
        $this->assertSame([
            'GET /repos/acme/mod/git/ref/heads/main',
            'GET /repos/acme/mod/git/commits/HEAD1',
            'POST /repos/acme/mod/git/blobs',
            'POST /repos/acme/mod/git/trees',
            'POST /repos/acme/mod/git/commits',
            'PATCH /repos/acme/mod/git/refs/heads/main',
        ], $seq);

        // The blob is the file content, base64.
        $blob = $calls[2]['json'];
        $this->assertSame(base64_encode('hello world'), $blob['content']);
        $this->assertSame('base64', $blob['encoding']);

        // The tree builds on the base tree; the modified file is a blob entry, the missing one a deletion.
        $treeBody = $calls[3]['json'];
        $this->assertSame('TREE0', $treeBody['base_tree']);
        $byPath = [];
        foreach ($treeBody['tree'] as $e) { $byPath[$e['path']] = $e; }
        $this->assertSame('BLOB1', $byPath['src/a.php']['sha']);
        $this->assertSame('100644', $byPath['src/a.php']['mode']);
        $this->assertNull($byPath['old.php']['sha'], 'a path absent on disk is staged as a deletion');

        // The commit parents the old head; the ref update is a non-forced fast-forward.
        $this->assertSame(['HEAD1'], $calls[4]['json']['parents']);
        $this->assertSame('TREE1', $calls[4]['json']['tree']);
        $this->assertSame('NEW1', $calls[5]['json']['sha']);
        $this->assertFalse($calls[5]['json']['force']);

        // Every call is authenticated.
        $this->assertSame('Bearer ghp_token', $calls[0]['headers']['Authorization']);
    }

    #[Test]
    public function a_non_fast_forward_ref_update_is_reported_not_forced(): void
    {
        $calls = [];
        $http = $this->http($calls, [
            ['PATCH', '/git/refs/heads/', ['status' => 422, 'body' => json_encode(['message' => 'Update is not a fast forward'])]],
        ]);
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);

        try {
            $api->commit($this->tree(), 'msg', ['src/a.php']);
            $this->fail('expected a NON_FAST_FORWARD exception');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::NON_FAST_FORWARD, $e->code());
        }
    }

    #[Test]
    public function an_empty_path_list_is_nothing_to_commit(): void
    {
        $t = []; $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($t));
        $this->expectException(Tiger_Git_Exception::class);
        $this->expectExceptionMessageMatches('/explicit paths/');
        $api->commit($this->tree(), 'msg', []);
    }

    #[Test]
    public function an_empty_message_is_refused(): void
    {
        $t = []; $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($t));
        $this->expectException(Tiger_Git_Exception::class);
        $api->commit($this->tree(), '   ', ['src/a.php']);
    }

    #[Test]
    public function a_missing_credential_fails_before_any_write(): void
    {
        $calls = [];
        $api = new Tiger_Git_Transport_Api($this->creds(null), $this->http($calls));
        try {
            $api->commit($this->tree(), 'msg', ['src/a.php']);
            $this->fail('expected CREDENTIAL_MISSING');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::CREDENTIAL_MISSING, $e->code());
        }
        $this->assertSame([], $calls, 'no API call is made without a credential');
    }

    #[Test]
    public function create_branch_posts_a_new_ref_off_the_current_head(): void
    {
        $calls = [];
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($calls));
        $api->createBranch($this->tree(), 'feature/x');

        $post = $calls[1];
        $this->assertSame('POST', $post['method']);
        $this->assertStringEndsWith('/git/refs', parse_url($post['url'], PHP_URL_PATH));
        $this->assertSame('refs/heads/feature/x', $post['json']['ref']);
        $this->assertSame('HEAD1', $post['json']['sha']);
    }

    #[Test]
    public function create_branch_refuses_the_default_branch(): void
    {
        $t = []; $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($t));
        $this->expectException(Tiger_Git_Exception::class);
        $api->createBranch($this->tree(), 'main');
    }

    #[Test]
    public function branches_lists_names(): void
    {
        $calls = [];
        $http = $this->http($calls, [
            ['GET', '/branches', ['status' => 200, 'body' => json_encode([['name' => 'main'], ['name' => 'dev']])]],
        ]);
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $http);
        $this->assertSame(['main', 'dev'], $api->branches($this->tree()));
    }

    #[Test]
    public function push_is_a_verified_no_op_on_the_api_transport(): void
    {
        $calls = [];
        $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($calls));
        $res = $api->push($this->tree(), 'main');
        $this->assertTrue($res['pushed']);
        $this->assertSame('HEAD1', $res['ref']);
    }

    #[Test]
    public function deferred_methods_report_not_implemented(): void
    {
        $t = []; $api = new Tiger_Git_Transport_Api($this->creds('ghp_token'), $this->http($t));
        try {
            $api->status($this->tree());
            $this->fail('expected NOT_IMPLEMENTED');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::NOT_IMPLEMENTED, $e->code());
        }
    }
}
