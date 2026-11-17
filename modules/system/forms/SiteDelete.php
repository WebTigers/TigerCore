<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_SiteDelete — remove a host → org mapping (System_Service_Sites::delete, TIGER-528).
 *
 * The id plus the Sites screen's shared token. `site_domain_id` stays optional here: the service keeps
 * its own refusal for a missing id (`core.api.error.general`), so the conversion adds the token check
 * without changing what the method accepts.
 *
 * @api
 */
class System_Form_SiteDelete extends System_Form_SiteWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'site_domain_id', ['required' => false, 'filters' => [], 'validators' => []]],
        ];
    }
}
