<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_OpenRouter — OpenRouter: one key, many models (incl. some free `:free`
 * variants), OpenAI-compatible. Adds optional attribution headers. @api
 */
class Tiger_Agent_Provider_OpenRouter extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://openrouter.ai/api/v1'; }
    protected function _providerKey() { return 'openrouter'; }

    protected function _headers($apiKey)
    {
        return array_merge(parent::_headers($apiKey), [
            'HTTP-Referer: https://webtigers.com',
            'X-Title: Tiger',
        ]);
    }
}
