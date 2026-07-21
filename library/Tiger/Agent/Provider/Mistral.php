<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Provider_Mistral — Mistral La Plateforme (OpenAI-compatible; free tier). @api
 */
class Tiger_Agent_Provider_Mistral extends Tiger_Agent_Provider_OpenAiCompatible
{
    protected function _base()        { return 'https://api.mistral.ai/v1'; }
    protected function _providerKey() { return 'mistral'; }
}
