<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics module — Spanish strings. Semantic, owner-prefixed keys (analytics.*).
 */
return [
    // /api response messages
    'analytics.saved'                  => 'Configuración de analítica guardada.',
    'analytics.reports.not_connected'  => 'Google Analytics aún no está conectado.',
    'analytics.reports.error'          => 'No se pudieron cargar los datos de Analytics ahora mismo — inténtalo de nuevo en unos momentos.',

    // Settings screen
    'analytics.title'                  => 'Analítica',
    'analytics.subtitle'               => 'Conecta Google Analytics 4 para medir el tráfico de tu sitio público.',
    'analytics.save'                   => 'Guardar',
    'analytics.nav.settings'           => 'Configuración',
    'analytics.net_error'              => 'Error de red — inténtalo de nuevo.',
    'analytics.fix_fields'             => 'Corrige los campos resaltados.',

    'analytics.tab.tag'                => 'Etiqueta de seguimiento',
    'analytics.tab.reports'            => 'Informes y panel',
    'analytics.connected'              => 'Conectado',
    'analytics.not_connected'          => 'No conectado',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Activar Google Analytics',
    'analytics.measurement_id'         => 'ID de medición',
    'analytics.exclude_staff'          => 'No rastrear al personal con sesión iniciada',
    'analytics.exclude_staff_help'     => 'Omite las visitas de gerentes, administradores y desarrolladores para que tu propio equipo no distorsione las cifras.',
    'analytics.privacy_title'          => 'Privacidad y consentimiento',

    'analytics.reports_heading'        => 'Informes — panel integrado',
    'analytics.reports_intro'          => 'Trae tu tráfico a un panel integrado, aquí mismo en la administración.',
    'analytics.property_id'            => 'ID de propiedad de GA4',

    'analytics.connection_method'      => 'Método de conexión',
    'analytics.method_oneclick'        => 'Un clic',
    'analytics.recommended'            => 'Recomendado',
    'analytics.method_oneclick_help'   => 'Conéctate con tu cuenta de Google — WebTigers gestiona la configuración de OAuth. No hay nada que registrar.',
    'analytics.method_byo'             => 'Usar mi propio cliente OAuth de Google',
    'analytics.method_byo_adv'         => '(avanzado / autohospedado)',
    'analytics.method_byo_help'        => 'Registra tu propio proyecto de Google Cloud — la conexión nunca pasa por WebTigers.',
    'analytics.oauth_client_id'        => 'ID de cliente OAuth',
    'analytics.oauth_client_secret'    => 'Secreto de cliente OAuth',
    'analytics.oauth_secret_keep'      => '•••••• (déjalo en blanco para conservarlo)',

    'analytics.view_dashboard'         => 'Ver panel',
    'analytics.disconnect'             => 'Desconectar',
    'analytics.connect'                => 'Conectar Google Analytics',
    'analytics.connect_hint'           => 'Guarda tu configuración y luego abre Google para autorizar.',
    'analytics.connect_need_property'  => 'Introduce primero el ID de propiedad de GA4 — nos indica sobre qué propiedad informar.',

    // Dashboard screen
    'analytics.dashboard.title'                => 'Analítica',
    'analytics.dashboard.subtitle'             => 'El tráfico de tu sitio en los últimos 28 días, desde Google Analytics.',
    'analytics.dashboard.not_connected_title'  => 'No conectado',
    'analytics.dashboard.not_connected_body'   => 'Conecta tu cuenta de Google Analytics para ver aquí los informes de tráfico.',
    'analytics.dashboard.go_settings'          => 'Ir a la configuración de Analytics',
    'analytics.metric.active_users'            => 'Usuarios activos',
    'analytics.metric.sessions'                => 'Sesiones',
    'analytics.metric.page_views'              => 'Páginas vistas',
    'analytics.card.traffic'                   => 'Tráfico',
    'analytics.card.top_pages'                 => 'Páginas principales',
    'analytics.card.top_channels'              => 'Canales principales',

    // Dashboard widget
    'analytics.widget.connect'         => 'Conecta Google Analytics para ver el tráfico.',
    'analytics.widget.setup'           => 'Configurar',
    'analytics.widget.active_users_28d'=> 'usuarios activos · 28 d',
    'analytics.widget.page_views'      => 'páginas vistas',
    'analytics.widget.view_dashboard'  => 'Ver panel',
];
