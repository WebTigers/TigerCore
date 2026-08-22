<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics module — French strings. Semantic, owner-prefixed keys (analytics.*).
 */
return [
    // /api response messages
    'analytics.saved'                  => 'Paramètres d’analytique enregistrés.',
    'analytics.reports.not_connected'  => 'Google Analytics n’est pas encore connecté.',
    'analytics.reports.error'          => 'Impossible de charger les données Analytics pour le moment — veuillez réessayer sous peu.',

    // Settings screen
    'analytics.title'                  => 'Analytique',
    'analytics.subtitle'               => 'Connectez Google Analytics 4 pour mesurer le trafic de votre site public.',
    'analytics.save'                   => 'Enregistrer',
    'analytics.nav.settings'           => 'Paramètres',
    'analytics.net_error'              => 'Erreur réseau — veuillez réessayer.',
    'analytics.fix_fields'             => 'Veuillez corriger les champs en surbrillance.',

    'analytics.tab.tag'                => 'Balise de suivi',
    'analytics.tab.reports'            => 'Rapports et tableau de bord',
    'analytics.connected'              => 'Connecté',
    'analytics.not_connected'          => 'Non connecté',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Activer Google Analytics',
    'analytics.measurement_id'         => 'ID de mesure',
    'analytics.exclude_staff'          => 'Ne pas suivre le personnel connecté',
    'analytics.exclude_staff_help'     => 'Ignore les visites des gestionnaires, administrateurs et développeurs pour que votre propre équipe ne fausse pas les chiffres.',
    'analytics.privacy_title'          => 'Confidentialité et consentement',

    'analytics.reports_heading'        => 'Rapports — tableau de bord intégré',
    'analytics.reports_intro'          => 'Rapatriez votre trafic dans un tableau de bord intégré, ici même dans l’administration.',
    'analytics.property_id'            => 'ID de propriété GA4',

    'analytics.connection_method'      => 'Méthode de connexion',
    'analytics.method_oneclick'        => 'En un clic',
    'analytics.recommended'            => 'Recommandé',
    'analytics.method_oneclick_help'   => 'Connectez-vous avec votre compte Google — WebTigers gère la configuration OAuth. Rien à enregistrer.',
    'analytics.method_byo'             => 'Utiliser mon propre client OAuth Google',
    'analytics.method_byo_adv'         => '(avancé / auto-hébergé)',
    'analytics.method_byo_help'        => 'Enregistrez votre propre projet Google Cloud — la connexion ne passe jamais par WebTigers.',
    'analytics.oauth_client_id'        => 'ID client OAuth',
    'analytics.oauth_client_secret'    => 'Secret client OAuth',
    'analytics.oauth_secret_keep'      => '•••••• (laissez vide pour conserver)',

    'analytics.view_dashboard'         => 'Voir le tableau de bord',
    'analytics.disconnect'             => 'Déconnecter',
    'analytics.connect'                => 'Connecter Google Analytics',
    'analytics.connect_hint'           => 'Enregistre vos paramètres, puis ouvre Google pour autoriser.',
    'analytics.connect_need_property'  => 'Saisissez d’abord votre ID de propriété GA4 — il nous indique sur quelle propriété générer les rapports.',

    // Dashboard screen
    'analytics.dashboard.title'                => 'Analytique',
    'analytics.dashboard.subtitle'             => 'Le trafic de votre site sur les 28 derniers jours, depuis Google Analytics.',
    'analytics.dashboard.not_connected_title'  => 'Non connecté',
    'analytics.dashboard.not_connected_body'   => 'Connectez votre compte Google Analytics pour voir les rapports de trafic ici.',
    'analytics.dashboard.go_settings'          => 'Aller aux paramètres d’Analytics',
    'analytics.metric.active_users'            => 'Utilisateurs actifs',
    'analytics.metric.sessions'                => 'Sessions',
    'analytics.metric.page_views'              => 'Pages vues',
    'analytics.card.traffic'                   => 'Trafic',
    'analytics.card.top_pages'                 => 'Pages les plus vues',
    'analytics.card.top_channels'              => 'Principaux canaux',

    // Dashboard widget
    'analytics.widget.connect'         => 'Connectez Google Analytics pour voir le trafic.',
    'analytics.widget.setup'           => 'Configurer',
    'analytics.widget.active_users_28d'=> 'utilisateurs actifs · 28 j',
    'analytics.widget.page_views'      => 'pages vues',
    'analytics.widget.view_dashboard'  => 'Voir le tableau de bord',
    'analytics.nav.label' => 'Analytique',
    'analytics.widget.traffic' => 'Trafic',
];
