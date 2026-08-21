<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations module — English strings (the source locale).
 */
return [
    // Screen chrome
    'translations.heading'            => 'Translations',
    'translations.subtitle'           => 'Search, edit, and override any interface string per language — no deploy.',
    'translations.target_locale'      => 'Language',
    'translations.filter'             => 'Show',
    'translations.filter_all'         => 'All keys',
    'translations.filter_missing'     => 'Untranslated',
    'translations.filter_overridden'  => 'Overridden',
    'translations.source_note'        => 'Source strings are shown in %s.',
    'translations.search_placeholder' => 'Search keys and text…',

    // Grid columns
    'translations.col_key'         => 'Key',
    'translations.col_source'      => 'Source',
    'translations.col_translation' => 'Translation',
    'translations.col_status'      => 'Status',
    'translations.col_actions'     => 'Edit',

    // Modal
    'translations.modal_title'      => 'Edit translation',
    'translations.context_heading'  => 'Where this is used',
    'translations.explain'          => 'Explain with AI',
    'translations.translate'        => 'Translate',
    'translations.translating'      => 'Translating…',
    'translations.badge_default'    => 'Source',
    'translations.badge_overridden' => 'Overridden',
    'translations.uses_file'        => 'Uses shipped default',
    'translations.revert_all'       => 'Revert to defaults',
    'translations.cancel'           => 'Cancel',
    'translations.save'             => 'Save',
    'translations.no_refs'          => 'No direct code references found — it may be used dynamically.',
    'translations.revert_confirm'   => 'Revert to shipped default?',

    // Toasts
    'translations.saved_toast'    => 'Translations saved.',
    'translations.reverted_toast' => 'Reverted to the shipped default.',
    'translations.net_error'      => 'Network error — please try again.',

    // Service messages
    'translations.saved'            => 'Translations saved.',
    'translations.reverted'         => 'Reverted to the shipped default.',
    'translations.error.no_key'     => 'No translation key was given.',
    'translations.error.no_ai'      => 'No AI provider is connected. Add one in AI Agent settings to use Translate.',
    'translations.error.bad_translate' => 'Nothing to translate, or an unsupported language.',
    'translations.error.ai_failed'  => 'The AI translation could not be completed. Please try again.',
    'translations.nav.label' => 'Translations',
];
