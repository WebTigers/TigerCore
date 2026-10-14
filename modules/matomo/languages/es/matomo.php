<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — Spanish strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Ajustes de Matomo guardados.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Analítica de Matomo',
    'matomo.subtitle'                  => 'Conecta tu Matomo autoalojado para medir tu sitio público y atribuir el tráfico de afiliados.',
    'matomo.save'                      => 'Guardar',

    // Connection card
    'matomo.card.connection'           => 'Conexión con Matomo',
    'matomo.enable'                    => 'Activar el seguimiento de Matomo',
    'matomo.url'                       => 'URL de Matomo',
    'matomo.url_help'                  => 'La URL base de tu instalación de Matomo, p. ej. https://analytics.example.com/',
    'matomo.site_id'                   => 'ID del sitio',
    'matomo.site_id_help'              => 'El sitio de Matomo (idsite) al que informa esta instalación — encuéntralo en Matomo en Administración → Sitios web.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'Atribución de afiliados',
    'matomo.affiliate_help'            => 'Cuando un visitante llega por un enlace de afiliado (/a/<name>), ese nombre de afiliado se registra en la visita como una dimensión personalizada de Matomo — de modo que las visitas y las conversiones se pueden segmentar por afiliado. El enlace y su cookie los establece el programa de afiliados; esto solo los lee.',
    'matomo.affiliate_dimension'       => 'Índice de la dimensión personalizada',
    'matomo.affiliate_dimension_help'  => 'El índice de la dimensión personalizada con alcance de visita en Matomo que contiene el nombre del afiliado (predeterminado 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'Privacidad y consentimiento',
    'matomo.exclude_staff'             => 'No rastrear al personal con sesión iniciada',
    'matomo.exclude_staff_help'        => 'Omite las visitas de gerentes, administradores y desarrolladores para que tu propio equipo no distorsione las cifras.',
    'matomo.privacy_note'              => 'El rastreador se carga solo cuando el consentimiento de analítica lo permite. Matomo también respeta Do Not Track y anonimiza las direcciones IP en el servidor.',

    // Client-side
    'matomo.net_error'                 => 'Error de red — por favor, inténtalo de nuevo.',
    'matomo.fix_fields'                => 'Por favor, corrige los campos resaltados.',
];
