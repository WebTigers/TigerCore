<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerCore — Português (pt) core strings. Chaves semânticas com prefixo (core.*).
 */
return [
    // --- Respostas de serviços /api (valores padrão) ---
    'core.api.success'               => 'Pronto.',
    'core.api.error.general'         => 'Algo deu errado. Tente novamente.',
    'core.api.error.form'            => 'Corrija os campos destacados.',
    'core.api.error.csrf'            => 'Ops — seu token de segurança expirou. Atualize a página para continuar. (Eles expiram de propósito; culpe os gremlins da segurança.)',
    'core.api.error.invalid_action'  => 'Essa ação não está disponível.',
    'core.api.error.not_allowed'     => 'Você não tem permissão para fazer isso.',
    'core.api.error.login_required'  => 'Faça login para continuar.',
    'core.token.created'          => 'Token criado — copie-o agora; ele não será exibido novamente.',
    'core.token.revoked'          => 'Token revogado.',
    'core.api.error.login_failed'    => 'E-mail ou senha inválidos.',
    'core.api.error.missing_module'  => 'Nenhum módulo foi especificado.',
    'core.api.error.missing_service' => 'Nenhum serviço foi especificado.',
    'core.api.error.missing_action'  => 'Nenhuma ação foi especificada.',

    // --- Formulários: validação de reCAPTCHA ---
    'core.form.recaptcha.missing'    => 'Confirme que você não é um robô.',
    'core.form.recaptcha.failed'     => 'A verificação do reCAPTCHA falhou. Tente novamente.',
    'core.form.recaptcha.error'      => 'Não foi possível verificar o reCAPTCHA no momento. Tente novamente.',

    // --- Autenticação de dois fatores (TOTP) ---
    'core.auth.twofa.enabled'        => 'A autenticação de dois fatores está ativada.',
    'core.auth.twofa.disabled'       => 'A autenticação de dois fatores foi desativada.',
    'core.auth.twofa.bad_code'       => 'Esse código está incorreto ou expirou.',
    'core.auth.twofa.unavailable'    => 'A autenticação de dois fatores não está disponível nesta instalação.',

    // --- Validação de formulários (a nível de campo) ---
    'core.form.password_mismatch'    => 'As senhas não coincidem.',

    // --- Política de senhas (chaves de Tiger_Policy_Password) ---
    'password.too_short'             => 'A senha é muito curta — use pelo menos 8 caracteres.',
    'password.needs_complexity'      => 'Adicione letras maiúsculas e minúsculas, um número e um símbolo.',
    'password.reused'                => 'Você já usou esta senha antes — escolha uma nova.',

    // --- Rótulos de interface comuns ---
    'core.common.close'              => 'Fechar',
    'core.common.done'               => 'Concluído',
    'core.common.back_home'          => 'Voltar ao início',

    // --- Páginas de erro (403 / 404 / 500) ---
    'core.error.badge'               => 'Erro',
    'core.error.403.title'           => 'Você não tem acesso a isso.',
    'core.error.404.title'           => 'Essa página não existe.',
    'core.error.500.title'           => 'Algo deu errado.',
    'core.error.403.sub'             => 'Você está conectado, mas esta área não está disponível para sua conta.',
    'core.error.404.sub'             => 'É possível que a página tenha sido movida ou que nunca tenha existido. Vamos colocá-lo de volta no caminho.',
    'core.error.500.sub'             => 'Algo quebrou do nosso lado. Já fomos notificados e estamos verificando — tente novamente em instantes.',
    'core.error.switch_account'      => 'Trocar de conta',

    // --- Autenticação: rótulos compartilhados ---
    'core.auth.email'                => 'E-mail',
    'core.auth.password'             => 'Senha',
    'core.auth.email_code'           => 'Enviar um código por e-mail',
    'core.auth.back_to_login'        => 'Voltar ao login',
    'core.auth.return_to'            => 'Voltar para %s',

    // --- Autenticação: entrar ---
    'core.auth.login.title'          => 'Entrar no Tiger',
    'core.auth.login.subtitle'       => 'Bem-vindo de volta.',
    'core.auth.login.identifier'     => 'E-mail ou nome de usuário',
    'core.auth.login.forgot'         => 'Esqueceu sua senha?',
    'core.auth.login.submit'         => 'Entrar',
    'core.auth.login.use_code'       => 'Entrar com um código',

    // --- Autenticação: aviso de dois fatores (etapa de login) ---
    'core.auth.twofa.prompt'         => 'Digite o código de 6 dígitos do seu aplicativo de autenticação.',
    'core.auth.twofa.code_label'     => 'Código de verificação',
    'core.auth.twofa.verify'         => 'Verificar',
    'core.auth.twofa.use_recovery'   => 'Usar um código de recuperação',

    // --- Autenticação: tela bloqueada ---
    'core.auth.lock.title'           => 'Tela bloqueada',
    'core.auth.lock.subtitle'        => 'Verifique novamente para continuar.',
    'core.auth.lock.unlock'          => 'Desbloquear',
    'core.auth.lock.use_code'        => 'Desbloquear com um código',
    'core.auth.lock.email_send_to'   => 'Enviaremos um código de uso único para',
    'core.auth.lock.use_password'    => 'Usar a senha',
    'core.auth.lock.not_you'         => 'Não é %s? Sair',

    // --- Autenticação: redefinir senha ---
    'core.auth.reset.title'          => 'Definir uma nova senha',
    'core.auth.reset.subtitle'       => 'Escolha uma senha forte que você não use em outro lugar.',
    'core.auth.reset.new_password'   => 'Nova senha',
    'core.auth.reset.confirm_password' => 'Confirmar senha',
    'core.auth.reset.submit'         => 'Definir nova senha',

    // --- Autenticação: senha esquecida ---
    'core.auth.forgot.title'         => 'Redefinir sua senha',
    'core.auth.forgot.subtitle'      => 'Enviaremos por e-mail um link para escolher uma nova.',
    'core.auth.forgot.submit'        => 'Enviar link de redefinição',

    // --- Autenticação: sessão encerrada ---
    'core.auth.logout.title'         => 'Você saiu da sessão.',
    'core.auth.logout.subtitle'      => 'Obrigado por passar por aqui.',
    'core.auth.logout.login_again'   => 'Entrar novamente',

    // --- Autenticação: login com código (sem senha) ---
    'core.auth.otp.title'            => 'Entrar com um código',
    'core.auth.otp.subtitle'         => 'Enviaremos por e-mail um código de uso único — sem senha.',
    'core.auth.otp.restart'          => 'Usar outro e-mail',
    'core.auth.otp.use_password'     => 'Entrar com uma senha',

    // --- Autenticação: gerenciamento de dois fatores (tela de segurança) ---
    'core.auth.twofa.heading'        => 'Autenticação de dois fatores',
    'core.auth.twofa.lead'           => 'Adicione um código de uso único de um aplicativo de autenticação ao seu login.',
    'core.auth.twofa.unavailable_detail' => 'A autenticação de dois fatores ainda não está disponível nesta instalação — a chave de criptografia do aplicativo (%s) não está configurada. Peça a um administrador para configurá-la.',
    'core.auth.twofa.enabled_badge'  => 'Ativado',
    'core.auth.twofa.protected'      => 'Seu aplicativo de autenticação está protegendo esta conta.',
    'core.auth.twofa.recovery_remaining' => 'Códigos de recuperação restantes:',
    'core.auth.twofa.recovery_help'  => 'Os códigos de recuperação permitem que você entre caso perca seu dispositivo. Reative para gerar um novo conjunto.',
    'core.auth.twofa.disable_prompt' => 'Para desativar a autenticação de dois fatores, confirme com um código atual do seu aplicativo (ou um código de recuperação):',
    'core.auth.twofa.disable_btn'    => 'Desativar 2FA',
    'core.auth.twofa.intro'          => 'Proteja sua conta com um código temporário de um aplicativo como Google Authenticator, 1Password, Authy ou Microsoft Authenticator.',
    'core.auth.twofa.enable_btn'     => 'Ativar a autenticação de dois fatores',
    'core.auth.twofa.step_scan'      => 'Escaneie o código QR',
    'core.auth.twofa.step_scan_detail' => 'com seu aplicativo de autenticação — ou digite a chave manualmente.',
    'core.auth.twofa.qr_preview'     => 'Prévia do QR',
    'core.auth.twofa.setup_key_label' => 'Chave de configuração (entrada manual)',
    'core.auth.twofa.open_in_app'    => 'Abrir no aplicativo',
    'core.auth.twofa.step_recovery'  => 'Salve seus códigos de recuperação.',
    'core.auth.twofa.step_recovery_detail' => 'Cada um pode ser usado uma vez caso você perca seu dispositivo. Guarde-os em um lugar seguro.',
    'core.auth.twofa.copy_codes'     => 'Copiar códigos',
    'core.auth.twofa.step_confirm'   => 'Confirme.',
    'core.auth.twofa.step_confirm_detail' => 'Digite o código de 6 dígitos que seu aplicativo mostra agora:',
    'core.auth.twofa.verify_turn_on' => 'Verificar e ativar',
    'core.auth.twofa.back_to_admin'  => 'Voltar à administração',

    // --- Painel (início da administração) ---
    'core.dashboard.title'           => 'Painel',
    'core.dashboard.lead'            => 'Bem-vindo à administração do Tiger.',
    'core.dashboard.customize'       => 'Personalizar',
    'core.dashboard.empty_title'     => 'Ainda não há widgets no painel',
    'core.dashboard.empty_lead'      => 'Os módulos que fornecem um widget de painel aparecerão aqui automaticamente quando estiverem ativos.',
    'core.dashboard.drag_hint'       => 'Arraste para reorganizar',
    'core.dashboard.collapse_aria'   => 'Recolher widget',
    'core.dashboard.customize_title' => 'Personalizar painel',
    'core.dashboard.customize_help'  => 'Ative ou desative widgets. Um widget oculto não é exibido — reative-o quando quiser.',

    // --- Início da conta ---
    'core.account.title'             => 'Minha conta',
    'core.account.lead'              => 'Sua assinatura, licenças e perfil.',
    'core.account.empty_lead'        => 'Os detalhes da sua conta aparecerão aqui à medida que você adicionar assinaturas e serviços.',
    'core.js.network_error' => 'Erro de rede — tente novamente.',
    'core.js.recaptcha' => 'Complete o reCAPTCHA e tente novamente.',
    'core.js.incorrect_password' => 'Senha incorreta.',
    'core.js.code_sent' => 'Enviamos um código de 6 dígitos para %s. Digite-o abaixo.',
    'core.js.code_invalid' => 'Esse código é inválido ou expirou.',
    'core.js.code_incorrect' => 'Esse código está incorreto ou expirou.',
    'core.js.invalid_login' => 'Usuário ou senha inválidos.',
    'core.js.passwords_mismatch' => 'As senhas não coincidem.',
    'core.js.reset_failed' => 'Não foi possível redefinir sua senha — o link pode ter expirado.',
    'core.js.twofa_disabled' => 'Autenticação de dois fatores desativada.',
    'core.js.twofa_code_wrong_on' => 'Esse código está incorreto. A autenticação de dois fatores continua ativada.',
    'core.js.setup_failed' => 'Não foi possível iniciar a configuração. Tente novamente.',
    'core.js.twofa_on' => 'A autenticação de dois fatores está ativada. 🎉',
    'core.js.twofa_code_wrong' => 'Esse código não coincide. Verifique o relógio do seu aplicativo e tente o código atual.',
    'core.js.widget_load_error' => 'Não foi possível carregar este widget.',
    'core.nav.dashboard' => 'Painel',
    'core.nav.account' => 'Minha conta',
    'core.nav.content' => 'Conteúdo',
    'core.nav.articles' => 'Artigos',
    'core.nav.menus' => 'Menus',
    'core.nav.media' => 'Mídia',
    'core.nav.users' => 'Usuários',
    'core.nav.orgs' => 'Organizações',
    'core.nav.code' => 'Código',
    'core.nav.modules' => 'Módulos',
    'core.nav.settings' => 'Configurações',
    'core.datatable.info' => 'Mostrando _START_ a _END_ de _TOTAL_ registros',
    'core.datatable.info_empty' => 'Mostrando 0 a 0 de 0 registros',
    'core.datatable.info_filtered' => '(filtrado de _MAX_ registros no total)',
    'core.datatable.length_menu' => '_MENU_ por página',
    'core.datatable.search_placeholder' => 'Pesquisar…',
    'core.datatable.zero_records' => 'Nenhum registro correspondente encontrado',
    'core.datatable.empty_table' => 'Nenhum dado disponível',
    'core.datatable.loading' => 'Carregando…',
    'core.datatable.processing' => 'Processando…',
    'core.datatable.paginate_first' => 'Primeiro',
    'core.datatable.paginate_last' => 'Último',
    'core.datatable.paginate_next' => 'Próximo',
    'core.datatable.paginate_prev' => 'Anterior',
];
