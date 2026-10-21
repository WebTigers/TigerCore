<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git_Credential;
use Tiger_Git_Environment;
use Tiger_Git_Exception;
use Tiger_Git_Transport_Shell;
use Tiger_Git_WorkingTree;

/**
 * Tiger_Git_Transport_Shell — the git-binary path, proven END TO END against a real local repo and a
 * bare "remote" (file://), with a second clone used to force a genuine non-fast-forward. No network.
 * Skipped where git isn't available.
 */
#[CoversClass(Tiger_Git_Transport_Shell::class)]
final class ShellTransportTest extends UnitTestCase
{
    private string $base;
    private string $work;
    private string $remote;

    protected function setUp(): void
    {
        parent::setUp();
        if (Tiger_Git_Environment::gitVersion() === null) {
            $this->markTestSkipped('git binary not available');
        }
        $this->base   = sys_get_temp_dir() . '/tiger-git-sh-' . bin2hex(random_bytes(4));
        $this->work   = $this->base . '/work';
        $this->remote = $this->base . '/remote.git';
        mkdir($this->base, 0700, true);

        $this->raw($this->base, ['init', '--bare', '-b', 'main', 'remote.git']);
        $this->raw($this->base, ['init', '-b', 'main', 'work']);
        $this->raw($this->work, ['config', 'user.email', 'dev@test']);
        $this->raw($this->work, ['config', 'user.name', 'Dev']);
        file_put_contents($this->work . '/README.md', "init\n");
        $this->raw($this->work, ['add', '-A']);
        $this->raw($this->work, ['commit', '-m', 'initial']);
        $this->raw($this->work, ['remote', 'add', 'origin', $this->remote]);
        $this->raw($this->work, ['push', '-u', 'origin', 'main']);
    }

    protected function tearDown(): void
    {
        if (isset($this->base) && is_dir($this->base)) { $this->rrmdir($this->base); }
        parent::tearDown();
    }

    private function tree(): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->work, 'acme', 'mod', 'main', 'main', 'shell', '', false);
    }

    private function transport(): Tiger_Git_Transport_Shell
    {
        // No token — a file:// remote needs none.
        $creds = new class extends Tiger_Git_Credential {
            public function resolve(string $ref): ?string { return null; }
        };
        return new Tiger_Git_Transport_Shell($creds);
    }

    #[Test]
    public function name_is_shell(): void
    {
        $this->assertSame('shell', $this->transport()->name());
    }

    #[Test]
    public function status_is_clean_then_sees_a_change(): void
    {
        $t = $this->transport();
        $clean = $t->status($this->tree());
        $this->assertSame('main', $clean['branch']);
        $this->assertTrue($clean['clean']);

        file_put_contents($this->work . '/new.php', "<?php\n");
        $dirty = $t->status($this->tree());
        $this->assertFalse($dirty['clean']);
        $this->assertContains('new.php', array_column($dirty['changed'], 'path'));
    }

    #[Test]
    public function commit_stages_and_returns_a_sha(): void
    {
        file_put_contents($this->work . '/feature.php', "<?php // feature\n");
        $sha = $this->transport()->commit($this->tree(), 'Add feature', ['feature.php']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $sha);
        $this->assertTrue($this->transport()->status($this->tree())['clean'], 'tree is clean after commit');
    }

    #[Test]
    public function commit_with_nothing_staged_is_nothing_to_commit(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        $this->expectExceptionMessageMatches('/nothing to commit/i');
        $this->transport()->commit($this->tree(), 'empty');   // no changes on disk
    }

    #[Test]
    public function create_and_list_branches(): void
    {
        $t = $this->transport();
        $t->createBranch($this->tree(), 'feature/x');
        $this->assertContains('feature/x', $t->branches($this->tree()));
    }

    #[Test]
    public function create_branch_refuses_the_default(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        $this->transport()->createBranch($this->tree(), 'main');
    }

    #[Test]
    public function switch_branch_refuses_a_dirty_tree(): void
    {
        $t = $this->transport();
        $t->createBranch($this->tree(), 'feature/x');
        file_put_contents($this->work . '/dirty.php', "<?php\n");
        try {
            $t->switchBranch($this->tree(), 'feature/x');
            $this->fail('expected DIRTY_TREE');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::DIRTY_TREE, $e->code());
        }
    }

    #[Test]
    public function push_advances_the_remote(): void
    {
        file_put_contents($this->work . '/p.php', "<?php\n");
        $this->transport()->commit($this->tree(), 'to push', ['p.php']);
        $res = $this->transport()->push($this->tree(), 'main');
        $this->assertTrue($res['pushed']);

        $localHead  = trim($this->raw($this->work, ['rev-parse', 'HEAD'])['out']);
        $remoteHead = trim($this->raw($this->remote, ['rev-parse', 'main'])['out']);
        $this->assertSame($localHead, $remoteHead, 'the bare remote advanced to the pushed commit');
    }

    #[Test]
    public function push_refuses_a_non_fast_forward(): void
    {
        // A second clone advances the remote behind our back.
        $this->raw($this->base, ['clone', $this->remote, 'other']);
        $other = $this->base . '/other';
        $this->raw($other, ['config', 'user.email', 'o@test']);
        $this->raw($other, ['config', 'user.name', 'Other']);
        file_put_contents($other . '/remote-change.php', "<?php\n");
        $this->raw($other, ['add', '-A']);
        $this->raw($other, ['commit', '-m', 'remote moves']);
        $this->raw($other, ['push', 'origin', 'main']);

        // Now our local commits on a now-stale main and pushes → must be refused, never forced.
        file_put_contents($this->work . '/local-change.php', "<?php\n");
        $this->transport()->commit($this->tree(), 'local moves', ['local-change.php']);
        try {
            $this->transport()->push($this->tree(), 'main');
            $this->fail('expected NON_FAST_FORWARD');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::NON_FAST_FORWARD, $e->code());
        }
    }

    #[Test]
    public function tag_creates_annotated_and_pushes_it(): void
    {
        $out = $this->transport()->tag($this->tree(), 'v1.0.0');
        $this->assertSame('v1.0.0', $out['tag']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $out['sha']);
        $remoteTags = $this->raw($this->remote, ['tag'])['out'];
        $this->assertStringContainsString('v1.0.0', $remoteTags, 'the tag reached the remote');
    }

    #[Test]
    public function diff_reports_numstat(): void
    {
        file_put_contents($this->work . '/README.md', "init\nmore\nlines\n");
        $diff = $this->transport()->diff($this->tree());
        $paths = array_column($diff['files'], 'path');
        $this->assertContains('README.md', $paths);
    }

    #[Test]
    public function release_ops_are_a_provider_concern(): void
    {
        try {
            $this->transport()->listReleases($this->tree());
            $this->fail('expected NOT_IMPLEMENTED');
        } catch (Tiger_Git_Exception $e) {
            $this->assertSame(Tiger_Git_Exception::NOT_IMPLEMENTED, $e->code());
        }
    }

    // --- helpers ---------------------------------------------------------------------------------

    /** Run git directly for setup/verification (asserts success). */
    private function raw(string $cwd, array $args): array
    {
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(array_merge(['git'], $args), $desc, $pipes, $cwd);
        $out = stream_get_contents($pipes[1]) ?: '';
        $err = stream_get_contents($pipes[2]) ?: '';
        foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
        $code = proc_close($proc);
        $this->assertSame(0, $code, "setup `git " . implode(' ', $args) . "` failed: $err");
        return ['out' => $out, 'err' => $err];
    }

    private function rrmdir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}
