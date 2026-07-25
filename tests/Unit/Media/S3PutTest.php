<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Storage_S3;

/**
 * Tiger_Media_Storage_S3 — the write path (`put()`/`write()` → `_putObject()`) with the AWS SDK
 * absent. Unlike the Azure adapter, S3 touches the SDK ONLY inside the guarded `_c()`, so the whole
 * `_putObject()` argument-assembly runs and the `_c()` failure is caught + rewrapped as a clean
 * "could not store" RuntimeException — including the public-ACL arm. (The successful upload needs the
 * SDK + a bucket → live suite; see WAVE7-FINDINGS-media.md.)
 */
#[CoversClass(Tiger_Media_Storage_S3::class)]
final class S3PutTest extends UnitTestCase
{
    private function adapter(array $overrides = []): Tiger_Media_Storage_S3
    {
        return new Tiger_Media_Storage_S3(['bucket' => 'my-bucket', 'region' => 'us-east-1'] + $overrides);
    }

    #[Test]
    public function put_surfaces_a_could_not_store_error_without_the_sdk(): void
    {
        $src = tempnam(sys_get_temp_dir(), 's3src');
        file_put_contents($src, 'bytes');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/could not store/i');
            $this->adapter()->put('org-1/a.jpg', $src, 'public', 'image/jpeg');
        } finally {
            @unlink($src);
        }
    }

    #[Test]
    public function write_surfaces_a_could_not_store_error_without_the_sdk(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not store/i');
        $this->adapter()->write('org-1/a.jpg', 'raw-bytes', 'private', 'application/octet-stream');
    }

    #[Test]
    public function write_takes_the_public_acl_arm_before_the_store_failure(): void
    {
        // public_acl=1 + a PUBLIC visibility exercises the ACL='public-read' argument arm of
        // _putObject() before _c() (SDK absent) fails.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not store/i');
        $this->adapter(['public_acl' => 1])->write('org-1/a.jpg', 'raw', 'public');
    }
}
