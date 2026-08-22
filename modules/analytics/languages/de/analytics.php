<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics module — German strings. Semantic, owner-prefixed keys (analytics.*).
 */
return [
    // /api response messages
    'analytics.saved'                  => 'Analytics-Einstellungen gespeichert.',
    'analytics.reports.not_connected'  => 'Google Analytics ist noch nicht verbunden.',
    'analytics.reports.error'          => 'Die Analytics-Daten konnten gerade nicht geladen werden — bitte versuchen Sie es in Kürze erneut.',

    // Settings screen
    'analytics.title'                  => 'Analytics',
    'analytics.subtitle'               => 'Verbinden Sie Google Analytics 4, um den Traffic Ihrer öffentlichen Website zu messen.',
    'analytics.save'                   => 'Speichern',
    'analytics.nav.settings'           => 'Einstellungen',
    'analytics.net_error'              => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'analytics.fix_fields'             => 'Bitte korrigieren Sie die hervorgehobenen Felder.',

    'analytics.tab.tag'                => 'Tracking-Tag',
    'analytics.tab.reports'            => 'Berichte & Dashboard',
    'analytics.connected'              => 'Verbunden',
    'analytics.not_connected'          => 'Nicht verbunden',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Google Analytics aktivieren',
    'analytics.measurement_id'         => 'Mess-ID',
    'analytics.exclude_staff'          => 'Angemeldete Mitarbeiter nicht tracken',
    'analytics.exclude_staff_help'     => 'Überspringt Besuche von Managern, Administratoren und Entwicklern, damit Ihr eigenes Team die Zahlen nicht verfälscht.',
    'analytics.privacy_title'          => 'Datenschutz & Einwilligung',

    'analytics.reports_heading'        => 'Berichte — integriertes Dashboard',
    'analytics.reports_intro'          => 'Holen Sie sich Ihren Traffic in ein integriertes Dashboard, direkt hier im Admin-Bereich.',
    'analytics.property_id'            => 'GA4-Property-ID',

    'analytics.connection_method'      => 'Verbindungsmethode',
    'analytics.method_oneclick'        => 'Ein-Klick',
    'analytics.recommended'            => 'Empfohlen',
    'analytics.method_oneclick_help'   => 'Verbinden Sie sich mit Ihrem Google-Konto — WebTigers übernimmt die OAuth-Einrichtung. Nichts zu registrieren.',
    'analytics.method_byo'             => 'Eigenen Google-OAuth-Client verwenden',
    'analytics.method_byo_adv'         => '(fortgeschritten / selbst gehostet)',
    'analytics.method_byo_help'        => 'Registrieren Sie Ihr eigenes Google-Cloud-Projekt — die Verbindung läuft nie über WebTigers.',
    'analytics.oauth_client_id'        => 'OAuth-Client-ID',
    'analytics.oauth_client_secret'    => 'OAuth-Client-Secret',
    'analytics.oauth_secret_keep'      => '•••••• (leer lassen, um es zu behalten)',

    'analytics.view_dashboard'         => 'Dashboard ansehen',
    'analytics.disconnect'             => 'Trennen',
    'analytics.connect'                => 'Google Analytics verbinden',
    'analytics.connect_hint'           => 'Speichert Ihre Einstellungen und öffnet dann Google zur Autorisierung.',
    'analytics.connect_need_property'  => 'Geben Sie zuerst Ihre GA4-Property-ID ein — sie sagt uns, über welche Property berichtet werden soll.',

    // Dashboard screen
    'analytics.dashboard.title'                => 'Analytics',
    'analytics.dashboard.subtitle'             => 'Der Traffic Ihrer Website in den letzten 28 Tagen, aus Google Analytics.',
    'analytics.dashboard.not_connected_title'  => 'Nicht verbunden',
    'analytics.dashboard.not_connected_body'   => 'Verbinden Sie Ihr Google-Analytics-Konto, um hier Traffic-Berichte zu sehen.',
    'analytics.dashboard.go_settings'          => 'Zu den Analytics-Einstellungen',
    'analytics.metric.active_users'            => 'Aktive Nutzer',
    'analytics.metric.sessions'                => 'Sitzungen',
    'analytics.metric.page_views'              => 'Seitenaufrufe',
    'analytics.card.traffic'                   => 'Traffic',
    'analytics.card.top_pages'                 => 'Top-Seiten',
    'analytics.card.top_channels'              => 'Top-Kanäle',

    // Dashboard widget
    'analytics.widget.connect'         => 'Verbinden Sie Google Analytics, um den Traffic zu sehen.',
    'analytics.widget.setup'           => 'Einrichten',
    'analytics.widget.active_users_28d'=> 'aktive Nutzer · 28 T.',
    'analytics.widget.page_views'      => 'Seitenaufrufe',
    'analytics.widget.view_dashboard'  => 'Dashboard ansehen',
    'analytics.nav.label' => 'Analytics',
    'analytics.widget.traffic' => 'Traffic',
];
