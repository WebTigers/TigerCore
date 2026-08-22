<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — Hindi strings (locale `hi`). Mirrors en/agent.php key-for-key.
 */
return [
    // Settings screen
    'agent.settings.title'        => 'AI एजेंट',
    'agent.settings.subtitle'     => 'अपना खुद का AI अकाउंट कनेक्ट करें और एजेंट को अपनी साइट के भीतर काम करने दें।',
    'agent.settings.saved'        => 'एजेंट सेटिंग्स सहेजी गईं।',
    'agent.settings.save'         => 'सहेजें',
    'agent.settings.provider'     => 'प्रदाता',
    'agent.settings.model'        => 'मॉडल',
    'agent.settings.model.ph'     => 'जैसे claude-sonnet-5',
    'agent.settings.model.refresh' => 'मॉडल सूची रिफ्रेश करें',
    'agent.settings.key'          => 'API कुंजी',
    'agent.settings.key.ph'       => 'कनेक्ट करने के लिए एक कुंजी पेस्ट करें (मौजूदा को बनाए रखने के लिए खाली छोड़ें)',
    'agent.settings.enabled'      => 'AI एजेंट सक्षम करें',
    'agent.settings.connected'    => 'कनेक्टेड — एक कुंजी संग्रहित है (एन्क्रिप्टेड)।',
    'agent.settings.disconnected' => 'कनेक्टेड नहीं — एजेंट सक्षम करने के लिए एक API कुंजी पेस्ट करें।',
    'agent.settings.connection'   => 'कनेक्शन',
    'agent.settings.crypto_missing' => 'एन्क्रिप्शन कॉन्फ़िगर नहीं है (<code>tiger.crypto.key</code>), इसलिए API कुंजी अभी सुरक्षित रूप से संग्रहित नहीं की जा सकती।',
    'agent.settings.mode_max'     => 'ऑटोमेशन की अधिकतम सीमा',
    'agent.settings.mode_max.help' => 'यहाँ कोई भी उपयोगकर्ता जिस उच्चतम ऑटोमेशन स्तर का उपयोग कर सकता है। उपयोगकर्ता इसे घटा सकते हैं, पर इससे आगे कभी नहीं।',
    'agent.settings.mode.ask'     => 'पूछें — हर बदलाव को मंज़ूरी दें (सबसे सुरक्षित)',
    'agent.settings.mode.auto'    => 'ऑटो — नियमित बदलाव स्वचालित रूप से चलते हैं; कोड/फ़ाइलें फिर भी पूछती हैं',
    'agent.settings.mode.yolo'    => 'YOLO — भूमिका जो अनुमति देती है वह सब स्वचालित रूप से चलता है',
    'agent.settings.how.title'    => 'यह कैसे काम करता है',
    'agent.settings.how.body1'    => 'एजेंट <strong>आपके रूप में</strong> काम करता है — यह कभी भी आपकी भूमिका की अनुमति से अधिक नहीं कर सकता। पढ़ने के काम अपने आप चलते हैं; बदलाव पहले आपकी मंज़ूरी के लिए दिखाए जाते हैं।',
    'agent.settings.how.body2'    => '<strong>अपना खुद का अकाउंट लाएँ:</strong> आप जो कुंजी पेस्ट करते हैं वह आपकी है, इस सर्वर पर एन्क्रिप्टेड संग्रहित होती है और कभी साझा नहीं की जाती। आपका AI प्रदाता आपसे सीधे शुल्क लेता है।',

    // Aside modes
    'agent.mode.ask'              => 'पूछें',
    'agent.mode.auto'            => 'ऑटो',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'हर बदलाव को मंज़ूरी दें',
    'agent.mode.auto.hint'      => 'नियमित बदलाव अपने आप चलते हैं; कोड/फ़ाइलें पूछती हैं',
    'agent.mode.yolo.hint'      => 'सब कुछ अपने आप चलता है — संभल कर रहें',

    // Turn results
    'agent.turn.ok'             => 'हो गया।',
    'agent.approve.ok'          => 'कार्रवाइयाँ पूरी हुईं।',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'फ़ाइल अटैच हुई।',
    'agent.file.type'           => 'वह फ़ाइल प्रकार समर्थित नहीं है।',
    'agent.file.too_large'      => 'वह फ़ाइल बहुत बड़ी है।',
    'agent.file.failed'         => 'फ़ाइल अटैच नहीं हो सकी। कृपया फिर से प्रयास करें।',

    // Errors
    'agent.error.empty'         => 'एजेंट के लिए एक संदेश लिखें।',
    'agent.error.unconfigured'  => 'AI एजेंट अभी तक कनेक्टेड नहीं है। सेटिंग्स → AI एजेंट में एक API कुंजी जोड़ें।',
    'agent.error.provider'      => 'AI प्रदाता से संपर्क नहीं हो सका। कुंजी जाँचें और फिर से प्रयास करें।',
    'agent.error.run_missing'   => 'वह बातचीत या टर्न अब उपलब्ध नहीं है।',

    // Aside UI
    'agent.aside.title'         => 'एजेंट',
    'agent.aside.placeholder'   => 'एजेंट से कुछ बनाने, बदलने या समझाने के लिए कहें…',
    'agent.aside.new'           => 'नई चैट',
    'agent.aside.send'          => 'भेजें',
    'agent.aside.approve'       => 'मंज़ूरी दें',
    'agent.aside.approve_all'   => 'सभी को मंज़ूरी दें',
    'agent.aside.thinking'      => 'काम चल रहा है…',
    'agent.aside.empty'         => 'बातचीत शुरू करें — एजेंट आपकी अनुमतियों के साथ काम करता है।',

    // Skills (messages)
    'agent.skills.installed'      => 'स्किल इंस्टॉल हुई।',
    'agent.skills.install_failed' => 'वह स्किल इंस्टॉल नहीं हो सकी।',
    'agent.skills.none_found'     => 'उस URL पर कोई SKILL.md नहीं मिला।',
    'agent.skills.enabled'        => 'स्किल चालू की गई।',
    'agent.skills.disabled'       => 'स्किल बंद की गई।',
    'agent.skills.removed'        => 'स्किल हटाई गई।',

    // Skills (admin screen)
    'agent.skills.title'          => 'एजेंट स्किल्स',
    'agent.skills.subtitle'       => 'AI एजेंट के लिए इंस्टॉल-योग्य जानकारी। Tiger इन रिपॉज़िटरी को ब्राउज़ करता है — इनकी गारंटी नहीं देता; इंस्टॉल कर चालू करने से पहले किसी स्किल का स्रोत जाँचें। इंस्टॉल की गई स्किल्स ऊपर पिन रहती हैं।',
    'agent.skills.rescan'         => 'फिर से स्कैन करें',
    'agent.skills.rescan.title'   => 'स्रोतों को फिर से स्कैन करें',
    'agent.skills.add_url'        => 'किसी GitHub URL से जोड़ें',
    'agent.skills.url.ph'         => 'https://github.com/owner/repo (या एक सबफ़ोल्डर / एक SKILL.md)',
    'agent.skills.install'        => 'इंस्टॉल करें',
    'agent.skills.add_url.help'   => 'कोई भी रिपॉज़िटरी, ब्रांच, सबफ़ोल्डर, या सीधे किसी SKILL.md का लिंक — सिर्फ़ सूचीबद्ध स्रोत ही नहीं।',
    'agent.skills.col.skill'      => 'स्किल',
    'agent.skills.col.description' => 'विवरण',
    'agent.skills.col.source'     => 'स्रोत',
    'agent.skills.col.status'     => 'स्थिति',
    'agent.skills.col.actions'    => 'कार्रवाइयाँ',
    'agent.skills.src.title'      => 'SKILL.md',
    'agent.skills.src.note'       => 'केवल उद्गम — इंस्टॉल करने से पहले जाँचें।',
    'agent.skills.close'          => 'बंद करें',

    // MCP connections (outbound) — messages
    'agent.mcp.saved'     => 'कनेक्शन सहेजा गया।',
    'agent.mcp.removed'   => 'कनेक्शन हटाया गया।',
    'agent.mcp.bad_url'   => 'MCP सर्वर के लिए एक मान्य http(s) URL दर्ज करें।',
    'agent.mcp.bad_label' => 'कनेक्शन को एक नाम दें।',
    'agent.mcp.not_found' => 'वह कनेक्शन उपलब्ध नहीं है।',

    // MCP connections (outbound) — admin screen
    'agent.mcp.title'         => 'MCP कनेक्शन',
    'agent.mcp.subtitle'      => 'बाहरी <strong>MCP सर्वर</strong> कनेक्ट करें ताकि AI एजेंट अपने साथ-साथ उनके टूल भी उपयोग कर सके। एक टूल कॉल रिमोट सर्वर पर चलती है और किसी भी एजेंट राइट की तरह मंज़ूरी से गेट होती है। केवल व्यवस्थापक।',
    'agent.mcp.add'           => 'एक कनेक्शन जोड़ें',
    'agent.mcp.name'          => 'नाम',
    'agent.mcp.name.ph'       => 'जैसे GitHub, Linear, Weather',
    'agent.mcp.url'           => 'सर्वर URL (Streamable HTTP)',
    'agent.mcp.token'         => 'Bearer टोकन',
    'agent.mcp.token.optional' => '(वैकल्पिक; एन्क्रिप्टेड संग्रहित)',
    'agent.mcp.token.ph'      => 'मौजूदा को बनाए रखने के लिए खाली छोड़ें',
    'agent.mcp.enabled'       => 'सक्षम',
    'agent.mcp.save'          => 'सहेजें',
    'agent.mcp.cancel'        => 'रद्द करें',
    'agent.mcp.connected'     => 'कनेक्टेड सर्वर',
    'agent.mcp.empty'         => 'अभी तक कोई कनेक्शन नहीं — बाईं ओर एक जोड़ें।',
    'agent.js.models_live' => 'आपके अकाउंट से लाइव।',
    'agent.js.models_static' => 'सामान्य मॉडल — लाइव सूची के लिए एक कुंजी कनेक्ट करें।',
    'agent.js.settings_saved' => 'सेटिंग्स सहेजी गईं।',
    'agent.js.network_error' => 'नेटवर्क त्रुटि — कृपया फिर से प्रयास करें।',
    'agent.js.connection_saved' => 'कनेक्शन सहेजा गया।',
    'agent.js.remove_connection_title' => 'कनेक्शन हटाएँ',
    'agent.js.remove_connection_body' => 'एजेंट अपने टूल तक पहुँच खो देगा।',
    'agent.js.remove_label' => 'हटाएँ',
    'agent.js.remove_skill_title' => 'स्किल हटाएँ',
    'agent.js.remove_skill_body' => 'इस स्किल और इसकी फ़ाइलें हटाएँ? (यह फिर से इंस्टॉल करने के लिए कैटलॉग में बनी रहती है।)',
    'agent.nav.label' => 'AI एजेंट',
    'agent.nav.skills' => 'एजेंट स्किल्स',
    'agent.nav.mcp' => 'MCP कनेक्शन',
];
