<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Update_Provider;

/**
 * Tiger_Update_Provider — the neutral seam that lets a module own how it detects + applies its own
 * updates while the Updates screen keeps treating it like any other module. Pure static registry + two
 * fail-safe dispatchers; no DB, no network.
 */
#[CoversClass(Tiger_Update_Provider::class)]
final class ProviderTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        Tiger_Update_Provider::_reset();
        parent::tearDown();
    }

    #[Test]
    public function registersAndLooksUpAProvider(): void
    {
        $this->assertFalse(Tiger_Update_Provider::has('widget'));
        Tiger_Update_Provider::register('widget', [
            'id'    => 'acme-authority',
            'check' => static fn($slug, $installed) => ['latest' => '2.0.0'],
            'apply' => static fn($slug, $desc) => ['ok' => true, 'version' => '2.0.0'],
        ]);
        $this->assertTrue(Tiger_Update_Provider::has('widget'));
        $this->assertSame('acme-authority', Tiger_Update_Provider::get('widget')['id']);
        $this->assertArrayHasKey('widget', Tiger_Update_Provider::all());

        Tiger_Update_Provider::unregister('widget');
        $this->assertFalse(Tiger_Update_Provider::has('widget'));
    }

    #[Test]
    public function ignoresAProviderThatCannotBothCheckAndApply(): void
    {
        Tiger_Update_Provider::register('bad1', ['check' => static fn() => null]);              // no apply
        Tiger_Update_Provider::register('bad2', ['apply' => static fn() => ['ok' => true]]);     // no check
        Tiger_Update_Provider::register('', ['check' => static fn() => null, 'apply' => static fn() => []]); // no slug
        $this->assertFalse(Tiger_Update_Provider::has('bad1'));
        $this->assertFalse(Tiger_Update_Provider::has('bad2'));
        $this->assertSame([], Tiger_Update_Provider::all());
    }

    #[Test]
    public function checkRequiresALatestAndIsFailSafe(): void
    {
        Tiger_Update_Provider::register('ok', [
            'check' => static fn($s, $i) => ['latest' => '1.1.0', 'installed' => '1.0.0'],
            'apply' => static fn() => ['ok' => true],
        ]);
        $this->assertSame(['latest' => '1.1.0', 'installed' => '1.0.0'], Tiger_Update_Provider::check('ok', '1.0.0'));

        // A provider that returns no 'latest' → treated as "nothing to say" (null).
        Tiger_Update_Provider::register('empty', [
            'check' => static fn($s, $i) => ['notes' => 'hi'],
            'apply' => static fn() => ['ok' => true],
        ]);
        $this->assertNull(Tiger_Update_Provider::check('empty', '1.0.0'));

        // A provider whose check THROWS must never bubble — null, no phantom update.
        Tiger_Update_Provider::register('boom', [
            'check' => static function () { throw new \RuntimeException('network'); },
            'apply' => static fn() => ['ok' => true],
        ]);
        $this->assertNull(Tiger_Update_Provider::check('boom', '1.0.0'));

        // No provider at all → null.
        $this->assertNull(Tiger_Update_Provider::check('nope', '1.0.0'));
    }

    #[Test]
    public function applyIsFailSafeOnThrowAndBadResult(): void
    {
        Tiger_Update_Provider::register('boom', [
            'check' => static fn() => ['latest' => '2.0.0'],
            'apply' => static function () { throw new \RuntimeException('disk full'); },
        ]);
        $r = Tiger_Update_Provider::apply('boom', ['slug' => 'boom']);
        $this->assertFalse($r['ok']);
        $this->assertSame('disk full', $r['log'][0]['detail']);

        Tiger_Update_Provider::register('weird', [
            'check' => static fn() => ['latest' => '2.0.0'],
            'apply' => static fn() => 'not-an-array',
        ]);
        $this->assertFalse(Tiger_Update_Provider::apply('weird', [])['ok']);

        // No provider registered → a failure result, never a throw.
        $this->assertFalse(Tiger_Update_Provider::apply('nope', [])['ok']);
    }

    #[Test]
    public function applyReturnsTheProvidersResult(): void
    {
        Tiger_Update_Provider::register('ok', [
            'check' => static fn() => ['latest' => '3.0.0'],
            'apply' => static fn($slug, $desc) => ['ok' => true, 'version' => '3.0.0', 'log' => [['step' => 'done', 'ok' => true, 'detail' => 'installed']]],
        ]);
        $r = Tiger_Update_Provider::apply('ok', ['slug' => 'ok', 'latest' => '3.0.0']);
        $this->assertTrue($r['ok']);
        $this->assertSame('3.0.0', $r['version']);
    }
}
