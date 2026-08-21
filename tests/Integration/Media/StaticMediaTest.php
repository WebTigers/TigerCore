<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Media;

use Media_Service_Media;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Media_Manifest;
use Tiger_Media_Storage;
use Tiger_Model_Media;
use Tiger_Model_Module;
use Zend_Config;
use Zend_Registry;

/**
 * Static (module-shipped) media — `Tiger_Media_Manifest` discovery + the `Media_Service_Media` surface
 * that merges it (datatable merge, the source filter, and copyToLibrary). Static entries are DISCOVERED
 * from every active module's `media.json`, never inserted; the only write is a deliberate Copy-to-Library.
 *
 * The fixture plants a theme module (`theme-fixstaticmedia`) under APPLICATION_PATH/modules with a
 * media.json + three real 1×1 PNGs, plus the `public/_fixstaticmedia` dir the discovery reachability gate
 * checks. `keep-one` is explicitly listed with baked dims; `keep-two` is picked up by the imageDir sweep;
 * `drop-three` is excluded by the `match` glob.
 */
#[CoversClass(Tiger_Media_Manifest::class)]
#[CoversClass(Media_Service_Media::class)]
final class StaticMediaTest extends IntegrationTestCase
{
    private const SLUG   = 'theme-fixstaticmedia';
    private const KEY    = 'fixstaticmedia';
    private const BASE   = '/_fixstaticmedia';

