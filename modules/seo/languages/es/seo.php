<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — Spanish strings (seo.*). Same key set as en/seo.php.
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'Tarjeta social guardada.',
    'seo.page.error.unknown_page'  => 'Esa página no existe, así que no se puede configurar su tarjeta social.',

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'Déjalo vacío para usar el título de la página',
    'seo.page.field.description'   => 'Déjalo vacío para usar la descripción del sitio',

    // Social Cards screen
    'seo.page.title'               => 'Tarjetas sociales',
    'seo.page.subtitle'            => 'El título, la descripción y la imagen que aparecen cuando una de tus páginas incorporadas se comparte en redes sociales o se muestra en los resultados de búsqueda.',
    'seo.action.site_defaults'     => 'Valores del sitio',

    'seo.card.defaults'            => 'A qué recurre un campo vacío',
    'seo.help.defaults'            => 'Si dejas vacío cualquier campo de abajo, la página hereda estos valores generales del sitio. Puedes cambiarlos en la pantalla Identidad del sitio.',
    'seo.label.default_title'      => 'Título predeterminado',
    'seo.label.default_description' => 'Descripción predeterminada',
    'seo.label.default_image'      => 'Imagen predeterminada',

    'seo.card.pages'               => 'Páginas incorporadas',
    'seo.help.pages'               => 'Estas páginas vienen con Tiger, por lo que no tienen un registro de contenido propio. Configura aquí su tarjeta social y surtirá efecto de inmediato, sin ningún despliegue.',
    'seo.col.page'                 => 'Página',
    'seo.col.url'                  => 'Dirección',
    'seo.col.title'                => 'Título',
    'seo.col.description'          => 'Descripción',
    'seo.col.image'                => 'Imagen',
    'seo.col.actions'              => 'Acciones',
    'seo.state.loading'            => 'Cargando páginas…',
    'seo.action.edit'              => 'Editar',

    // Editor
    'seo.modal.title'              => 'Tarjeta social',
    'seo.action.close'             => 'Cerrar',
    'seo.label.title'              => 'Título',
    'seo.help.title'               => 'Aparece como el titular del enlace compartido. Déjalo vacío para usar el título de la página y, si no lo hay:',
    'seo.label.description'        => 'Descripción',
    'seo.help.description'         => 'El breve resumen bajo el titular. Déjalo vacío para usar:',
    'seo.label.image'              => 'Imagen',
    'seo.action.choose_image'      => 'Elegir imagen',
    'seo.help.image'               => 'Elígela de la Biblioteca multimedia: el tamaño real se lee del archivo, así que la tarjeta se muestra correctamente.',
    'seo.label.image_url'          => 'Dirección de la imagen',
    'seo.help.image_url'           => 'O indica una imagen alojada en otro sitio. Deja ambos campos vacíos para usar:',
    'seo.action.clear'             => 'Vaciar todo',
    'seo.action.cancel'            => 'Cancelar',
    'seo.action.save'              => 'Guardar',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'Tarjeta social guardada.',
    'seo.js.fix_fields'            => 'Corrige los campos marcados.',
    'seo.js.network_error'         => 'Error de red: vuelve a intentarlo.',
    'seo.js.load_error'            => 'No se pudo cargar la lista de páginas.',
    'seo.js.authored'              => 'Definida',
    'seo.js.using_default'         => 'Valor del sitio',
    'seo.js.edit_title'            => 'Tarjeta social',
    'seo.js.empty'                 => 'No se encontraron páginas incorporadas.',
];
