<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerMCP — French strings (locale `fr`). Mirrors en/mcp.php key-for-key.
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'Serveur MCP activé.',
    'mcp.settings.disabled' => 'Serveur MCP désactivé.',
    'mcp.token.created'     => 'Token généré.',
    'mcp.token.revoked'     => 'Token révoqué.',

    // Connect screen — header
    'mcp.connect.title'     => 'Serveur MCP',
    'mcp.connect.subtitle'  => 'Permettez à un client IA externe (Claude Desktop/Code, Cursor, ChatGPT) de piloter ce site via l’<code>/api</code> de Tiger — avec les mêmes permissions qu’un utilisateur connecté du rôle du token. Désactivé par défaut ; chaque action est contrôlée par l’ACL et auditée.',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'Activer le point de terminaison MCP',
    'mcp.connect.serve'        => 'Servir',
    'mcp.connect.enable.help'  => 'Lorsqu’il est activé, <code>POST /mcp</code> accepte le MCP (JSON-RPC) de tout client présentant un token valide. Lorsqu’il est désactivé, il renvoie 404. Un appelant sans token est un invité et n’accède qu’aux outils de lecture autorisés aux invités.',
    'mcp.connect.save'         => 'Enregistrer',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'Tokens d’accès',
    'mcp.tokens.scope'    => 'Définir la portée d’un nouveau token',
    'mcp.tokens.readonly' => 'Lecture seule — aucune écriture (lectures uniquement)',
    'mcp.tokens.org'      => 'Token d’organisation — agit en tant qu’organisation, pas vous (aucun utilisateur associé)',
    'mcp.tokens.mint'     => 'Générer le token',
    'mcp.tokens.once'     => 'Copiez-le maintenant — il n’est affiché qu’une seule fois.',
    'mcp.copy'            => 'Copier',
    'mcp.tokens.empty'    => 'Aucun token pour l’instant — générez-en un pour connecter un client.',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'Connecter un client',
    'mcp.connect.token_field'    => 'Token pour la configuration ci-dessous',
    'mcp.connect.token_field.ph' => 'tgr_… (collez, ou générez ci-dessus)',
    'mcp.connect.tab.npx'        => 'npx (nécessite Node)',
    'mcp.connect.tab.php'        => 'Sans Node (PHP)',
    'mcp.connect.npx.help'       => 'Ajoutez ceci à la configuration <code>mcpServers</code> de votre client (Claude Desktop, Cursor, …). Utilise le pont communautaire <code>mcp-remote</code> — rien à installer.',
    'mcp.connect.php.help'       => 'Pas de Node ? <a href="/mcp/admin/download">Téléchargez <code>mcp-bridge.php</code></a>, enregistrez-le sur votre machine et définissez le chemin ci-dessous. Nécessite uniquement PHP.',
    'mcp.connect.test'           => 'Ou testez-le directement :',
    'mcp.js.copied' => 'Copié.',
    'mcp.js.token_revoked' => 'Token révoqué.',
    'mcp.js.revoke_title' => 'Révoquer le token',
    'mcp.js.revoke_body' => 'Tout client utilisant ce token cessera de fonctionner.',
    'mcp.js.revoke_label' => 'Révoquer',
    'mcp.nav.label' => 'Serveur MCP',
];
