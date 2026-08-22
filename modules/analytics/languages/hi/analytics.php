<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics module — Hindi strings. Semantic, owner-prefixed keys (analytics.*).
 */
return [
    // /api response messages
    'analytics.saved'                  => 'एनालिटिक्स सेटिंग्स सहेजी गईं।',
    'analytics.reports.not_connected'  => 'Google Analytics अभी तक कनेक्टेड नहीं है।',
    'analytics.reports.error'          => 'अभी Analytics डेटा लोड नहीं हो सका — कृपया थोड़ी देर में फिर से प्रयास करें।',

    // Settings screen
    'analytics.title'                  => 'एनालिटिक्स',
    'analytics.subtitle'               => 'अपनी सार्वजनिक साइट के ट्रैफ़िक को मापने के लिए Google Analytics 4 कनेक्ट करें।',
    'analytics.save'                   => 'सहेजें',
    'analytics.nav.settings'           => 'सेटिंग्स',
    'analytics.net_error'              => 'नेटवर्क त्रुटि — कृपया फिर से प्रयास करें।',
    'analytics.fix_fields'             => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',

    'analytics.tab.tag'                => 'ट्रैकिंग टैग',
    'analytics.tab.reports'            => 'रिपोर्ट और डैशबोर्ड',
    'analytics.connected'              => 'कनेक्टेड',
    'analytics.not_connected'          => 'कनेक्टेड नहीं',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Google Analytics सक्षम करें',
    'analytics.measurement_id'         => 'मेज़रमेंट ID',
    'analytics.exclude_staff'          => 'साइन-इन किए गए स्टाफ़ को ट्रैक न करें',
    'analytics.exclude_staff_help'     => 'मैनेजर, एडमिन और डेवलपर की विज़िट छोड़ें ताकि आपकी अपनी टीम आँकड़ों को प्रभावित न करे।',
    'analytics.privacy_title'          => 'गोपनीयता और सहमति',

    'analytics.reports_heading'        => 'रिपोर्ट — इन-ऐप डैशबोर्ड',
    'analytics.reports_intro'          => 'अपने ट्रैफ़िक को यहीं एडमिन में एक इन-ऐप डैशबोर्ड में लाएँ।',
    'analytics.property_id'            => 'GA4 प्रॉपर्टी ID',

    'analytics.connection_method'      => 'कनेक्शन विधि',
    'analytics.method_oneclick'        => 'एक-क्लिक',
    'analytics.recommended'            => 'अनुशंसित',
    'analytics.method_oneclick_help'   => 'अपने Google अकाउंट से कनेक्ट करें — WebTigers OAuth सेटअप संभालता है। पंजीकृत करने के लिए कुछ नहीं।',
    'analytics.method_byo'             => 'अपना खुद का Google OAuth क्लाइंट उपयोग करें',
    'analytics.method_byo_adv'         => '(उन्नत / स्व-होस्टेड)',
    'analytics.method_byo_help'        => 'अपना खुद का Google Cloud प्रोजेक्ट पंजीकृत करें — कनेक्शन कभी WebTigers से होकर नहीं जाता।',
    'analytics.oauth_client_id'        => 'OAuth क्लाइंट ID',
    'analytics.oauth_client_secret'    => 'OAuth क्लाइंट सीक्रेट',
    'analytics.oauth_secret_keep'      => '•••••• (बनाए रखने के लिए खाली छोड़ें)',

    'analytics.view_dashboard'         => 'डैशबोर्ड देखें',
    'analytics.disconnect'             => 'डिस्कनेक्ट करें',
    'analytics.connect'                => 'Google Analytics कनेक्ट करें',
    'analytics.connect_hint'           => 'आपकी सेटिंग्स सहेजता है, फिर अधिकृत करने के लिए Google खोलता है।',
    'analytics.connect_need_property'  => 'पहले अपना GA4 प्रॉपर्टी ID दर्ज करें — यह हमें बताता है कि किस प्रॉपर्टी पर रिपोर्ट करनी है।',

    // Dashboard screen
    'analytics.dashboard.title'                => 'एनालिटिक्स',
    'analytics.dashboard.subtitle'             => 'Google Analytics से, पिछले 28 दिनों में आपकी साइट का ट्रैफ़िक।',
    'analytics.dashboard.not_connected_title'  => 'कनेक्टेड नहीं',
    'analytics.dashboard.not_connected_body'   => 'यहाँ ट्रैफ़िक रिपोर्ट देखने के लिए अपना Google Analytics अकाउंट कनेक्ट करें।',
    'analytics.dashboard.go_settings'          => 'Analytics सेटिंग्स पर जाएँ',
    'analytics.metric.active_users'            => 'सक्रिय उपयोगकर्ता',
    'analytics.metric.sessions'                => 'सत्र',
    'analytics.metric.page_views'              => 'पेज व्यू',
    'analytics.card.traffic'                   => 'ट्रैफ़िक',
    'analytics.card.top_pages'                 => 'शीर्ष पेज',
    'analytics.card.top_channels'              => 'शीर्ष चैनल',

    // Dashboard widget
    'analytics.widget.connect'         => 'ट्रैफ़िक देखने के लिए Google Analytics कनेक्ट करें।',
    'analytics.widget.setup'           => 'सेट अप करें',
    'analytics.widget.active_users_28d'=> 'सक्रिय उपयोगकर्ता · 28 दिन',
    'analytics.widget.page_views'      => 'पेज व्यू',
    'analytics.widget.view_dashboard'  => 'डैशबोर्ड देखें',
    'analytics.nav.label' => 'एनालिटिक्स',
    'analytics.widget.traffic' => 'ट्रैफ़िक',
];
