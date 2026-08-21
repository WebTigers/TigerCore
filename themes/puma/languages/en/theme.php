<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PUMA theme — public chrome strings (header nav, Solutions mega-menu, auth, footer).
 *
 * A theme ships its own translations here: the bootstrap + Tiger_I18n_Catalog scan
 * `themes/<theme>/languages/<lang>/*.php`, so these keys resolve like any other and are
 * editable in the Translations admin. Brand names (Tiger, WordPress, GitHub) are left
 * untranslated by design.
 */
return [
    // Header nav
    'theme.nav.why_tiger'      => 'Why Tiger',
    'theme.nav.why_tiger_desc' => 'The case for Tiger — own your whole stack, ship real SaaS, and skip the vendor lock-in.',
    'theme.nav.how_it_works'      => 'How It Works',
    'theme.nav.how_it_works_desc' => 'One framework, three paths — build a website, vibe-code an app, or ship enterprise software.',
    'theme.nav.saas_vs_sias'      => 'SaaS vs. SiaS',
    'theme.nav.saas_vs_sias_desc' => 'Do you actually own your app? The difference that costs you everything — and how to spot the trap.',
    'theme.nav.solutions' => 'Solutions',
    'theme.nav.features'  => 'Features',
    'theme.nav.docs'      => 'Docs',
    'theme.nav.github'    => 'GitHub',

    // Header search + auth
    'theme.search.placeholder' => 'Search…',
    'theme.auth.dashboard'     => 'Dashboard',
    'theme.auth.account'       => 'My Account',
    'theme.auth.signup'        => 'Sign up',
    'theme.auth.signin'        => 'Sign in',

    // Solutions mega-menu (title / tagline / description per card)
    'theme.mega.websites.title'   => 'Build Killer Websites',
    'theme.mega.websites.tag'     => 'No WordPress&reg; required',
    'theme.mega.websites.desc'    => 'Install Tiger and start building in minutes. Full CMS, drag-and-drop page builder, themes, and 10ms page loads — no plugins required.',
    'theme.mega.agency.title'     => 'Agency Friendly',
    'theme.mega.agency.tag'       => 'Host hundreds of sites',
    'theme.mega.agency.desc'      => 'Multi-tenant by design. One install, unlimited client sites, each with their own branding, users, and permissions — your agency scales without your server costs scaling with it.',
    'theme.mega.saas.title'       => 'Vibe SaaS Startups',
    'theme.mega.saas.tag'         => 'A secure MVP in minutes, not days',
    'theme.mega.saas.desc'        => 'Auth, billing, ACL, tenant isolation, and database sessions — all included. Tell your AI what to build and Tiger handles the architecture. Ship your MVP before lunch.',
    'theme.mega.developers.title' => 'Developers &amp; Open Source',
    'theme.mega.developers.tag'   => 'Free, BSD-licensed, yours to extend',
    'theme.mega.developers.desc'  => 'Fork it. Extend it. Build modules on a clean, AI-native architecture — and share them on your own terms. Your code stays yours.',
    'theme.mega.creators.title'   => 'Plugin &amp; Theme Creators',
    'theme.mega.creators.tag'     => 'Your code. Your terms.',
    'theme.mega.creators.desc'    => 'Build modules and themes for Tiger and share them on your terms. Your work stays yours — how you license it is up to you.',
    'theme.mega.hosting.title'    => 'Hosting Partners',
    'theme.mega.hosting.tag'      => 'Add Tiger to your stack. Free.',
    'theme.mega.hosting.desc'     => 'Tiger installs faster, runs leaner, and generates fewer support tickets than WordPress. Free to add to your cPanel or Softaculous stack — and we help grow your business through the referral program you already run.',

    // Footer
    'theme.footer.tagline' => 'the AI-native SaaS platform. Own the business your AI ships.',
    'theme.footer.privacy' => 'Privacy',
    'theme.footer.terms'   => 'Terms',
    'theme.footer.github'  => 'GitHub',
    'theme.footer.home'    => 'Home',
];
