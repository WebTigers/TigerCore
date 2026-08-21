<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module — Spanish strings (blog.*). Same key set as languages/en/blog.php.
 */
return [
    // API responses
    'blog.post.saved'    => 'Artículo guardado.',
    'blog.post.deleted'  => 'Artículo eliminado.',
    'blog.post.restored' => 'Artículo restaurado a la versión seleccionada.',
    'blog.error.slug'          => 'Este artículo necesita un título o un slug.',
    'blog.error.slug_reserved' => 'Ese slug está reservado (post, category, tag, feed). Elige otro.',

    // status + locale (form selects + list filter)
    'blog.status.draft'     => 'Borrador',
    'blog.status.published' => 'Publicado',
    'blog.status.archived'  => 'Archivado',
    'blog.locale.en' => 'Inglés',
    'blog.locale.es' => 'Español',

    // public listings — card + archive + index
    'blog.card.min_read'         => 'min de lectura',
    'blog.term.category'         => 'Categoría',
    'blog.term.tag'              => 'Etiqueta',
    'blog.archive.all_articles'  => 'Todos los artículos',
    'blog.archive.empty'         => 'Aún no hay artículos aquí.',
    'blog.index.heading'         => 'Blog',
    'blog.index.rss'             => 'Fuente RSS',
    'blog.index.empty'           => 'Aún no se ha publicado ningún artículo.',

    // editor labels
    'blog.editor.kicker'       => 'Antetítulo',
    'blog.editor.title'        => 'Título',
    'blog.editor.subtitle'     => 'Subtítulo',
    'blog.editor.preamble'     => 'Preámbulo',
    'blog.editor.body'         => 'Artículo',
    'blog.editor.excerpt'      => 'Extracto',
    'blog.editor.feature'      => 'Imagen destacada',
    'blog.editor.author'       => 'Autor',
    'blog.editor.categories'   => 'Categorías',
    'blog.editor.tags'         => 'Etiquetas',
    'blog.editor.status'       => 'Estado',
    'blog.editor.publish_at'   => 'Fecha de publicación',
    'blog.editor.seo'          => 'SEO y redes sociales',
    'blog.editor.seo_title'    => 'Meta título',
    'blog.editor.seo_desc'     => 'Meta descripción',
    'blog.editor.canonical'    => 'URL canónica',
    'blog.editor.comments'     => 'Permitir comentarios',
    'blog.editor.language'     => 'Idioma',
    'blog.editor.slug'         => 'Slug',

    // editor — chrome, actions, hints
    'blog.editor.back'            => 'Volver a los artículos',
    'blog.editor.edit_article'    => 'Editar artículo',
    'blog.editor.new_article'     => 'Nuevo artículo',
    'blog.editor.settings'        => 'Configuración de la entrada',
    'blog.editor.save'            => 'Guardar',
    'blog.editor.close'           => 'Cerrar',
    'blog.editor.feature_set'     => 'Establecer imagen destacada',
    'blog.editor.feature_replace' => 'Reemplazar',
    'blog.editor.feature_remove'  => 'Quitar',
    'blog.editor.publish_hint'    => 'En blanco = publicar ahora. Una fecha futura lo programa.',
    'blog.editor.categories_hint' => 'Separadas por comas. Las nuevas se crean al guardar.',
    'blog.editor.tags_hint'       => 'Separadas por comas.',
    'blog.editor.excerpt_hint'    => 'Se muestra en los listados y las tarjetas sociales. Si falta, se usa el subtítulo.',
    'blog.editor.slug_hint'       => 'Automático desde el título si se deja en blanco. Cambiarlo deja un 301.',

    // editor — formatting toolbar (title / aria-label)
    'blog.editor.tool.formatting'    => 'Formato',
    'blog.editor.tool.heading'       => 'Encabezado',
    'blog.editor.tool.subheading'    => 'Subencabezado',
    'blog.editor.tool.body_text'     => 'Texto del cuerpo',
    'blog.editor.tool.bold'          => 'Negrita',
    'blog.editor.tool.italic'        => 'Cursiva',
    'blog.editor.tool.quote'         => 'Cita',
    'blog.editor.tool.bullet_list'   => 'Lista con viñetas',
    'blog.editor.tool.numbered_list' => 'Lista numerada',
    'blog.editor.tool.link'          => 'Enlace',
    'blog.editor.tool.image'         => 'Insertar imagen',
    'blog.editor.tool.source'        => 'Editar código HTML',

    // editor — version history
    'blog.editor.versions'    => 'Historial de versiones',
    'blog.editor.col_version' => 'Versión',
    'blog.editor.col_saved'   => 'Guardado',
    'blog.editor.untitled'    => '(sin título)',
    'blog.editor.restore'     => 'Restaurar',

    // placeholders
    'blog.ph.kicker'   => 'Antetítulo — una etiqueta breve sobre el título',
    'blog.ph.title'    => 'Título',
    'blog.ph.subtitle' => 'Añade un subtítulo…',
    'blog.ph.preamble' => 'Una entrada en fuente grande que atrae al lector…',
    'blog.ph.body'     => 'Cuenta tu historia…',

    // admin list
    'blog.list.title'       => 'Artículos',
    'blog.list.subtitle'    => 'Entradas y artículos: almacenados en el CMS como',
    'blog.list.new'         => 'Nuevo artículo',
    'blog.list.empty'       => 'Aún no hay artículos: escribe el primero.',
    'blog.list.status_all'  => 'Todos los estados',
    'blog.list.clear'       => 'Limpiar',
    'blog.list.clear_title' => 'Limpiar filtros',
    'blog.list.col_title'   => 'Título',
    'blog.list.col_slug'    => 'Slug',
    'blog.list.col_lang'    => 'Idioma',
    'blog.list.col_status'  => 'Estado',
    'blog.list.col_read'    => 'Lectura',
    'blog.list.col_updated' => 'Actualizado',
    'blog.list.col_actions' => 'Acciones',
    'blog.js.confirm_delete_article' => '¿Eliminar este artículo? Se elimina de forma reversible y se puede recuperar.',
    'blog.js.media_picker_unavailable' => 'El selector de medios no está disponible.',
    'blog.js.fix_fields' => 'Corrige los campos resaltados.',
    'blog.js.network_error' => 'Error de red — inténtalo de nuevo.',
    'blog.js.confirm_restore' => '¿Restaurar la versión n.º %s? El contenido actual se guarda primero como una nueva versión.',
    'blog.js.link_url' => 'URL del enlace:',
    'blog.nav.label' => 'Artículos',
];
