<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — Hindi strings (seo.*). Devanagari, keeping the usual tech loanwords. Same key
 * set as en/seo.php.
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'सोशल कार्ड सेव हो गया।',
    'seo.page.error.unknown_page'  => 'यह पेज मौजूद नहीं है, इसलिए इसका सोशल कार्ड सेट नहीं किया जा सकता।',

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'पेज का टाइटल इस्तेमाल करने के लिए खाली छोड़ें',
    'seo.page.field.description'   => 'साइट का विवरण इस्तेमाल करने के लिए खाली छोड़ें',

    // Social Cards screen
    'seo.page.title'               => 'सोशल कार्ड',
    'seo.page.subtitle'            => 'जब आपका कोई बिल्ट-इन पेज सोशल मीडिया पर शेयर होता है या सर्च रिज़ल्ट में दिखता है, तब जो टाइटल, विवरण और इमेज दिखाई देते हैं।',
    'seo.action.site_defaults'     => 'साइट डिफ़ॉल्ट',

    'seo.card.defaults'            => 'खाली फ़ील्ड किस पर लौटता है',
    'seo.help.defaults'            => 'नीचे कोई भी फ़ील्ड खाली छोड़ें और पेज ये साइट-स्तरीय वैल्यू अपना लेगा। इन्हें साइट आइडेंटिटी स्क्रीन पर बदला जा सकता है।',
    'seo.label.default_title'      => 'डिफ़ॉल्ट टाइटल',
    'seo.label.default_description' => 'डिफ़ॉल्ट विवरण',
    'seo.label.default_image'      => 'डिफ़ॉल्ट इमेज',

    'seo.card.pages'               => 'बिल्ट-इन पेज',
    'seo.help.pages'               => 'ये पेज Tiger के साथ आते हैं, इसलिए इनका अपना कोई कंटेंट रिकॉर्ड नहीं होता। यहाँ सोशल कार्ड सेट करें — यह तुरंत लागू हो जाता है, किसी डिप्लॉय की ज़रूरत नहीं।',
    'seo.col.page'                 => 'पेज',
    'seo.col.url'                  => 'पता',
    'seo.col.title'                => 'टाइटल',
    'seo.col.description'          => 'विवरण',
    'seo.col.image'                => 'इमेज',
    'seo.col.actions'              => 'कार्रवाइयाँ',
    'seo.state.loading'            => 'पेज लोड हो रहे हैं…',
    'seo.action.edit'              => 'बदलें',

    // Editor
    'seo.modal.title'              => 'सोशल कार्ड',
    'seo.action.close'             => 'बंद करें',
    'seo.label.title'              => 'टाइटल',
    'seo.help.title'               => 'शेयर किए गए लिंक की हेडलाइन के रूप में दिखता है। पेज का टाइटल इस्तेमाल करने के लिए खाली छोड़ें, उसके बाद:',
    'seo.label.description'        => 'विवरण',
    'seo.help.description'         => 'हेडलाइन के नीचे का छोटा सारांश। यह इस्तेमाल करने के लिए खाली छोड़ें:',
    'seo.label.image'              => 'इमेज',
    'seo.action.choose_image'      => 'इमेज चुनें',
    'seo.help.image'               => 'मीडिया लाइब्रेरी से चुनें — असली साइज़ फ़ाइल से पढ़ी जाती है, इसलिए कार्ड सही तरीके से दिखता है।',
    'seo.label.image_url'          => 'इमेज का पता',
    'seo.help.image_url'           => 'या कहीं और होस्ट की गई इमेज का पता दें। दोनों खाली छोड़ने पर यह इस्तेमाल होगा:',
    'seo.action.clear'             => 'सब खाली करें',
    'seo.action.cancel'            => 'रद्द करें',
    'seo.action.save'              => 'सेव करें',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'सोशल कार्ड सेव हो गया।',
    'seo.js.fix_fields'            => 'कृपया चिह्नित फ़ील्ड ठीक करें।',
    'seo.js.network_error'         => 'नेटवर्क त्रुटि — कृपया फिर से कोशिश करें।',
    'seo.js.load_error'            => 'पेज सूची लोड नहीं हो सकी।',
    'seo.js.authored'              => 'सेट है',
    'seo.js.using_default'         => 'साइट डिफ़ॉल्ट',
    'seo.js.edit_title'            => 'सोशल कार्ड',
    'seo.js.empty'                 => 'कोई बिल्ट-इन पेज नहीं मिला।',
];
