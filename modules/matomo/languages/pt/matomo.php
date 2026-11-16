<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Matomo module — Portuguese strings. Semantic, owner-prefixed keys (matomo.*). Loaded on top of core
 * strings by the translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // /api response messages
    'matomo.saved'                     => 'Configurações do Matomo salvas.',

    // Nav + settings screen chrome
    'matomo.nav.label'                 => 'Matomo',
    'matomo.title'                     => 'Análise do Matomo',
    'matomo.subtitle'                  => 'Conecte seu Matomo auto-hospedado para medir seu site público e atribuir o tráfego de afiliados.',
    'matomo.save'                      => 'Salvar',

    // Connection card
    'matomo.card.connection'           => 'Conexão com o Matomo',
    'matomo.enable'                    => 'Ativar o rastreamento do Matomo',
    'matomo.url'                       => 'URL do Matomo',
    'matomo.url_help'                  => 'A URL base da sua instalação do Matomo, por exemplo https://analytics.example.com/',
    'matomo.site_id'                   => 'ID do site',
    'matomo.site_id_help'              => 'O site do Matomo (idsite) para o qual esta instalação reporta — encontre-o no Matomo em Administração → Sites.',

    // Affiliate attribution card
    'matomo.card.affiliate'            => 'Atribuição de afiliados',
    'matomo.affiliate_help'            => 'Quando um visitante chega por um link de afiliado (/a/<name>), esse nome de afiliado é registrado na visita como uma dimensão personalizada do Matomo — para que visitas e conversões possam ser segmentadas por afiliado. O link e seu cookie são definidos pelo programa de afiliados; isto apenas os lê.',
    'matomo.affiliate_dimension'       => 'Índice da dimensão personalizada',
    'matomo.affiliate_dimension_help'  => 'O índice da dimensão personalizada com escopo de visita no Matomo que contém o nome do afiliado (padrão 1).',

    // Privacy & consent card
    'matomo.card.privacy'              => 'Privacidade e consentimento',
    'matomo.exclude_staff'             => 'Não rastrear a equipe com sessão iniciada',
    'matomo.exclude_staff_help'        => 'Ignore as visitas de gerentes, administradores e desenvolvedores para que a sua própria equipe não distorça os números.',
    'matomo.privacy_note'              => 'O rastreador carrega apenas quando o consentimento de análise o permite. O Matomo também respeita o Do Not Track e anonimiza os endereços IP no servidor.',

    // Client-side
    'matomo.net_error'                 => 'Erro de rede — por favor, tente novamente.',
    'matomo.fix_fields'                => 'Por favor, corrija os campos destacados.',

    // Token da API de relatórios (1.61) — somente escrita
    'matomo.token'                     => 'Token de API (token_auth)',
    'matomo.token_help'                => 'Crie um no Matomo em Administração → Pessoal → Segurança → Tokens de autenticação. Ele precisa de acesso de visualização a este site. Fica armazenado criptografado e não é mostrado novamente; deixe em branco para manter o salvo.',
    'matomo.token_placeholder'         => 'Cole seu token de API do Matomo',
    'matomo.token_keep'                => '•••••• salvo (deixe em branco para manter)',
    'matomo.token_saved'               => 'Token salvo',
    'matomo.card.tag'                  => 'Tag de rastreamento',
    'matomo.error.no_crypto'           => 'É preciso configurar uma chave de criptografia antes de armazenar um token de API.',
];
