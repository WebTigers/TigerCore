<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — Spanish strings (identity.*).
 */
return [
    // Service / API messages
    'identity.saved'            => 'Identidad del sitio guardada.',

    // Form placeholders
    'identity.field.site_name'  => 'p. ej., Acme, Inc.',
    'identity.field.tagline'    => 'Una línea breve debajo del nombre',
    'identity.field.site_description' => 'p. ej., Acme publica guías de campo para lectores curiosos.',

    // Site Identity screen
    'identity.page.title'       => 'Identidad del sitio',
    'identity.page.subtitle'    => 'El nombre, el logotipo, el favicon y los perfiles sociales de tu sitio: la marca que aparece en las pestañas del navegador, los resultados de búsqueda y las redes sociales.',
    'identity.action.save'      => 'Guardar',
    'identity.card.identity'    => 'Identidad',
    'identity.label.site_name'  => 'Nombre del sitio',
    'identity.help.site_name'   => 'Se muestra en el encabezado del sitio y en la pestaña del navegador, y se usa como título de página predeterminado y nombre de marca en los resultados de búsqueda.',
    'identity.label.tagline'    => 'Eslogan',
    'identity.help.tagline'     => 'Una línea breve que describe el sitio (opcional).',
    'identity.label.site_description' => 'Descripción del sitio',
    'identity.help.site_description'  => 'Una o dos frases sobre el sitio. Se usa como descripción predeterminada en los resultados de búsqueda y en las tarjetas para compartir en redes sociales cuando una página no tiene la suya.',
    'identity.card.logo_favicon' => 'Logotipo y favicon',
    'identity.label.logo'       => 'Logotipo',
    'identity.label.favicon'    => 'Favicon',
    'identity.help.logo'        => 'Se usa para tu marca en los resultados de búsqueda (esquema de Organización) y está disponible para los temas.',
    'identity.help.favicon'     => 'El pequeño icono en la pestaña del navegador. Usa una imagen <strong>cuadrada</strong>: 512&times;512 o más grande es ideal; el navegador la reduce a todos los tamaños que necesita.',
    'identity.card.share_image'  => 'Imagen para compartir',
    'identity.help.share_image'  => 'La imagen que se muestra cuando una página de este sitio se comparte en redes sociales (Open Graph). Elige una de la Biblioteca de medios para obtener la mejor tarjeta —su tamaño real se publica con ella— o pega la dirección de una imagen alojada en otro lugar. Si defines ambas, gana la imagen de la biblioteca. 1200 × 630 píxeles funciona en todas partes.',
    'identity.label.og_image'    => 'Elegir imagen para compartir',
    'identity.help.og_image'     => 'Elige una imagen de la Biblioteca de medios (recomendado).',
    'identity.label.og_image_url' => 'O la dirección de una imagen',
    'identity.help.og_image_url'  => 'La dirección https:// completa de una imagen alojada en otro lugar. Solo se usa si no eliges una imagen de la biblioteca.',
    'identity.card.social'      => 'Perfiles sociales',
    'identity.help.social'      => 'URLs completas de tus perfiles oficiales. Se publican como los enlaces verificados de tu marca (schema.org <code>sameAs</code>): deja cualquiera en blanco.',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'Identidad del sitio guardada.',
    'identity.js.fix_fields'    => 'Corrige los campos resaltados.',
    'identity.js.network_error' => 'Error de red: inténtalo de nuevo.',
    'identity.nav.label' => 'Identidad del sitio',
];
