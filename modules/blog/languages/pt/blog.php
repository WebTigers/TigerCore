<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module — Brazilian Portuguese strings (blog.*). Same key set as languages/en/blog.php.
 */
return [
    // API responses
    'blog.post.saved'    => 'Artigo salvo.',
    'blog.post.deleted'  => 'Artigo excluído.',
    'blog.post.restored' => 'Artigo restaurado para a versão selecionada.',
    'blog.error.slug'          => 'Este artigo precisa de um título ou slug.',
    'blog.error.slug_reserved' => 'Esse slug é reservado (post, category, tag, feed). Escolha outro.',

    // status + locale (form selects + list filter)
    'blog.status.draft'     => 'Rascunho',
    'blog.status.published' => 'Publicado',
    'blog.status.archived'  => 'Arquivado',
    'blog.locale.en' => 'English',
    'blog.locale.es' => 'Español',

    // public listings — card + archive + index
    'blog.card.min_read'         => 'min de leitura',
    'blog.term.category'         => 'Categoria',
    'blog.term.tag'              => 'Etiqueta',
    'blog.archive.all_articles'  => 'Todos os artigos',
    'blog.archive.empty'         => 'Ainda não há artigos aqui.',
    'blog.index.heading'         => 'Blog',
    'blog.index.rss'             => 'Feed RSS',
    'blog.index.empty'           => 'Ainda não há artigos publicados.',

    // editor labels
    'blog.editor.kicker'       => 'Antetítulo',
    'blog.editor.title'        => 'Título',
    'blog.editor.subtitle'     => 'Subtítulo',
    'blog.editor.preamble'     => 'Preâmbulo',
    'blog.editor.body'         => 'Artigo',
    'blog.editor.excerpt'      => 'Resumo',
    'blog.editor.feature'      => 'Imagem destacada',
    'blog.editor.author'       => 'Autor',
    'blog.editor.categories'   => 'Categorias',
    'blog.editor.tags'         => 'Etiquetas',
    'blog.editor.status'       => 'Status',
    'blog.editor.publish_at'   => 'Data de publicação',
    'blog.editor.seo'          => 'SEO e redes sociais',
    'blog.editor.seo_title'    => 'Meta título',
    'blog.editor.seo_desc'     => 'Meta descrição',
    'blog.editor.canonical'    => 'URL canônica',
    'blog.editor.comments'     => 'Permitir comentários',
    'blog.editor.language'     => 'Idioma',
    'blog.editor.slug'         => 'Slug',

    // editor — chrome, actions, hints
    'blog.editor.back'            => 'Voltar aos artigos',
    'blog.editor.edit_article'    => 'Editar artigo',
    'blog.editor.new_article'     => 'Novo artigo',
    'blog.editor.settings'        => 'Configurações da publicação',
    'blog.editor.save'            => 'Salvar',
    'blog.editor.close'           => 'Fechar',
    'blog.editor.feature_set'     => 'Definir imagem destacada',
    'blog.editor.feature_replace' => 'Substituir',
    'blog.editor.feature_remove'  => 'Remover',
    'blog.editor.publish_hint'    => 'Em branco = publicar agora. Uma data futura agenda a publicação.',
    'blog.editor.categories_hint' => 'Separadas por vírgulas. As novas são criadas ao salvar.',
    'blog.editor.tags_hint'       => 'Separadas por vírgulas.',
    'blog.editor.excerpt_hint'    => 'Exibido nas listagens e nos cartões sociais. Se faltar, usa o subtítulo.',
    'blog.editor.slug_hint'       => 'Automático a partir do título se ficar em branco. Alterá-lo deixa um 301.',

    // editor — formatting toolbar (title / aria-label)
    'blog.editor.tool.formatting'    => 'Formatação',
    'blog.editor.tool.heading'       => 'Cabeçalho',
    'blog.editor.tool.subheading'    => 'Subcabeçalho',
    'blog.editor.tool.body_text'     => 'Texto do corpo',
    'blog.editor.tool.bold'          => 'Negrito',
    'blog.editor.tool.italic'        => 'Itálico',
    'blog.editor.tool.quote'         => 'Citação',
    'blog.editor.tool.bullet_list'   => 'Lista com marcadores',
    'blog.editor.tool.numbered_list' => 'Lista numerada',
    'blog.editor.tool.link'          => 'Link',
    'blog.editor.tool.image'         => 'Inserir imagem',
    'blog.editor.tool.source'        => 'Editar código HTML',

    // editor — version history
    'blog.editor.versions'    => 'Histórico de versões',
    'blog.editor.col_version' => 'Versão',
    'blog.editor.col_saved'   => 'Salvo',
    'blog.editor.untitled'    => '(sem título)',
    'blog.editor.restore'     => 'Restaurar',

    // placeholders
    'blog.ph.kicker'   => 'Antetítulo — um rótulo curto acima do título',
    'blog.ph.title'    => 'Título',
    'blog.ph.subtitle' => 'Adicione um subtítulo…',
    'blog.ph.preamble' => 'Uma abertura em fonte maior que atrai o leitor…',
    'blog.ph.body'     => 'Conte sua história…',

    // admin list
    'blog.list.title'       => 'Artigos',
    'blog.list.subtitle'    => 'Posts e artigos: armazenados no CMS como',
    'blog.list.new'         => 'Novo artigo',
    'blog.list.empty'       => 'Ainda não há artigos: escreva o primeiro.',
    'blog.list.status_all'  => 'Todos os status',
    'blog.list.clear'       => 'Limpar',
    'blog.list.clear_title' => 'Limpar filtros',
    'blog.list.col_title'   => 'Título',
    'blog.list.col_slug'    => 'Slug',
    'blog.list.col_lang'    => 'Idioma',
    'blog.list.col_status'  => 'Status',
    'blog.list.col_read'    => 'Leitura',
    'blog.list.col_updated' => 'Atualizado',
    'blog.list.col_actions' => 'Ações',
    'blog.js.confirm_delete_article' => 'Excluir este artigo? Ele é excluído de forma reversível e pode ser recuperado.',
    'blog.js.media_picker_unavailable' => 'O seletor de mídia não está disponível.',
    'blog.js.fix_fields' => 'Corrija os campos destacados.',
    'blog.js.network_error' => 'Erro de rede — tente novamente.',
    'blog.js.confirm_restore' => 'Restaurar a versão nº %s? O conteúdo atual é salvo primeiro como uma nova versão.',
    'blog.js.link_url' => 'URL do link:',
];
