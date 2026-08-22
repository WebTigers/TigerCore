<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerMCP — German strings (locale `de`). Mirrors en/mcp.php key-for-key.
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'MCP-Server aktiviert.',
    'mcp.settings.disabled' => 'MCP-Server deaktiviert.',
    'mcp.token.created'     => 'Token erstellt.',
    'mcp.token.revoked'     => 'Token widerrufen.',

    // Connect screen — header
    'mcp.connect.title'     => 'MCP-Server',
    'mcp.connect.subtitle'  => 'Lassen Sie einen externen KI-Client (Claude Desktop/Code, Cursor, ChatGPT) diese Website über Tigers <code>/api</code> steuern — mit denselben Berechtigungen, die ein angemeldeter Benutzer der Token-Rolle hat. Standardmäßig aus; jede Aktion ist ACL-geschützt und wird auditiert.',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'Den MCP-Endpunkt aktivieren',
    'mcp.connect.serve'        => 'Bereitstellen',
    'mcp.connect.enable.help'  => 'Wenn aktiviert, akzeptiert <code>POST /mcp</code> MCP (JSON-RPC) von jedem Client, der ein gültiges Token vorlegt. Wenn deaktiviert, gibt es 404 zurück. Ein Aufrufer ohne Token ist ein Gast und kann nur die für Gäste erlaubten Lese-Tools erreichen.',
    'mcp.connect.save'         => 'Speichern',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'Zugriffstokens',
    'mcp.tokens.scope'    => 'Einem neuen Token einen Geltungsbereich geben',
    'mcp.tokens.readonly' => 'Nur Lesen — keine Schreibvorgänge (nur Lesen)',
    'mcp.tokens.org'      => 'Organisations-Token — handelt als die Organisation, nicht als Sie (kein gebundener Benutzer)',
    'mcp.tokens.mint'     => 'Token erstellen',
    'mcp.tokens.once'     => 'Kopieren Sie es jetzt — es wird nur einmal angezeigt.',
    'mcp.copy'            => 'Kopieren',
    'mcp.tokens.empty'    => 'Noch keine Tokens — erstellen Sie eines, um einen Client zu verbinden.',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'Einen Client verbinden',
    'mcp.connect.token_field'    => 'Token für die untenstehende Konfiguration',
    'mcp.connect.token_field.ph' => 'tgr_… (einfügen oder oben erstellen)',
    'mcp.connect.tab.npx'        => 'npx (benötigt Node)',
    'mcp.connect.tab.php'        => 'Ohne Node (PHP)',
    'mcp.connect.npx.help'       => 'Fügen Sie dies der <code>mcpServers</code>-Konfiguration Ihres Clients hinzu (Claude Desktop, Cursor, …). Verwendet die Community-Bridge <code>mcp-remote</code> — nichts zu installieren.',
    'mcp.connect.php.help'       => 'Kein Node? <a href="/mcp/admin/download"><code>mcp-bridge.php</code> herunterladen</a>, auf Ihrem Rechner speichern und unten den Pfad festlegen. Benötigt nur PHP.',
    'mcp.connect.test'           => 'Oder testen Sie es direkt:',
    'mcp.js.copied' => 'Kopiert.',
    'mcp.js.token_revoked' => 'Token widerrufen.',
    'mcp.js.revoke_title' => 'Token widerrufen',
    'mcp.js.revoke_body' => 'Jeder Client, der dieses Token verwendet, funktioniert nicht mehr.',
    'mcp.js.revoke_label' => 'Widerrufen',
    'mcp.nav.label' => 'MCP-Server',
];
