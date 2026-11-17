<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Form_Revoke — revoke one of the caller's MCP tokens (TIGER-528).
 *
 * Ownership stays the model's predicate (credential + owner + type); the form adds the CSRF token.
 */
class Mcp_Form_Revoke extends Mcp_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('credential_id')];
    }
}
