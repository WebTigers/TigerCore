<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — Brazilian Portuguese strings (locale `pt`). Mirrors en/agent.php key-for-key.
 */
return [
    // Settings screen
    'agent.settings.title'        => 'Agente de IA',
    'agent.settings.subtitle'     => 'Conecte sua própria conta de IA e deixe o agente trabalhar dentro do seu site.',
    'agent.settings.saved'        => 'Configurações do agente salvas.',
    'agent.settings.save'         => 'Salvar',
    'agent.settings.provider'     => 'Provedor',
    'agent.settings.model'        => 'Modelo',
    'agent.settings.model.ph'     => 'ex.: claude-sonnet-5',
    'agent.settings.model.refresh' => 'Atualizar a lista de modelos',
    'agent.settings.key'          => 'Chave de API',
    'agent.settings.key.ph'       => 'Cole uma chave para conectar (deixe em branco para manter a atual)',
    'agent.settings.enabled'      => 'Ativar o agente de IA',
    'agent.settings.connected'    => 'Conectado — há uma chave armazenada (criptografada).',
    'agent.settings.disconnected' => 'Não conectado — cole uma chave de API para ativar o agente.',
    'agent.settings.connection'   => 'Conexão',
    'agent.settings.crypto_missing' => 'A criptografia não está configurada (<code>tiger.crypto.key</code>), então ainda não é possível armazenar uma chave de API com segurança.',
    'agent.settings.mode_max'     => 'Limite de automação',
    'agent.settings.mode_max.help' => 'O nível de automação mais alto que qualquer pessoa aqui pode usar. Os usuários podem reduzir, nunca ultrapassar esse limite.',
    'agent.settings.mode.ask'     => 'Perguntar — aprove cada alteração (mais seguro)',
    'agent.settings.mode.auto'    => 'Auto — alterações rotineiras são executadas automaticamente; código/arquivos ainda perguntam',
    'agent.settings.mode.yolo'    => 'YOLO — tudo o que o papel permite é executado automaticamente',
    'agent.settings.how.title'    => 'Como funciona',
    'agent.settings.how.body1'    => 'O agente age <strong>como você</strong> — ele nunca pode fazer mais do que o seu papel permite. As leituras são executadas sozinhas; as alterações são exibidas primeiro para a sua aprovação.',
    'agent.settings.how.body2'    => '<strong>Traga a sua própria conta:</strong> a chave que você cola é sua, armazenada criptografada neste servidor e nunca compartilhada. O seu provedor de IA cobra você diretamente.',

    // Aside modes
    'agent.mode.ask'              => 'Perguntar',
    'agent.mode.auto'            => 'Auto',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'Aprovar cada alteração',
    'agent.mode.auto.hint'      => 'Alterações rotineiras são executadas sozinhas; código/arquivos perguntam',
    'agent.mode.yolo.hint'      => 'Tudo é executado sozinho — segure firme',

    // Turn results
    'agent.turn.ok'             => 'Pronto.',
    'agent.approve.ok'          => 'Ações concluídas.',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'Arquivo anexado.',
    'agent.file.type'           => 'Esse tipo de arquivo não é compatível.',
    'agent.file.too_large'      => 'Esse arquivo é grande demais.',
    'agent.file.failed'         => 'Não foi possível anexar o arquivo. Tente novamente.',

    // Errors
    'agent.error.empty'         => 'Digite uma mensagem para o agente.',
    'agent.error.unconfigured'  => 'O agente de IA ainda não está conectado. Adicione uma chave de API em Configurações → Agente de IA.',
    'agent.error.provider'      => 'Não foi possível contatar o provedor de IA. Verifique a chave e tente novamente.',
    'agent.error.run_missing'   => 'Essa conversa ou etapa não está mais disponível.',

    // Aside UI
    'agent.aside.title'         => 'Agente',
    'agent.aside.placeholder'   => 'Peça ao agente para criar, alterar ou explicar algo…',
    'agent.aside.new'           => 'Novo chat',
    'agent.aside.send'          => 'Enviar',
    'agent.aside.approve'       => 'Aprovar',
    'agent.aside.approve_all'   => 'Aprovar tudo',
    'agent.aside.thinking'      => 'Trabalhando…',
    'agent.aside.empty'         => 'Inicie uma conversa — o agente age com as suas permissões.',

    // Skills (messages)
    'agent.skills.installed'      => 'Habilidade instalada.',
    'agent.skills.install_failed' => 'Não foi possível instalar essa habilidade.',
    'agent.skills.none_found'     => 'Nenhum SKILL.md encontrado nessa URL.',
    'agent.skills.enabled'        => 'Habilidade ativada.',
    'agent.skills.disabled'       => 'Habilidade desativada.',
    'agent.skills.removed'        => 'Habilidade removida.',

    // Skills (admin screen)
    'agent.skills.title'          => 'Habilidades do agente',
    'agent.skills.subtitle'       => 'Conhecimento instalável para o agente de IA. O Tiger navega por esses repositórios — ele não os avaliza; revise a fonte de uma habilidade antes de instalar e ativar. As habilidades instaladas ficam fixadas no topo.',
    'agent.skills.rescan'         => 'Reexaminar',
    'agent.skills.rescan.title'   => 'Examinar as fontes novamente',
    'agent.skills.add_url'        => 'Adicionar a partir de uma URL do GitHub',
    'agent.skills.url.ph'         => 'https://github.com/owner/repo (ou uma subpasta / um SKILL.md)',
    'agent.skills.install'        => 'Instalar',
    'agent.skills.add_url.help'   => 'Qualquer repositório, branch, subpasta ou um link direto para um SKILL.md — não apenas as fontes listadas.',
    'agent.skills.col.skill'      => 'Habilidade',
    'agent.skills.col.description' => 'Descrição',
    'agent.skills.col.source'     => 'Fonte',
    'agent.skills.col.status'     => 'Status',
    'agent.skills.col.actions'    => 'Ações',
    'agent.skills.src.title'      => 'SKILL.md',
    'agent.skills.src.note'       => 'Apenas procedência — revise antes de instalar.',
    'agent.skills.close'          => 'Fechar',

    // MCP connections (outbound) — messages
    'agent.mcp.saved'     => 'Conexão salva.',
    'agent.mcp.removed'   => 'Conexão removida.',
    'agent.mcp.bad_url'   => 'Informe uma URL http(s) válida para o servidor MCP.',
    'agent.mcp.bad_label' => 'Dê um nome à conexão.',
    'agent.mcp.not_found' => 'Essa conexão não está disponível.',

    // MCP connections (outbound) — admin screen
    'agent.mcp.title'         => 'Conexões MCP',
    'agent.mcp.subtitle'      => 'Conecte <strong>servidores MCP</strong> externos para que o agente de IA possa usar as ferramentas deles junto com as suas. Uma chamada de ferramenta é executada no servidor remoto e exige aprovação como qualquer escrita do agente. Somente administradores.',
    'agent.mcp.add'           => 'Adicionar uma conexão',
    'agent.mcp.name'          => 'Nome',
    'agent.mcp.name.ph'       => 'ex.: GitHub, Linear, Weather',
    'agent.mcp.url'           => 'URL do servidor (Streamable HTTP)',
    'agent.mcp.token'         => 'Token Bearer',
    'agent.mcp.token.optional' => '(opcional; armazenado criptografado)',
    'agent.mcp.token.ph'      => 'deixe em branco para manter o atual',
    'agent.mcp.enabled'       => 'Ativado',
    'agent.mcp.save'          => 'Salvar',
    'agent.mcp.cancel'        => 'Cancelar',
    'agent.mcp.connected'     => 'Servidores conectados',
    'agent.mcp.empty'         => 'Ainda não há conexões — adicione uma à esquerda.',
    'agent.js.models_live' => 'Ao vivo da sua conta.',
    'agent.js.models_static' => 'Modelos comuns — conecte uma chave para a lista ao vivo.',
    'agent.js.settings_saved' => 'Configurações salvas.',
    'agent.js.network_error' => 'Erro de rede — tente novamente.',
    'agent.js.connection_saved' => 'Conexão salva.',
    'agent.js.remove_connection_title' => 'Remover conexão',
    'agent.js.remove_connection_body' => 'O agente perderá acesso às ferramentas dele.',
    'agent.js.remove_label' => 'Remover',
    'agent.js.remove_skill_title' => 'Remover habilidade',
    'agent.js.remove_skill_body' => 'Remover esta habilidade e seus arquivos? (Ela permanece no catálogo para reinstalar.)',
    'agent.nav.label' => 'Agente de IA',
    'agent.nav.skills' => 'Habilidades do agente',
    'agent.nav.mcp' => 'Conexões MCP',
];
