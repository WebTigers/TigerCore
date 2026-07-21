<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_DeepSeek — DeepSeek (OpenAI-compatible; very low cost). @api
 */
class Tiger_Agent_Provider_DeepSeek extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://api.deepseek.com/v1'; }
    protected function _providerKey() { return 'deepseek'; }
}
