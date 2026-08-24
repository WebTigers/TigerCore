<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — French strings (identity.*).
 */
return [
    // Service / API messages
    'identity.saved'            => 'Identité du site enregistrée.',

    // Form placeholders
    'identity.field.site_name'  => 'ex. Acme, Inc.',
    'identity.field.tagline'    => 'Une courte phrase sous le nom',
    'identity.field.site_description' => 'ex. Acme publie des guides de terrain pour les lecteurs curieux.',

    // Site Identity screen
    'identity.page.title'       => 'Identité du site',
    'identity.page.subtitle'    => 'Le nom, le logo, le favicon et les profils sociaux de votre site — la marque qui apparaît dans les onglets du navigateur, les résultats de recherche et les partages sur les réseaux sociaux.',
    'identity.action.save'      => 'Enregistrer',
    'identity.card.identity'    => 'Identité',
    'identity.label.site_name'  => 'Nom du site',
    'identity.help.site_name'   => 'Affiché dans l’en-tête du site et l’onglet du navigateur, et utilisé comme titre de page par défaut et nom de marque dans les résultats de recherche.',
    'identity.label.tagline'    => 'Slogan',
    'identity.help.tagline'     => 'Une courte phrase décrivant le site (facultatif).',
    'identity.label.site_description' => 'Description du site',
    'identity.help.site_description'  => 'Une ou deux phrases sur le site. Utilisée comme description par défaut dans les résultats de recherche et sur les cartes de partage des réseaux sociaux quand une page n’a pas la sienne.',
    'identity.card.logo_favicon' => 'Logo et favicon',
    'identity.label.logo'       => 'Logo',
    'identity.label.favicon'    => 'Favicon',
    'identity.help.logo'        => 'Utilisé pour votre marque dans les résultats de recherche (schéma Organization) et disponible pour les thèmes.',
    'identity.help.favicon'     => 'La petite icône dans l’onglet du navigateur. Utilisez une image <strong>carrée</strong> — 512&times;512 ou plus est idéal ; le navigateur la réduit à toutes les tailles dont il a besoin.',
    'identity.card.share_image'  => 'Image de partage',
    'identity.help.share_image'  => 'L’image affichée quand une page de ce site est partagée sur les réseaux sociaux (Open Graph). Choisissez-en une dans la médiathèque pour obtenir la meilleure carte — sa taille réelle est publiée avec elle — ou collez l’adresse d’une image hébergée ailleurs. Si vous renseignez les deux, l’image de la médiathèque l’emporte. 1200 × 630 pixels fonctionne partout.',
    'identity.label.og_image'    => 'Choisir une image de partage',
    'identity.help.og_image'     => 'Choisissez une image dans la médiathèque (recommandé).',
    'identity.label.og_image_url' => 'Ou l’adresse d’une image',
    'identity.help.og_image_url'  => 'L’adresse https:// complète d’une image hébergée ailleurs. Utilisée uniquement si aucune image de la médiathèque n’est choisie.',
    'identity.card.social'      => 'Profils sociaux',
    'identity.help.social'      => 'URLs complètes de vos profils officiels. Elles sont publiées comme les liens vérifiés de votre marque (schema.org <code>sameAs</code>) — laissez-en vide au besoin.',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'Identité du site enregistrée.',
    'identity.js.fix_fields'    => 'Veuillez corriger les champs en surbrillance.',
    'identity.js.network_error' => 'Erreur réseau — veuillez réessayer.',
    'identity.nav.label' => 'Identité du site',
];
