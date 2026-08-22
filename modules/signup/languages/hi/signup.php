<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup module — Hindi strings (signup.*).
 */
return [
    // Service / API messages
    'signup.disabled'        => 'सार्वजनिक साइन-अप फ़िलहाल बंद है।',
    'signup.error.recaptcha' => 'यह सत्यापित नहीं हो सका कि आप मनुष्य हैं — कृपया पुनः प्रयास करें।',
    'signup.check_email'     => 'खाता बन गया — इसे सत्यापित करने के लिए अपना ईमेल जाँचें, फिर लॉग इन करें।',
    'signup.verified'        => 'आपका ईमेल सत्यापित है और आपका खाता सक्रिय है।',
    'signup.invalid_link'    => 'यह सत्यापन लिंक अमान्य है या समाप्त हो गया है।',

    // Signup form view
    'signup.form.heading'          => 'अपना खाता बनाएँ',
    'signup.form.subheading'       => 'अपना Tiger वर्कस्पेस शुरू करें — इसमें एक मिनट लगता है।',
    'signup.form.label.first_name' => 'पहला नाम',
    'signup.form.label.last_name'  => 'अंतिम नाम',
    'signup.form.label.company'    => 'कंपनी',
    'signup.form.label.username'   => 'उपयोगकर्ता नाम',
    'signup.form.label.password'   => 'पासवर्ड',
    'signup.form.aria.show_password' => 'पासवर्ड दिखाएँ',
    'signup.form.label.email'      => 'ईमेल',
    'signup.form.label.street'     => 'सड़क का पता',
    'signup.form.label.city'       => 'शहर',
    'signup.form.label.region'     => 'राज्य / प्रांत',
    'signup.form.label.postal'     => 'पिन कोड',
    'signup.form.label.country'    => 'देश',
    'signup.form.option.select'    => '— चुनें —',
    'signup.form.group.frequent'   => 'अक्सर',
    'signup.form.group.all'        => 'सभी देश',
    'signup.form.label.phone_type' => 'फ़ोन प्रकार',
    'signup.form.label.phone'      => 'फ़ोन',
    'signup.form.submit'           => 'खाता बनाएँ',
    'signup.form.have_account'     => 'पहले से खाता है? लॉग इन करें',

    // Email-verification result view
    'signup.verify.heading'        => 'ईमेल सत्यापन',
    'signup.verify.success.body'   => 'आपका ईमेल सत्यापित है और आपका खाता सक्रिय है। अब आप लॉग इन कर सकते हैं।',
    'signup.verify.action.signin'  => 'लॉग इन करें',
    'signup.verify.invalid.body'   => 'यह सत्यापन लिंक अमान्य है या समाप्त हो गया है। आप फिर से साइन अप कर सकते हैं, या यदि आपने पहले ही पंजीकरण किया है तो सहायता से संपर्क करें।',
    'signup.verify.action.back'    => 'साइन-अप पर वापस',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'signup.js.verify_sent'   => '<strong>बस थोड़ा और।</strong> आपके खाते को सक्रिय करने के लिए हमने एक सत्यापन लिंक ईमेल किया है — उस पर क्लिक करें, फिर लॉग इन करें।',
    'signup.js.fix_fields'    => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',
    'signup.js.check_field'   => 'कृपया इस फ़ील्ड को जाँचें।',
    'signup.js.went_wrong'    => 'कुछ गलत हो गया। कृपया पुनः प्रयास करें।',
    'signup.js.network_error' => 'नेटवर्क त्रुटि — कृपया पुनः प्रयास करें।',
];
