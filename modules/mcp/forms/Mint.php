<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Form_Mint — mint a scoped MCP access token (TIGER-528).
 *
 * `scopes` may arrive as an array or a csv, so it carries no filter (StringTrim on an array is a
 * warning); Tiger_Mcp_Scope::resolve() authorizes the chosen offers and refuses an empty set.
 */
class Mcp_Form_Mint extends Mcp_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('scopes'), $this->_field('read_only'), $this->_field('org_scoped')];
    }
}
