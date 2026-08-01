<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Module_Source;

/**
 * Tiger_Module_Source — the value object for one catalog feed. Pure, no network: construction
 * defaults, kind coercion, bool casting, the partial `apply()` overlay, fetchability, and the
 * derived cache filename.
 */
#[CoversClass(Tiger_Module_Source::class)]
final class SourceTest extends UnitTestCase
{
    #[Test]
    public function it_fills_sane_defaults_and_derives_a_cache_filename(): void
    {
        $s = new Tiger_Module_Source(['id' => 'acme']);
        $this->assertSame('acme', $s->id);
        $this->assertSame('acme', $s->label, 'label defaults to the id');
        $this->assertSame(Tiger_Module_Source::KIND_GIT_INDEX, $s->kind, 'default kind is the public git index');
        $this->assertSame('', $s->url);
        $this->assertSame(100, $s->priority);
        $this->assertTrue($s->enabled);
        $this->assertTrue($s->removable);
        $this->assertFalse($s->default);
        $this->assertSame('registry-acme.json', $s->cacheFile());
    }

    #[Test]
    public function an_unknown_kind_coerces_to_git_index(): void
    {
        $this->assertSame(Tiger_Module_Source::KIND_GIT_INDEX, (new Tiger_Module_Source(['id' => 'x', 'kind' => 'bogus']))->kind);
        $this->assertSame(Tiger_Module_Source::KIND_LIVE_API, (new Tiger_Module_Source(['id' => 'x', 'kind' => 'live-api']))->kind);
    }

    #[Test]
    public function origin_defaults_to_connected_with_no_provider_and_rides_in_toArray(): void
    {
        $s = new Tiger_Module_Source(['id' => 'x']);
        $this->assertSame('connected', $s->origin, 'an admin-added source is the default shape');
        $this->assertSame('', $s->provider);
        $arr = $s->toArray();
        $this->assertSame('connected', $arr['origin']);
        $this->assertSame('', $arr['provider']);
    }

    #[Test]
    public function origin_and_provider_come_from_the_spec_and_an_unknown_origin_coerces(): void
    {
        $s = new Tiger_Module_Source(['id' => 'x', 'origin' => 'module', 'provider' => 'acme-mod']);
        $this->assertSame('module', $s->origin);
        $this->assertSame('acme-mod', $s->provider);
        $this->assertSame('connected', (new Tiger_Module_Source(['id' => 'y', 'origin' => 'bogus']))->origin, 'an unknown origin coerces to connected');
    }

    #[Test]
    public function an_id_is_slugged_to_a_safe_key(): void
    {
        $this->assertSame('web-tigers', (new Tiger_Module_Source(['id' => 'Web Tigers!']))->id);
    }

    #[Test]
    public function config_ish_bools_cast_correctly(): void
    {
        $this->assertFalse((new Tiger_Module_Source(['id' => 'x', 'enabled' => '0']))->enabled, '"0" is false');
        $this->assertFalse((new Tiger_Module_Source(['id' => 'x', 'enabled' => 'false']))->enabled, '"false" is false');
        $this->assertTrue((new Tiger_Module_Source(['id' => 'x', 'enabled' => '1']))->enabled, '"1" is true');
        $this->assertTrue((new Tiger_Module_Source(['id' => 'x', 'enabled' => true]))->enabled, 'bool true stays true');
    }

    #[Test]
    public function is_fetchable_needs_enabled_and_a_url(): void
    {
        $this->assertFalse((new Tiger_Module_Source(['id' => 'x', 'url' => '']))->isFetchable(), 'no url → not fetchable');
        $this->assertFalse((new Tiger_Module_Source(['id' => 'x', 'url' => 'u', 'enabled' => false]))->isFetchable(), 'disabled → not fetchable');
        $this->assertTrue((new Tiger_Module_Source(['id' => 'x', 'url' => 'u']))->isFetchable());
    }

    #[Test]
    public function apply_overlays_only_the_keys_present(): void
    {
        $s = new Tiger_Module_Source(['id' => 'x', 'url' => 'keep', 'label' => 'Keep', 'enabled' => true, 'priority' => 5]);
        $s->apply(['enabled' => '0']);                        // flip only enabled
        $this->assertFalse($s->enabled);
        $this->assertSame('keep', $s->url, 'url untouched');
        $this->assertSame('Keep', $s->label, 'label untouched');
        $this->assertSame(5, $s->priority, 'priority untouched');

        $s->apply(['url' => 'new', 'priority' => 2]);
        $this->assertSame('new', $s->url);
        $this->assertSame(2, $s->priority);
    }

    #[Test]
    public function to_array_round_trips_the_public_shape(): void
    {
        $spec = ['id' => 'acme', 'label' => 'Acme', 'kind' => 'live-api', 'url' => 'https://a/i.json',
                 'priority' => 3, 'enabled' => true, 'removable' => false, 'default' => true, 'cache' => 'registry-acme.json',
                 'origin' => 'module', 'provider' => 'acme-mod'];
        $this->assertSame($spec, (new Tiger_Module_Source($spec))->toArray());
    }
}
