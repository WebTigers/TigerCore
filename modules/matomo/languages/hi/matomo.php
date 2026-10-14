<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — Hindi strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Matomo सेटिंग्स सहेजी गईं।',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Matomo एनालिटिक्स',
    'matomo.subtitle'                  => 'अपनी सार्वजनिक साइट को मापने और एफिलिएट ट्रैफ़िक को एट्रिब्यूट करने के लिए अपने स्वयं-होस्ट किए गए Matomo को कनेक्ट करें।',
    'matomo.save'                      => 'सहेजें',

    // Connection card
    'matomo.card.connection'           => 'Matomo कनेक्शन',
    'matomo.enable'                    => 'Matomo ट्रैकिंग सक्षम करें',
    'matomo.url'                       => 'Matomo URL',
    'matomo.url_help'                  => 'आपके Matomo इंस्टॉल का बेस URL, उदाहरण के लिए https://analytics.example.com/',
    'matomo.site_id'                   => 'साइट ID',
    'matomo.site_id_help'              => 'वह Matomo साइट (idsite) जिसे यह इंस्टॉल रिपोर्ट करता है — इसे Matomo में Administration → Websites के अंतर्गत खोजें।',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'एफिलिएट एट्रिब्यूशन',
    'matomo.affiliate_help'            => 'जब कोई विज़िटर किसी एफिलिएट लिंक (/a/<name>) से आता है, तो उस एफिलिएट नाम को विज़िट पर Matomo कस्टम डाइमेंशन के रूप में दर्ज किया जाता है — ताकि विज़िट और कन्वर्ज़न को एफिलिएट के अनुसार सेगमेंट किया जा सके। लिंक और उसकी कुकी एफिलिएट प्रोग्राम द्वारा सेट की जाती है; यह केवल उन्हें पढ़ता है।',
    'matomo.affiliate_dimension'       => 'कस्टम डाइमेंशन इंडेक्स',
    'matomo.affiliate_dimension_help'  => 'Matomo में विज़िट-स्कोप वाले कस्टम डाइमेंशन का इंडेक्स जो एफिलिएट नाम रखता है (डिफ़ॉल्ट 1)।',

    // Privacy & consent card
    'matomo.card.privacy'              => 'गोपनीयता और सहमति',
    'matomo.exclude_staff'             => 'साइन-इन किए हुए स्टाफ़ को ट्रैक न करें',
    'matomo.exclude_staff_help'        => 'मैनेजर, एडमिन और डेवलपर की विज़िट छोड़ दें ताकि आपकी अपनी टीम आंकड़ों को विकृत न करे।',
    'matomo.privacy_note'              => 'ट्रैकर केवल तभी लोड होता है जब एनालिटिक्स सहमति इसकी अनुमति देती है। Matomo Do Not Track का भी सम्मान करता है और सर्वर पर IP पतों को अनामित करता है।',

    // Client-side
    'matomo.net_error'                 => 'नेटवर्क त्रुटि — कृपया पुनः प्रयास करें।',
    'matomo.fix_fields'                => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',
];
