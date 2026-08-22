<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — Hindi strings (code.*). Same key set as languages/en/code.php.
 */
return [
    // API responses
    'code.saved'       => 'स्निपेट सहेजा गया।',
    'code.activated'   => 'स्निपेट सक्रिय — अब लाइव है।',
    'code.deactivated' => 'स्निपेट निष्क्रिय किया गया।',
    'code.deleted'     => 'स्निपेट हटाया गया।',
    'code.restored'    => 'स्निपेट चयनित संस्करण पर पुनर्स्थापित किया गया।',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'सहेजा नहीं गया —',
    'code.error.saved_not_activated'      => 'सहेजा गया, लेकिन सक्रिय नहीं — यह चल रहे सेट के साथ टकराता है:',
    'code.error.cannot_activate'          => 'सक्रिय नहीं किया जा सकता —',
    'code.error.cannot_activate_conflict' => 'सक्रिय नहीं किया जा सकता — यह चल रहे सेट के साथ टकराता है:',
    'code.error.snippet_unavailable'      => 'वह स्निपेट अब उपलब्ध नहीं है — हो सकता है मॉड्यूल हटा दिया गया हो।',

    // admin list
    'code.list.title'  => 'कोड',
    'code.list.new'    => 'नया स्निपेट',
    'code.list.subtitle_a'       => 'PHP स्निपेट जो पूरे प्लेटफ़ॉर्म पर चलते हैं — कंपाइल्ड + कैश्ड, हर अनुरोध पर निष्पादित। लोकल स्निपेट डेटाबेस में संग्रहीत होते हैं;',
    'code.list.subtitle_b'       => 'स्निपेट इंस्टॉल किए गए कोड मॉड्यूल से आते हैं (सक्रिय करने से पहले स्रोत पढ़ें)।',
    'code.list.badge_module'     => 'मॉड्यूल',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'नाम',
    'code.list.col_lang'     => 'भाषा',
    'code.list.col_runs'     => 'चलता है',
    'code.list.col_priority' => 'प्राथमिकता',
    'code.list.col_state'    => 'स्थिति',
    'code.list.col_updated'  => 'अपडेट किया गया',
    'code.list.col_actions'  => 'कार्रवाइयाँ',

    // view-source modal
    'code.source.title'    => 'स्निपेट स्रोत',
    'code.source.close'    => 'बंद करें',
    'code.source.warn'     => 'सक्रिय करने पर यह PHP आपके ऐप में चलता है।',
    'code.source.activate' => 'सक्रिय करें',

    // snippet editor
    'code.edit.edit_title' => 'स्निपेट संपादित करें',
    'code.edit.new_title'  => 'नया स्निपेट',
    'code.edit.back'       => 'कोड पर वापस',
    'code.edit.cancel'     => 'रद्द करें',
    'code.edit.save'       => 'सहेजें',
    'code.edit.warn'       => 'सक्रिय होने पर यह PHP <strong>हर अनुरोध</strong> पर चलता है। यह सहेजने पर जाँचा जाता है और लोड पर घातक त्रुटि होने पर स्वतः निष्क्रिय हो जाता है।',
    'code.edit.name'       => 'नाम',
    'code.edit.code'       => 'कोड',
    'code.edit.type'       => 'प्रकार',
    'code.edit.language'   => 'भाषा',
    'code.edit.inject_at'  => 'यहाँ इंजेक्ट करें',
    'code.edit.inject_hint'      => 'इंजेक्ट किया गया CSS/JS/HTML/PHTML कहाँ रखा जाता है।',
    'code.edit.activation'       => 'सक्रियण',
    'code.edit.active_label'     => 'सक्रिय — यह स्निपेट चलाएँ',
    'code.edit.priority'         => 'प्राथमिकता',
    'code.edit.priority_hint'    => 'कम मान पहले लोड होता है। वैश्विक रूप से चलता है (हर अनुरोध पर)।',
    'code.edit.notes'            => 'नोट्स',
    'code.edit.description'      => 'विवरण',
    'code.edit.description_hint' => 'यह स्निपेट क्या करता है (सूची के लिए)।',

    // snippet editor — version history
    'code.edit.versions'       => 'संस्करण इतिहास',
    'code.edit.col_version'    => 'संस्करण',
    'code.edit.col_name'       => 'नाम',
    'code.edit.col_state'      => 'स्थिति',
    'code.edit.col_saved'      => 'सहेजा गया',
    'code.edit.state_active'   => 'सक्रिय',
    'code.edit.state_inactive' => 'निष्क्रिय',
    'code.edit.untitled'       => '(शीर्षकहीन)',
    'code.edit.restore'        => 'पुनर्स्थापित करें',

    // form — language select
    'code.lang.php'   => 'PHP — हर अनुरोध पर चलता है (फ़ंक्शन/हुक)',
    'code.lang.phtml' => 'PHTML — रेंडर + इंजेक्ट किया गया',
    'code.lang.html'  => 'HTML — यथावत इंजेक्ट किया गया',
    'code.lang.css'   => 'CSS — स्टाइलशीट के रूप में इंजेक्ट किया गया',
    'code.lang.js'    => 'JavaScript — स्क्रिप्ट के रूप में इंजेक्ट किया गया',

    // form — inject-at select
    'code.auto.head'   => 'हेड',
    'code.auto.footer' => 'फ़ुटर',
    'code.js.fix_form' => 'कृपया फ़ॉर्म जाँचें और पुनः प्रयास करें।',
    'code.js.network_error' => 'नेटवर्क त्रुटि — कृपया पुनः प्रयास करें।',
    'code.js.confirm_restore' => 'संस्करण #%s पुनर्स्थापित करें? वर्तमान सामग्री पहले नए संस्करण के रूप में सहेजी जाती है।',
    'code.js.confirm_delete_snippet' => 'यह स्निपेट हटाएँ? यह सॉफ़्ट-डिलीट होता है और पुनर्प्राप्त किया जा सकता है।',
];
