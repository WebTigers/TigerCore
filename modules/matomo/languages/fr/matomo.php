<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — French strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Paramètres Matomo enregistrés.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Analyse Matomo',
    'matomo.subtitle'                  => 'Connectez votre Matomo auto-hébergé pour mesurer votre site public et attribuer le trafic d\'affiliation.',
    'matomo.save'                      => 'Enregistrer',

    // Connection card
    'matomo.card.connection'           => 'Connexion Matomo',
    'matomo.enable'                    => 'Activer le suivi Matomo',
    'matomo.url'                       => 'URL Matomo',
    'matomo.url_help'                  => 'L\'URL de base de votre installation Matomo, par exemple https://analytics.example.com/',
    'matomo.site_id'                   => 'ID du site',
    'matomo.site_id_help'              => 'Le site Matomo (idsite) auquel cette installation envoie ses données — trouvez-le dans Matomo sous Administration → Sites web.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'Attribution d\'affiliation',
    'matomo.affiliate_help'            => 'Lorsqu\'un visiteur arrive via un lien d\'affiliation (/a/<name>), ce nom d\'affilié est enregistré sur la visite en tant que dimension personnalisée Matomo — ainsi les visites et les conversions peuvent être segmentées par affilié. Le lien et son cookie sont définis par le programme d\'affiliation ; ceci se contente de les lire.',
    'matomo.affiliate_dimension'       => 'Index de la dimension personnalisée',
    'matomo.affiliate_dimension_help'  => 'L\'index de la dimension personnalisée à portée de visite dans Matomo qui contient le nom de l\'affilié (par défaut 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'Confidentialité et consentement',
    'matomo.exclude_staff'             => 'Ne pas suivre le personnel connecté',
    'matomo.exclude_staff_help'        => 'Ignorez les visites des gestionnaires, administrateurs et développeurs afin que votre propre équipe ne fausse pas les chiffres.',
    'matomo.privacy_note'              => 'Le traqueur se charge uniquement lorsque le consentement analytique l\'autorise. Matomo respecte également Do Not Track et anonymise les adresses IP sur le serveur.',

    // Client-side
    'matomo.net_error'                 => 'Erreur réseau — veuillez réessayer.',
    'matomo.fix_fields'                => 'Veuillez corriger les champs en surbrillance.',
];
