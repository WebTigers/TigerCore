<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — Brazilian Portuguese strings (locale `pt`). Mirrors en/access.php.
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'Nome',
    'access.label.slug'          => 'Identificador',
    'access.label.status'        => 'Status',
    'access.label.created'       => 'Criado',
    'access.label.email'         => 'E-mail',
    'access.label.username'      => 'Nome de usuário',
    'access.label.parent'        => 'Pai',
    'access.label.members'       => 'Membros',
    'access.label.roles'         => 'Papéis',
    'access.label.orgs'          => 'Organizações',
    'access.label.actions'       => 'Ações',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'Salvar',
    'access.action.cancel'       => 'Cancelar',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'Todos os status',
    'access.filter.clear'        => 'Limpar',
    'access.filter.clear_title'  => 'Limpar filtros',

    // --- Status values ---
    'access.status.active'       => 'Ativo',
    'access.status.suspended'    => 'Suspenso',

    // --- Users: list ---
    'access.user.list.title'     => 'Usuários',
    'access.user.list.subtitle'  => 'Identidades: e-mail, nome de usuário, status e associação.',
    'access.user.list.new'       => 'Novo usuário',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'Novo usuário',
    'access.user.edit.title_edit' => 'Editar usuário',
    'access.user.edit.back'       => 'Voltar aos usuários',
    'access.user.field.email_help'          => 'O identificador de login canônico. Deve ser único.',
    'access.user.field.username_help'       => 'Opcional. Único se definido.',
    'access.user.field.language'            => 'Idioma',
    'access.user.field.language_help'       => 'O idioma preferido do usuário.',
    'access.user.field.timezone'            => 'Fuso horário',
    'access.user.field.timezone_placeholder'=> 'Busque por cidade, abreviação (EST) ou deslocamento (-05:00)…',
    'access.user.field.password'            => 'Definir senha',
    'access.user.field.password_help'       => 'Deixe em branco para manter a senha atual. Defini-la aqui a redefine imediatamente.',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'Usuário salvo.',
    'access.user.deleted'        => 'Usuário excluído.',
    'access.user.email_taken'    => 'Esse e-mail já está em uso.',
    'access.user.username_taken' => 'Esse nome de usuário já está em uso.',
    'access.user.no_self_delete' => 'Você não pode excluir a sua própria conta.',

    // --- Organizations: list ---
    'access.org.list.title'      => 'Organizações',
    'access.org.list.subtitle'   => 'Inquilinos: nome, identificador, hierarquia e associação.',
    'access.org.list.new'        => 'Nova organização',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'Nova organização',
    'access.org.edit.title_edit' => 'Editar organização',
    'access.org.edit.back'       => 'Voltar às organizações',
    'access.org.field.slug_help'    => 'Identificador compatível com URL. Derivado do nome se deixado em branco; deve ser único.',
    'access.org.field.parent'       => 'Organização pai',
    'access.org.field.parent_help'  => 'Para subinquilinos; deixe como “nenhuma” para uma organização raiz.',
    'access.org.parent.none'        => '— nenhuma (organização raiz) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'Organização salva.',
    'access.org.deleted'         => 'Organização excluída.',
    'access.org.slug_taken'      => 'Esse identificador (slug) já está em uso.',
    'access.org.slug_required'   => 'É necessário um identificador (ou forneça um nome para derivá-lo).',
    'access.org.parent_self'     => 'Uma organização não pode ser pai de si mesma.',
    'access.org.no_self_delete'  => 'Você não pode excluir a organização na qual está atuando no momento.',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'Buscar nome / identificador…',
    'access.js.search_users'        => 'Buscar e-mail / nome de usuário…',
    'access.js.edit'                => 'Editar',
    'access.js.delete'              => 'Excluir',
    'access.js.org_no_delete'       => 'Não é possível excluir a sua organização ativa',
    'access.js.delete_self'         => 'Você não pode excluir a si mesmo',
    'access.js.not_permitted'       => 'Não permitido',
    'access.js.confirm_delete_org'  => 'Excluir esta organização? Ela é excluída de forma reversível e pode ser recuperada.',
    'access.js.confirm_delete_user' => 'Excluir este usuário? Ele é excluído de forma reversível e pode ser recuperado.',
    'access.js.fix_fields'          => 'Corrija os campos destacados e tente novamente.',
    'access.js.network_error'       => 'Erro de rede — tente novamente.',
    'access.js.parent_root'         => '— raiz —',
];
