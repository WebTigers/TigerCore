<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_Grok — xAI Grok (OpenAI-compatible). @api
 */
class Tiger_Agent_Provider_Grok extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://api.x.ai/v1'; }
    protected function _providerKey() { return 'grok'; }
}
