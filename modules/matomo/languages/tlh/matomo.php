<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — Klingon (tlhIngan Hol) strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top
 * of core strings by the translate cascade; API response messages resolve these in the caller's locale.
 * Best-effort Klingon; English is retained where no sensible Klingon rendering exists.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Matomo DuHmey pollu\'.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Matomo nughmey',
    'matomo.subtitle'                  => 'Do\'Ha\'ghach HaqwI\' Matomo yIrar \'ej Hoch yaHmey juHDaj yIjuv; affiliate leng yItlhej.',
    'matomo.save'                      => 'yIpol',

    // Connection card
    'matomo.card.connection'           => 'Matomo rar',
    'matomo.enable'                    => 'Matomo tlha\'meH yIchu\'',
    'matomo.url'                       => 'Matomo URL',
    'matomo.url_help'                  => 'Matomo chenmoHwI\'lIj URL mung, mughwI\': https://analytics.example.com/',
    'matomo.site_id'                   => 'Site ID',
    'matomo.site_id_help'              => 'Matomo site (idsite) ja\'bogh chenmoHwI\'vam — Matomo Daq yItu\': Administration → Websites.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'affiliate nobmey',
    'matomo.affiliate_help'            => 'affiliate rar (/a/<name>) lo\'taHvIS Suchwl\', Suchpu\'Daq affiliate pong qonlu\' Matomo custom dimension rur — vaj affiliate pIomo\' Suchmey conversions je lajlaH. rar cookie je affiliate programme cherlu\'; reH laDlu\' neH.',
    'matomo.affiliate_dimension'       => 'custom dimension mI\'',
    'matomo.affiliate_dimension_help'  => 'Matomo Daq visit-scoped custom dimension mI\', affiliate pong polbogh (choH pagh 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'pegh \'ej QochHa\'ghach',
    'matomo.exclude_staff'             => 'tlha\'be\' yaS ngaqwI\'pu\'',
    'matomo.exclude_staff_help'        => 'DevwI\'pu\', che\'wI\'pu\', chenmoHwI\'pu\' je Suchmey yIbuSHa\'; vaj ghom tlhIH mI\'mey Dochoqbe\'.',
    'matomo.privacy_note'              => 'nughmey QochHa\'ghach chawmeH neH tlha\'wI\' pollu\'. Matomo Do Not Track je quvmoH \'ej jopwI\' Daq IP SumDaqmey pegh.',

    // Client-side
    'matomo.net_error'                 => 'tlhoy Qagh — yInID ghIq.',
    'matomo.fix_fields'                => 'yvtlhmey boqHa\'lu\'bogh yISaH.',
];
