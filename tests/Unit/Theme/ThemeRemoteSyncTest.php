<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Theme_Remote;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Theme_Remote::sync() — the fetch pipeline, exercised network-free through a test double that
 * overrides the two HTTP seams (_get / _getMany) with canned responses. Covers the manifest diff,
 * the atomic install, hash verification, prune, and the error paths, without touching the network.
 */
#[CoversClass(Tiger_Theme_Remote::class)]
final class ThemeRemoteSyncTest extends UnitTestCase
{
    private string $mirror = '';
    private string $base = 'https://cdn.test/marketing';

    protected function setUp(): void
    {
        parent::setUp();
        $this->mirror = sys_get_temp_dir() . '/tigsync-' . uniqid('', true);
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['theme' => [
                'content_url'    => $this->base,
                'content_mirror' => $this->mirror,
            ]],
        ]));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->mirror)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->mirror, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($this->mirror);
        }
        parent::tearDown();
    }

    /** Build a fake remote whose manifest + files come from the given [relpath => body] map. */
    private function remoteWith(array $files, ?string $generated = '2026-10-30T00:00:00Z'): FakeThemeRemote
    {
        $manifest = ['generated' => $generated, 'files' => []];
        $responses = [];
        foreach ($files as $rel => $body) {
            $manifest['files'][$rel] = ['sha256' => hash('sha256', $body), 'size' => strlen($body)];
            $responses[$this->base . '/' . $rel] = $body;
        }
        $responses[$this->base . '/index.json'] = json_encode($manifest);

        $r = new FakeThemeRemote();
        $r->responses = $responses;
        return $r;
    }

    #[Test]
    public function cold_sync_installs_every_file_and_writes_the_manifest(): void
    {
        $r = $this->remoteWith([
            'get-tiger.phtml'    => '<p>get</p>',
            'how-it-works.phtml' => '<p>how</p>',
        ]);
        $res = $r->sync();

        $this->assertTrue($res['ok']);
        $this->assertSame(2, $res['changed']);
        $this->assertSame(2, $res['files']);
        $this->assertSame('', $res['error']);
        $this->assertStringEqualsFile($this->mirror . '/get-tiger.phtml', '<p>get</p>');
        $this->assertStringEqualsFile($this->mirror . '/how-it-works.phtml', '<p>how</p>');
        $this->assertFileExists($this->mirror . '/.manifest.json');
        $this->assertSame($this->mirror, $r->contentRoot());        // now holds content
        $this->assertSame('remote 2026-10-30T00:00:00Z', $r->version());
        $this->assertSame(2, $r->status()['files']);
    }

    #[Test]
    public function an_unchanged_second_sync_downloads_nothing(): void
    {
        $this->remoteWith(['a.phtml' => 'A'])->sync();           // seed
        $res = $this->remoteWith(['a.phtml' => 'A'])->sync();    // same bytes → nothing to do
        $this->assertTrue($res['ok']);
        $this->assertSame(0, $res['changed']);
    }

    #[Test]
    public function a_changed_file_is_re_fetched(): void
    {
        $this->remoteWith(['a.phtml' => 'A'])->sync();
        $res = $this->remoteWith(['a.phtml' => 'A-edited'])->sync();
        $this->assertSame(1, $res['changed']);
        $this->assertStringEqualsFile($this->mirror . '/a.phtml', 'A-edited');
    }

    #[Test]
    public function a_dropped_file_is_pruned(): void
    {
        $this->remoteWith(['a.phtml' => 'A', 'b.phtml' => 'B'])->sync();
        $this->assertFileExists($this->mirror . '/b.phtml');
        $res = $this->remoteWith(['a.phtml' => 'A'])->sync();    // b.phtml gone from the manifest
        $this->assertSame(1, $res['removed']);
        $this->assertFileDoesNotExist($this->mirror . '/b.phtml');
        $this->assertFileExists($this->mirror . '/a.phtml');
    }

    #[Test]
    public function a_hash_mismatch_is_refused(): void
    {
        $r = $this->remoteWith(['a.phtml' => 'A']);
        // Corrupt the served body so it no longer matches the manifest's sha256.
        $r->responses[$this->base . '/a.phtml'] = 'TAMPERED';
        $res = $r->sync();
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('Hash mismatch', $res['error']);
    }

    #[Test]
    public function an_unreadable_manifest_errors(): void
    {
        $r = new FakeThemeRemote();
        $r->responses = [];   // no manifest response → _get returns null
        $res = $r->sync();
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('index.json', $res['error']);
    }

    #[Test]
    public function a_manifest_that_is_not_a_content_manifest_errors(): void
    {
        $r = new FakeThemeRemote();
        $r->responses = [$this->base . '/index.json' => json_encode(['nope' => true])];
        $res = $r->sync();
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('not a content manifest', $res['error']);
    }

    #[Test]
    public function unsafe_and_disallowed_paths_in_a_manifest_are_dropped(): void
    {
        // A traversal path and a .php path are refused by _safePath/_normalize, so a manifest that
        // lists only those has "no usable content files".
        $manifest = ['generated' => 'x', 'files' => [
            '../escape.phtml' => ['sha256' => str_repeat('a', 64), 'size' => 1],
            'evil.php'        => ['sha256' => str_repeat('b', 64), 'size' => 1],
        ]];
        $r = new FakeThemeRemote();
        $r->responses = [$this->base . '/index.json' => json_encode($manifest)];
        $res = $r->sync();
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('no usable content files', $res['error']);
    }

    #[Test]
    public function sync_is_a_noop_when_unconfigured(): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([]));   // no content_url
        $res = (new FakeThemeRemote())->sync();
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('No remote content source', $res['error']);
    }
}

/**
 * A Tiger_Theme_Remote with its two HTTP seams replaced by a canned url=>body map, so the fetch
 * pipeline runs with no network.
 */
class FakeThemeRemote extends Tiger_Theme_Remote // phpcs:ignore
{
    /** @var array<string,?string> url => body (missing key = a failed request) */
    public array $responses = [];

    protected function _get($url, $timeout = 30)
    {
        return $this->responses[$url] ?? null;
    }

    protected function _getMany(array $urls)
    {
        $out = [];
        foreach ($urls as $key => $url) { $out[$key] = $this->responses[$url] ?? null; }
        return $out;
    }

    /** Silence the structured log line so the fetch pipeline runs without printing during a test. */
    protected function _log($event, array $ctx)
    {
        // no-op (the real _log writes a Tiger_Log line; irrelevant to these unit assertions)
    }
}
