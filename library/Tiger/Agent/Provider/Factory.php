<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_Factory — resolve a provider adapter (and its default model) by key.
 *
 * The one place that knows the provider roster, so adding OpenAI/Ollama later is a single
 * `case` here plus a new adapter class — nothing else in the agent changes (TIGERAGENT.md
 * §7). Anthropic is the reference; unknown keys fall back to it rather than failing hard, so
 * a stale config value never bricks the aside.
 *
 * @api
 */
class Tiger_Agent_Provider_Factory
{
    /** The provider roster: key => [label, adapter class, default model, curated static models]. The
     *  `models` list is the NO-KEY fallback for the selector; with a key the adapter fetches the live
     *  list from the provider (vendors churn models constantly — never hardcode as the source, and
     *  the static ids below WILL age). Most providers reuse the one OpenAI-compatible base adapter;
     *  Anthropic and Gemini have their own wire formats. */
    const PROVIDERS = [
        'anthropic' => [
            'label'   => 'Anthropic (Claude)',
            'adapter' => 'Tiger_Agent_Provider_Anthropic',
            'default' => 'claude-sonnet-5',
            'models'  => ['claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5-20251001'],
        ],
        'openai' => [
            'label'   => 'OpenAI (GPT)',
            'adapter' => 'Tiger_Agent_Provider_OpenAi',
            'default' => 'gpt-4o',
            'models'  => ['gpt-4o', 'gpt-4o-mini', 'gpt-4.1', 'gpt-4.1-mini', 'o3-mini'],
        ],
        'gemini' => [
            'label'   => 'Google (Gemini)',
            'adapter' => 'Tiger_Agent_Provider_Gemini',
            'default' => 'gemini-2.0-flash',
            'models'  => ['gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'],
        ],
        'grok' => [
            'label'   => 'xAI (Grok)',
            'adapter' => 'Tiger_Agent_Provider_Grok',
            'default' => 'grok-2-latest',
            'models'  => ['grok-2-latest', 'grok-2-vision-latest', 'grok-beta'],
        ],
        'groq' => [
            'label'   => 'Groq (fast open models)',
            'adapter' => 'Tiger_Agent_Provider_Groq',
            'default' => 'llama-3.3-70b-versatile',
            'models'  => ['llama-3.3-70b-versatile', 'llama-3.1-8b-instant', 'mixtral-8x7b-32768', 'gemma2-9b-it'],
        ],
        'mistral' => [
            'label'   => 'Mistral',
            'adapter' => 'Tiger_Agent_Provider_Mistral',
            'default' => 'mistral-small-latest',
            'models'  => ['mistral-large-latest', 'mistral-small-latest', 'open-mistral-nemo'],
        ],
        'deepseek' => [
            'label'   => 'DeepSeek',
            'adapter' => 'Tiger_Agent_Provider_DeepSeek',
            'default' => 'deepseek-chat',
            'models'  => ['deepseek-chat', 'deepseek-reasoner'],
        ],
        'openrouter' => [
            'label'   => 'OpenRouter (many providers)',
            'adapter' => 'Tiger_Agent_Provider_OpenRouter',
            'default' => 'openai/gpt-4o-mini',
            'models'  => ['openai/gpt-4o-mini', 'anthropic/claude-sonnet-5', 'google/gemini-2.0-flash-exp:free', 'meta-llama/llama-3.3-70b-instruct'],
        ],
    ];

    /** @var Tiger_Agent_Provider_Adapter|null a test-injected adapter that make() returns for every key. */
    protected static $_override = null;

    /**
     * Force make() to return a given adapter, bypassing the roster — a TEST SEAM. The agent loop's one
     * provider call (`Tiger_Agent_Loop::_step` → `make($provider)->complete(...)`) is otherwise
     * un-stubbable, so a full turn can't be driven deterministically. This is the same pattern as
     * `Tiger_Mail::setDefaultTransport` (give CI a seam past an external dependency). Pass null to clear;
     * always clear it in a test's tearDown so it can't leak into another test.
     *
     * @param  Tiger_Agent_Provider_Adapter|null $adapter the double to return, or null to restore normal resolution
     * @return void
     */
    public static function setAdapter($adapter = null)
    {
        self::$_override = $adapter;
    }

    /**
     * Build the adapter for a provider key.
     *
     * @param  string $provider the provider key
     * @return Tiger_Agent_Provider_Adapter
     */
    public static function make($provider)
    {
        if (self::$_override !== null) { return self::$_override; }
        $spec  = self::PROVIDERS[$provider] ?? self::PROVIDERS['anthropic'];
        $class = $spec['adapter'];
        return new $class();
    }

    /**
     * The default model id for a provider.
     *
     * @param  string $provider the provider key
     * @return string
     */
    public static function defaultModel($provider)
    {
        $spec = self::PROVIDERS[$provider] ?? self::PROVIDERS['anthropic'];
        return $spec['default'];
    }

    /**
     * The curated static model ids for a provider — the selector's fallback when there's no key.
     *
     * @param  string $provider the provider key
     * @return string[]
     */
    public static function staticModels($provider)
    {
        $spec = self::PROVIDERS[$provider] ?? self::PROVIDERS['anthropic'];
        return !empty($spec['models']) ? $spec['models'] : [$spec['default']];
    }

