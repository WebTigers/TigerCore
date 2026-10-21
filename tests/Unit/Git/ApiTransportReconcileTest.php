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
 * Tiger_Git_Transport_Api — the no-`.git` reconciler (TIGER-367): status / diff / pull / switchBranch.
 *
 * With no local repo, the transport keeps a `.tiger-git/head` merge-base marker and compares the on-disk
 * files to the base commit's tree by hashing each as a git blob. Proven against a canned GitHub fixture
 * (trees + blobs + compare), no network: working-tree A/M/D detection, `behind` from the compare API,
 * per-file line counts, a fast-forward pull that preserves non-conflicting local edits, a MERGE_CONFLICT
 * on an overlapping edit, and switchBranch refusing a dirty tree.
 */
#[CoversClass(Tiger_Git_Transport_Api::class)]
final class ApiTransportReconcileTest extends UnitTestCase
{
    private string $dir;
    private const HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const BASE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const DEV  = 'cccccccccccccccccccccccccccccccccccccccc';

    /** @var array<int,array{filename:string,status:string}> compare files, set per test */
    private array $compareFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger-git-rec-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/sub', 0700, true);
        file_put_contents($this->dir . '/keep.php', "keep a\nkeep b\n");  // unchanged (matches base)
        file_put_contents($this->dir . '/sub/mod.php', "a\nc\nd\n");       // modified (base has "a\nb\n")
        file_put_contents($this->dir . '/new.php', "added one\n");         // added (untracked)
        // gone.php is in the base tree but NOT on disk → deleted
        mkdir($this->dir . '/.tiger-git', 0700, true);
        file_put_contents($this->dir . '/.tiger-git/head', self::BASE . "\n");
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        parent::tearDown();
    }

    private function blobSha(string $c): string { return sha1('blob ' . strlen($c) . "\0" . $c); }

    private function tree(): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->dir, 'acme', 'mod', 'main', 'main', 'api', 'cred', true);
    }

    private function creds(): Tiger_Git_Credential
    {
        return new class extends Tiger_Git_Credential { public function resolve(string $ref): ?string { return 'ghp'; } };
    }

    /** The canned GitHub fixture: refs, commits→trees, trees→entries, blobs, compare. */
    private function api(): Tiger_Git_Transport_Api
    {
        $base = [
            'keep.php'    => ['sha' => $this->blobSha("keep a\nkeep b\n"), 'mode' => '100644', 'type' => 'blob'],
            'sub/mod.php' => ['sha' => $this->blobSha("a\nb\n"),           'mode' => '100644', 'type' => 'blob'],
            'gone.php'    => ['sha' => $this->blobSha("gone\n"),           'mode' => '100644', 'type' => 'blob'],
        ];
        $pulledSha = $this->blobSha("pulled!\n");
        $head = $base + ['pulled.php' => ['sha' => $pulledSha, 'mode' => '100644', 'type' => 'blob']];

        $blobs = [
            $this->blobSha("a\nb\n") => "a\nb\n",   // base of sub/mod.php (for the modify diff)
            $this->blobSha("gone\n") => "gone\n",    // base of gone.php (for the delete diff)
            $pulledSha               => "pulled!\n",
        ];

        $tree = function (array $entries) {
            return ['tree' => array_map(fn ($p, $m) => ['path' => $p, 'type' => 'blob', 'sha' => $m['sha'], 'mode' => $m['mode']], array_keys($entries), $entries)];
        };

        $fixture = function (string $method, string $url) use ($base, $head, $blobs, $tree): array {
            if (str_contains($url, '/git/ref/heads/main')) { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => self::HEAD]])]; }
            if (str_contains($url, '/git/ref/heads/dev'))  { return ['status' => 200, 'body' => json_encode(['object' => ['sha' => self::DEV]])]; }
            if (str_contains($url, '/git/commits/' . self::BASE)) { return ['status' => 200, 'body' => json_encode(['tree' => ['sha' => 'BASETREE']])]; }
            if (str_contains($url, '/git/commits/' . self::HEAD)) { return ['status' => 200, 'body' => json_encode(['tree' => ['sha' => 'HEADTREE']])]; }
            if (str_contains($url, '/git/commits/' . self::DEV))  { return ['status' => 200, 'body' => json_encode(['tree' => ['sha' => 'DEVTREE']])]; }
            if (str_contains($url, '/git/trees/BASETREE')) { return ['status' => 200, 'body' => json_encode($tree($base))]; }
            if (str_contains($url, '/git/trees/HEADTREE')) { return ['status' => 200, 'body' => json_encode($tree($head))]; }
            if (str_contains($url, '/git/trees/DEVTREE'))  { return ['status' => 200, 'body' => json_encode($tree($head))]; }
            if (str_contains($url, '/compare/')) { return ['status' => 200, 'body' => json_encode(['ahead_by' => 2, 'behind_by' => 0, 'files' => $this->compareFiles])]; }
            foreach ($blobs as $sha => $content) {
                if (str_contains($url, '/git/blobs/' . $sha)) { return ['status' => 200, 'body' => json_encode(['encoding' => 'base64', 'content' => base64_encode($content)])]; }
            }
            return ['status' => 404, 'body' => json_encode(['message' => 'not found'])];
        };
        return new Tiger_Git_Transport_Api($this->creds(), $fixture);
    }

    #[Test]
    public function status_reports_working_changes_and_behind(): void
    {
        $this->compareFiles = [['filename' => 'pulled.php', 'status' => 'added']];
        $st = $this->api()->status($this->tree());

        $this->assertSame('main', $st['branch']);
        $this->assertSame(0, $st['ahead'], 'the API transport has no local-only commits');
        $this->assertSame(2, $st['behind'], 'the remote is 2 commits ahead of the base');
        $this->assertFalse($st['clean']);

        $by = [];
        foreach ($st['changed'] as $c) { $by[$c['path']] = $c['state']; }
        $this->assertSame('M', $by['sub/mod.php']);
        $this->assertSame('A', $by['new.php']);
        $this->assertSame('D', $by['gone.php']);
        $this->assertArrayNotHasKey('keep.php', $by, 'an unchanged file is not listed');
        $this->assertArrayNotHasKey('.tiger-git/head', $by, 'the marker is never part of the working tree');
    }

    #[Test]
    public function diff_counts_added_and_removed_lines(): void
    {
        $diff = $this->api()->diff($this->tree());
        $by = [];
        foreach ($diff['files'] as $f) { $by[$f['path']] = $f; }

        $this->assertSame(2, $by['sub/mod.php']['added'], 'c and d added');
        $this->assertSame(1, $by['sub/mod.php']['removed'], 'b removed');
        $this->assertSame(1, $by['new.php']['added']);
        $this->assertSame(0, $by['new.php']['removed']);
        $this->assertSame(1, $by['gone.php']['removed']);
        $this->assertFalse($by['sub/mod.php']['binary']);
    }

    #[Test]
    public function pull_fast_forwards_and_preserves_local_edits(): void
    {
        $this->compareFiles = [['filename' => 'pulled.php', 'status' => 'added']];   // no overlap with local edits
        $res = $this->api()->pull($this->tree(), 'main');

        $this->assertTrue($res['updated']);
        $this->assertSame(self::HEAD, $res['ref']);
        $this->assertSame([], $res['conflicts']);
        $this->assertSame("pulled!\n", file_get_contents($this->dir . '/pulled.php'), 'the incoming file was materialised');
        $this->assertSame("added one\n", file_get_contents($this->dir . '/new.php'), 'the non-conflicting local edit survives');
        $this->assertSame(self::HEAD . "\n", file_get_contents($this->dir . '/.tiger-git/head'), 'the base advanced to the head');
    }

    #[Test]
    public function pull_refuses_a_conflicting_edit(): void
    {
        $this->compareFiles = [['filename' => 'sub/mod.php', 'status' => 'modified']];   // the file I edited locally
        try {
            $this->api()->pull($this->tree(), 'main');
            $this->fail('expected MERGE_CONFLICT');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::MERGE_CONFLICT, $e->code());
            $this->assertContains('sub/mod.php', $e->getDetail()['files']);
        }
        $this->assertFileDoesNotExist($this->dir . '/pulled.php', 'a refused pull touches nothing');
        $this->assertSame(self::BASE . "\n", file_get_contents($this->dir . '/.tiger-git/head'), 'the base is unchanged');
    }

    #[Test]
    public function switch_branch_refuses_a_dirty_tree(): void
    {
        try {
            $this->api()->switchBranch($this->tree(), 'dev');
            $this->fail('expected DIRTY_TREE');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::DIRTY_TREE, $e->code());
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}
