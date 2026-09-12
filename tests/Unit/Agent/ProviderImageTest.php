<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Agent_Provider_Adapter;
use Tiger_Agent_Provider_Factory;
use Tiger_Agent_Provider_Gemini;
use Tiger_Agent_Provider_ImageAdapter;
use Tiger_Agent_Provider_OpenAi;

/**
 * The image-adapter REGISTRY (TIGER-103).
 *
 * Core declares the contract and holds the register. It ships no image-generation code and knows
 * nothing about which models can draw — a module providing the capability registers its adapters at
 * bootstrap, the same shape Tiger_Search and Tiger_Audience already use.
 *
 * So what is tested here is the seam, not any provider's behaviour. The implementations and their
 * model knowledge are TigerImage's, and are tested there.
 *
 * @see Tiger_Agent_Provider_Factory
 */
#[CoversClass(Tiger_Agent_Provider_Factory::class)]
final class ProviderImageTest extends UnitTestCase
{
    protected function setUp(): void { Tiger_Agent_Provider_Factory::clearImageAdapters(); }
    protected function tearDown(): void { Tiger_Agent_Provider_Factory::clearImageAdapters(); }

    /** The property that matters most: a plain Tiger cannot draw, and says so. */
    #[Test]
    public function core_alone_has_no_image_capability(): void
    {
        $this->assertSame([], Tiger_Agent_Provider_Factory::imageProviders(),
            'an install with no image module must report no providers');
        $this->assertNull(Tiger_Agent_Provider_Factory::imageAdapter('openai'));
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-image-1'));
    }

    /** Core ships no image-generation code — the interface is a declaration, not an implementation. */
    #[Test]
    public function the_shipped_adapters_do_not_implement_the_image_interface(): void
    {
        $this->assertNotInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tiger_Agent_Provider_OpenAi());
        $this->assertNotInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tiger_Agent_Provider_Gemini());
    }

    #[Test]
    public function a_module_can_register_and_core_then_reports_it(): void
    {
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new FakeDrawingAdapter(['gpt-image-1']));

        $this->assertSame(['openai'], Tiger_Agent_Provider_Factory::imageProviders());
        $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class,
            Tiger_Agent_Provider_Factory::imageAdapter('openai'));
        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-image-1'));
    }

    /** Registration is not capability: the ADAPTER decides whether it can draw with a given model. */
    #[Test]
    public function a_registered_adapter_still_refuses_a_model_it_cannot_draw_with(): void
    {
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new FakeDrawingAdapter(['gpt-image-1']));

        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-image-1'));
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-5'),
            'a text model behind a registered adapter still cannot draw');
    }

    #[Test]
    public function registering_twice_replaces_rather_than_duplicates(): void
    {
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new FakeDrawingAdapter(['a']));
        Tiger_Agent_Provider_Factory::registerImageAdapter('openai', new FakeDrawingAdapter(['b']));

        $this->assertSame(['openai'], Tiger_Agent_Provider_Factory::imageProviders(), 'one entry, not two');
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'a'), 'the later registration wins');
        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'b'));
    }

    #[Test]
    public function provider_keys_are_case_insensitive_and_trimmed(): void
    {
        Tiger_Agent_Provider_Factory::registerImageAdapter('  OpenAI  ', new FakeDrawingAdapter(['m']));
        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'm'));
    }

    #[Test]
    public function an_empty_provider_key_is_ignored(): void
    {
        Tiger_Agent_Provider_Factory::registerImageAdapter('   ', new FakeDrawingAdapter(['m']));
        $this->assertSame([], Tiger_Agent_Provider_Factory::imageProviders());
    }

    /** Vision stays core's — it describes the CHAT surface core actually uses — and stays separate. */
    #[Test]
    public function vision_is_unaffected_and_independent(): void
    {
        $this->assertTrue(Tiger_Agent_Provider_Factory::supportsVision('openai', 'gpt-4o'));
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-4o'),
            'seeing an image is not drawing one');
    }
}

/** A stand-in for whatever a capability module registers. */
final class FakeDrawingAdapter implements Tiger_Agent_Provider_Adapter, Tiger_Agent_Provider_ImageAdapter
{
    /** @param array<int,string> $models */
    public function __construct(private array $models) {}

    public function complete($system, array $messages, $model, $apiKey) { return ['text' => '', 'usage' => []]; }
    public function models($apiKey = '') { return []; }
    public function supportsModel($model) { return in_array((string) $model, $this->models, true); }
    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        return ['images' => [['mime' => 'image/png', 'data' => base64_encode('x')]], 'params' => []];
    }
}
