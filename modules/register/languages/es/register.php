<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Register module — Spanish strings. Owner-prefixed keys (register.*). */
return [
    // /api response messages
    'register.registered'      => 'Tu sitio está registrado — revisa tu correo para terminar de verificarlo.',
    'register.domain_verified' => 'Tu dominio está verificado.',
    'register.domain_pending'  => 'Tu dominio aún no está verificado — seguiremos intentándolo; también puedes reintentar ahora.',
    'register.email_sent'      => 'Correo de verificación enviado.',
    'register.error.no_domain'            => 'No se pudo detectar el dominio de este sitio.',
    'register.error.registry_unreachable' => 'La red Tiger no está disponible ahora mismo — inténtalo de nuevo en unos momentos.',
    'register.error.not_registered'       => 'Este sitio no está registrado.',

    // Settings → Registration screen
    'register.title'        => 'Registro',
    'register.subtitle'     => 'Opcional. Registra este sitio para obtener un Site ID verificado y unirte a la red Tiger — no activa ni desactiva nada.',
    'register.status'       => 'Estado',
    'register.verified'     => 'Verificado',
    'register.not_registered' => 'No registrado',
    'register.field.domain' => 'Dominio',
    'register.field.email'  => 'Correo electrónico',
    'register.field.tsid'   => 'Site ID (TSID)',
    'register.intro_body'   => 'El registro verifica tu <strong>dominio</strong> (servido automáticamente — nada que subir) y tu <strong>correo</strong>. Solo compartimos tu dominio, este correo y tus versiones de Tiger/PHP. ¿No quieres? Déjalo — o desactiva el widget de Registro / desactiva este módulo.',
    'register.admin_email'  => 'Correo del administrador',
    'register.register_btn' => 'Registrar',
    'register.badge.domain' => 'Dominio',
    'register.badge.email'  => 'Correo',
    'register.state.verified' => 'verificado',
    'register.state.pending'  => 'pendiente',
    'register.verify_domain'  => 'Verificar dominio',
    'register.resend_email'   => 'Reenviar correo de verificación',
    'register.net_error'      => 'Error de red — inténtalo de nuevo.',

    // Public email-verify landing
    'register.verify.ok_title'   => 'Tu sitio está verificado',
    'register.verify.ok_body'    => 'Gracias — tu correo está confirmado.',
    'register.verify.ok_cta'     => 'Ir a tu panel',
    'register.verify.fail_title' => 'Ese enlace no funcionó',
    'register.verify.fail_body'  => 'Puede haber caducado o ya haberse usado. Reenvíalo desde el widget de Registro o desde Configuración.',
    'register.verify.fail_cta'   => 'Ir a Registro',
    'register.nav.label' => 'Registro',
    'register.widget.title' => 'Registro',
    'register.widget.registered' => 'Tu sitio está registrado',
    'register.widget.site_id' => 'ID del sitio',
    'register.widget.intro' => 'Registra este sitio para obtener un ID de sitio verificado y unirte a la red Tiger — es opcional y no activa ni desactiva nada. Solo compartimos tu dominio, este correo y tus versiones de Tiger/PHP.',
    'register.widget.register' => 'Registrar',
    'register.widget.confirming' => 'Confirmando que controlas %s.',
    'register.widget.last_step' => 'Último paso: haz clic en el enlace que enviamos a %s.',
    'register.widget.resend' => 'Reenviar correo',
];
