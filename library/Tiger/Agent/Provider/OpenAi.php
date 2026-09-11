<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_OpenAi — OpenAI (GPT) via the chat/completions API. @api
 */
class Tiger_Agent_Provider_OpenAi extends Tiger_Agent_Provider_OpenAiCompatible
    implements Tiger_Agent_Provider_ImageAdapter
{
    /** Sizes the images endpoint accepts. A caller's request is snapped to the nearest of these. */
    const IMAGE_SIZES = ['1024x1024', '1024x1536', '1536x1024'];

    protected function _base()        { return 'https://api.openai.com/v1'; }
    protected function _providerKey() { return 'openai'; }

    /** OpenAI's gpt-5 / o-series reject `max_tokens` and require `max_completion_tokens`. */
    protected function _maxTokensField() { return 'max_completion_tokens'; }

    /**
     * Generate images via `POST /images/generations` (TIGER-96).
     *
     * A different endpoint from chat — which is exactly why this is a separate interface rather than
     * a branch inside complete().
     *
     * `b64_json` is requested explicitly. The endpoint can return a URL instead, but those URLs
     * expire, and handing the caller something that rots is how you get an image that works in
     * testing and 404s a week later. gpt-image-* returns base64 regardless; asking for it keeps
     * dall-e-* consistent with it.
     *
     * @inheritDoc
     */
    public function generateImage($prompt, array $options, $model, $apiKey)
    {
        $prompt = trim((string) $prompt);
        if ($prompt === '') { throw new RuntimeException('An image prompt cannot be empty.'); }

        $size = $this->_snapSize($options['size'] ?? '');
        $n    = max(1, min(10, (int) ($options['n'] ?? 1)));

        $payload = [
            'model'           => $model,
            'prompt'          => $prompt,
            'n'               => $n,
            'size'            => $size,
            'response_format' => 'b64_json',
        ];

        // dall-e-3 rejects both `n > 1` and `response_format` is still honoured; gpt-image-* ignores
        // response_format and always returns b64. Send what each accepts rather than one payload that
        // half-works on both.
        if (strpos(strtolower((string) $model), 'dall-e-3') !== false) {
            $payload['n'] = 1;
        }
        if (strpos(strtolower((string) $model), 'gpt-image') !== false) {
            unset($payload['response_format']);
        }

        $body = $this->_post($this->_base() . '/images/generations', $payload, $this->_headers($apiKey));

        $images = [];
        foreach (($body['data'] ?? []) as $item) {
            if (empty($item['b64_json'])) { continue; }
            $images[] = ['mime' => 'image/png', 'data' => (string) $item['b64_json']];
        }
        if (!$images) {
            throw new RuntimeException('The provider returned no image data.');
        }

        return [
            'images' => $images,
            // Echo what was ACTUALLY used, including our snap and the model's own revision of the
            // prompt where it makes one — without that, a refinement cannot reproduce this image.
            'params' => array_filter([
                'provider'        => 'openai',
                'model'           => $model,
                'size'            => $size,
                'n'               => count($images),
                'revised_prompt'  => $body['data'][0]['revised_prompt'] ?? null,
            ], static fn($v) => $v !== null),
        ];
    }

    /** Snap a requested size to the nearest legal one; default square. */
    protected function _snapSize($requested)
    {
        $requested = strtolower(trim((string) $requested));
        if (in_array($requested, self::IMAGE_SIZES, true)) { return $requested; }
        if (preg_match('~^(\d+)x(\d+)$~', $requested, $m)) {
            $w = (int) $m[1]; $h = (int) $m[2];
            if ($w > $h) { return '1536x1024'; }
            if ($h > $w) { return '1024x1536'; }
        }
        return '1024x1024';
    }
}
