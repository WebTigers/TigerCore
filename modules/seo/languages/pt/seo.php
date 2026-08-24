<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — Brazilian Portuguese strings (seo.*). Same key set as en/seo.php.
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'Cartão social salvo.',
    'seo.page.error.unknown_page'  => 'Essa página não existe, então não é possível configurar o cartão social dela.',

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'Deixe em branco para usar o título da página',
    'seo.page.field.description'   => 'Deixe em branco para usar a descrição do site',

    // Social Cards screen
    'seo.page.title'               => 'Cartões sociais',
    'seo.page.subtitle'            => 'O título, a descrição e a imagem que aparecem quando uma das páginas nativas do seu site é compartilhada nas redes sociais ou listada nos resultados de busca.',
    'seo.action.site_defaults'     => 'Padrões do site',

    'seo.card.defaults'            => 'O que um campo em branco usa',
    'seo.help.defaults'            => 'Deixe qualquer campo abaixo em branco e a página herda estes valores gerais do site. Você os altera na tela Identidade do site.',
    'seo.label.default_title'      => 'Título padrão',
    'seo.label.default_description' => 'Descrição padrão',
    'seo.label.default_image'      => 'Imagem padrão',

    'seo.card.pages'               => 'Páginas nativas',
    'seo.help.pages'               => 'Estas páginas já vêm com o Tiger, então não têm um registro de conteúdo próprio. Configure o cartão social aqui e ele passa a valer na hora — sem publicar nada.',
    'seo.col.page'                 => 'Página',
    'seo.col.url'                  => 'Endereço',
    'seo.col.title'                => 'Título',
    'seo.col.description'          => 'Descrição',
    'seo.col.image'                => 'Imagem',
    'seo.col.actions'              => 'Ações',
    'seo.state.loading'            => 'Carregando páginas…',
    'seo.action.edit'              => 'Editar',

    // Editor
    'seo.modal.title'              => 'Cartão social',
    'seo.action.close'             => 'Fechar',
    'seo.label.title'              => 'Título',
    'seo.help.title'               => 'Aparece como a manchete do link compartilhado. Deixe em branco para usar o título da página e, na falta dele:',
    'seo.label.description'        => 'Descrição',
    'seo.help.description'         => 'O resumo curto abaixo da manchete. Deixe em branco para usar:',
    'seo.label.image'              => 'Imagem',
    'seo.action.choose_image'      => 'Escolher imagem',
    'seo.help.image'               => 'Escolha na Biblioteca de mídia — o tamanho real é lido do arquivo, então o cartão aparece com o formato certo.',
    'seo.label.image_url'          => 'Endereço da imagem',
    'seo.help.image_url'           => 'Ou aponte para uma imagem hospedada em outro lugar. Deixe os dois campos em branco para usar:',
    'seo.action.clear'             => 'Limpar tudo',
    'seo.action.cancel'            => 'Cancelar',
    'seo.action.save'              => 'Salvar',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'Cartão social salvo.',
    'seo.js.fix_fields'            => 'Corrija os campos destacados.',
    'seo.js.network_error'         => 'Erro de rede — tente novamente.',
    'seo.js.load_error'            => 'Não foi possível carregar a lista de páginas.',
    'seo.js.authored'              => 'Definida',
    'seo.js.using_default'         => 'Padrão do site',
    'seo.js.edit_title'            => 'Cartão social',
    'seo.js.empty'                 => 'Nenhuma página nativa encontrada.',
];
