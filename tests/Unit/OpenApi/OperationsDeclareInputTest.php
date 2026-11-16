<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\OpenApi;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_OpenApi_Generator;

/**
 * Every /api operation in core's modules declares what it takes — `@apiRequest <Form>|datatable|none` or
 * `@apiParam` lines — so an MCP client sees typed tool arguments instead of a blank object it has to guess
 * at. When the MCP test on the prod test site listed its tools, 55 of 61 were untyped (2026-11-16).
 *
 * A new service method without a declaration fails here, naming the method.
 */
#[CoversNothing]
final class OperationsDeclareInputTest extends UnitTestCase
{
    #[Test]
    public function everyCoreOperationDeclaresItsInput(): void
    {
        $gen     = new Tiger_OpenApi_Generator();
        $classes = $gen->discover(glob(dirname(__DIR__, 3) . '/modules/*/services', GLOB_ONLYDIR) ?: []);
        $this->assertNotEmpty($classes, 'the scan found core services');

        $untyped = $gen->untyped($classes);
        $this->assertSame([], $untyped, "These /api operations declare no input — add `@apiRequest <Form>|datatable|none`"
            . " or `@apiParam <type> <name> [required] <description>` lines to each docblock:\n  " . implode("\n  ", $untyped));
    }
}
