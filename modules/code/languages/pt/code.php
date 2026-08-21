<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — Brazilian Portuguese strings (code.*). Same key set as languages/en/code.php.
 */
return [
    // API responses
    'code.saved'       => 'Trecho salvo.',
    'code.activated'   => 'Trecho ativado: já está em execução.',
    'code.deactivated' => 'Trecho desativado.',
    'code.deleted'     => 'Trecho excluído.',
    'code.restored'    => 'Trecho restaurado para a versão selecionada.',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'Não foi salvo:',
    'code.error.saved_not_activated'      => 'Salvo, mas não ativado: entra em conflito com o conjunto em execução:',
    'code.error.cannot_activate'          => 'Não é possível ativar:',
    'code.error.cannot_activate_conflict' => 'Não é possível ativar: entra em conflito com o conjunto em execução:',
    'code.error.snippet_unavailable'      => 'Esse trecho não está mais disponível — o módulo pode ter sido removido.',

    // admin list
    'code.list.title'  => 'Código',
    'code.list.new'    => 'Novo trecho',
    'code.list.subtitle_a'       => 'Trechos de PHP que são executados em toda a plataforma: compilados + em cache, executados em cada solicitação. Os trechos locais são armazenados no banco de dados;',
    'code.list.subtitle_b'       => 'os trechos vêm de módulos de código instalados (leia o código-fonte antes de ativar).',
    'code.list.badge_module'     => 'módulo',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'Nome',
    'code.list.col_lang'     => 'Linguagem',
    'code.list.col_runs'     => 'Executa',
    'code.list.col_priority' => 'Prioridade',
    'code.list.col_state'    => 'Estado',
    'code.list.col_updated'  => 'Atualizado',
    'code.list.col_actions'  => 'Ações',

    // view-source modal
    'code.source.title'    => 'Código-fonte do trecho',
    'code.source.close'    => 'Fechar',
    'code.source.warn'     => 'Ao ativar, este PHP é executado no seu aplicativo.',
    'code.source.activate' => 'Ativar',

    // snippet editor
    'code.edit.edit_title' => 'Editar trecho',
    'code.edit.new_title'  => 'Novo trecho',
    'code.edit.back'       => 'Voltar ao código',
    'code.edit.cancel'     => 'Cancelar',
    'code.edit.save'       => 'Salvar',
    'code.edit.warn'       => 'Este PHP é executado em <strong>cada solicitação</strong> uma vez ativo. É verificado ao salvar e é desativado automaticamente se falhar ao carregar.',
    'code.edit.name'       => 'Nome',
    'code.edit.code'       => 'Código',
    'code.edit.type'       => 'Tipo',
    'code.edit.language'   => 'Linguagem',
    'code.edit.inject_at'  => 'Injetar em',
    'code.edit.inject_hint'      => 'Onde o CSS/JS/HTML/PHTML injetado é inserido.',
    'code.edit.activation'       => 'Ativação',
    'code.edit.active_label'     => 'Ativo: executar este trecho',
    'code.edit.priority'         => 'Prioridade',
    'code.edit.priority_hint'    => 'Um valor menor carrega primeiro. É executado globalmente (em cada solicitação).',
    'code.edit.notes'            => 'Notas',
    'code.edit.description'      => 'Descrição',
    'code.edit.description_hint' => 'O que este trecho faz (para a listagem).',

    // snippet editor — version history
    'code.edit.versions'       => 'Histórico de versões',
    'code.edit.col_version'    => 'Versão',
    'code.edit.col_name'       => 'Nome',
    'code.edit.col_state'      => 'Estado',
    'code.edit.col_saved'      => 'Salvo',
    'code.edit.state_active'   => 'Ativo',
    'code.edit.state_inactive' => 'Inativo',
    'code.edit.untitled'       => '(sem título)',
    'code.edit.restore'        => 'Restaurar',

    // form — language select
    'code.lang.php'   => 'PHP: executado em cada solicitação (funções/hooks)',
    'code.lang.phtml' => 'PHTML: renderizado + injetado',
    'code.lang.html'  => 'HTML: injetado tal como está',
    'code.lang.css'   => 'CSS: injetado como folha de estilos',
    'code.lang.js'    => 'JavaScript: injetado como script',

    // form — inject-at select
    'code.auto.head'   => 'Cabeçalho',
    'code.auto.footer' => 'Rodapé',
    'code.js.fix_form' => 'Verifique o formulário e tente novamente.',
    'code.js.network_error' => 'Erro de rede — tente novamente.',
    'code.js.confirm_restore' => 'Restaurar a versão nº %s? O conteúdo atual é salvo primeiro como uma nova versão.',
    'code.js.confirm_delete_snippet' => 'Excluir este trecho? Ele é excluído de forma reversível e pode ser recuperado.',
];
