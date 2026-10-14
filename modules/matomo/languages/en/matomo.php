<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — English strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Matomo settings saved.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Matomo analytics',
    'matomo.subtitle'                  => 'Connect your self-hosted Matomo to measure your public site and attribute affiliate traffic.',
    'matomo.save'                      => 'Save',

    // Connection card
    'matomo.card.connection'           => 'Matomo connection',
    'matomo.enable'                    => 'Enable Matomo tracking',
    'matomo.url'                       => 'Matomo URL',
    'matomo.url_help'                  => 'The base URL of your Matomo install, e.g. https://analytics.example.com/',
    'matomo.site_id'                   => 'Site ID',
    'matomo.site_id_help'              => 'The Matomo site (idsite) this install reports to — find it in Matomo under Administration → Websites.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'Affiliate attribution',
    'matomo.affiliate_help'            => 'When a visitor arrives on an affiliate link (/a/<name>), that affiliate name is recorded on the visit as a Matomo custom dimension — so visits and conversions are segmentable by affiliate. The link and its cookie are set by the affiliate programme; this only reads them.',
    'matomo.affiliate_dimension'       => 'Custom dimension index',
    'matomo.affiliate_dimension_help'  => 'The index of the visit-scoped custom dimension in Matomo that holds the affiliate name (default 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'Privacy & consent',
    'matomo.exclude_staff'             => "Don't track signed-in staff",
    'matomo.exclude_staff_help'        => "Skip visits from managers, admins, and developers so your own team doesn't skew the numbers.",
    'matomo.privacy_note'              => 'The tracker loads only when analytics consent allows it. Matomo also honors Do Not Track and anonymizes IP addresses on the server.',

    // Client-side
    'matomo.net_error'                 => 'Network error — please try again.',
    'matomo.fix_fields'                => 'Please fix the highlighted fields.',
];
