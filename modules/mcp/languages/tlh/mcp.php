<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
// STUB — tlh (Klingon): English placeholders; translate the values to Klingon (pIqaD).
/**
 * TigerMCP — English strings (SOURCE). Semantic, owner-prefixed keys (AGENTS.md i18n / I18N.md).
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'MCP server enabled.',
    'mcp.settings.disabled' => 'MCP server disabled.',
    'mcp.token.created'     => 'Token minted.',
    'mcp.token.revoked'     => 'Token revoked.',

    // Connect screen — header
    'mcp.connect.title'     => 'MCP Server',
    'mcp.connect.subtitle'  => 'Let an external AI client (Claude Desktop/Code, Cursor, ChatGPT) drive this site through Tiger’s <code>/api</code> — with the same permissions a signed-in user of the token’s role has. Off by default; every action is ACL-gated and audited.',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'Enable the MCP endpoint',
    'mcp.connect.serve'        => 'Serve',
    'mcp.connect.enable.help'  => 'When on, <code>POST /mcp</code> accepts MCP (JSON-RPC) from any client presenting a valid token. When off, it returns 404. A caller with no token is a guest and can reach only guest-allowed read tools.',
    'mcp.connect.save'         => 'Save',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'Access tokens',
    'mcp.tokens.scope'    => 'Scope a new token',
    'mcp.tokens.readonly' => 'Read-only — no writes (reads only)',
    'mcp.tokens.org'      => 'Org token — acts as the org, not you (no bound user)',
    'mcp.tokens.mint'     => 'Mint token',
    'mcp.tokens.once'     => 'Copy this now — it’s shown only once.',
    'mcp.copy'            => 'Copy',
    'mcp.tokens.empty'    => 'No tokens yet — mint one to connect a client.',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'Connect a client',
    'mcp.connect.token_field'    => 'Token for the config below',
    'mcp.connect.token_field.ph' => 'tgr_… (paste, or mint above)',
    'mcp.connect.tab.npx'        => 'npx (needs Node)',
    'mcp.connect.tab.php'        => 'Zero-Node (PHP)',
    'mcp.connect.npx.help'       => 'Add to your client’s <code>mcpServers</code> config (Claude Desktop, Cursor, …). Uses the community <code>mcp-remote</code> bridge — nothing to install.',
    'mcp.connect.php.help'       => 'No Node? <a href="/mcp/admin/download">Download <code>mcp-bridge.php</code></a>, save it on your machine, and set the path below. Needs only PHP.',
    'mcp.connect.test'           => 'Or test it directly:',
    'mcp.js.copied' => 'Copied.',
    'mcp.js.token_revoked' => 'Token revoked.',
    'mcp.js.revoke_title' => 'Revoke token',
    'mcp.js.revoke_body' => 'Any client using this token will stop working.',
    'mcp.js.revoke_label' => 'Revoke',
];
