<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — French strings (seo.*). Same key set as en/seo.php.
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'Carte sociale enregistrée.',
    'seo.page.error.unknown_page'  => "Cette page n'existe pas : impossible de définir sa carte sociale.",

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'Laissez vide pour utiliser le titre de la page',
    'seo.page.field.description'   => 'Laissez vide pour utiliser la description du site',

    // Social Cards screen
    'seo.page.title'               => 'Cartes sociales',
    'seo.page.subtitle'            => "Le titre, la description et l'image affichés lorsqu'une de vos pages intégrées est partagée sur les réseaux sociaux ou listée dans les résultats de recherche.",
    'seo.action.site_defaults'     => 'Valeurs du site',

    'seo.card.defaults'            => 'Ce qu’utilise un champ vide',
    'seo.help.defaults'            => 'Laissez un champ vide ci-dessous et la page hérite de ces valeurs globales du site. Vous les modifiez dans l’écran Identité du site.',
    'seo.label.default_title'      => 'Titre par défaut',
    'seo.label.default_description' => 'Description par défaut',
    'seo.label.default_image'      => 'Image par défaut',

    'seo.card.pages'               => 'Pages intégrées',
    'seo.help.pages'               => 'Ces pages sont livrées avec Tiger : elles n’ont donc pas de fiche de contenu propre. Définissez ici leur carte sociale — l’effet est immédiat, sans déploiement.',
    'seo.col.page'                 => 'Page',
    'seo.col.url'                  => 'Adresse',
    'seo.col.title'                => 'Titre',
    'seo.col.description'          => 'Description',
    'seo.col.image'                => 'Image',
    'seo.col.actions'              => 'Actions',
    'seo.state.loading'            => 'Chargement des pages…',
    'seo.action.edit'              => 'Modifier',

    // Editor
    'seo.modal.title'              => 'Carte sociale',
    'seo.action.close'             => 'Fermer',
    'seo.label.title'              => 'Titre',
    'seo.help.title'               => 'Sert de titre au lien partagé. Laissez vide pour utiliser le titre de la page, puis :',
    'seo.label.description'        => 'Description',
    'seo.help.description'         => 'Le court résumé sous le titre. Laissez vide pour utiliser :',
    'seo.label.image'              => 'Image',
    'seo.action.choose_image'      => 'Choisir une image',
    'seo.help.image'               => 'Choisissez-la dans la médiathèque : la taille réelle est lue dans le fichier, donc la carte s’affiche correctement.',
    'seo.label.image_url'          => 'Adresse de l’image',
    'seo.help.image_url'           => 'Ou pointez vers une image hébergée ailleurs. Laissez les deux champs vides pour utiliser :',
    'seo.action.clear'             => 'Tout vider',
    'seo.action.cancel'            => 'Annuler',
    'seo.action.save'              => 'Enregistrer',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'Carte sociale enregistrée.',
    'seo.js.fix_fields'            => 'Corrigez les champs signalés.',
    'seo.js.network_error'         => 'Erreur réseau — réessayez.',
    'seo.js.load_error'            => 'Impossible de charger la liste des pages.',
    'seo.js.authored'              => 'Définie',
    'seo.js.using_default'         => 'Valeur du site',
    'seo.js.edit_title'            => 'Carte sociale',
    'seo.js.empty'                 => 'Aucune page intégrée trouvée.',
];
