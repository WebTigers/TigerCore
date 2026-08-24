<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — English strings (seo.*). The SOURCE locale: every other seo.php carries the same
 * key set, so a missing translation degrades to English rather than showing a raw key. Loaded on top
 * of core strings by the translate cascade; API response messages resolve these in the caller's
 * locale, and views resolve them via $this->t('key').
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'Social card saved.',
    'seo.page.error.unknown_page'  => "That page doesn't exist, so its social card can't be set.",

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'Leave blank to use the page title',
    'seo.page.field.description'   => 'Leave blank to use the site description',

    // Social Cards screen
    'seo.page.title'               => 'Social Cards',
    'seo.page.subtitle'            => 'The title, description, and image shown when one of your built-in pages is shared on social media or listed in search results.',
    'seo.action.site_defaults'     => 'Site defaults',

    'seo.card.defaults'            => 'What a blank field falls back to',
    'seo.help.defaults'            => 'Leave any field below empty and the page inherits these site-wide values instead. Change them on the Site Identity screen.',
    'seo.label.default_title'      => 'Default title',
    'seo.label.default_description' => 'Default description',
    'seo.label.default_image'      => 'Default image',

    'seo.card.pages'               => 'Built-in pages',
    'seo.help.pages'               => 'These pages ship with Tiger, so they have no content record of their own. Set a social card here and it takes effect immediately — no deploy.',
    'seo.col.page'                 => 'Page',
    'seo.col.url'                  => 'Address',
    'seo.col.title'                => 'Title',
    'seo.col.description'          => 'Description',
    'seo.col.image'                => 'Image',
    'seo.col.actions'              => 'Actions',
    'seo.state.loading'            => 'Loading pages…',
    'seo.action.edit'              => 'Edit',

    // Editor
    'seo.modal.title'              => 'Social card',
    'seo.action.close'             => 'Close',
    'seo.label.title'              => 'Title',
    'seo.help.title'               => 'Shown as the headline of the shared link. Leave blank to use the page title, then:',
    'seo.label.description'        => 'Description',
    'seo.help.description'         => 'The short summary under the headline. Leave blank to use:',
    'seo.label.image'              => 'Image',
    'seo.action.choose_image'      => 'Choose image',
    'seo.help.image'               => 'Pick from the Media Library — the real size is read from the file, so the card lays out correctly.',
    'seo.label.image_url'          => 'Image address',
    'seo.help.image_url'           => 'Or point at an image hosted elsewhere. Leave both blank to use:',
    'seo.action.clear'             => 'Clear all',
    'seo.action.cancel'            => 'Cancel',
    'seo.action.save'              => 'Save',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'Social card saved.',
    'seo.js.fix_fields'            => 'Please correct the highlighted fields.',
    'seo.js.network_error'         => 'Network error — please try again.',
    'seo.js.load_error'            => 'Could not load the page list.',
    'seo.js.authored'              => 'Set',
    'seo.js.using_default'         => 'Site default',
    'seo.js.edit_title'            => 'Social card',
    'seo.js.empty'                 => 'No built-in pages found.',
];
