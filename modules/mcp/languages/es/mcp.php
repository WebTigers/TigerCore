<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerMCP — Spanish strings (locale `es`). Mirrors en/mcp.php key-for-key.
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'Servidor MCP activado.',
    'mcp.settings.disabled' => 'Servidor MCP desactivado.',
    'mcp.token.created'     => 'Token generado.',
    'mcp.token.revoked'     => 'Token revocado.',

    // Connect screen — header
    'mcp.connect.title'     => 'Servidor MCP',
    'mcp.connect.subtitle'  => 'Permite que un cliente de IA externo (Claude Desktop/Code, Cursor, ChatGPT) controle este sitio a través de la <code>/api</code> de Tiger — con los mismos permisos que tiene un usuario conectado del rol del token. Desactivado por defecto; cada acción se controla por ACL y se audita.',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'Activar el punto de conexión MCP',
    'mcp.connect.serve'        => 'Servir',
    'mcp.connect.enable.help'  => 'Cuando está activado, <code>POST /mcp</code> acepta MCP (JSON-RPC) de cualquier cliente que presente un token válido. Cuando está desactivado, devuelve 404. Quien no tenga token es un invitado y solo puede acceder a las herramientas de lectura permitidas a invitados.',
    'mcp.connect.save'         => 'Guardar',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'Tokens de acceso',
    'mcp.tokens.scope'    => 'Definir el alcance de un nuevo token',
    'mcp.tokens.readonly' => 'Solo lectura — sin escrituras (solo lecturas)',
    'mcp.tokens.org'      => 'Token de organización — actúa como la organización, no como tú (sin usuario asociado)',
    'mcp.tokens.mint'     => 'Generar token',
    'mcp.tokens.once'     => 'Cópialo ahora — solo se muestra una vez.',
    'mcp.copy'            => 'Copiar',
    'mcp.tokens.empty'    => 'Aún no hay tokens — genera uno para conectar un cliente.',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'Conectar un cliente',
    'mcp.connect.token_field'    => 'Token para la configuración de abajo',
    'mcp.connect.token_field.ph' => 'tgr_… (pega, o genera arriba)',
    'mcp.connect.tab.npx'        => 'npx (necesita Node)',
    'mcp.connect.tab.php'        => 'Sin Node (PHP)',
    'mcp.connect.npx.help'       => 'Añádelo a la configuración <code>mcpServers</code> de tu cliente (Claude Desktop, Cursor, …). Usa el puente comunitario <code>mcp-remote</code> — nada que instalar.',
    'mcp.connect.php.help'       => '¿Sin Node? <a href="/mcp/admin/download">Descarga <code>mcp-bridge.php</code></a>, guárdalo en tu equipo y define la ruta abajo. Solo necesita PHP.',
    'mcp.connect.test'           => 'O pruébalo directamente:',
    'mcp.js.copied' => 'Copiado.',
    'mcp.js.token_revoked' => 'Token revocado.',
    'mcp.js.revoke_title' => 'Revocar token',
    'mcp.js.revoke_body' => 'Cualquier cliente que use este token dejará de funcionar.',
    'mcp.js.revoke_label' => 'Revocar',
    'mcp.nav.label' => 'Servidor MCP',
];