    private string $moduleDir;
    private string $publicDir;
    private int $keepOneSize = 0;
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);

        // Storage disk (temp roots) so copyToLibrary has somewhere to write.
        $pub  = sys_get_temp_dir() . '/tiger-static-media/pub-' . uniqid();
        $priv = sys_get_temp_dir() . '/tiger-static-media/priv-' . uniqid();
        @mkdir($pub, 0777, true);
        @mkdir($priv, 0777, true);
        $this->created[] = dirname($pub);
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'media' => [
                'default_disk' => 'local',
                'max_upload'   => 52428800,
                'allow'        => ['image' => 'png,jpg,jpeg,webp,gif,svg'],   // classify() reads media.allow.<group>
                'disks'        => ['local' => ['adapter' => 'filesystem', 'public_root' => $pub, 'private_root' => $priv, 'public_url' => '/_media']],
                'variants'     => ['server' => 0],   // skip GD variant generation in the test
            ],
        ], true));
        Tiger_Media_Storage::reset();

        // A 1×1 PNG the manifest images point at (valid enough for getimagesize()).
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMCAQAY6dLwAAAAAElFTkSuQmCC');

        $this->moduleDir = APPLICATION_PATH . '/modules/' . self::SLUG;
        @mkdir($this->moduleDir . '/assets/images', 0775, true);
        $this->created[] = $this->moduleDir;
        foreach (['keep-one.png', 'keep-two.png', 'drop-three.png'] as $f) {
            file_put_contents($this->moduleDir . '/assets/images/' . $f, $png);
        }
        $this->keepOneSize = (int) filesize($this->moduleDir . '/assets/images/keep-one.png');

        file_put_contents($this->moduleDir . '/theme.json', json_encode([
            'key' => self::KEY, 'name' => 'Fix Static Media', 'assetBase' => self::BASE,
        ]));
        file_put_contents($this->moduleDir . '/media.json', json_encode([
            'imageDir' => 'images',
            'match'    => ['keep-*'],
            'images'   => [
                ['file' => 'images/keep-one.png', 'w' => 640, 'h' => 480, 'size' => 12345, 'title' => 'Keep One', 'alt' => 'the first'],
            ],
        ]));

        // The reachability gate checks public/<assetBase> exists (i.e. the module was activated).
        $this->publicDir = APPLICATION_ROOT . '/public/' . ltrim(self::BASE, '/');
        @mkdir($this->publicDir, 0775, true);

        // The fixture is an APP module (application/modules), which is now opt-in: it must be
        // ACTIVATED (an active=1 row) to be discovered/contribute media. (Rolled back per test.)
        (new \Tiger_Model_Module())->setActive(self::SLUG, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->publicDir);
        foreach ($this->created as $d) { $this->rrmdir($d); }
        @rmdir(APPLICATION_PATH . '/modules');
        @rmdir(APPLICATION_PATH);
        Tiger_Media_Storage::reset();
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new Media_Service_Media(['action' => $action] + $params))->getResponse();
    }

    private function entryId(string $rel): string
    {
        return 'static:' . self::SLUG . ':images/' . $rel;
    }

    // ----- Tiger_Media_Manifest ------------------------------------------------------------------

    #[Test]
    public function discovers_active_module_media_with_baked_dimensions(): void
    {
        $byId = [];
        foreach (Tiger_Media_Manifest::entries() as $e) { $byId[$e['id']] = $e; }

        $one = $this->entryId('keep-one.png');
        $this->assertArrayHasKey($one, $byId, 'the explicitly-listed image is discovered');
        $this->assertSame(self::BASE . '/images/keep-one.png', $byId[$one]['url'], 'url resolves through the assetBase symlink');
        $this->assertSame(640, $byId[$one]['w'], 'baked width from the manifest');
        $this->assertSame(480, $byId[$one]['h']);
        $this->assertSame(12345, $byId[$one]['size']);
        $this->assertSame('Fix Static Media', $byId[$one]['moduleName']);

        // Swept by imageDir (match keep-*) with no baked dims.
        $this->assertArrayHasKey($this->entryId('keep-two.png'), $byId, 'imageDir sweep picks up an unlisted match');
        $this->assertSame(0, $byId[$this->entryId('keep-two.png')]['w'], 'a swept file has no baked dimensions');
    }

    #[Test]
    public function the_match_glob_keeps_chrome_out(): void
    {
        $ids = array_column(Tiger_Media_Manifest::entries(), 'id');
        $this->assertNotContains($this->entryId('drop-three.png'), $ids, "'drop-*' is excluded by the match glob");
    }

    #[Test]
    public function sources_lists_the_contributing_module(): void
    {
        $slugs = array_column(Tiger_Media_Manifest::sources(), 'slug');
        $this->assertContains(self::SLUG, $slugs);
    }

    #[Test]
    public function file_resolves_a_declared_entry_and_guards_the_rest(): void
    {
        $this->assertSame(
            $this->moduleDir . '/assets/images/keep-one.png',
            Tiger_Media_Manifest::file($this->entryId('keep-one.png')),
            'a declared entry resolves to its real path'
        );
        $this->assertSame('', Tiger_Media_Manifest::file('static:' . self::SLUG . ':images/../../secret.txt'), 'traversal refused');
        $this->assertSame('', Tiger_Media_Manifest::file('static:' . self::SLUG . ':images/not-declared.png'), 'an undeclared/absent file is refused');
        $this->assertSame('', Tiger_Media_Manifest::file('not-a-static-id'), 'a malformed id is refused');
    }

    #[Test]
    public function an_inactive_module_contributes_nothing(): void
    {
        (new Tiger_Model_Module())->setActive(self::SLUG, false);   // mark it deactivated
        $slugs = array_column(Tiger_Media_Manifest::entries(), 'module');
        $this->assertNotContains(self::SLUG, $slugs, 'a deactivated module is skipped by discovery');
        $this->assertSame('', Tiger_Media_Manifest::file($this->entryId('keep-one.png')), 'and its files can no longer be copied');
    }

    #[Test]
    public function a_module_with_no_public_symlink_is_unreachable(): void
    {
        @rmdir($this->publicDir);   // simulate "installed but never activated" (no asset symlink)
        $slugs = array_column(Tiger_Media_Manifest::entries(), 'module');
        $this->assertNotContains(self::SLUG, $slugs);
    }

    // ----- datatable merge + source filter -------------------------------------------------------

    #[Test]
    public function datatable_merges_static_media_ahead_of_uploads(): void
    {
        $this->loginAs('admin');
        $uploadId = $this->seedImage('my-upload.png');

        $data = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 100, 'kind' => 'image'])->data;

        $ids = array_column($data['data'], 'media_id');
        $this->assertContains($this->entryId('keep-one.png'), $ids, 'static media appears in the feed');
        $this->assertContains($uploadId, $ids, 'managed uploads appear too');
        $this->assertTrue($data['data'][0]['is_static'], 'static media leads the feed');
        // recordsTotal counts both segments.
        $this->assertGreaterThanOrEqual(3, $data['recordsTotal'], 'total spans static + managed');
    }

    #[Test]
    public function the_source_filter_can_exclude_uploads(): void
    {
        $this->loginAs('admin');
        $this->seedImage('excluded-upload.png');

        $data = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 100, 'sources' => self::SLUG])->data;

        $this->assertNotEmpty($data['data']);
        foreach ($data['data'] as $row) {
            $this->assertTrue($row['is_static'], 'with Uploads unchecked only static media remains');
        }
    }

    #[Test]
    public function the_source_filter_none_shows_nothing(): void
    {
        $this->loginAs('admin');
        $this->seedImage('anything.png');
        $data = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 100, 'sources' => 'none'])->data;
        $this->assertSame(0, (int) $data['recordsFiltered']);
        $this->assertEmpty($data['data']);
    }

    // ----- copyToLibrary -------------------------------------------------------------------------

    #[Test]
    public function copy_to_library_creates_an_owned_row(): void
    {
        $this->loginAs('admin');
        $res = $this->call('copyToLibrary', ['media_id' => $this->entryId('keep-one.png')]);

        $this->assertSame(1, (int) $res->result, 'copy succeeds: ' . json_encode($res->messages));
        $media = $res->data['media'];
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $media['media_id'], 'a real UUID row, not the static id');
        $this->assertSame(Tiger_Model_Media::KIND_IMAGE, $media['kind']);
        $this->assertSame('keep-one.png', $media['filename']);

        $row = (new Tiger_Model_Media())->findById($media['media_id']);
        $this->assertNotNull($row, 'the row is persisted');
        $this->assertNotEmpty($row->checksum, 'checksum recorded (drives idempotency)');
        $this->assertSame((string) $this->keepOneSize, (string) $row->file_size);
    }

    #[Test]
    public function copy_to_library_is_idempotent(): void
    {
        $this->loginAs('admin');
        $first  = $this->call('copyToLibrary', ['media_id' => $this->entryId('keep-one.png')]);
        $second = $this->call('copyToLibrary', ['media_id' => $this->entryId('keep-one.png')]);

        $this->assertSame(1, (int) $second->result);
        $this->assertTrue((bool) ($second->data['existing'] ?? false), 'the second copy reports existing');
        $this->assertSame($first->data['media']['media_id'], $second->data['media']['media_id'], 'same row, no duplicate');

        $count = (int) $this->db->fetchOne(
            'SELECT COUNT(*) FROM media WHERE org_id = ? AND filename = ? AND deleted = 0',
            ['org-test', 'keep-one.png']
        );
        $this->assertSame(1, $count, 'exactly one managed copy exists');
    }

    #[Test]
    public function copy_to_library_rejects_an_unknown_or_inactive_reference(): void
    {
        $this->loginAs('admin');
        $this->assertSame(0, (int) $this->call('copyToLibrary', ['media_id' => 'static:theme-fixstaticmedia:images/nope.png'])->result);
        $this->assertSame(0, (int) $this->call('copyToLibrary', ['media_id' => 'garbage'])->result);
    }

    #[Test]
    public function copy_to_library_is_denied_for_a_guest(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $res = $this->call('copyToLibrary', ['media_id' => $this->entryId('keep-one.png')]);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }

    /** Insert a managed IMAGE row directly. */
    private function seedImage(string $filename): string
    {
        return (new Tiger_Model_Media())->insert([
            'org_id'      => 'org-test',
            'disk'        => 'local',
            'storage_key' => 'org-test/images/' . uniqid() . '.png',
            'visibility'  => Tiger_Model_Media::VISIBILITY_PUBLIC,
            'kind'        => Tiger_Model_Media::KIND_IMAGE,
            'mime_type'   => 'image/png',
            'extension'   => 'png',
            'file_size'   => 10,
            'filename'    => $filename,
            'title'       => $filename,
        ]);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $p = $dir . '/' . $item;
            (is_dir($p) && !is_link($p)) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
