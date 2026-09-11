<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Agent_Provider_Factory;
use Tiger_Agent_Provider_Gemini;
use Tiger_Agent_Provider_ImageAdapter;
use Tiger_Agent_Provider_OpenAi;

/**
 * Image generation across the provider layer (TIGER-96) — the capability probes, and each adapter's
 * request-building / response-normalising, exercised by stubbing the one transport seam (`_post`).
 *
 * The property under test throughout: a caller gets ONE shape back regardless of which provider
 * served it, and `params` reports what was actually used rather than what was asked for. Without
 * that echo a later refinement cannot reproduce the image.
 *
 * @see Tiger_Agent_Provider_Factory
 * @see Tiger_Agent_Provider_OpenAi
 * @see Tiger_Agent_Provider_Gemini
 */
#[CoversClass(Tiger_Agent_Provider_Factory::class)]
final class ProviderImageTest extends UnitTestCase
{
    /* ---- capability probes ------------------------------------------------------------------ */

    #[Test]
    public function it_recognises_image_models(): void
    {
        $yes = [
            ['openai', 'gpt-image-1'], ['openai', 'dall-e-3'],
            ['gemini', 'imagen-3.0-generate-002'], ['gemini', 'gemini-2.5-flash-image-preview'],
            ['grok',   'grok-2-image'],
            ['openrouter', 'black-forest-labs/flux-1.1-pro'],
        ];
        foreach ($yes as [$p, $m]) {
            $this->assertTrue(Tiger_Agent_Provider_Factory::supportsImageGeneration($p, $m), "$p/$m should draw");
        }
    }

    #[Test]
    public function it_refuses_text_only_models(): void
    {
        $no = [
            ['openai', 'gpt-5'], ['openai', 'gpt-4o'],
            ['anthropic', 'claude-opus-5'],
            ['gemini', 'gemini-2.5-pro'],
            ['deepseek', 'deepseek-chat'], ['groq', 'llama-3.3-70b'], ['mistral', 'mistral-large'],
        ];
        foreach ($no as [$p, $m]) {
            $this->assertFalse(Tiger_Agent_Provider_Factory::supportsImageGeneration($p, $m), "$p/$m should NOT draw");
        }
    }

    /**
     * The whole reason these are two probes. Conflating them would offer a user a model that cannot
     * do the job they picked it for, in both directions.
     */
    #[Test]
    public function vision_and_generation_are_independent(): void
    {
        // sees, cannot draw
        $this->assertTrue(Tiger_Agent_Provider_Factory::supportsVision('openai', 'gpt-4o'));
        $this->assertFalse(Tiger_Agent_Provider_Factory::supportsImageGeneration('openai', 'gpt-4o'));
        // draws, is not a chat/vision model
        $this->assertTrue(Tiger_Agent_Provider_Factory::supportsImageGeneration('openai', 'gpt-image-1'));
        $this->assertFalse(Tiger_Agent_Provider_Factory::supportsVision('openai', 'gpt-image-1'));
    }

    #[Test]
    public function full_capability_needs_both_halves(): void
    {
        // model draws AND the adapter implements the interface
        $this->assertTrue(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-image-1'));
        // adapter implements it, but this model is a text model
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('openai', 'gpt-5'));
        // no image adapter at all, whatever the model is called
        $this->assertFalse(Tiger_Agent_Provider_Factory::canGenerateImages('anthropic', 'claude-opus-5'));

        // THE case that pins the instanceof half: OpenRouter genuinely routes to flux, so the MODEL
        // draws — but its adapter has no image code path yet, so the full answer must still be no.
        // Without this, dropping the adapter check entirely would go unnoticed (found by mutation).
        $this->assertTrue(
            Tiger_Agent_Provider_Factory::supportsImageGeneration('openrouter', 'black-forest-labs/flux-1.1-pro'),
            'the model can draw'
        );
        $this->assertFalse(
            Tiger_Agent_Provider_Factory::canGenerateImages('openrouter', 'black-forest-labs/flux-1.1-pro'),
            'but this adapter cannot drive it, so the full capability answer is no'
        );
    }

    #[Test]
    public function it_lists_only_drawing_adapters(): void
    {
        $providers = Tiger_Agent_Provider_Factory::imageProviders();
        $this->assertContains('openai', $providers);
        $this->assertContains('gemini', $providers);
        $this->assertNotContains('anthropic', $providers);
        $this->assertNotContains('deepseek', $providers);
    }

    /* ---- OpenAI ----------------------------------------------------------------------------- */

    #[Test]
    public function openai_asks_for_base64_and_normalises_the_result(): void
    {
        $a = new FakeImageOpenAi();
        FakeImageOpenAi::$response = ['data' => [
            ['b64_json' => 'AAAA', 'revised_prompt' => 'a tidier prompt'],
            ['b64_json' => 'BBBB'],
        ]];

        $out = $a->generateImage('a tiger', ['n' => 2, 'size' => '1024x1024'], 'gpt-image-1', 'k');

        $this->assertCount(2, $out['images']);
        $this->assertSame('image/png', $out['images'][0]['mime']);
        $this->assertSame('AAAA', $out['images'][0]['data']);
        // params echo what was used, including the model's own rewrite
        $this->assertSame('gpt-image-1', $out['params']['model']);
        $this->assertSame(2, $out['params']['n']);
        $this->assertSame('a tidier prompt', $out['params']['revised_prompt']);
    }

    /** A URL would expire; base64 is requested so a stored image cannot rot. */
    #[Test]
    public function openai_requests_b64_for_dalle_and_clamps_n(): void
    {
        $a = new FakeImageOpenAi();
        FakeImageOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $a->generateImage('x', ['n' => 5], 'dall-e-3', 'k');

        $this->assertSame('b64_json', FakeImageOpenAi::$sent['response_format']);
        $this->assertSame(1, FakeImageOpenAi::$sent['n'], 'dall-e-3 rejects n > 1');
    }

