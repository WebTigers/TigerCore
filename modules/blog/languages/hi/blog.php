<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module — Hindi strings (blog.*). Same key set as languages/en/blog.php.
 */
return [
    // API responses
    'blog.post.saved'    => 'लेख सहेजा गया।',
    'blog.post.deleted'  => 'लेख हटाया गया।',
    'blog.post.restored' => 'लेख चयनित संस्करण पर पुनर्स्थापित किया गया।',
    'blog.error.slug'          => 'इस लेख के लिए एक शीर्षक या slug आवश्यक है।',
    'blog.error.slug_reserved' => 'वह slug आरक्षित है (post, category, tag, feed)। कोई दूसरा चुनें।',

    // status + locale (form selects + list filter)
    'blog.status.draft'     => 'ड्राफ़्ट',
    'blog.status.published' => 'प्रकाशित',
    'blog.status.archived'  => 'संग्रहीत',
    'blog.locale.en' => 'अंग्रेज़ी',
    'blog.locale.es' => 'स्पेनिश',

    // public listings — card + archive + index
    'blog.card.min_read'         => 'मिनट पढ़ने में',
    'blog.term.category'         => 'श्रेणी',
    'blog.term.tag'              => 'टैग',
    'blog.archive.all_articles'  => 'सभी लेख',
    'blog.archive.empty'         => 'यहाँ अभी कोई लेख नहीं है।',
    'blog.index.heading'         => 'ब्लॉग',
    'blog.index.rss'             => 'RSS फ़ीड',
    'blog.index.empty'           => 'अभी तक कोई लेख प्रकाशित नहीं हुआ।',

    // editor labels
    'blog.editor.kicker'       => 'किकर',
    'blog.editor.title'        => 'शीर्षक',
    'blog.editor.subtitle'     => 'उपशीर्षक',
    'blog.editor.preamble'     => 'प्रस्तावना',
    'blog.editor.body'         => 'लेख',
    'blog.editor.excerpt'      => 'अंश',
    'blog.editor.feature'      => 'फ़ीचर छवि',
    'blog.editor.author'       => 'लेखक',
    'blog.editor.categories'   => 'श्रेणियाँ',
    'blog.editor.tags'         => 'टैग',
    'blog.editor.status'       => 'स्थिति',
    'blog.editor.publish_at'   => 'प्रकाशन तिथि',
    'blog.editor.seo'          => 'SEO और सोशल',
    'blog.editor.seo_title'    => 'मेटा शीर्षक',
    'blog.editor.seo_desc'     => 'मेटा विवरण',
    'blog.editor.canonical'    => 'कैनोनिकल URL',
    'blog.editor.comments'     => 'टिप्पणियाँ अनुमति दें',
    'blog.editor.language'     => 'भाषा',
    'blog.editor.slug'         => 'Slug',

    // editor — chrome, actions, hints
    'blog.editor.back'            => 'लेखों पर वापस जाएँ',
    'blog.editor.edit_article'    => 'लेख संपादित करें',
    'blog.editor.new_article'     => 'नया लेख',
    'blog.editor.settings'        => 'पोस्ट सेटिंग्स',
    'blog.editor.save'            => 'सहेजें',
    'blog.editor.close'           => 'बंद करें',
    'blog.editor.feature_set'     => 'फ़ीचर छवि सेट करें',
    'blog.editor.feature_replace' => 'बदलें',
    'blog.editor.feature_remove'  => 'हटाएँ',
    'blog.editor.publish_hint'    => 'खाली = अभी लाइव। भविष्य का समय इसे शेड्यूल करता है।',
    'blog.editor.categories_hint' => 'कॉमा से अलग करें। नई श्रेणियाँ सहेजने पर बनाई जाती हैं।',
    'blog.editor.tags_hint'       => 'कॉमा से अलग करें।',
    'blog.editor.excerpt_hint'    => 'सूचियों और सोशल कार्ड में दिखाया जाता है। न होने पर उपशीर्षक उपयोग होता है।',
    'blog.editor.slug_hint'       => 'खाली छोड़ने पर शीर्षक से स्वतः बनता है। इसे बदलने पर एक 301 रहता है।',

    // editor — formatting toolbar (title / aria-label)
    'blog.editor.tool.formatting'    => 'फ़ॉर्मेटिंग',
    'blog.editor.tool.heading'       => 'शीर्षक',
    'blog.editor.tool.subheading'    => 'उपशीर्षक',
    'blog.editor.tool.body_text'     => 'मुख्य पाठ',
    'blog.editor.tool.bold'          => 'बोल्ड',
    'blog.editor.tool.italic'        => 'इटैलिक',
    'blog.editor.tool.quote'         => 'उद्धरण',
    'blog.editor.tool.bullet_list'   => 'बुलेट सूची',
    'blog.editor.tool.numbered_list' => 'क्रमांकित सूची',
    'blog.editor.tool.link'          => 'लिंक',
    'blog.editor.tool.image'         => 'छवि डालें',
    'blog.editor.tool.source'        => 'HTML स्रोत संपादित करें',

    // editor — version history
    'blog.editor.versions'    => 'संस्करण इतिहास',
    'blog.editor.col_version' => 'संस्करण',
    'blog.editor.col_saved'   => 'सहेजा गया',
    'blog.editor.untitled'    => '(शीर्षकहीन)',
    'blog.editor.restore'     => 'पुनर्स्थापित करें',

    // placeholders
    'blog.ph.kicker'   => 'किकर — शीर्षक के ऊपर एक छोटा लेबल',
    'blog.ph.title'    => 'शीर्षक',
    'blog.ph.subtitle' => 'एक उपशीर्षक जोड़ें…',
    'blog.ph.preamble' => 'एक बड़े फ़ॉन्ट वाली शुरुआत जो पाठक को खींच ले…',
    'blog.ph.body'     => 'अपनी कहानी बताएँ…',

    // admin list
    'blog.list.title'       => 'लेख',
    'blog.list.subtitle'    => 'पोस्ट और लेख — CMS सामग्री स्टोर में संग्रहीत, इस रूप में',
    'blog.list.new'         => 'नया लेख',
    'blog.list.empty'       => 'अभी कोई लेख नहीं — अपना पहला लिखें।',
    'blog.list.status_all'  => 'सभी स्थितियाँ',
    'blog.list.clear'       => 'साफ़ करें',
    'blog.list.clear_title' => 'फ़िल्टर साफ़ करें',
    'blog.list.col_title'   => 'शीर्षक',
    'blog.list.col_slug'    => 'Slug',
    'blog.list.col_lang'    => 'भाषा',
    'blog.list.col_status'  => 'स्थिति',
    'blog.list.col_read'    => 'पढ़ना',
    'blog.list.col_updated' => 'अपडेट किया गया',
    'blog.list.col_actions' => 'क्रियाएँ',
    'blog.js.confirm_delete_article' => 'इस लेख को हटाएँ? यह सॉफ़्ट-डिलीट होता है और पुनर्प्राप्त किया जा सकता है।',
    'blog.js.media_picker_unavailable' => 'मीडिया पिकर उपलब्ध नहीं है।',
    'blog.js.fix_fields' => 'कृपया हाइलाइट किए गए फ़ील्ड ठीक करें।',
    'blog.js.network_error' => 'नेटवर्क त्रुटि — कृपया फिर से प्रयास करें।',
    'blog.js.confirm_restore' => 'संस्करण #%s पुनर्स्थापित करें? मौजूदा सामग्री पहले एक नए संस्करण के रूप में सहेजी जाती है।',
    'blog.js.link_url' => 'लिंक URL:',
];
