<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Secret_Adapter_File;

/**
 * Tiger_Secret_Adapter_File — read a mounted secret file (Docker/k8s/systemd convention). A single
 * trailing newline is stripped; missing/unreadable/empty is null.
 */
#[CoversClass(Tiger_Secret_Adapter_File::class)]
final class FileTest extends UnitTestCase
{
    private ?string $tmp = null;

    protected function tearDown(): void
    {
        if ($this->tmp !== null) { @unlink($this->tmp); $this->tmp = null; }
        parent::tearDown();
    }

    #[Test]
    public function reads_a_file_and_strips_one_trailing_newline(): void
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'tsecf');
        file_put_contents($this->tmp, "the-secret\n");
        $a = new Tiger_Secret_Adapter_File();

        $this->assertTrue($a->available());
        $this->assertSame('the-secret', $a->get($this->tmp));
    }

    #[Test]
    public function a_missing_file_is_null(): void
    {
        $this->assertNull((new Tiger_Secret_Adapter_File())->get('/no/such/tiger/secret/file'));
        $this->assertNull((new Tiger_Secret_Adapter_File())->get(''));
    }

    #[Test]
    public function an_empty_file_is_null(): void
    {
        $this->tmp = tempnam(sys_get_temp_dir(), 'tsecf');
        file_put_contents($this->tmp, "\n");
        $this->assertNull((new Tiger_Secret_Adapter_File())->get($this->tmp));
    }
}
