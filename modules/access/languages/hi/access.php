<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — Hindi strings (language-only locale `hi`). Mirrors en/access.php.
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'नाम',
    'access.label.slug'          => 'स्लग',
    'access.label.status'        => 'स्थिति',
    'access.label.created'       => 'बनाया गया',
    'access.label.email'         => 'ईमेल',
    'access.label.username'      => 'उपयोगकर्ता नाम',
    'access.label.parent'        => 'मूल',
    'access.label.members'       => 'सदस्य',
    'access.label.roles'         => 'भूमिकाएँ',
    'access.label.orgs'          => 'संगठन',
    'access.label.actions'       => 'क्रियाएँ',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'सहेजें',
    'access.action.cancel'       => 'रद्द करें',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'सभी स्थितियाँ',
    'access.filter.clear'        => 'साफ़ करें',
    'access.filter.clear_title'  => 'फ़िल्टर साफ़ करें',

    // --- Status values ---
    'access.status.active'       => 'सक्रिय',
    'access.status.suspended'    => 'निलंबित',

    // --- Users: list ---
    'access.user.list.title'     => 'उपयोगकर्ता',
    'access.user.list.subtitle'  => 'पहचान — ईमेल, उपयोगकर्ता नाम, स्थिति और सदस्यता।',
    'access.user.list.new'       => 'नया उपयोगकर्ता',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'नया उपयोगकर्ता',
    'access.user.edit.title_edit' => 'उपयोगकर्ता संपादित करें',
    'access.user.edit.back'       => 'उपयोगकर्ताओं पर वापस जाएँ',
    'access.user.field.email_help'          => 'कैननिकल लॉगिन पहचानकर्ता। यह अद्वितीय होना चाहिए।',
    'access.user.field.username_help'       => 'वैकल्पिक। सेट करने पर अद्वितीय।',
    'access.user.field.language'            => 'भाषा',
    'access.user.field.language_help'       => 'उपयोगकर्ता की पसंदीदा भाषा।',
    'access.user.field.timezone'            => 'समय क्षेत्र',
    'access.user.field.timezone_placeholder'=> 'शहर, संक्षिप्त रूप (EST) या ऑफ़सेट (-05:00) से खोजें…',
    'access.user.field.password'            => 'पासवर्ड सेट करें',
    'access.user.field.password_help'       => 'वर्तमान पासवर्ड बनाए रखने के लिए खाली छोड़ें। इसे यहाँ सेट करने पर यह तुरंत रीसेट हो जाता है।',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'उपयोगकर्ता सहेजा गया।',
    'access.user.deleted'        => 'उपयोगकर्ता हटाया गया।',
    'access.user.email_taken'    => 'यह ईमेल पहले से उपयोग में है।',
    'access.user.username_taken' => 'यह उपयोगकर्ता नाम पहले से उपयोग में है।',
    'access.user.no_self_delete' => 'आप अपना स्वयं का खाता नहीं हटा सकते।',

    // --- Organizations: list ---
    'access.org.list.title'      => 'संगठन',
    'access.org.list.subtitle'   => 'टेनेंट — नाम, स्लग, पदानुक्रम और सदस्यता।',
    'access.org.list.new'        => 'नया संगठन',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'नया संगठन',
    'access.org.edit.title_edit' => 'संगठन संपादित करें',
    'access.org.edit.back'       => 'संगठनों पर वापस जाएँ',
    'access.org.field.slug_help'    => 'URL-सुरक्षित पहचानकर्ता। खाली छोड़ने पर नाम से स्वतः प्राप्त होता है; यह अद्वितीय होना चाहिए।',
    'access.org.field.parent'       => 'मूल संगठन',
    'access.org.field.parent_help'  => 'उप-टेनेंट के लिए; रूट संगठन के लिए “कोई नहीं” रहने दें।',
    'access.org.parent.none'        => '— कोई नहीं (रूट संगठन) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'संगठन सहेजा गया।',
    'access.org.deleted'         => 'संगठन हटाया गया।',
    'access.org.slug_taken'      => 'यह स्लग पहले से उपयोग में है।',
    'access.org.slug_required'   => 'एक स्लग आवश्यक है (या इसे प्राप्त करने के लिए एक नाम दें)।',
    'access.org.parent_self'     => 'कोई संगठन अपना स्वयं का मूल नहीं हो सकता।',
    'access.org.no_self_delete'  => 'आप जिस संगठन में इस समय कार्य कर रहे हैं, उसे हटा नहीं सकते।',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'नाम / स्लग खोजें…',
    'access.js.search_users'        => 'ईमेल / उपयोगकर्ता नाम खोजें…',
    'access.js.edit'                => 'संपादित करें',
    'access.js.delete'              => 'हटाएँ',
    'access.js.org_no_delete'       => 'आपके सक्रिय संगठन को हटाया नहीं जा सकता',
    'access.js.delete_self'         => 'आप स्वयं को हटा नहीं सकते',
    'access.js.not_permitted'       => 'अनुमति नहीं है',
    'access.js.confirm_delete_org'  => 'इस संगठन को हटाएँ? यह सॉफ़्ट-डिलीट होता है और पुनर्प्राप्त किया जा सकता है।',
    'access.js.confirm_delete_user' => 'इस उपयोगकर्ता को हटाएँ? यह सॉफ़्ट-डिलीट होता है और पुनर्प्राप्त किया जा सकता है।',
    'access.js.fix_fields'          => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें और पुनः प्रयास करें।',
    'access.js.network_error'       => 'नेटवर्क त्रुटि — कृपया पुनः प्रयास करें।',
    'access.js.parent_root'         => '— रूट —',
];
