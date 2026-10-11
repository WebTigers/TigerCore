<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Secret;
use Tiger_Secret_Adapter_Chain;
use Tiger_Secret_Adapter_Interface;

/**
 * Tiger_Secret_Adapter_Chain — try backends in order, first hit wins. Children are resolved through the
 * facade, an unavailable/empty child is skipped, and 'chain' is filtered from its own list (no recursion).
 */
#[CoversClass(Tiger_Secret_Adapter_Chain::class)]
final class ChainTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Secret::_reset();
    }

    protected function tearDown(): void
    {
        Tiger_Secret::_reset();
        parent::tearDown();
    }

    #[Test]
    public function the_first_available_hit_wins(): void
    {
        Tiger_Secret::register('miss', $this->adapter(null));
        Tiger_Secret::register('hit',  $this->adapter('found-here'));
        Tiger_Secret::register('late', $this->adapter('too-late'));

        $chain = new Tiger_Secret_Adapter_Chain(['miss', 'hit', 'late']);
        $this->assertSame('found-here', $chain->get('whatever'));
    }

    #[Test]
    public function all_misses_resolve_to_null(): void
    {
        Tiger_Secret::register('a', $this->adapter(null));
        Tiger_Secret::register('b', $this->adapter(null));

        $this->assertNull((new Tiger_Secret_Adapter_Chain(['a', 'b']))->get('x'));
    }

    #[Test]
    public function an_unavailable_child_is_skipped(): void
    {
        Tiger_Secret::register('down', $this->adapter('unreachable', false));
        Tiger_Secret::register('up',   $this->adapter('reached'));

        $this->assertSame('reached', (new Tiger_Secret_Adapter_Chain(['down', 'up']))->get('x'));
    }

    #[Test]
    public function available_reflects_whether_any_child_is_available(): void
    {
        Tiger_Secret::register('d1', $this->adapter(null, false));
        $this->assertFalse((new Tiger_Secret_Adapter_Chain(['d1']))->available());

        Tiger_Secret::register('u1', $this->adapter('v'));
        $this->assertTrue((new Tiger_Secret_Adapter_Chain(['d1', 'u1']))->available());
    }

    #[Test]
    public function chain_cannot_recurse_into_itself(): void
    {
        // 'chain' is filtered out of the child list, so this resolves against 'real' only (no infinite loop).
        Tiger_Secret::register('real', $this->adapter('ok'));
        $chain = new Tiger_Secret_Adapter_Chain(['chain', 'real']);
        $this->assertSame('ok', $chain->get('x'));
    }

    private function adapter(?string $value, bool $available = true): Tiger_Secret_Adapter_Interface
    {
        return new class($value, $available) implements Tiger_Secret_Adapter_Interface {
            public function __construct(private ?string $value, private bool $up) {}
            public function available(): bool { return $this->up; }
            public function get(string $path): ?string { return $this->value; }
        };
    }
}
