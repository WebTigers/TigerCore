<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup module — Brazilian Portuguese strings (signup.*).
 */
return [
    // Service / API messages
    'signup.disabled'        => 'O cadastro público está desativado no momento.',
    'signup.error.recaptcha' => 'Não foi possível verificar que você é humano; tente novamente.',
    'signup.check_email'     => 'Conta criada: verifique seu e-mail para confirmá-la e depois faça login.',
    'signup.verified'        => 'Seu e-mail foi verificado e sua conta está ativa.',
    'signup.invalid_link'    => 'Este link de verificação é inválido ou expirou.',

    // Signup form view
    'signup.form.heading'          => 'Crie sua conta',
    'signup.form.subheading'       => 'Comece seu espaço de trabalho do Tiger: leva só um minuto.',
    'signup.form.label.first_name' => 'Nome',
    'signup.form.label.last_name'  => 'Sobrenome',
    'signup.form.label.company'    => 'Empresa',
    'signup.form.label.username'   => 'Nome de usuário',
    'signup.form.label.password'   => 'Senha',
    'signup.form.aria.show_password' => 'Mostrar senha',
    'signup.form.label.email'      => 'E-mail',
    'signup.form.label.street'     => 'Endereço',
    'signup.form.label.city'       => 'Cidade',
    'signup.form.label.region'     => 'Estado / Província',
    'signup.form.label.postal'     => 'Código postal',
    'signup.form.label.country'    => 'País',
    'signup.form.option.select'    => '— Selecionar —',
    'signup.form.group.frequent'   => 'Frequentes',
    'signup.form.group.all'        => 'Todos os países',
    'signup.form.label.phone_type' => 'Tipo de telefone',
    'signup.form.label.phone'      => 'Telefone',
    'signup.form.submit'           => 'Criar conta',
    'signup.form.have_account'     => 'Já tem uma conta? Faça login',

    // Email-verification result view
    'signup.verify.heading'        => 'Verificação de e-mail',
    'signup.verify.success.body'   => 'Seu e-mail foi verificado e sua conta está ativa. Você já pode fazer login.',
    'signup.verify.action.signin'  => 'Fazer login',
    'signup.verify.invalid.body'   => 'Este link de verificação é inválido ou expirou. Você pode se cadastrar novamente ou entrar em contato com o suporte se já se registrou.',
    'signup.verify.action.back'    => 'Voltar ao cadastro',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'signup.js.verify_sent'   => '<strong>Quase lá.</strong> Enviamos um link de verificação para ativar sua conta: clique nele e depois faça login.',
    'signup.js.fix_fields'    => 'Corrija os campos destacados.',
    'signup.js.check_field'   => 'Verifique este campo.',
    'signup.js.went_wrong'    => 'Algo deu errado. Tente novamente.',
    'signup.js.network_error' => 'Erro de rede: tente novamente.',
];
