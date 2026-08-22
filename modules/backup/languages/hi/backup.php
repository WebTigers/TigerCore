<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'बैकअप पूरा हुआ।',
    'backup.failed'           => 'बैकअप विफल रहा। विवरण के लिए लॉग जाँचें।',
    'backup.deleted'          => 'बैकअप हटाया गया।',
    'backup.restore.done'     => 'पुनर्स्थापना पूरी हुई।',
    'backup.restore.failed'   => 'पुनर्स्थापना विफल रही। आपका पुनर्स्थापना-पूर्व सुरक्षा बैकअप उपलब्ध है।',
    'backup.restore.confirm'  => 'इस विनाशकारी क्रिया की पुष्टि के लिए RESTORE टाइप करें।',
    'backup.settings.saved'   => 'बैकअप सेटिंग्स सहेजी गईं।',
    'backup.bad_component'    => 'बैकअप के लिए कम से कम एक घटक चुनें।',
    'backup.bad_disk'         => 'अज्ञात बैकअप गंतव्य।',
    'backup.bad_email'        => 'मान्य ईमेल पता(ते) दर्ज करें, अल्पविराम से अलग करके।',
    'backup.not_found'        => 'वह बैकअप नहीं मिल सका।',
    'backup.upload.failed'    => 'अपलोड पूरा नहीं हुआ।',
    'backup.upload.invalid'   => 'वह फ़ाइल TigerBackup संग्रह नहीं है।',

    // Component labels
    'backup.comp.database'      => 'डेटाबेस',
    'backup.comp.database_desc' => 'सभी टेबल — एक पोर्टेबल SQL डंप',
    'backup.comp.media'         => 'मीडिया',
    'backup.comp.media_desc'    => 'अपलोड की गई फ़ाइलें',
    'backup.comp.modules'       => 'मॉड्यूल',
    'backup.comp.modules_desc'  => 'आपके ऐप मॉड्यूल',
    'backup.comp.platform'      => 'प्लेटफ़ॉर्म',
    'backup.comp.platform_desc' => 'ऐप कोड + कॉन्फ़िगरेशन (साइट स्थानांतरित करने के लिए)',

    // Outcome badges
    'backup.outcome.ok'      => 'ठीक',
    'backup.outcome.error'   => 'विफल',
    'backup.outcome.running' => 'चल रहा है',

    // Screen header
    'backup.title'      => 'बैकअप &amp; पुनर्स्थापना',
    'backup.subtitle'   => 'अपनी साइट को डाउनलोड करने योग्य ज़िप में संग्रहित करें — इसे स्थानीय रखें या क्लाउड स्टोरेज पर भेजें। यहाँ पुनर्स्थापित करें, या साइट को नए स्थान पर ले जाएँ।',
    'backup.action.run' => 'अभी बैकअप लें',

    // Create card
    'backup.card.create'             => 'एक बैकअप बनाएँ',
    'backup.create.include_label'     => 'क्या शामिल करें',
    'backup.create.destination_label' => 'गंतव्य',
    'backup.create.destination_help'  => 'सर्वर से बाहर बैकअप लेने के लिए एक क्लाउड <em>मीडिया डिस्क</em> (S3/GCS/Azure) कॉन्फ़िगर करें।',
    'backup.create.secrets_label'     => 'सीक्रेट्स शामिल करें (local.ini)',
    'backup.create.secrets_help'      => 'किसी साइट को अक्षुण्ण स्थानांतरित करने के लिए आवश्यक। संग्रह को सुरक्षित रूप से संभालें।',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'किसी फ़ाइल से पुनर्स्थापित करें',
    'backup.restore_file.help'   => 'इसे यहाँ पुनर्स्थापित करने के लिए एक <code>TigerBackup-*.zip</code> अपलोड करें — किसी साइट को नई इंस्टॉलेशन पर ले जाने का तरीका। यह <strong>विनाशकारी</strong> है: यह रखरखाव मोड में चलता है और पहले एक सुरक्षा बैकअप लेता है।',
    'backup.action.restore'      => 'पुनर्स्थापित करें',

    // History card
    'backup.card.history'         => 'बैकअप',
    'backup.history.empty'        => 'अभी तक कोई बैकअप नहीं। चुनें कि क्या शामिल करना है और <strong>अभी बैकअप लें</strong> पर क्लिक करें।',
    'backup.col.archive'          => 'संग्रह',
    'backup.col.size'             => 'आकार',
    'backup.col.includes'         => 'शामिल है',
    'backup.col.when'             => 'कब',
    'backup.col.where'            => 'कहाँ',
    'backup.col.actions'          => 'क्रियाएँ',
    'backup.pinned_title'         => 'मैन्युअल बैकअप कभी स्वतः प्रून नहीं किए जाते',
    'backup.action.download_title' => 'डाउनलोड करें',
    'backup.action.restore_title'  => 'इस बैकअप को पुनर्स्थापित करें',
    'backup.action.delete_title'   => 'हटाएँ',

    // Scheduled backups card
    'backup.card.scheduled'          => 'अनुसूचित बैकअप',
    'backup.scheduled.help'          => 'एक लय निर्धारित करें और Tiger स्वयं बैकअप लेता है। रोलिंग अवधारण सबसे नए <strong>N</strong> अनुसूचित बैकअप रखता है; मैन्युअल बैकअप कभी स्वतः नहीं हटाए जाते।',
    'backup.scheduled.schedule_label' => 'अनुसूची',
    'backup.scheduled.retention_label' => 'सबसे नए रखें (रोलिंग अधिकतम)',
    'backup.scheduled.retention_help' => '0 = सभी रखें।',
    'backup.scheduled.email_label'    => 'स्थिति ईमेल करें',
    'backup.scheduled.notify_label'   => 'सफलता &amp; विफलता पर ईमेल करें',
    'backup.scheduled.note'           => 'अनुसूचित बैकअप ऊपर <em>एक बैकअप बनाएँ</em> में चुने गए घटकों &amp; गंतव्य का उपयोग करते हैं, जो यहाँ सहेजे गए हैं:',
    'backup.action.save_settings'     => 'अनुसूची सेटिंग्स सहेजें',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'पुनर्स्थापना की पुष्टि करें',
    'backup.action.close'                => 'बंद करें',
    'backup.restore_modal.body_pre'      => 'आप पुनर्स्थापित करने वाले हैं ',
    'backup.restore_modal.body_post'     => '। यह वर्तमान डेटाबेस और/या फ़ाइलों को <strong>अधिलेखित</strong> करता है और इसे पूर्ववत नहीं किया जा सकता। पहले एक सुरक्षा बैकअप लिया जाता है, और पुनर्स्थापना के दौरान साइट रखरखाव मोड में चली जाती है।',
    'backup.restore_modal.confirm_label' => 'पुष्टि के लिए <code>RESTORE</code> टाइप करें',
    'backup.action.cancel'               => 'रद्द करें',
    'backup.action.restore_now'          => 'अभी पुनर्स्थापित करें',
    'backup.js.select_component'     => 'कम से कम एक घटक चुनें।',
    'backup.js.confirm_delete_named' => '%s हटाएँ? यह संग्रह को स्थायी रूप से हटा देता है।',
    'backup.js.choose_zip'           => 'पहले एक .zip संग्रह चुनें।',
    'backup.nav.label' => 'बैकअप',
];
