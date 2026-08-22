<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Register module — Hindi strings. Owner-prefixed keys (register.*). */
return [
    // /api response messages
    'register.registered'      => 'आपकी साइट पंजीकृत है — सत्यापन पूरा करने के लिए अपना ईमेल जाँचें।',
    'register.domain_verified' => 'आपका डोमेन सत्यापित है।',
    'register.domain_pending'  => 'आपका डोमेन अभी सत्यापित नहीं है — हम प्रयास करते रहेंगे; आप अभी भी पुनः प्रयास कर सकते हैं।',
    'register.email_sent'      => 'सत्यापन ईमेल भेजा गया।',
    'register.error.no_domain'            => 'इस साइट का डोमेन नहीं पहचाना जा सका।',
    'register.error.registry_unreachable' => 'Tiger नेटवर्क अभी उपलब्ध नहीं है — कृपया कुछ देर बाद पुनः प्रयास करें।',
    'register.error.not_registered'       => 'यह साइट पंजीकृत नहीं है।',

    // Settings → Registration screen
    'register.title'        => 'पंजीकरण',
    'register.subtitle'     => 'वैकल्पिक। सत्यापित Site ID पाने और Tiger नेटवर्क से जुड़ने के लिए इस साइट को पंजीकृत करें — यह कुछ भी चालू या बंद नहीं करता।',
    'register.status'       => 'स्थिति',
    'register.verified'     => 'सत्यापित',
    'register.not_registered' => 'पंजीकृत नहीं',
    'register.field.domain' => 'डोमेन',
    'register.field.email'  => 'ईमेल',
    'register.field.tsid'   => 'Site ID (TSID)',
    'register.intro_body'   => 'पंजीकरण आपके <strong>डोमेन</strong> (स्वतः सर्व किया जाता है — कुछ अपलोड नहीं करना) और आपके <strong>ईमेल</strong> को सत्यापित करता है। हम केवल आपका डोमेन, यह ईमेल और आपके Tiger/PHP संस्करण साझा करते हैं। नहीं चाहते? इसे छोड़ दें — या पंजीकरण विजेट बंद करें / इस मॉड्यूल को निष्क्रिय करें।',
    'register.admin_email'  => 'व्यवस्थापक ईमेल',
    'register.register_btn' => 'पंजीकृत करें',
    'register.badge.domain' => 'डोमेन',
    'register.badge.email'  => 'ईमेल',
    'register.state.verified' => 'सत्यापित',
    'register.state.pending'  => 'लंबित',
    'register.verify_domain'  => 'डोमेन सत्यापित करें',
    'register.resend_email'   => 'सत्यापन ईमेल फिर भेजें',
    'register.net_error'      => 'नेटवर्क त्रुटि — कृपया पुनः प्रयास करें।',

    // Public email-verify landing
    'register.verify.ok_title'   => 'आपकी साइट सत्यापित है',
    'register.verify.ok_body'    => 'धन्यवाद — आपका ईमेल पुष्ट हो गया है।',
    'register.verify.ok_cta'     => 'अपने डैशबोर्ड पर जाएँ',
    'register.verify.fail_title' => 'वह लिंक काम नहीं आया',
    'register.verify.fail_body'  => 'यह समाप्त हो गया होगा या पहले ही उपयोग किया जा चुका होगा। इसे पंजीकरण विजेट या सेटिंग्स से फिर भेजें।',
    'register.verify.fail_cta'   => 'पंजीकरण पर जाएँ',
    'register.nav.label' => 'पंजीकरण',
    'register.widget.title' => 'पंजीकरण',
    'register.widget.registered' => 'आपकी साइट पंजीकृत है',
    'register.widget.site_id' => 'Site ID',
    'register.widget.intro' => 'सत्यापित Site ID पाने और Tiger नेटवर्क से जुड़ने के लिए इस साइट को पंजीकृत करें — वैकल्पिक, और यह कुछ भी चालू या बंद नहीं करता। हम केवल आपका डोमेन, यह ईमेल और आपके Tiger/PHP संस्करण साझा करते हैं।',
    'register.widget.register' => 'पंजीकृत करें',
    'register.widget.confirming' => 'पुष्टि की जा रही है कि आप %s को नियंत्रित करते हैं।',
    'register.widget.last_step' => 'अंतिम चरण: हमने %s पर जो लिंक भेजा है, उस पर क्लिक करें।',
    'register.widget.resend' => 'ईमेल फिर भेजें',
];
