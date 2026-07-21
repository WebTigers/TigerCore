<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_Groq — Groq's fast open-model inference (OpenAI-compatible; free tier). @api
 */
class Tiger_Agent_Provider_Groq extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://api.groq.com/openai/v1'; }
    protected function _providerKey() { return 'groq'; }
}
