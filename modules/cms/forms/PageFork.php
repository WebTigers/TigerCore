<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_PageFork — fork an active-theme template into an editable CMS row (TIGER-528).
 *
 * All optional, as before: an unknown `kind` falls back to `page`, a missing template is the service's
 * `cms.page.template_unavailable`, and a non-active `theme` is `cms.page.theme_inactive`.
 *
 * @api
 */
class Cms_Form_PageFork extends Cms_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_optional('slug'),
            $this->_optional('kind'),
            $this->_optional('theme'),
        ];
    }
}
