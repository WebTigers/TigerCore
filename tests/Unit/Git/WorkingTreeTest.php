<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git_Exception;
use Tiger_Git_WorkingTree;

/**
 * Tiger_Git_WorkingTree — the immutable per-module tree handed to a transport. The load-bearing
 * behavior is `resolve()`: every path a transport touches must stay UNDER the module root, so `..`,
 * an absolute path, and an escaping target are all refused structurally (not by discipline).
 */
#[CoversClass(Tiger_Git_WorkingTree::class)]
final class WorkingTreeTest extends UnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/tiger-git-wt-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/sub', 0700, true);
        file_put_contents($this->dir . '/module.json', '{}');
        file_put_contents($this->dir . '/sub/x.php', '<?php');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/sub/x.php');
        @unlink($this->dir . '/module.json');
        @rmdir($this->dir . '/sub');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function tree(): Tiger_Git_WorkingTree
    {
        return new Tiger_Git_WorkingTree($this->dir, 'WebTigers', 'tiger-core', 'main', 'main', 'api', 'cred-1', true);
    }

    #[Test]
    public function it_exposes_its_facts(): void
    {
        $t = $this->tree();
        $this->assertSame('WebTigers/tiger-core', $t->slug());
        $this->assertSame('api', $t->transport());
        $this->assertSame('cred-1', $t->credentialRef());
        $this->assertTrue($t->writeEnabled());
        $this->assertSame(realpath($this->dir), $t->dir());
    }

    #[Test]
    public function resolve_accepts_an_in_tree_path(): void
    {
        $t = $this->tree();
        $this->assertSame(realpath($this->dir . '/sub/x.php'), $t->resolve('sub/x.php'));
    }

    #[Test]
    public function resolve_allows_a_not_yet_existing_in_tree_path(): void
    {
        $t = $this->tree();
        // The tree canonicalizes its root via realpath(); a not-yet-existing path is that root + rel.
        $this->assertSame(realpath($this->dir) . '/sub/new.php', $t->resolve('sub/new.php'));
    }

    #[Test]
    public function resolve_refuses_a_parent_traversal(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        $this->tree()->resolve('../escape.php');
    }

    #[Test]
    public function resolve_refuses_an_absolute_path(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        $this->tree()->resolve('/etc/passwd');
    }

    #[Test]
    public function constructor_refuses_a_missing_directory(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        new Tiger_Git_WorkingTree('/no/such/dir/' . bin2hex(random_bytes(4)), 'a', 'b', 'main', 'main', 'api');
    }

    #[Test]
    public function constructor_refuses_an_invalid_slug(): void
    {
        $this->expectException(Tiger_Git_Exception::class);
        new Tiger_Git_WorkingTree($this->dir, 'owner/evil', 'repo', 'main', 'main', 'api');
    }

    #[Test]
    public function with_branch_returns_a_copy_on_the_new_branch(): void
    {
        $t = $this->tree()->withBranch('feature/x');
        $this->assertSame('feature/x', $t->currentBranch());
        $this->assertSame('main', $t->defaultBranch());
    }
}
