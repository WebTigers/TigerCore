<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Media_Migrator;
use Tiger_Media_Storage;
use Tiger_Model_Media;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Media_Migrator — relocate stored media from one disk to another (any adapter → any adapter).
 *
 * Two filesystem disks (`local` → `cloudy`, temp dirs) stand in for local→cloud. Covered: COPY keeps
 * the original + flips the row; MOVE deletes the original after a verified copy; variant (thumbnail)
 * objects travel too; and a second run is idempotent (a row already on the target is skipped).
 */
#[CoversClass(Tiger_Media_Migrator::class)]
final class MigratorTest extends IntegrationTestCase
{
    private string $srcPub;
    private string $srcPriv;
    private string $dstPub;
    private string $dstPriv;

    protected function setUp(): void
    {
        parent::setUp();
        $u = uniqid();
        $this->srcPub  = sys_get_temp_dir() . "/tiger-mig/a-pub-$u";
        $this->srcPriv = sys_get_temp_dir() . "/tiger-mig/a-priv-$u";
        $this->dstPub  = sys_get_temp_dir() . "/tiger-mig/b-pub-$u";
        $this->dstPriv = sys_get_temp_dir() . "/tiger-mig/b-priv-$u";
        foreach ([$this->srcPub, $this->srcPriv, $this->dstPub, $this->dstPriv] as $d) { @mkdir($d, 0777, true); }

        Zend_Registry::set('Zend_Config', new Zend_Config(['media' => [
            'default_disk' => 'local',
            'disks' => [
                'local'  => ['adapter' => 'filesystem', 'public_root' => $this->srcPub, 'private_root' => $this->srcPriv, 'public_url' => '/_a'],
                'cloudy' => ['adapter' => 'filesystem', 'public_root' => $this->dstPub, 'private_root' => $this->dstPriv, 'public_url' => '/_b'],
            ],
        ]], true));
        Tiger_Media_Storage::reset();
    }

    protected function tearDown(): void
    {
        Tiger_Media_Storage::reset();
        parent::tearDown();
    }

    /** Seed a media row on `local` + write its bytes (and optional variant bytes) to that disk. */
    private function seedWithFile(array $variantKeys = []): array
    {
        $key = 'org-test/documents/' . uniqid() . '.txt';
        $vis = Tiger_Model_Media::VISIBILITY_PUBLIC;
        Tiger_Media_Storage::disk('local')->write($key, 'hello-tiger', $vis, 'text/plain');
        foreach ($variantKeys as $vk) { Tiger_Media_Storage::disk('local')->write($vk, 'thumb-bytes', $vis, 'image/jpeg'); }

        $id = (new Tiger_Model_Media())->insert([
            'org_id'      => 'org-test',
            'disk'        => 'local',
            'storage_key' => $key,
            'visibility'  => $vis,
            'kind'        => Tiger_Model_Media::KIND_DOCUMENT,
            'mime_type'   => 'text/plain',
            'extension'   => 'txt',
            'file_size'   => 11,
            'filename'    => 'seed.txt',
            'title'       => 'Seed',
            'variants'    => $variantKeys ? json_encode(['thumbnail' => ['key' => $variantKeys[0]]]) : null,
        ]);
        return ['id' => $id, 'key' => $key, 'vis' => $vis];
    }

    private function diskOf(string $id): string
    {
        return (string) (new Tiger_Model_Media())->getAdapter()->fetchOne('SELECT disk FROM media WHERE media_id = ?', $id);
    }

    #[Test]
    public function copy_relocates_bytes_and_flips_the_row_but_keeps_the_original(): void
    {
        $m = $this->seedWithFile();
        $r = Tiger_Media_Migrator::migrate('cloudy', false);

        $this->assertGreaterThanOrEqual(1, $r['rows']);
        $this->assertFalse($r['move']);
        $this->assertSame('cloudy', $this->diskOf($m['id']), 'row flipped to the target disk');
        $this->assertTrue(Tiger_Media_Storage::disk('cloudy')->exists($m['key'], $m['vis']), 'object on the target');
        $this->assertTrue(Tiger_Media_Storage::disk('local')->exists($m['key'], $m['vis']), 'original kept (copy mode)');
    }

    #[Test]
    public function move_deletes_the_original_after_a_verified_copy(): void
    {
        $m = $this->seedWithFile();
        $r = Tiger_Media_Migrator::migrate('cloudy', true);

        $this->assertTrue($r['move']);
        $this->assertSame('cloudy', $this->diskOf($m['id']));
        $this->assertTrue(Tiger_Media_Storage::disk('cloudy')->exists($m['key'], $m['vis']), 'object on the target');
        $this->assertFalse(Tiger_Media_Storage::disk('local')->exists($m['key'], $m['vis']), 'original removed (move mode)');
    }

    #[Test]
    public function variant_objects_are_migrated_too(): void
    {
        $vk = 'org-test/documents/' . uniqid() . '-thumbnail.jpg';
        $m  = $this->seedWithFile([$vk]);

        Tiger_Media_Migrator::migrate('cloudy', false);

        $this->assertTrue(Tiger_Media_Storage::disk('cloudy')->exists($vk, $m['vis']), 'variant copied to the target');
    }

    #[Test]
    public function a_second_run_finds_nothing_to_migrate(): void
    {
        $this->seedWithFile();
        Tiger_Media_Migrator::migrate('cloudy', false);   // flips the row to 'cloudy'
        $again = Tiger_Media_Migrator::migrate('cloudy', false);

        $this->assertSame(0, $again['rows'], 'a row already on the target is not re-migrated');
    }
}
