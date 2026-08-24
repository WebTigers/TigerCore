<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — English strings. Semantic, owner-prefixed keys (identity.*). Loaded on top of
 * core strings by the translate cascade; API response messages resolve these in the caller's locale,
 * and the views resolve them via $this->t('key').
 */
return [
    // Service / API messages
    'identity.saved'            => 'Site identity saved.',

    // Form placeholders
    'identity.field.site_name'  => 'e.g. Acme, Inc.',
    'identity.field.tagline'    => 'A short line under the name',
    'identity.field.site_description' => 'e.g. Acme publishes field guides for curious readers.',

    // Site Identity screen
    'identity.page.title'       => 'Site Identity',
    'identity.page.subtitle'    => "Your site's name, logo, favicon, and social profiles — the brand that appears in browser tabs, search results, and social shares.",
    'identity.action.save'      => 'Save',
    'identity.card.identity'    => 'Identity',
    'identity.label.site_name'  => 'Site name',
    'identity.help.site_name'   => 'Shown in the site header and browser tab, and used as the default page title and brand name in search results.',
    'identity.label.tagline'    => 'Tagline',
    'identity.help.tagline'     => 'A short line describing the site (optional).',
    'identity.label.site_description' => 'Site description',
    'identity.help.site_description'  => 'One or two sentences about the site. Used as the default description in search results and on social share cards when a page has none of its own.',
    'identity.card.logo_favicon' => 'Logo & favicon',
    'identity.label.logo'       => 'Logo',
    'identity.label.favicon'    => 'Favicon',
    'identity.help.logo'        => 'Used for your brand in search results (Organization schema) and available to themes.',
    'identity.help.favicon'     => 'The little icon in the browser tab. Use a <strong>square</strong> image — 512&times;512 or larger is ideal; the browser scales it down to every size it needs.',
    'identity.card.share_image'  => 'Share image',
    'identity.help.share_image'  => 'The picture shown when a page from this site is shared on social media (Open Graph). Choose one from the Media Library for the sharpest card — its real size is published with it — or paste the address of an image hosted elsewhere. A library image wins if you set both. 1200 × 630 pixels works everywhere.',
    'identity.label.og_image'    => 'Choose share image',
    'identity.help.og_image'     => 'Pick an image from the Media Library (recommended).',
    'identity.label.og_image_url' => 'Or an image address',
    'identity.help.og_image_url'  => 'The full https:// address of an image hosted elsewhere. Used only when no library image is chosen.',
    'identity.card.social'      => 'Social profiles',
    'identity.help.social'      => 'Full URLs to your official profiles. These are published as your brand\'s verified links (schema.org <code>sameAs</code>) — leave any blank.',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'Site identity saved.',
    'identity.js.fix_fields'    => 'Please fix the highlighted fields.',
    'identity.js.network_error' => 'Network error — please try again.',
    'identity.nav.label' => 'Site Identity',
];
