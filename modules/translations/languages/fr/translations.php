<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations module — French strings.
 */
return [
    // Screen chrome
    'translations.heading'            => 'Traductions',
    'translations.subtitle'           => 'Recherchez, modifiez et remplacez n’importe quelle chaîne de l’interface par langue — sans redéploiement.',
    'translations.target_locale'      => 'Langue',
    'translations.filter'             => 'Afficher',
    'translations.filter_all'         => 'Toutes les clés',
    'translations.filter_missing'     => 'Non traduites',
    'translations.filter_overridden'  => 'Remplacées',
    'translations.source_note'        => 'Les chaînes source sont affichées en %s.',
    'translations.search_placeholder' => 'Rechercher des clés et du texte…',

    // Grid columns
    'translations.col_key'         => 'Clé',
    'translations.col_source'      => 'Source',
    'translations.col_translation' => 'Traduction',
    'translations.col_status'      => 'Statut',
    'translations.col_actions'     => 'Modifier',

    // Modal
    'translations.modal_title'      => 'Modifier la traduction',
    'translations.context_heading'  => 'Où ceci est utilisé',
    'translations.explain'          => 'Expliquer avec l’IA',
    'translations.translate'        => 'Traduire',
    'translations.translating'      => 'Traduction…',
    'translations.badge_default'    => 'Source',
    'translations.badge_overridden' => 'Remplacée',
    'translations.uses_file'        => 'Utilise la valeur par défaut fournie',
    'translations.revert_all'       => 'Rétablir les valeurs par défaut',
    'translations.cancel'           => 'Annuler',
    'translations.save'             => 'Enregistrer',
    'translations.no_refs'          => 'Aucune référence directe trouvée dans le code — elle peut être utilisée de façon dynamique.',
    'translations.revert_confirm'   => 'Rétablir la valeur par défaut fournie ?',

    // Toasts
    'translations.saved_toast'    => 'Traductions enregistrées.',
    'translations.reverted_toast' => 'Rétabli à la valeur par défaut fournie.',
    'translations.net_error'      => 'Erreur réseau — veuillez réessayer.',

    // Service messages
    'translations.saved'            => 'Traductions enregistrées.',
    'translations.reverted'         => 'Rétabli à la valeur par défaut fournie.',
    'translations.error.no_key'     => 'Aucune clé de traduction n’a été fournie.',
    'translations.error.no_ai'      => 'Aucun fournisseur d’IA n’est connecté. Ajoutez-en un dans les paramètres de l’Agent IA pour utiliser Traduire.',
    'translations.error.bad_translate' => 'Rien à traduire, ou langue non prise en charge.',
    'translations.error.ai_failed'  => 'La traduction par IA n’a pas pu être effectuée. Veuillez réessayer.',
    'translations.nav.label' => 'Traductions',
];
