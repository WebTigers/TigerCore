<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_OpenAi — OpenAI (GPT) via the chat/completions API. @api
 *
 * TEXT ONLY. Image generation lives in whichever module provides that capability, as a subclass of
 * this one registered via Tiger_Agent_Provider_Factory::registerImageAdapter() — core ships no calls
 * to an endpoint it does not use (TIGER-103).
 */
class Tiger_Agent_Provider_OpenAi extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://api.openai.com/v1'; }
    protected function _providerKey() { return 'openai'; }

    /** OpenAI's gpt-5 / o-series reject `max_tokens` and require `max_completion_tokens`. */
    protected function _maxTokensField() { return 'max_completion_tokens'; }
}
