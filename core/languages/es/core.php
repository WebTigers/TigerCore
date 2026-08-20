<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerCore — Español (es) core strings. Claves semánticas con prefijo (core.*).
 */
return [
    // --- Respuestas de servicios /api (valores por defecto) ---
    'core.api.success'               => 'Listo.',
    'core.api.error.general'         => 'Algo salió mal. Inténtalo de nuevo.',
    'core.api.error.form'            => 'Corrige los campos resaltados.',
    'core.api.error.csrf'            => 'Vaya — tu token de seguridad caducó. Actualiza la página para continuar. (Caducan a propósito; culpa a los duendes de la seguridad.)',
    'core.api.error.invalid_action'  => 'Esa acción no está disponible.',
    'core.api.error.not_allowed'     => 'No tienes permiso para hacer eso.',
    'core.api.error.login_required'  => 'Inicia sesión para continuar.',
    'core.token.created'          => 'Token creado — cópialo ahora; no se volverá a mostrar.',
    'core.token.revoked'          => 'Token revocado.',
    'core.api.error.login_failed'    => 'Correo o contraseña inválidos.',
    'core.api.error.missing_module'  => 'No se especificó ningún módulo.',
    'core.api.error.missing_service' => 'No se especificó ningún servicio.',
    'core.api.error.missing_action'  => 'No se especificó ninguna acción.',

    // --- Formularios: validación de reCAPTCHA ---
    'core.form.recaptcha.missing'    => 'Confirma que no eres un robot.',
    'core.form.recaptcha.failed'     => 'La verificación de reCAPTCHA falló. Inténtalo de nuevo.',
    'core.form.recaptcha.error'      => 'No se pudo verificar reCAPTCHA en este momento. Inténtalo de nuevo.',

    // --- Autenticación de dos factores (TOTP) ---
    'core.auth.twofa.enabled'        => 'La autenticación de dos factores está activada.',
    'core.auth.twofa.disabled'       => 'Se desactivó la autenticación de dos factores.',
    'core.auth.twofa.bad_code'       => 'Ese código es incorrecto o ha caducado.',
    'core.auth.twofa.unavailable'    => 'La autenticación de dos factores no está disponible en esta instalación.',

    // --- Validación de formularios (a nivel de campo) ---
    'core.form.password_mismatch'    => 'Las contraseñas no coinciden.',

    // --- Política de contraseñas (claves de Tiger_Policy_Password) ---
    'password.too_short'             => 'La contraseña es demasiado corta — usa al menos 8 caracteres.',
    'password.needs_complexity'      => 'Añade letras mayúsculas y minúsculas, un número y un símbolo.',
    'password.reused'                => 'Ya has usado esta contraseña antes — elige una nueva.',

    // --- Etiquetas de interfaz comunes ---
    'core.common.close'              => 'Cerrar',
    'core.common.done'               => 'Hecho',
    'core.common.back_home'          => 'Volver al inicio',

    // --- Páginas de error (403 / 404 / 500) ---
    'core.error.badge'               => 'Error',
    'core.error.403.title'           => 'No tienes acceso a eso.',
    'core.error.404.title'           => 'Esa página no existe.',
    'core.error.500.title'           => 'Algo salió mal.',
    'core.error.403.sub'             => 'Has iniciado sesión, pero esta área no está disponible para tu cuenta.',
    'core.error.404.sub'             => 'Es posible que la página se haya movido o que nunca haya existido. Volvamos al buen camino.',
    'core.error.500.sub'             => 'Algo se rompió de nuestro lado. Ya nos avisaron y lo estamos revisando — inténtalo de nuevo en un momento.',
    'core.error.switch_account'      => 'Cambiar de cuenta',

    // --- Autenticación: etiquetas compartidas ---
    'core.auth.email'                => 'Correo electrónico',
    'core.auth.password'             => 'Contraseña',
    'core.auth.email_code'           => 'Enviarme un código',
    'core.auth.back_to_login'        => 'Volver a iniciar sesión',
    'core.auth.return_to'            => 'Volver a %s',

    // --- Autenticación: iniciar sesión ---
    'core.auth.login.title'          => 'Iniciar sesión en Tiger',
    'core.auth.login.subtitle'       => 'Bienvenido de nuevo.',
    'core.auth.login.identifier'     => 'Correo electrónico o usuario',
    'core.auth.login.forgot'         => '¿Olvidaste tu contraseña?',
    'core.auth.login.submit'         => 'Iniciar sesión',
    'core.auth.login.use_code'       => 'Iniciar sesión con un código',

    // --- Autenticación: aviso de dos factores (paso de inicio de sesión) ---
    'core.auth.twofa.prompt'         => 'Introduce el código de 6 dígitos de tu aplicación de autenticación.',
    'core.auth.twofa.code_label'     => 'Código de verificación',
    'core.auth.twofa.verify'         => 'Verificar',
    'core.auth.twofa.use_recovery'   => 'Usar un código de recuperación',

    // --- Autenticación: pantalla bloqueada ---
    'core.auth.lock.title'           => 'Pantalla bloqueada',
    'core.auth.lock.subtitle'        => 'Vuelve a verificar para continuar.',
    'core.auth.lock.unlock'          => 'Desbloquear',
    'core.auth.lock.use_code'        => 'Desbloquear con un código',
    'core.auth.lock.email_send_to'   => 'Enviaremos un código de un solo uso a',
    'core.auth.lock.use_password'    => 'Usar la contraseña',
    'core.auth.lock.not_you'         => '¿No eres %s? Cerrar sesión',

    // --- Autenticación: restablecer contraseña ---
    'core.auth.reset.title'          => 'Establecer una nueva contraseña',
    'core.auth.reset.subtitle'       => 'Elige una contraseña segura que no uses en otro sitio.',
    'core.auth.reset.new_password'   => 'Nueva contraseña',
    'core.auth.reset.confirm_password' => 'Confirmar contraseña',
    'core.auth.reset.submit'         => 'Establecer nueva contraseña',

    // --- Autenticación: contraseña olvidada ---
    'core.auth.forgot.title'         => 'Restablecer tu contraseña',
    'core.auth.forgot.subtitle'      => 'Te enviaremos por correo un enlace para elegir una nueva.',
    'core.auth.forgot.submit'        => 'Enviar enlace de restablecimiento',

    // --- Autenticación: sesión cerrada ---
    'core.auth.logout.title'         => 'Has cerrado sesión.',
    'core.auth.logout.subtitle'      => 'Gracias por pasar por aquí.',
    'core.auth.logout.login_again'   => 'Iniciar sesión de nuevo',

    // --- Autenticación: inicio de sesión con código (sin contraseña) ---
    'core.auth.otp.title'            => 'Iniciar sesión con un código',
    'core.auth.otp.subtitle'         => 'Te enviaremos por correo un código de un solo uso — sin contraseña.',
    'core.auth.otp.restart'          => 'Usar otro correo electrónico',
    'core.auth.otp.use_password'     => 'Iniciar sesión con una contraseña',

    // --- Autenticación: gestión de dos factores (pantalla de seguridad) ---
    'core.auth.twofa.heading'        => 'Autenticación de dos factores',
    'core.auth.twofa.lead'           => 'Añade un código de un solo uso de una aplicación de autenticación a tu inicio de sesión.',
    'core.auth.twofa.unavailable_detail' => 'La autenticación de dos factores aún no está disponible en esta instalación — la clave de cifrado de la aplicación (%s) no está configurada. Pide a un administrador que la configure.',
    'core.auth.twofa.enabled_badge'  => 'Activado',
    'core.auth.twofa.protected'      => 'Tu aplicación de autenticación está protegiendo esta cuenta.',
    'core.auth.twofa.recovery_remaining' => 'Códigos de recuperación restantes:',
    'core.auth.twofa.recovery_help'  => 'Los códigos de recuperación te permiten iniciar sesión si pierdes tu dispositivo. Vuelve a activar para generar un nuevo conjunto.',
    'core.auth.twofa.disable_prompt' => 'Para desactivar la autenticación de dos factores, confirma con un código actual de tu aplicación (o un código de recuperación):',
    'core.auth.twofa.disable_btn'    => 'Desactivar 2FA',
    'core.auth.twofa.intro'          => 'Protege tu cuenta con un código temporal de una aplicación como Google Authenticator, 1Password, Authy o Microsoft Authenticator.',
    'core.auth.twofa.enable_btn'     => 'Activar la autenticación de dos factores',
    'core.auth.twofa.step_scan'      => 'Escanea el código QR',
    'core.auth.twofa.step_scan_detail' => 'con tu aplicación de autenticación — o introduce la clave a mano.',
    'core.auth.twofa.qr_preview'     => 'Vista previa del QR',
    'core.auth.twofa.setup_key_label' => 'Clave de configuración (entrada manual)',
    'core.auth.twofa.open_in_app'    => 'Abrir en la aplicación',
    'core.auth.twofa.step_recovery'  => 'Guarda tus códigos de recuperación.',
    'core.auth.twofa.step_recovery_detail' => 'Cada uno puede usarse una vez si pierdes tu dispositivo. Guárdalos en un lugar seguro.',
    'core.auth.twofa.copy_codes'     => 'Copiar códigos',
    'core.auth.twofa.step_confirm'   => 'Confirma.',
    'core.auth.twofa.step_confirm_detail' => 'Introduce el código de 6 dígitos que muestra tu aplicación ahora:',
    'core.auth.twofa.verify_turn_on' => 'Verificar y activar',
    'core.auth.twofa.back_to_admin'  => 'Volver a la administración',

    // --- Panel (inicio de administración) ---
    'core.dashboard.title'           => 'Panel',
    'core.dashboard.lead'            => 'Bienvenido a la administración de Tiger.',
    'core.dashboard.customize'       => 'Personalizar',
    'core.dashboard.empty_title'     => 'Aún no hay widgets en el panel',
    'core.dashboard.empty_lead'      => 'Los módulos que proporcionan un widget de panel aparecerán aquí automáticamente cuando estén activos.',
    'core.dashboard.drag_hint'       => 'Arrastra para reorganizar',
    'core.dashboard.collapse_aria'   => 'Contraer widget',
    'core.dashboard.customize_title' => 'Personalizar panel',
    'core.dashboard.customize_help'  => 'Activa o desactiva widgets. Un widget oculto no se muestra — vuelve a activarlo cuando quieras.',

    // --- Inicio de cuenta ---
    'core.account.title'             => 'Mi cuenta',
    'core.account.lead'              => 'Tu suscripción, licencias y perfil.',
    'core.account.empty_lead'        => 'Los detalles de tu cuenta aparecerán aquí a medida que añadas suscripciones y servicios.',
    'core.js.network_error' => 'Error de red — inténtalo de nuevo.',
    'core.js.recaptcha' => 'Completa el reCAPTCHA e inténtalo de nuevo.',
    'core.js.incorrect_password' => 'Contraseña incorrecta.',
    'core.js.code_sent' => 'Enviamos un código de 6 dígitos a %s. Introdúcelo abajo.',
    'core.js.code_invalid' => 'Ese código no es válido o ha caducado.',
    'core.js.code_incorrect' => 'Ese código es incorrecto o ha caducado.',
    'core.js.invalid_login' => 'Usuario o contraseña no válidos.',
    'core.js.passwords_mismatch' => 'Las contraseñas no coinciden.',
    'core.js.reset_failed' => 'No se pudo restablecer tu contraseña — el enlace puede haber caducado.',
    'core.js.twofa_disabled' => 'Autenticación de dos factores desactivada.',
    'core.js.twofa_code_wrong_on' => 'Ese código es incorrecto. La verificación en dos pasos sigue activada.',
    'core.js.setup_failed' => 'No se pudo iniciar la configuración. Inténtalo de nuevo.',
    'core.js.twofa_on' => 'La autenticación de dos factores está activada. 🎉',
    'core.js.twofa_code_wrong' => 'Ese código no coincide. Revisa la hora de tu app y prueba el código actual.',
    'core.js.widget_load_error' => 'No se pudo cargar este widget.',
];
