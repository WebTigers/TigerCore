<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerMCP — Brazilian Portuguese strings (locale `pt`). Mirrors en/mcp.php key-for-key.
 */
return [
    // Messages
    'mcp.settings.enabled'  => 'Servidor MCP ativado.',
    'mcp.settings.disabled' => 'Servidor MCP desativado.',
    'mcp.token.created'     => 'Token gerado.',
    'mcp.token.revoked'     => 'Token revogado.',

    // Connect screen — header
    'mcp.connect.title'     => 'Servidor MCP',
    'mcp.connect.subtitle'  => 'Permita que um cliente de IA externo (Claude Desktop/Code, Cursor, ChatGPT) controle este site através da <code>/api</code> do Tiger — com as mesmas permissões que um usuário conectado com o papel do token possui. Desativado por padrão; cada ação é controlada por ACL e auditada.',

    // Connect screen — enable
    'mcp.connect.enable.title' => 'Ativar o ponto de conexão MCP',
    'mcp.connect.serve'        => 'Servir',
    'mcp.connect.enable.help'  => 'Quando ativado, <code>POST /mcp</code> aceita MCP (JSON-RPC) de qualquer cliente que apresente um token válido. Quando desativado, retorna 404. Quem não tiver token é um visitante e só pode acessar as ferramentas de leitura permitidas a visitantes.',
    'mcp.connect.save'         => 'Salvar',

    // Connect screen — tokens
    'mcp.tokens.title'    => 'Tokens de acesso',
    'mcp.tokens.scope'    => 'Definir o escopo de um novo token',
    'mcp.tokens.readonly' => 'Somente leitura — sem escritas (apenas leituras)',
    'mcp.tokens.org'      => 'Token de organização — atua como a organização, não como você (sem usuário associado)',
    'mcp.tokens.mint'     => 'Gerar token',
    'mcp.tokens.once'     => 'Copie agora — é exibido apenas uma vez.',
    'mcp.copy'            => 'Copiar',
    'mcp.tokens.empty'    => 'Ainda não há tokens — gere um para conectar um cliente.',

    // Connect screen — connect a client
    'mcp.connect.client.title'   => 'Conectar um cliente',
    'mcp.connect.token_field'    => 'Token para a configuração abaixo',
    'mcp.connect.token_field.ph' => 'tgr_… (cole ou gere acima)',
    'mcp.connect.tab.npx'        => 'npx (precisa de Node)',
    'mcp.connect.tab.php'        => 'Sem Node (PHP)',
    'mcp.connect.npx.help'       => 'Adicione à configuração <code>mcpServers</code> do seu cliente (Claude Desktop, Cursor, …). Usa a ponte comunitária <code>mcp-remote</code> — nada para instalar.',
    'mcp.connect.php.help'       => 'Sem Node? <a href="/mcp/admin/download">Baixe o <code>mcp-bridge.php</code></a>, salve-o na sua máquina e defina o caminho abaixo. Precisa apenas de PHP.',
    'mcp.connect.test'           => 'Ou teste diretamente:',
    'mcp.js.copied' => 'Copiado.',
    'mcp.js.token_revoked' => 'Token revogado.',
    'mcp.js.revoke_title' => 'Revogar token',
    'mcp.js.revoke_body' => 'Qualquer cliente que use este token deixará de funcionar.',
    'mcp.js.revoke_label' => 'Revogar',
    'mcp.nav.label' => 'Servidor MCP',
];
