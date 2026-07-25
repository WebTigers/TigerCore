<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Storage;
use Tiger_Media_Storage_Azure;
use Tiger_Media_Storage_Gcs;

/**
 * Tiger_Media_Storage — the two cloud switch arms Wave 1's StorageTest didn't reach.
 *
 * The clients are lazy, so construct-only resolution touches no network/credentials — this just
 * proves the factory dispatches `gcs` → Gcs and `azure` → Azure (each needs only its minimal
 * required config to construct).
 */
#[CoversClass(Tiger_Media_Storage::class)]
final class StorageMoreTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Media_Storage::reset();
    }

    protected function tearDown(): void
    {
        Tiger_Media_Storage::reset();
        parent::tearDown();
    }

    #[Test]
    public function resolves_a_gcs_disk_to_the_gcs_adapter(): void
    {
        $this->setConfig(['media' => ['disks' => ['g' => ['adapter' => 'gcs', 'bucket' => 'my-bucket']]]]);
        $this->assertInstanceOf(Tiger_Media_Storage_Gcs::class, Tiger_Media_Storage::disk('g'));
    }

    #[Test]
    public function resolves_an_azure_disk_to_the_azure_adapter(): void
    {
        $this->setConfig(['media' => ['disks' => ['az' => ['adapter' => 'azure', 'container' => 'media']]]]);
        $this->assertInstanceOf(Tiger_Media_Storage_Azure::class, Tiger_Media_Storage::disk('az'));
    }

    #[Test]
    public function the_adapter_name_is_matched_case_insensitively(): void
    {
        // _build() lowercases the adapter token, so 'S3'/'GCS' resolve like their lowercase forms.
        $this->setConfig(['media' => ['disks' => ['g' => ['adapter' => 'GCS', 'bucket' => 'b']]]]);
        $this->assertInstanceOf(Tiger_Media_Storage_Gcs::class, Tiger_Media_Storage::disk('g'));
    }
}
