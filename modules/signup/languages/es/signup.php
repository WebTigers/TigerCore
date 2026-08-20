<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup module — Spanish strings (signup.*).
 */
return [
    // Service / API messages
    'signup.disabled'        => 'El registro público está desactivado actualmente.',
    'signup.error.recaptcha' => 'No pudimos verificar que eres humano; inténtalo de nuevo.',
    'signup.check_email'     => 'Cuenta creada: revisa tu correo electrónico para verificarla y luego inicia sesión.',
    'signup.verified'        => 'Tu correo electrónico está verificado y tu cuenta está activa.',
    'signup.invalid_link'    => 'Este enlace de verificación no es válido o ha caducado.',

    // Signup form view
    'signup.form.heading'          => 'Crea tu cuenta',
    'signup.form.subheading'       => 'Comienza tu espacio de trabajo de Tiger: solo toma un minuto.',
    'signup.form.label.first_name' => 'Nombre',
    'signup.form.label.last_name'  => 'Apellido',
    'signup.form.label.company'    => 'Empresa',
    'signup.form.label.username'   => 'Nombre de usuario',
    'signup.form.label.password'   => 'Contraseña',
    'signup.form.aria.show_password' => 'Mostrar contraseña',
    'signup.form.label.email'      => 'Correo electrónico',
    'signup.form.label.street'     => 'Dirección',
    'signup.form.label.city'       => 'Ciudad',
    'signup.form.label.region'     => 'Estado / Provincia',
    'signup.form.label.postal'     => 'Código postal',
    'signup.form.label.country'    => 'País',
    'signup.form.option.select'    => '— Seleccionar —',
    'signup.form.group.frequent'   => 'Frecuentes',
    'signup.form.group.all'        => 'Todos los países',
    'signup.form.label.phone_type' => 'Tipo de teléfono',
    'signup.form.label.phone'      => 'Teléfono',
    'signup.form.submit'           => 'Crear cuenta',
    'signup.form.have_account'     => '¿Ya tienes una cuenta? Inicia sesión',

    // Email-verification result view
    'signup.verify.heading'        => 'Verificación de correo electrónico',
    'signup.verify.success.body'   => 'Tu correo electrónico está verificado y tu cuenta está activa. Ya puedes iniciar sesión.',
    'signup.verify.action.signin'  => 'Iniciar sesión',
    'signup.verify.invalid.body'   => 'Este enlace de verificación no es válido o ha caducado. Puedes registrarte de nuevo o contactar con soporte si ya te registraste.',
    'signup.verify.action.back'    => 'Volver al registro',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'signup.js.verify_sent'   => '<strong>Ya casi está.</strong> Te enviamos un enlace de verificación para activar tu cuenta: haz clic en él y luego inicia sesión.',
    'signup.js.fix_fields'    => 'Corrige los campos resaltados.',
    'signup.js.check_field'   => 'Revisa este campo.',
    'signup.js.went_wrong'    => 'Algo salió mal. Inténtalo de nuevo.',
    'signup.js.network_error' => 'Error de red: inténtalo de nuevo.',
];
