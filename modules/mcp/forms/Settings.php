<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Form_Settings — turn the `/mcp` endpoint on or off (TIGER-528).
 *
 * Switching on an AI-drivable surface is exactly the write a cross-site page must not be able to make.
 */
class Mcp_Form_Settings extends Mcp_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('enabled')];
    }
}
