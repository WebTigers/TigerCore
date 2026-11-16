<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — German strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Matomo-Einstellungen gespeichert.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Matomo-Analyse',
    'matomo.subtitle'                  => 'Verbinde dein selbst gehostetes Matomo, um deine öffentliche Website zu messen und Affiliate-Traffic zuzuordnen.',
    'matomo.save'                      => 'Speichern',

    // Connection card
    'matomo.card.connection'           => 'Matomo-Verbindung',
    'matomo.enable'                    => 'Matomo-Tracking aktivieren',
    'matomo.url'                       => 'Matomo-URL',
    'matomo.url_help'                  => 'Die Basis-URL deiner Matomo-Installation, z. B. https://analytics.example.com/',
    'matomo.site_id'                   => 'Website-ID',
    'matomo.site_id_help'              => 'Die Matomo-Website (idsite), an die diese Installation meldet — zu finden in Matomo unter Administration → Websites.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'Affiliate-Zuordnung',
    'matomo.affiliate_help'            => 'Wenn ein Besucher über einen Affiliate-Link (/a/<name>) kommt, wird dieser Affiliate-Name beim Besuch als benutzerdefinierte Dimension in Matomo erfasst — so lassen sich Besuche und Conversions nach Affiliate segmentieren. Der Link und sein Cookie werden vom Affiliate-Programm gesetzt; dies liest sie nur aus.',
    'matomo.affiliate_dimension'       => 'Index der benutzerdefinierten Dimension',
    'matomo.affiliate_dimension_help'  => 'Der Index der besuchsbezogenen benutzerdefinierten Dimension in Matomo, die den Affiliate-Namen enthält (Standard 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'Datenschutz & Einwilligung',
    'matomo.exclude_staff'             => 'Angemeldetes Personal nicht tracken',
    'matomo.exclude_staff_help'        => 'Überspringe Besuche von Managern, Administratoren und Entwicklern, damit dein eigenes Team die Zahlen nicht verfälscht.',
    'matomo.privacy_note'              => 'Der Tracker wird nur geladen, wenn die Analyse-Einwilligung es erlaubt. Matomo berücksichtigt außerdem Do Not Track und anonymisiert IP-Adressen auf dem Server.',

    // Client-side
    'matomo.net_error'                 => 'Netzwerkfehler — bitte versuche es erneut.',
    'matomo.fix_fields'                => 'Bitte korrigiere die hervorgehobenen Felder.',

    // Token für die Reporting-API (1.61) — nur schreibbar
    'matomo.token'                     => 'API-Token (token_auth)',
    'matomo.token_help'                => 'Erstelle eines in Matomo unter Administration → Persönlich → Sicherheit → Auth-Tokens. Es braucht Lesezugriff auf diese Website. Wird verschlüsselt gespeichert und nie wieder angezeigt; leer lassen, um das gespeicherte zu behalten.',
    'matomo.token_placeholder'         => 'Matomo-API-Token einfügen',
    'matomo.token_keep'                => '•••••• gespeichert (leer lassen zum Behalten)',
    'matomo.token_saved'               => 'Token gespeichert',
    'matomo.card.tag'                  => 'Tracking-Tag',
    'matomo.error.no_crypto'           => 'Bevor ein API-Token gespeichert werden kann, muss ein Verschlüsselungsschlüssel eingerichtet sein.',
];
