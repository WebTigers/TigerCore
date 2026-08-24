<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — Hindi strings (identity.*).
 */
return [
    // Service / API messages
    'identity.saved'            => 'साइट पहचान सहेजी गई।',

    // Form placeholders
    'identity.field.site_name'  => 'जैसे Acme, Inc.',
    'identity.field.tagline'    => 'नाम के नीचे एक छोटी पंक्ति',
    'identity.field.site_description' => 'जैसे Acme जिज्ञासु पाठकों के लिए फ़ील्ड गाइड प्रकाशित करता है।',

    // Site Identity screen
    'identity.page.title'       => 'साइट पहचान',
    'identity.page.subtitle'    => 'आपकी साइट का नाम, लोगो, फ़ेविकॉन और सोशल प्रोफ़ाइल — वह ब्रांड जो ब्राउज़र टैब, खोज परिणामों और सोशल शेयर में दिखता है।',
    'identity.action.save'      => 'सहेजें',
    'identity.card.identity'    => 'पहचान',
    'identity.label.site_name'  => 'साइट का नाम',
    'identity.help.site_name'   => 'साइट हेडर और ब्राउज़र टैब में दिखाया जाता है, और खोज परिणामों में डिफ़ॉल्ट पेज शीर्षक और ब्रांड नाम के रूप में उपयोग होता है।',
    'identity.label.tagline'    => 'टैगलाइन',
    'identity.help.tagline'     => 'साइट का वर्णन करने वाली एक छोटी पंक्ति (वैकल्पिक)।',
    'identity.label.site_description' => 'साइट विवरण',
    'identity.help.site_description'  => 'साइट के बारे में एक या दो वाक्य। जब किसी पेज का अपना विवरण न हो, तो खोज परिणामों और सोशल शेयर कार्ड में इसे डिफ़ॉल्ट विवरण के रूप में उपयोग किया जाता है।',
    'identity.card.logo_favicon' => 'लोगो और फ़ेविकॉन',
    'identity.label.logo'       => 'लोगो',
    'identity.label.favicon'    => 'फ़ेविकॉन',
    'identity.help.logo'        => 'खोज परिणामों (Organization schema) में आपके ब्रांड के लिए उपयोग होता है और थीम के लिए उपलब्ध है।',
    'identity.help.favicon'     => 'ब्राउज़र टैब में छोटा आइकन। एक <strong>वर्गाकार</strong> छवि उपयोग करें — 512&times;512 या बड़ी आदर्श है; ब्राउज़र इसे आवश्यक हर आकार में छोटा कर देता है।',
    'identity.card.share_image'  => 'शेयर छवि',
    'identity.help.share_image'  => 'जब इस साइट का कोई पेज सोशल मीडिया पर शेयर होता है तो दिखने वाली छवि (Open Graph)। सबसे अच्छे कार्ड के लिए मीडिया लाइब्रेरी से एक चुनें — उसका वास्तविक आकार भी प्रकाशित होता है — या कहीं और होस्ट की गई छवि का पता पेस्ट करें। दोनों सेट करने पर लाइब्रेरी वाली छवि प्राथमिकता लेती है। 1200 × 630 पिक्सेल हर जगह काम करता है।',
    'identity.label.og_image'    => 'शेयर छवि चुनें',
    'identity.help.og_image'     => 'मीडिया लाइब्रेरी से एक छवि चुनें (अनुशंसित)।',
    'identity.label.og_image_url' => 'या छवि का पता',
    'identity.help.og_image_url'  => 'कहीं और होस्ट की गई छवि का पूरा https:// पता। इसका उपयोग केवल तभी होता है जब लाइब्रेरी से कोई छवि न चुनी गई हो।',
    'identity.card.social'      => 'सोशल प्रोफ़ाइल',
    'identity.help.social'      => 'आपके आधिकारिक प्रोफ़ाइल के पूर्ण URL। इन्हें आपके ब्रांड के सत्यापित लिंक के रूप में प्रकाशित किया जाता है (schema.org <code>sameAs</code>) — किसी को भी खाली छोड़ें।',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'साइट पहचान सहेजी गई।',
    'identity.js.fix_fields'    => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',
    'identity.js.network_error' => 'नेटवर्क त्रुटि — कृपया फिर से प्रयास करें।',
    'identity.nav.label' => 'साइट पहचान',
];
