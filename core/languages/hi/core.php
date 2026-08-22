<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerCore — हिन्दी (hi) core strings. प्रीफ़िक्स वाली सिमेंटिक कुंजियाँ (core.*).
 */
return [
    // --- /api सेवाओं की प्रतिक्रियाएँ (डिफ़ॉल्ट मान) ---
    'core.api.success'               => 'हो गया।',
    'core.api.error.general'         => 'कुछ गड़बड़ हो गई। कृपया फिर से प्रयास करें।',
    'core.api.error.form'            => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',
    'core.api.error.csrf'            => 'ओह — आपका सुरक्षा टोकन समाप्त हो गया। जारी रखने के लिए कृपया पेज रिफ़्रेश करें। (ये जानबूझकर समाप्त होते हैं; दोष सुरक्षा ग्रेमलिन्स को दें।)',
    'core.api.error.invalid_action'  => 'वह क्रिया उपलब्ध नहीं है।',
    'core.api.error.not_allowed'     => 'आपके पास ऐसा करने की अनुमति नहीं है।',
    'core.api.error.login_required'  => 'जारी रखने के लिए कृपया लॉग इन करें।',
    'core.token.created'          => 'टोकन बनाया गया — इसे अभी कॉपी करें; यह दोबारा नहीं दिखाया जाएगा।',
    'core.token.revoked'          => 'टोकन रद्द किया गया।',
    'core.api.error.login_failed'    => 'अमान्य ईमेल या पासवर्ड।',
    'core.api.error.missing_module'  => 'कोई मॉड्यूल निर्दिष्ट नहीं किया गया।',
    'core.api.error.missing_service' => 'कोई सेवा निर्दिष्ट नहीं की गई।',
    'core.api.error.missing_action'  => 'कोई क्रिया निर्दिष्ट नहीं की गई।',

    // --- फ़ॉर्म: reCAPTCHA सत्यापन ---
    'core.form.recaptcha.missing'    => 'कृपया पुष्टि करें कि आप रोबोट नहीं हैं।',
    'core.form.recaptcha.failed'     => 'reCAPTCHA सत्यापन विफल रहा। कृपया फिर से प्रयास करें।',
    'core.form.recaptcha.error'      => 'अभी reCAPTCHA सत्यापित नहीं किया जा सका। कृपया फिर से प्रयास करें।',

    // --- टू-फ़ैक्टर प्रमाणीकरण (TOTP) ---
    'core.auth.twofa.enabled'        => 'टू-फ़ैक्टर प्रमाणीकरण अब चालू है।',
    'core.auth.twofa.disabled'       => 'टू-फ़ैक्टर प्रमाणीकरण बंद कर दिया गया है।',
    'core.auth.twofa.bad_code'       => 'वह कोड गलत है या समाप्त हो चुका है।',
    'core.auth.twofa.unavailable'    => 'इस इंस्टॉल पर टू-फ़ैक्टर प्रमाणीकरण उपलब्ध नहीं है।',

    // --- फ़ॉर्म सत्यापन (फ़ील्ड स्तर पर) ---
    'core.form.password_mismatch'    => 'पासवर्ड मेल नहीं खाते।',

    // --- पासवर्ड नीति (Tiger_Policy_Password की कुंजियाँ) ---
    'password.too_short'             => 'पासवर्ड बहुत छोटा है — कृपया कम से कम 8 अक्षर उपयोग करें।',
    'password.needs_complexity'      => 'बड़े और छोटे अक्षर, एक संख्या और एक चिह्न जोड़ें।',
    'password.reused'                => 'आप यह पासवर्ड पहले उपयोग कर चुके हैं — कृपया नया चुनें।',

    // --- सामान्य UI लेबल ---
    'core.common.close'              => 'बंद करें',
    'core.common.done'               => 'हो गया',
    'core.common.back_home'          => 'होम पर वापस जाएँ',

    // --- त्रुटि पेज (403 / 404 / 500) ---
    'core.error.badge'               => 'त्रुटि',
    'core.error.403.title'           => 'आपके पास इसकी पहुँच नहीं है।',
    'core.error.404.title'           => 'वह पेज मौजूद नहीं है।',
    'core.error.500.title'           => 'कुछ गड़बड़ हो गई।',
    'core.error.403.sub'             => 'आप लॉग इन हैं, लेकिन यह क्षेत्र आपके खाते के लिए उपलब्ध नहीं है।',
    'core.error.404.sub'             => 'हो सकता है पेज हट गया हो, या कभी मौजूद ही न रहा हो। चलिए आपको वापस सही रास्ते पर लाते हैं।',
    'core.error.500.sub'             => 'हमारी ओर से कुछ टूट गया। हमें सूचित कर दिया गया है और हम इसे देख रहे हैं — कृपया थोड़ी देर में फिर से प्रयास करें।',
    'core.error.switch_account'      => 'खाता बदलें',

    // --- प्रमाणीकरण: साझा लेबल ---
    'core.auth.email'                => 'ईमेल',
    'core.auth.password'             => 'पासवर्ड',
    'core.auth.email_code'           => 'मुझे एक कोड ईमेल करें',
    'core.auth.back_to_login'        => 'लॉग इन पर वापस जाएँ',
    'core.auth.return_to'            => '%s पर वापस जाएँ',

    // --- प्रमाणीकरण: लॉग इन ---
    'core.auth.login.title'          => 'Tiger में लॉग इन करें',
    'core.auth.login.subtitle'       => 'फिर से स्वागत है।',
    'core.auth.login.identifier'     => 'ईमेल या उपयोगकर्ता नाम',
    'core.auth.login.forgot'         => 'अपना पासवर्ड भूल गए?',
    'core.auth.login.submit'         => 'लॉग इन करें',
    'core.auth.login.use_code'       => 'इसके बजाय कोड से लॉग इन करें',

    // --- प्रमाणीकरण: टू-फ़ैक्टर संकेत (लॉग इन चरण) ---
    'core.auth.twofa.prompt'         => 'अपने ऑथेंटिकेटर ऐप से 6 अंकों का कोड दर्ज करें।',
    'core.auth.twofa.code_label'     => 'सत्यापन कोड',
    'core.auth.twofa.verify'         => 'सत्यापित करें',
    'core.auth.twofa.use_recovery'   => 'रिकवरी कोड का उपयोग करें',

    // --- प्रमाणीकरण: लॉक स्क्रीन ---
    'core.auth.lock.title'           => 'स्क्रीन लॉक है',
    'core.auth.lock.subtitle'        => 'जारी रखने के लिए फिर से सत्यापित करें।',
    'core.auth.lock.unlock'          => 'अनलॉक करें',
    'core.auth.lock.use_code'        => 'कोड से अनलॉक करें',
    'core.auth.lock.email_send_to'   => 'हम एक बार उपयोग होने वाला कोड यहाँ भेजेंगे',
    'core.auth.lock.use_password'    => 'इसके बजाय पासवर्ड का उपयोग करें',
    'core.auth.lock.not_you'         => '%s नहीं हैं? लॉग आउट करें',

    // --- प्रमाणीकरण: पासवर्ड रीसेट करें ---
    'core.auth.reset.title'          => 'नया पासवर्ड सेट करें',
    'core.auth.reset.subtitle'       => 'एक मज़बूत पासवर्ड चुनें जिसे आप कहीं और उपयोग नहीं करते।',
    'core.auth.reset.new_password'   => 'नया पासवर्ड',
    'core.auth.reset.confirm_password' => 'पासवर्ड की पुष्टि करें',
    'core.auth.reset.submit'         => 'नया पासवर्ड सेट करें',

    // --- प्रमाणीकरण: पासवर्ड भूल गए ---
    'core.auth.forgot.title'         => 'अपना पासवर्ड रीसेट करें',
    'core.auth.forgot.subtitle'      => 'हम आपको नया चुनने के लिए एक लिंक ईमेल करेंगे।',
    'core.auth.forgot.submit'        => 'रीसेट लिंक भेजें',

    // --- प्रमाणीकरण: लॉग आउट ---
    'core.auth.logout.title'         => 'आप लॉग आउट हो गए हैं।',
    'core.auth.logout.subtitle'      => 'आने के लिए धन्यवाद।',
    'core.auth.logout.login_again'   => 'फिर से लॉग इन करें',

    // --- प्रमाणीकरण: कोड से लॉग इन (बिना पासवर्ड) ---
    'core.auth.otp.title'            => 'कोड से लॉग इन करें',
    'core.auth.otp.subtitle'         => 'हम आपको एक बार उपयोग होने वाला कोड ईमेल करेंगे — पासवर्ड की ज़रूरत नहीं।',
    'core.auth.otp.restart'          => 'दूसरा ईमेल उपयोग करें',
    'core.auth.otp.use_password'     => 'इसके बजाय पासवर्ड से लॉग इन करें',

    // --- प्रमाणीकरण: टू-फ़ैक्टर प्रबंधन (सुरक्षा स्क्रीन) ---
    'core.auth.twofa.heading'        => 'टू-फ़ैक्टर प्रमाणीकरण',
    'core.auth.twofa.lead'           => 'अपने लॉग इन में एक ऑथेंटिकेटर ऐप का एक बार उपयोग होने वाला कोड जोड़ें।',
    'core.auth.twofa.unavailable_detail' => 'इस इंस्टॉल पर टू-फ़ैक्टर प्रमाणीकरण अभी उपलब्ध नहीं है — ऐप एन्क्रिप्शन कुंजी (%s) कॉन्फ़िगर नहीं है। किसी व्यवस्थापक से इसे सेट करने के लिए कहें।',
    'core.auth.twofa.enabled_badge'  => 'सक्षम',
    'core.auth.twofa.protected'      => 'आपका ऑथेंटिकेटर ऐप इस खाते की रक्षा कर रहा है।',
    'core.auth.twofa.recovery_remaining' => 'शेष रिकवरी कोड:',
    'core.auth.twofa.recovery_help'  => 'रिकवरी कोड आपको तब लॉग इन करने देते हैं जब आप अपना डिवाइस खो देते हैं। नया सेट बनाने के लिए फिर से सक्षम करें।',
    'core.auth.twofa.disable_prompt' => 'टू-फ़ैक्टर प्रमाणीकरण बंद करने के लिए, अपने ऐप के मौजूदा कोड (या रिकवरी कोड) से पुष्टि करें:',
    'core.auth.twofa.disable_btn'    => '2FA अक्षम करें',
    'core.auth.twofa.intro'          => 'Google Authenticator, 1Password, Authy या Microsoft Authenticator जैसे ऐप के समय-आधारित कोड से अपने खाते की रक्षा करें।',
    'core.auth.twofa.enable_btn'     => 'टू-फ़ैक्टर प्रमाणीकरण सक्षम करें',
    'core.auth.twofa.step_scan'      => 'QR कोड स्कैन करें',
    'core.auth.twofa.step_scan_detail' => 'अपने ऑथेंटिकेटर ऐप से — या कुंजी हाथ से दर्ज करें।',
    'core.auth.twofa.qr_preview'     => 'QR पूर्वावलोकन',
    'core.auth.twofa.setup_key_label' => 'सेटअप कुंजी (मैन्युअल प्रविष्टि)',
    'core.auth.twofa.open_in_app'    => 'ऐप में खोलें',
    'core.auth.twofa.step_recovery'  => 'अपने रिकवरी कोड सहेजें।',
    'core.auth.twofa.step_recovery_detail' => 'यदि आप अपना डिवाइस खो देते हैं तो हर एक को एक बार उपयोग किया जा सकता है। इन्हें किसी सुरक्षित स्थान पर रखें।',
    'core.auth.twofa.copy_codes'     => 'कोड कॉपी करें',
    'core.auth.twofa.step_confirm'   => 'पुष्टि करें।',
    'core.auth.twofa.step_confirm_detail' => 'आपका ऐप अभी जो 6 अंकों का कोड दिखा रहा है, उसे दर्ज करें:',
    'core.auth.twofa.verify_turn_on' => 'सत्यापित करें और चालू करें',
    'core.auth.twofa.back_to_admin'  => 'व्यवस्थापन पर वापस जाएँ',

    // --- डैशबोर्ड (व्यवस्थापन होम) ---
    'core.dashboard.title'           => 'डैशबोर्ड',
    'core.dashboard.lead'            => 'Tiger व्यवस्थापन में आपका स्वागत है।',
    'core.dashboard.customize'       => 'अनुकूलित करें',
    'core.dashboard.empty_title'     => 'अभी कोई डैशबोर्ड विजेट नहीं',
    'core.dashboard.empty_lead'      => 'डैशबोर्ड विजेट प्रदान करने वाले मॉड्यूल सक्रिय होते ही यहाँ स्वतः दिखाई देंगे।',
    'core.dashboard.drag_hint'       => 'पुनर्व्यवस्थित करने के लिए खींचें',
    'core.dashboard.collapse_aria'   => 'विजेट संक्षिप्त करें',
    'core.dashboard.customize_title' => 'डैशबोर्ड अनुकूलित करें',
    'core.dashboard.customize_help'  => 'विजेट चालू या बंद करें। छिपा हुआ विजेट नहीं दिखाया जाता — इसे कभी भी वापस चालू करें।',

    // --- खाता होम ---
    'core.account.title'             => 'मेरा खाता',
    'core.account.lead'              => 'आपकी सदस्यता, लाइसेंस और प्रोफ़ाइल।',
    'core.account.empty_lead'        => 'जैसे-जैसे आप सदस्यताएँ और सेवाएँ जोड़ेंगे, आपके खाते का विवरण यहाँ दिखाई देगा।',
    'core.js.network_error' => 'नेटवर्क त्रुटि — कृपया फिर से प्रयास करें।',
    'core.js.recaptcha' => 'कृपया reCAPTCHA पूरा करें और फिर से प्रयास करें।',
    'core.js.incorrect_password' => 'गलत पासवर्ड।',
    'core.js.code_sent' => 'हमने %s पर 6 अंकों का कोड भेजा है। इसे नीचे दर्ज करें।',
    'core.js.code_invalid' => 'वह कोड अमान्य है या समाप्त हो चुका है।',
    'core.js.code_incorrect' => 'वह कोड गलत है या समाप्त हो चुका है।',
    'core.js.invalid_login' => 'अमान्य लॉगिन या पासवर्ड।',
    'core.js.passwords_mismatch' => 'पासवर्ड मेल नहीं खाते।',
    'core.js.reset_failed' => 'आपका पासवर्ड रीसेट नहीं किया जा सका — लिंक समाप्त हो चुका हो सकता है।',
    'core.js.twofa_disabled' => 'टू-फ़ैक्टर प्रमाणीकरण अक्षम।',
    'core.js.twofa_code_wrong_on' => 'वह कोड गलत है। टू-फ़ैक्टर प्रमाणीकरण अभी भी चालू है।',
    'core.js.setup_failed' => 'सेटअप शुरू नहीं किया जा सका। कृपया फिर से प्रयास करें।',
    'core.js.twofa_on' => 'टू-फ़ैक्टर प्रमाणीकरण चालू है। 🎉',
    'core.js.twofa_code_wrong' => 'वह कोड मेल नहीं खाया। अपने ऐप की घड़ी जाँचें और मौजूदा कोड आज़माएँ।',
    'core.js.widget_load_error' => 'यह विजेट लोड नहीं किया जा सका।',
    'core.nav.dashboard' => 'डैशबोर्ड',
    'core.nav.account' => 'मेरा खाता',
    'core.nav.content' => 'सामग्री',
    'core.nav.articles' => 'लेख',
    'core.nav.menus' => 'मेन्यू',
    'core.nav.media' => 'मीडिया',
    'core.nav.users' => 'उपयोगकर्ता',
    'core.nav.orgs' => 'संगठन',
    'core.nav.code' => 'कोड',
    'core.nav.modules' => 'मॉड्यूल',
    'core.nav.settings' => 'सेटिंग्स',
    'core.datatable.info' => '_TOTAL_ में से _START_ से _END_ प्रविष्टियाँ दिखा रहे हैं',
    'core.datatable.info_empty' => '0 में से 0 से 0 प्रविष्टियाँ दिखा रहे हैं',
    'core.datatable.info_filtered' => '(कुल _MAX_ प्रविष्टियों में से फ़िल्टर किया गया)',
    'core.datatable.length_menu' => 'प्रति पेज _MENU_',
    'core.datatable.search_placeholder' => 'खोजें…',
    'core.datatable.zero_records' => 'कोई मिलती-जुलती प्रविष्टि नहीं मिली',
    'core.datatable.empty_table' => 'कोई डेटा उपलब्ध नहीं',
    'core.datatable.loading' => 'लोड हो रहा है…',
    'core.datatable.processing' => 'प्रोसेस हो रहा है…',
    'core.datatable.paginate_first' => 'पहला',
    'core.datatable.paginate_last' => 'अंतिम',
    'core.datatable.paginate_next' => 'अगला',
    'core.datatable.paginate_prev' => 'पिछला',
    'core.nav.modules_manage' => 'प्रबंधित करें',
];
