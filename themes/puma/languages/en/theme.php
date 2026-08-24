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
    'theme.nav.tech_stack'      => 'Tech Stack',
    'theme.nav.tech_stack_desc' => 'Every layer chosen to be proven, fast, and easy for your AI to build on.',
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

    // ------------------------------------------------------------------
    // Admin chrome (the shared shell: layout, header, sidebars, asides)
    // ------------------------------------------------------------------

    // Accessibility labels (skip links, landmark + control names)
    'theme.a11y.skip_to_content' => 'Skip to content',
    'theme.a11y.toggle_nav'      => 'Toggle navigation',
    'theme.a11y.main_nav'        => 'Main navigation',
    'theme.a11y.sidebar_nav'     => 'Sidebar navigation',
    'theme.a11y.primary_nav'     => 'Primary navigation',

    // Shared actions
    'theme.action.save' => 'Save',
    'theme.action.copy' => 'Copy',

    // Admin shell
    'theme.admin.title'         => 'Tiger Admin',
    'theme.admin.navigation'    => 'Navigation',
    'theme.admin.guest'         => 'Guest',
    'theme.admin.user_fallback' => 'User',
    'theme.search.label'        => 'Search',

    // Header notifications
    'theme.admin.alerts'                    => 'Alerts',
    'theme.admin.alert.user_locked'         => 'User locked',
    'theme.admin.alert.connection_restored' => 'Connection restored',
    'theme.admin.time.minutes_ago'          => '%d minutes ago',
    'theme.admin.time.hour_ago'             => '%d hour ago',
    'theme.admin.view_all'                  => 'View all',

    // Header user menu
    'theme.admin.menu.profile'     => 'My Profile',
    'theme.admin.menu.two_factor'  => 'Two-Factor Auth',
    'theme.admin.menu.lock_screen' => 'Lock Screen',
    'theme.admin.menu.sign_out'    => 'Sign Out',

    // Right aside (contextual rail)
    'theme.aside.label'       => 'Assistant panel',
    'theme.aside.activity'    => 'Activity',
    'theme.aside.placeholder' => 'This optional rail appears when a view sets %s. Drop contextual widgets here — recent activity, inline help, filters.',

    // TigerAgent aside
    'theme.agent.label'             => 'AI Agent',
    'theme.agent.title'             => 'Agent',
    'theme.agent.model'             => 'Model',
    'theme.agent.new_chat'          => 'New chat',
    'theme.agent.drop_files'        => 'Drop files to attach',
    'theme.agent.empty'             => 'Start a conversation — the agent acts with your permissions.',
    'theme.agent.input_placeholder' => 'Ask the agent to build, change, or explain something…',
    'theme.agent.attach'            => 'Attach a file',
    'theme.agent.mode_title'        => 'Automation level',
    'theme.agent.send'              => 'Send',
    'theme.agent.open'              => 'Open the AI agent',

    // Language / theme / skin switchers
    'theme.switcher.language'    => 'Language',
    'theme.switcher.theme'       => 'Theme',
    'theme.switcher.browser'     => 'Browser',
    'theme.switcher.light'       => 'Light',
    'theme.switcher.dark'        => 'Dark',
    'theme.switcher.skin'        => 'Skin',
    'theme.switcher.skin_title'  => 'Skin (live preview)',
    'theme.switcher.skin_header' => 'Skin — live preview',
    'theme.switcher.custom'      => 'Custom',

    // Cookie-consent banner ("Global Privacy Control" is the standard's own name — kept verbatim)
    'theme.consent.label'      => 'Cookie consent',
    'theme.consent.learn_more' => 'Learn more',
    'theme.consent.gpc'        => "Global Privacy Control detected — you're opted out of analytics &amp; sharing.",

    // Auth layout
    'theme.auth.title_default' => 'Sign in — Tiger',

    // ------------------------------------------------------------------
    // Cron setup card. NOTE: the names of controls in cPanel's / Plesk's OWN
    // interface ("Add New Cron Job", "Common Settings", "Once Per Minute",
    // "Command", "Add Task", "Run a command", "Cron style", …) MUST stay in
    // English in every locale — the user is looking for them on an English
    // cPanel/Plesk screen. Translate the prose around them only.
    // ------------------------------------------------------------------
    'theme.cron.title'               => 'How to set up Cron',
    'theme.cron.status.real.title'   => 'A real cron is running.',
    'theme.cron.status.real.body'    => 'Tiger heard from your server cron in the last couple of minutes — schedules run on time.',
    'theme.cron.status.pseudo.title' => 'WordPress-style pseudo-cron is active.',
    'theme.cron.status.pseudo.body'  => 'No real cron detected, so scheduled jobs run on <em>site traffic</em> — fine to start, but a quiet site can run jobs late. Add a real cron below for reliable, on-time scheduling.',
    'theme.cron.status.none.title'   => 'No cron is running.',
    'theme.cron.status.none.body'    => 'Both real cron and the traffic-driven fallback are off — scheduled jobs will not run until you set one up below.',
    'theme.cron.command_label'       => 'Your cron command',
    'theme.cron.every_minute_note'   => 'Run it <strong>every minute</strong> (<code>* * * * *</code>) — Tiger decides internally which jobs are actually due, so a per-minute cron is cheap and keeps everything on time.',
    'theme.cron.tab.cli'             => 'Command line',
    'theme.cron.tab.claude'          => 'Let Claude Code do it',
    'theme.cron.cpanel.step1'        => 'In cPanel, open <strong>Advanced → Cron Jobs</strong>.',
    'theme.cron.cpanel.step2'        => 'Under <em>Add New Cron Job</em>, set <strong>Common Settings</strong> to <strong>Once Per Minute</strong> (<code>* * * * *</code>).',
    'theme.cron.cpanel.step3'        => 'Paste the command above into <strong>Command</strong>.',
    'theme.cron.cpanel.step4'        => 'Click <strong>Add New Cron Job</strong>. Done — this card flips to green within a minute or two.',
    'theme.cron.cpanel.php_path'     => "If <code>php</code> isn't found, use your host's full PHP path (e.g. <code>/usr/local/bin/ea-php82</code>) in place of <code>php</code>.",
    'theme.cron.plesk.step1'         => 'In Plesk, open <strong>Websites &amp; Domains → Scheduled Tasks</strong> for your domain.',
    'theme.cron.plesk.step2'         => 'Click <strong>Add Task</strong>, choose <strong>Run a command</strong>.',
    'theme.cron.plesk.step3'         => 'Set <strong>Run</strong> to <strong>Cron style</strong> and enter <code>* * * * *</code>.',
    'theme.cron.plesk.step4'         => 'Paste the command above, then <strong>OK</strong> / <strong>Run Now</strong> to test.',
    'theme.cron.cli.intro'           => 'SSH into the server and edit the crontab:',
    'theme.cron.cli.add'             => 'Add this line, then save:',
    'theme.cron.claude.intro'        => 'Have Claude Code (or any shell access to this server) do it for you — just ask:',
    'theme.cron.claude.prompt'       => 'Add a per-minute cron that runs:',
    'theme.cron.claude.note'         => 'Claude Code will add the crontab entry and confirm it. Until then, the traffic-driven pseudo-cron keeps things moving.',

    // Schedule control (values posted to the API stay the English keys; only the labels localize)
    'theme.schedule.frequency'         => 'Frequency',
    'theme.schedule.time'              => 'Time',
    'theme.schedule.day_of_week'       => 'Day of week',
    'theme.schedule.day_of_month'      => 'Day of month',
    'theme.schedule.enabled'           => 'Enabled',
    'theme.schedule.freq.every_minute' => 'Every minute',
    'theme.schedule.freq.every_5_min'  => 'Every 5 minutes',
    'theme.schedule.freq.every_15_min' => 'Every 15 minutes',
    'theme.schedule.freq.hourly'       => 'Hourly',
    'theme.schedule.freq.daily'        => 'Daily',
    'theme.schedule.freq.weekly'       => 'Weekly',
    'theme.schedule.freq.monthly'      => 'Monthly',
    'theme.schedule.day.sunday'        => 'Sunday',
    'theme.schedule.day.monday'        => 'Monday',
    'theme.schedule.day.tuesday'       => 'Tuesday',
    'theme.schedule.day.wednesday'     => 'Wednesday',
    'theme.schedule.day.thursday'      => 'Thursday',
    'theme.schedule.day.friday'        => 'Friday',
    'theme.schedule.day.saturday'      => 'Saturday',
];
