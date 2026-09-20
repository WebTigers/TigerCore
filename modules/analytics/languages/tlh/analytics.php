<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
// STUB — tlh (Klingon): English placeholders; translate the values to Klingon (pIqaD).
/**
 * Analytics module — English strings. Semantic, owner-prefixed keys (analytics.*). Loaded on top of
 * core strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'analytics.saved'                  => 'Analytics settings saved.',
    'analytics.reports.not_connected'  => 'Google Analytics is not connected yet.',
    'analytics.reports.error'          => 'Could not load Analytics data right now — please try again shortly.',
    'analytics.reports.reconnect_required' => 'Reconnect Google Analytics to resume reporting — the connection expired or was revoked.',

    // Settings screen
    'analytics.title'                  => 'Analytics',
    'analytics.subtitle'               => "Connect Google Analytics 4 to measure your public site's traffic.",
    'analytics.save'                   => 'Save',
    'analytics.nav.settings'           => 'Settings',
    'analytics.net_error'              => 'Network error — please try again.',
    'analytics.fix_fields'             => 'Please fix the highlighted fields.',

    'analytics.tab.tag'                => 'Tracking tag',
    'analytics.tab.reports'            => 'Reports & dashboard',
    'analytics.connected'              => 'Connected',
    'analytics.not_connected'          => 'Not connected',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Enable Google Analytics',
    'analytics.measurement_id'         => 'Measurement ID',
    'analytics.exclude_staff'          => "Don't track signed-in staff",
    'analytics.exclude_staff_help'     => "Skip visits from managers, admins, and developers so your own team doesn't skew the numbers.",
    'analytics.privacy_title'          => 'Privacy & consent',

    'analytics.reports_heading'        => 'Reports — in-app dashboard',
    'analytics.reports_intro'          => 'Pull your traffic into an in-app dashboard, right here in the admin.',
    'analytics.property_id'            => 'GA4 property ID',

    'analytics.connection_method'      => 'Connection method',
    'analytics.method_oneclick'        => 'One-click',
    'analytics.recommended'            => 'Recommended',
    'analytics.method_oneclick_help'   => 'Connect with your Google account — WebTigers handles the OAuth setup. Nothing to register.',
    'analytics.method_byo'             => 'Use my own Google OAuth client',
    'analytics.method_byo_adv'         => '(advanced / self-hosted)',
    'analytics.method_byo_help'        => 'Register your own Google Cloud project — the connection never routes through WebTigers.',
    'analytics.oauth_client_id'        => 'OAuth client ID',
    'analytics.oauth_client_secret'    => 'OAuth client secret',
    'analytics.oauth_secret_keep'      => '•••••• (leave blank to keep)',

    'analytics.view_dashboard'         => 'View dashboard',
    // OAuth verification requirements (TIGER-117/118)
    'analytics.connect_google' => 'Continue with Google',
    'analytics.disconnected' => 'Disconnected from Google Analytics. The authorization was revoked with Google.',
    'analytics.disconnected_not_revoked' => 'Disconnected from this site, but Google could not be reached to revoke the authorization. You can remove it at myaccount.google.com/permissions.',
    'analytics.gdata.title' => 'What connecting does with your Google data',
    'analytics.gdata.scope' => 'Read-only access to your Google Analytics reports — the analytics.readonly scope, and nothing else.',
    'analytics.gdata.where' => 'Reports are fetched by this site directly from Google and shown only to your administrators.',
    'analytics.gdata.retain' => 'WebTigers does not store or see your analytics data; the connection service only exchanges tokens.',
    'analytics.gdata.revoke' => 'Disconnect at any time here, or remove access in your Google Account.',
    'analytics.gdata.policy_link' => 'How we handle Google user data — Privacy Policy',
    'analytics.disconnect'             => 'Disconnect',
    'analytics.connect'                => 'Connect Google Analytics',
    'analytics.connect_hint'           => 'Saves your settings, then opens Google to authorize.',
    'analytics.connect_need_property'  => 'Enter your GA4 property ID first — it tells us which property to report on.',

    // Dashboard screen
    'analytics.dashboard.title'                => 'Analytics',
    'analytics.dashboard.subtitle'             => "Your site's traffic over the last 28 days, from Google Analytics.",
    'analytics.dashboard.not_connected_title'  => 'Not connected',
    'analytics.dashboard.not_connected_body'   => 'Connect your Google Analytics account to see traffic reports here.',
    'analytics.dashboard.go_settings'          => 'Go to Analytics settings',
    'analytics.metric.active_users'            => 'Active users',
    'analytics.metric.sessions'                => 'Sessions',
    'analytics.metric.page_views'              => 'Page views',
    'analytics.card.traffic'                   => 'Traffic',
    'analytics.card.top_pages'                 => 'Top pages',
    'analytics.card.top_channels'              => 'Top channels',

    // Dashboard widget
    'analytics.widget.connect'         => 'Connect Google Analytics to see traffic.',
    'analytics.widget.setup'           => 'Set up',
    'analytics.widget.active_users_28d'=> 'active users · 28d',
    'analytics.widget.page_views'      => 'page views',
    'analytics.widget.view_dashboard'  => 'View dashboard',
];
