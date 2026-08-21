<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Register module — Brazilian Portuguese strings. Owner-prefixed keys (register.*). */
return [
    // /api response messages
    'register.registered'      => 'Seu site está registrado — verifique seu e-mail para concluir a verificação.',
    'register.domain_verified' => 'Seu domínio está verificado.',
    'register.domain_pending'  => 'Seu domínio ainda não está verificado — continuaremos tentando; você também pode tentar novamente agora.',
    'register.email_sent'      => 'E-mail de verificação enviado.',
    'register.error.no_domain'            => 'Não foi possível detectar o domínio deste site.',
    'register.error.registry_unreachable' => 'A rede Tiger está indisponível agora — tente novamente em instantes.',
    'register.error.not_registered'       => 'Este site não está registrado.',

    // Settings → Registration screen
    'register.title'        => 'Registro',
    'register.subtitle'     => 'Opcional. Registre este site para obter um Site ID verificado e entrar na rede Tiger — isso não ativa nem desativa nada.',
    'register.status'       => 'Status',
    'register.verified'     => 'Verificado',
    'register.not_registered' => 'Não registrado',
    'register.field.domain' => 'Domínio',
    'register.field.email'  => 'E-mail',
    'register.field.tsid'   => 'Site ID (TSID)',
    'register.intro_body'   => 'O registro verifica seu <strong>domínio</strong> (servido automaticamente — nada para enviar) e seu <strong>e-mail</strong>. Só compartilhamos seu domínio, este e-mail e suas versões do Tiger/PHP. Não quer? Deixe assim — ou desative o widget de Registro / desative este módulo.',
    'register.admin_email'  => 'E-mail do administrador',
    'register.register_btn' => 'Registrar',
    'register.badge.domain' => 'Domínio',
    'register.badge.email'  => 'E-mail',
    'register.state.verified' => 'verificado',
    'register.state.pending'  => 'pendente',
    'register.verify_domain'  => 'Verificar domínio',
    'register.resend_email'   => 'Reenviar e-mail de verificação',
    'register.net_error'      => 'Erro de rede — tente novamente.',

    // Public email-verify landing
    'register.verify.ok_title'   => 'Seu site está verificado',
    'register.verify.ok_body'    => 'Obrigado — seu e-mail está confirmado.',
    'register.verify.ok_cta'     => 'Ir para o seu painel',
    'register.verify.fail_title' => 'Esse link não funcionou',
    'register.verify.fail_body'  => 'Ele pode ter expirado ou já ter sido usado. Reenvie-o pelo widget de Registro ou pelas Configurações.',
    'register.verify.fail_cta'   => 'Ir para Registro',
    'register.nav.label' => 'Registro',
    'register.widget.title' => 'Registro',
    'register.widget.registered' => 'Seu site está registrado',
    'register.widget.site_id' => 'ID do site',
    'register.widget.intro' => 'Registre este site para obter um ID de site verificado e juntar-se à rede Tiger — é opcional e não ativa nem desativa nada. Compartilhamos apenas seu domínio, este e-mail e suas versões do Tiger/PHP.',
    'register.widget.register' => 'Registrar',
    'register.widget.confirming' => 'Confirmando que você controla %s.',
    'register.widget.last_step' => 'Última etapa: clique no link que enviamos para %s.',
    'register.widget.resend' => 'Reenviar e-mail',
];
