<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerMCP — Hindi strings (locale `hi`). Mirrors en/mcp.php key-for-key.
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'MCP सर्वर सक्षम किया गया।',
    'mcp.settings.disabled' => 'MCP सर्वर अक्षम किया गया।',
    'mcp.token.created'     => 'टोकन बनाया गया।',
    'mcp.token.revoked'     => 'टोकन रद्द किया गया।',

    // Connect screen — header
    'mcp.connect.title'     => 'MCP सर्वर',
    'mcp.connect.subtitle'  => 'किसी बाहरी AI क्लाइंट (Claude Desktop/Code, Cursor, ChatGPT) को Tiger की <code>/api</code> के माध्यम से इस साइट को चलाने दें — उन्हीं अनुमतियों के साथ जो टोकन की भूमिका वाले लॉग-इन उपयोगकर्ता के पास होती हैं। डिफ़ॉल्ट रूप से बंद; हर कार्रवाई ACL-नियंत्रित और ऑडिट की जाती है।',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'MCP एंडपॉइंट सक्षम करें',
    'mcp.connect.serve'        => 'सर्व करें',
    'mcp.connect.enable.help'  => 'चालू होने पर, <code>POST /mcp</code> किसी भी क्लाइंट से MCP (JSON-RPC) स्वीकार करता है जो मान्य टोकन प्रस्तुत करता है। बंद होने पर, यह 404 लौटाता है। बिना टोकन वाला कॉलर एक अतिथि है और केवल अतिथियों के लिए अनुमत रीड टूल तक पहुँच सकता है।',
    'mcp.connect.save'         => 'सहेजें',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'एक्सेस टोकन',
    'mcp.tokens.scope'    => 'नए टोकन का दायरा तय करें',
    'mcp.tokens.readonly' => 'केवल-पढ़ने — कोई लेखन नहीं (केवल पढ़ना)',
    'mcp.tokens.org'      => 'संगठन टोकन — संगठन के रूप में कार्य करता है, आपके रूप में नहीं (कोई संबद्ध उपयोगकर्ता नहीं)',
    'mcp.tokens.mint'     => 'टोकन बनाएँ',
    'mcp.tokens.once'     => 'इसे अभी कॉपी करें — यह केवल एक बार दिखाया जाता है।',
    'mcp.copy'            => 'कॉपी करें',
    'mcp.tokens.empty'    => 'अभी तक कोई टोकन नहीं — क्लाइंट कनेक्ट करने के लिए एक बनाएँ।',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'एक क्लाइंट कनेक्ट करें',
    'mcp.connect.token_field'    => 'नीचे दिए गए कॉन्फ़िग के लिए टोकन',
    'mcp.connect.token_field.ph' => 'tgr_… (पेस्ट करें, या ऊपर बनाएँ)',
    'mcp.connect.tab.npx'        => 'npx (Node आवश्यक)',
    'mcp.connect.tab.php'        => 'बिना Node (PHP)',
    'mcp.connect.npx.help'       => 'इसे अपने क्लाइंट के <code>mcpServers</code> कॉन्फ़िग में जोड़ें (Claude Desktop, Cursor, …)। कम्युनिटी <code>mcp-remote</code> ब्रिज का उपयोग करता है — कुछ भी इंस्टॉल नहीं करना।',
    'mcp.connect.php.help'       => 'Node नहीं है? <a href="/mcp/admin/download"><code>mcp-bridge.php</code> डाउनलोड करें</a>, इसे अपनी मशीन पर सहेजें और नीचे पथ सेट करें। केवल PHP आवश्यक।',
    'mcp.connect.test'           => 'या इसे सीधे टेस्ट करें:',
    'mcp.js.copied' => 'कॉपी किया गया।',
    'mcp.js.token_revoked' => 'टोकन रद्द किया गया।',
    'mcp.js.revoke_title' => 'टोकन रद्द करें',
    'mcp.js.revoke_body' => 'इस टोकन का उपयोग करने वाला कोई भी क्लाइंट काम करना बंद कर देगा।',
    'mcp.js.revoke_label' => 'रद्द करें',
    'mcp.nav.label' => 'MCP सर्वर',
];
