<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics module — Brazilian Portuguese strings. Semantic, owner-prefixed keys (analytics.*).
 */
return [
    // /api response messages
    'analytics.saved'                  => 'Configurações de analytics salvas.',
    'analytics.reports.not_connected'  => 'O Google Analytics ainda não está conectado.',
    'analytics.reports.error'          => 'Não foi possível carregar os dados do Analytics agora — tente novamente em instantes.',

    // Settings screen
    'analytics.title'                  => 'Analytics',
    'analytics.subtitle'               => 'Conecte o Google Analytics 4 para medir o tráfego do seu site público.',
    'analytics.save'                   => 'Salvar',
    'analytics.nav.settings'           => 'Configurações',
    'analytics.net_error'              => 'Erro de rede — tente novamente.',
    'analytics.fix_fields'             => 'Corrija os campos destacados.',

    'analytics.tab.tag'                => 'Tag de rastreamento',
    'analytics.tab.reports'            => 'Relatórios e painel',
    'analytics.connected'              => 'Conectado',
    'analytics.not_connected'          => 'Não conectado',

    'analytics.ga4'                    => 'Google Analytics 4',
    'analytics.enable'                 => 'Ativar o Google Analytics',
    'analytics.measurement_id'         => 'ID de medição',
    'analytics.exclude_staff'          => 'Não rastrear a equipe com sessão iniciada',
    'analytics.exclude_staff_help'     => 'Ignore as visitas de gerentes, administradores e desenvolvedores para que sua própria equipe não distorça os números.',
    'analytics.privacy_title'          => 'Privacidade e consentimento',

    'analytics.reports_heading'        => 'Relatórios — painel integrado',
    'analytics.reports_intro'          => 'Traga seu tráfego para um painel integrado, aqui mesmo na administração.',
    'analytics.property_id'            => 'ID de propriedade do GA4',

    'analytics.connection_method'      => 'Método de conexão',
    'analytics.method_oneclick'        => 'Um clique',
    'analytics.recommended'            => 'Recomendado',
    'analytics.method_oneclick_help'   => 'Conecte-se com sua conta do Google — a WebTigers cuida da configuração do OAuth. Nada para registrar.',
    'analytics.method_byo'             => 'Usar meu próprio cliente OAuth do Google',
    'analytics.method_byo_adv'         => '(avançado / auto-hospedado)',
    'analytics.method_byo_help'        => 'Registre seu próprio projeto do Google Cloud — a conexão nunca passa pela WebTigers.',
    'analytics.oauth_client_id'        => 'ID do cliente OAuth',
    'analytics.oauth_client_secret'    => 'Segredo do cliente OAuth',
    'analytics.oauth_secret_keep'      => '•••••• (deixe em branco para manter)',

    'analytics.view_dashboard'         => 'Ver painel',
    'analytics.disconnect'             => 'Desconectar',
    'analytics.connect'                => 'Conectar o Google Analytics',
    'analytics.connect_hint'           => 'Salva suas configurações e depois abre o Google para autorizar.',
    'analytics.connect_need_property'  => 'Informe primeiro o ID de propriedade do GA4 — ele indica sobre qual propriedade gerar relatórios.',

    // Dashboard screen
    'analytics.dashboard.title'                => 'Analytics',
    'analytics.dashboard.subtitle'             => 'O tráfego do seu site nos últimos 28 dias, do Google Analytics.',
    'analytics.dashboard.not_connected_title'  => 'Não conectado',
    'analytics.dashboard.not_connected_body'   => 'Conecte sua conta do Google Analytics para ver os relatórios de tráfego aqui.',
    'analytics.dashboard.go_settings'          => 'Ir para as configurações do Analytics',
    'analytics.metric.active_users'            => 'Usuários ativos',
    'analytics.metric.sessions'                => 'Sessões',
    'analytics.metric.page_views'              => 'Visualizações de página',
    'analytics.card.traffic'                   => 'Tráfego',
    'analytics.card.top_pages'                 => 'Páginas principais',
    'analytics.card.top_channels'              => 'Canais principais',

    // Dashboard widget
    'analytics.widget.connect'         => 'Conecte o Google Analytics para ver o tráfego.',
    'analytics.widget.setup'           => 'Configurar',
    'analytics.widget.active_users_28d'=> 'usuários ativos · 28d',
    'analytics.widget.page_views'      => 'visualizações de página',
    'analytics.widget.view_dashboard'  => 'Ver painel',
    'analytics.nav.label' => 'Análises',
    'analytics.widget.traffic' => 'Tráfego',
];