    /**
     * The roster for the settings dropdown: [key => label].
     *
     * @return array<string,string>
     */
    public static function options()
    {
        $out = [];
        foreach (self::PROVIDERS as $key => $spec) {
            $out[$key] = $spec['label'];
        }
        return $out;
    }

    /**
     * Can this provider+model accept image input (multimodal / "vision")?
     *
     * Deliberately CONSERVATIVE: return true only for models we're confident take images. When unsure
     * we return false so the caller degrades to a caption/OCR pass (which works for every model) rather
     * than sending an image to a text-only model and getting an API error. Model names churn, so this
     * is a heuristic on the id — the safe direction is "no" (caption) not "yes" (risk a hard failure).
     *
     * @param  string $provider provider key
     * @param  string $model    model id
     * @return bool
     */
    public static function supportsVision($provider, $model)
    {
        $m = strtolower((string) $model);
        switch ((string) $provider) {
            case 'anthropic':                                   // every current Claude is multimodal
                return strpos($m, 'claude') !== false;
            case 'openai':                                      // 4o / 4.1 / 5 / o-series see images; not audio-only
                return (bool) preg_match('~gpt-4o|gpt-4\.1|gpt-5|chatgpt-4o|(?:^|[^a-z])o[134](?:$|[^a-z])~', $m)
                       && strpos($m, 'audio') === false && strpos($m, 'realtime') === false;
            case 'gemini':                                      // 1.5+ and 2.x are all multimodal
                return strpos($m, 'gemini') !== false;
            case 'grok':
                return strpos($m, 'vision') !== false || (bool) preg_match('~grok-(4|2-vision)~', $m);
            case 'mistral':                                     // pixtral + the newer vision-capable small/medium
                return strpos($m, 'pixtral') !== false || (bool) preg_match('~mistral-(small|medium)-2[45]~', $m);
            case 'openrouter':                                  // route id names its upstream — reuse the same cues
                return (bool) preg_match('~vision|gpt-4o|gpt-4\.1|gpt-5|claude|gemini|pixtral|llama-3\.2~', $m);
            case 'groq':
                return strpos($m, 'vision') !== false || strpos($m, 'llama-3.2') !== false;
            case 'deepseek':                                    // text-only today
            default:
                return false;
        }
    }

    /**
     * Can this provider+model GENERATE an image? (TIGER-96)
     *
     * Sibling to supportsVision(), and deliberately independent of it. Vision READS images,
     * generation WRITES them, and the overlap is partial: gpt-4o sees but does not draw, gpt-image-1
     * draws but is not a chat model. Conflating them would offer users models that cannot do the job
     * they picked them for.
     *
     * This answers only "can the model draw". The adapter must ALSO implement
     * Tiger_Agent_Provider_ImageAdapter — see canGenerateImages(), which asks both questions.
     *
     * Named-model cues rather than a live capability call, matching supportsVision(): vendors do not
     * expose a machine-readable "can draw" flag, and a wrong answer here costs a greyed-out option,
     * not a failure.
     *
     * @param  string $provider provider key
     * @param  string $model    model id
     * @return bool
     */
    public static function supportsImageGeneration($provider, $model)
    {
        $m = strtolower((string) $model);
        switch ((string) $provider) {
            case 'openai':                                      // the images endpoint: gpt-image-* and dall-e-*
                return strpos($m, 'gpt-image') !== false || strpos($m, 'dall-e') !== false;
            case 'gemini':                                      // imagen-*, and the *-image-* generation variants
                return strpos($m, 'imagen') !== false
                       || (bool) preg_match('~gemini-[^ ]*image~', $m);
            case 'grok':                                        // xAI's image models are named for it
                return strpos($m, 'image') !== false;
            case 'openrouter':                                  // route id names its upstream — reuse the same cues
                return (bool) preg_match('~gpt-image|dall-e|imagen|flux|stable-diffusion|sdxl~', $m);
            case 'anthropic':                                   // no image generation model today
            case 'deepseek':
            case 'groq':
            case 'mistral':
            default:
                return false;
        }
    }

    /**
     * The full capability answer: this provider can draw with this model, AND its adapter knows how.
     *
     * Both halves are required and neither implies the other — an adapter may implement the image
     * interface while the selected model is a text model, and a drawing model is useless behind an
     * adapter that has no code path to call it. Callers should use THIS rather than either half.
     *
     * @param  string $provider provider key
     * @param  string $model    model id
     * @return bool
     */
    public static function canGenerateImages($provider, $model)
    {
        if (!self::supportsImageGeneration($provider, $model)) { return false; }
        return self::make($provider) instanceof Tiger_Agent_Provider_ImageAdapter;
    }

    /**
     * Every configured provider that can draw, for a UI that has to offer a choice.
     *
     * @return array<int,string> provider keys whose adapter implements the image interface
     */
    public static function imageProviders()
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $key) {
            if (self::make($key) instanceof Tiger_Agent_Provider_ImageAdapter) { $out[] = $key; }
        }
        return $out;
    }
}