    /** gpt-image-* always returns base64 and rejects the field, so it must not be sent. */
    #[Test]
    public function openai_omits_response_format_for_gpt_image(): void
    {
        $a = new FakeImageOpenAi();
        FakeImageOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];
        $a->generateImage('x', [], 'gpt-image-1', 'k');

        $this->assertArrayNotHasKey('response_format', FakeImageOpenAi::$sent);
    }

    #[Test]
    public function openai_snaps_an_illegal_size(): void
    {
        $a = new FakeImageOpenAi();
        FakeImageOpenAi::$response = ['data' => [['b64_json' => 'AAAA']]];

        $a->generateImage('x', ['size' => '4000x1000'], 'gpt-image-1', 'k');
        $this->assertSame('1536x1024', FakeImageOpenAi::$sent['size'], 'landscape snaps to landscape');

        $a->generateImage('x', ['size' => 'enormous'], 'gpt-image-1', 'k');
        $this->assertSame('1024x1024', FakeImageOpenAi::$sent['size'], 'nonsense falls back to square');
    }

    #[Test]
    public function openai_fails_loudly_on_no_image_data(): void
    {
        $a = new FakeImageOpenAi();
        FakeImageOpenAi::$response = ['data' => []];
        $this->expectException(RuntimeException::class);
        $a->generateImage('x', [], 'gpt-image-1', 'k');
    }

    /* ---- Gemini ----------------------------------------------------------------------------- */

    #[Test]
    public function gemini_uses_predict_for_imagen(): void
    {
        $a = new FakeImageGemini();
        FakeImageGemini::$response = ['predictions' => [
            ['bytesBase64Encoded' => 'CCCC', 'mimeType' => 'image/jpeg'],
        ]];

        $out = $a->generateImage('a tiger', ['size' => '1920x1080', 'n' => 1], 'imagen-3.0-generate-002', 'k');

        $this->assertStringContainsString(':predict', FakeImageGemini::$url);
        $this->assertSame('16:9', FakeImageGemini::$sent['parameters']['aspectRatio'], 'WxH maps to an aspect ratio');
        $this->assertSame('CCCC', $out['images'][0]['data']);
        $this->assertSame('image/jpeg', $out['images'][0]['mime'], 'the provider mime is preserved');
    }

    #[Test]
    public function gemini_uses_generate_content_for_the_chat_image_models(): void
    {
        $a = new FakeImageGemini();
        FakeImageGemini::$response = ['candidates' => [
            ['content' => ['parts' => [['inlineData' => ['data' => 'DDDD', 'mimeType' => 'image/png']]]]],
        ]];

        $out = $a->generateImage('a tiger', [], 'gemini-2.5-flash-image-preview', 'k');

        $this->assertStringContainsString(':generateContent', FakeImageGemini::$url);
        $this->assertSame('DDDD', $out['images'][0]['data']);
    }

    /** The reference rides as inlineData — the same wire shape vision input already uses. */
    #[Test]
    public function gemini_sends_a_reference_image_as_inline_data(): void
    {
        $a = new FakeImageGemini();
        FakeImageGemini::$response = ['candidates' => [
            ['content' => ['parts' => [['inlineData' => ['data' => 'DDDD']]]]],
        ]];

        $out = $a->generateImage('warmer', ['reference' => ['mime' => 'image/png', 'data' => 'SEED']],
            'gemini-2.5-flash-image-preview', 'k');

        $parts = FakeImageGemini::$sent['contents'][0]['parts'];
        $this->assertSame('SEED', $parts[1]['inlineData']['data']);
        $this->assertSame('supplied', $out['params']['reference']);
    }

    /**
     * Imagen's predict endpoint has no reference slot. Dropping the steer silently would produce a
     * plausible image that is not what was asked for — worse than refusing.
     */
    #[Test]
    public function gemini_refuses_a_reference_imagen_cannot_honour(): void
    {
        $a = new FakeImageGemini();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('~reference image~i');
        $a->generateImage('x', ['reference' => ['mime' => 'image/png', 'data' => 'SEED']],
            'imagen-3.0-generate-002', 'k');
    }

    /* ---- shared ----------------------------------------------------------------------------- */

    #[Test]
    public function an_empty_prompt_is_refused_by_every_adapter(): void
    {
        foreach ([new FakeImageOpenAi(), new FakeImageGemini()] as $a) {
            try {
                $a->generateImage('   ', [], 'gpt-image-1', 'k');
                $this->fail('an empty prompt should throw: ' . get_class($a));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('empty', strtolower($e->getMessage()));
            }
        }
    }

    #[Test]
    public function both_adapters_declare_the_interface(): void
    {
        $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tiger_Agent_Provider_OpenAi());
        $this->assertInstanceOf(Tiger_Agent_Provider_ImageAdapter::class, new Tiger_Agent_Provider_Gemini());
    }
}

/** Stubs the one cURL seam so the payload can be inspected and a canned body returned. */
final class FakeImageOpenAi extends Tiger_Agent_Provider_OpenAi
{
    public static array $response = [];
    public static array $sent     = [];
    public static string $url     = '';

    protected function _post($url, array $payload, array $headers)
    {
        self::$url  = $url;
        self::$sent = $payload;
        return self::$response;
    }
}

final class FakeImageGemini extends Tiger_Agent_Provider_Gemini
{
    public static array $response = [];
    public static array $sent     = [];
    public static string $url     = '';

    protected function _post($url, array $payload, $apiKey)
    {
        self::$url  = $url;
        self::$sent = $payload;
        return self::$response;
    }
}
