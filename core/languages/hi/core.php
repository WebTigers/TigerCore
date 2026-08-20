<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
// STUB — hi: English placeholders; translate the values.
/**
 * TigerCore — हिन्दी (hi) core strings. STUB — English placeholders; translate the values.
 */
return [
    // --- API service responses (Tiger_Service_Service / ServiceFactory defaults) ---
    'core.api.success'               => 'Done.',
    'core.api.error.general'         => 'Something went wrong. Please try again.',
    'core.api.error.form'            => 'Please correct the highlighted fields.',
    'core.api.error.csrf'            => 'Oops — your security token expired. Please refresh the page to continue. (They time out on purpose; blame the security gremlins.)',
    'core.api.error.invalid_action'  => 'That action is not available.',
    'core.api.error.not_allowed'     => "You don't have permission to do that.",
    'core.api.error.login_required'  => 'Please sign in to continue.',
    'core.token.created'          => 'Token created — copy it now; it won\'t be shown again.',
    'core.token.revoked'          => 'Token revoked.',
    'core.api.error.login_failed'    => 'Invalid email or password.',
    'core.api.error.missing_module'  => 'No module was specified.',
    'core.api.error.missing_service' => 'No service was specified.',
    'core.api.error.missing_action'  => 'No action was specified.',

    // --- Forms: reCAPTCHA validation ---
    'core.form.recaptcha.missing'    => 'Please confirm you are not a robot.',
    'core.form.recaptcha.failed'     => "reCAPTCHA verification failed. Please try again.",
    'core.form.recaptcha.error'      => "Couldn't verify reCAPTCHA right now. Please try again.",

    // --- Two-factor auth (TOTP) ---
    'core.auth.twofa.enabled'        => 'Two-factor authentication is now on.',
    'core.auth.twofa.disabled'       => 'Two-factor authentication has been turned off.',
    'core.auth.twofa.bad_code'       => 'That code is incorrect or has expired.',
    'core.auth.twofa.unavailable'    => 'Two-factor authentication is not available on this install.',

    // --- Form validation (field-level; localized by Tiger_Service_Service::_formErrors) ---
    'core.form.password_mismatch'    => 'Passwords do not match.',

    // --- Password policy (Tiger_Policy_Password violation keys) ---
    'password.too_short'             => 'Password is too short — please use at least 8 characters.',
    'password.needs_complexity'      => 'Add upper- and lower-case letters, a number, and a symbol.',
    'password.reused'                => "You've used this password before — please choose a new one.",

    // --- Common UI labels (shared across views) ---
    'core.common.close'              => 'Close',
    'core.common.done'               => 'Done',
    'core.common.back_home'          => 'Back to home',

    // --- Error pages (403 / 404 / 500) ---
    'core.error.badge'               => 'Error',
    'core.error.403.title'           => "You don't have access to that.",
    'core.error.404.title'           => "That page doesn't exist.",
    'core.error.500.title'           => 'Something went wrong.',
    'core.error.403.sub'             => "You're signed in, but this area isn't available to your account.",
    'core.error.404.sub'             => "The page may have moved, or never existed. Let's get you back on track.",
    'core.error.500.sub'             => "Something broke on our end. We've been notified and are looking into it — please try again shortly.",
    'core.error.switch_account'      => 'Switch account',

    // --- Auth: shared labels ---
    'core.auth.email'                => 'Email',
    'core.auth.password'             => 'Password',
    'core.auth.email_code'           => 'Email me a code',
    'core.auth.back_to_login'        => 'Back to sign in',
    'core.auth.return_to'            => 'Return to %s',

    // --- Auth: sign in ---
    'core.auth.login.title'          => 'Sign in to Tiger',
    'core.auth.login.subtitle'       => 'Welcome back.',
    'core.auth.login.identifier'     => 'Email or username',
    'core.auth.login.forgot'         => 'Forgot your password?',
    'core.auth.login.submit'         => 'Sign in',
    'core.auth.login.use_code'       => 'Sign in with a code instead',

    // --- Auth: two-factor prompt (sign-in step) ---
    'core.auth.twofa.prompt'         => 'Enter the 6-digit code from your authenticator app.',
    'core.auth.twofa.code_label'     => 'Verification code',
    'core.auth.twofa.verify'         => 'Verify',
    'core.auth.twofa.use_recovery'   => 'Use a recovery code',

    // --- Auth: lock screen ---
    'core.auth.lock.title'           => 'Screen locked',
    'core.auth.lock.subtitle'        => 'Re-verify to continue.',
    'core.auth.lock.unlock'          => 'Unlock',
    'core.auth.lock.use_code'        => 'Unlock with a code instead',
    'core.auth.lock.email_send_to'   => "We'll email a one-time code to",
    'core.auth.lock.use_password'    => 'Use password instead',
    'core.auth.lock.not_you'         => 'Not %s? Sign out',

    // --- Auth: reset password ---
    'core.auth.reset.title'          => 'Set a new password',
    'core.auth.reset.subtitle'       => "Choose a strong password you don't use elsewhere.",
    'core.auth.reset.new_password'   => 'New password',
    'core.auth.reset.confirm_password' => 'Confirm password',
    'core.auth.reset.submit'         => 'Set new password',

    // --- Auth: forgot password ---
    'core.auth.forgot.title'         => 'Reset your password',
    'core.auth.forgot.subtitle'      => "We'll email you a link to choose a new one.",
    'core.auth.forgot.submit'        => 'Send reset link',

    // --- Auth: logged out ---
    'core.auth.logout.title'         => 'You have been logged out.',
    'core.auth.logout.subtitle'      => 'Thanks for stopping by.',
    'core.auth.logout.login_again'   => 'Login again',

    // --- Auth: passwordless code sign-in (OTP) ---
    'core.auth.otp.title'            => 'Sign in with a code',
    'core.auth.otp.subtitle'         => "We'll email you a one-time code — no password needed.",
    'core.auth.otp.restart'          => 'Use a different email',
    'core.auth.otp.use_password'     => 'Sign in with a password instead',

    // --- Auth: two-factor management (security screen) ---
    'core.auth.twofa.heading'        => 'Two-Factor Authentication',
    'core.auth.twofa.lead'           => 'Add a one-time code from an authenticator app to your sign-in.',
    'core.auth.twofa.unavailable_detail' => "Two-factor authentication isn't available on this install yet — the app encryption key (%s) is not configured. Ask an administrator to set it.",
    'core.auth.twofa.enabled_badge'  => 'Enabled',
    'core.auth.twofa.protected'      => 'Your authenticator app is protecting this account.',
    'core.auth.twofa.recovery_remaining' => 'Recovery codes remaining:',
    'core.auth.twofa.recovery_help'  => 'Recovery codes let you sign in if you lose your device. Re-enable to generate a fresh set.',
    'core.auth.twofa.disable_prompt' => 'To turn off two-factor auth, confirm with a current code from your app (or a recovery code):',
    'core.auth.twofa.disable_btn'    => 'Disable 2FA',
    'core.auth.twofa.intro'          => 'Protect your account with a time-based code from an app like Google Authenticator, 1Password, Authy, or Microsoft Authenticator.',
    'core.auth.twofa.enable_btn'     => 'Enable two-factor authentication',
    'core.auth.twofa.step_scan'      => 'Scan the QR code',
    'core.auth.twofa.step_scan_detail' => 'with your authenticator app — or enter the key by hand.',
    'core.auth.twofa.qr_preview'     => 'QR preview',
    'core.auth.twofa.setup_key_label' => 'Setup key (manual entry)',
    'core.auth.twofa.open_in_app'    => 'Open in app',
    'core.auth.twofa.step_recovery'  => 'Save your recovery codes.',
    'core.auth.twofa.step_recovery_detail' => 'Each can be used once if you lose your device. Store them somewhere safe.',
    'core.auth.twofa.copy_codes'     => 'Copy codes',
    'core.auth.twofa.step_confirm'   => 'Confirm.',
    'core.auth.twofa.step_confirm_detail' => 'Enter the 6-digit code your app shows now:',
    'core.auth.twofa.verify_turn_on' => 'Verify & turn on',
    'core.auth.twofa.back_to_admin'  => 'Back to admin',

    // --- Dashboard (admin home) ---
    'core.dashboard.title'           => 'Dashboard',
    'core.dashboard.lead'            => 'Welcome to the Tiger admin.',
    'core.dashboard.customize'       => 'Customize',
    'core.dashboard.empty_title'     => 'No dashboard widgets yet',
    'core.dashboard.empty_lead'      => "Modules that provide a dashboard widget will appear here automatically once they're active.",
    'core.dashboard.drag_hint'       => 'Drag to rearrange',
    'core.dashboard.collapse_aria'   => 'Collapse widget',
    'core.dashboard.customize_title' => 'Customize dashboard',
    'core.dashboard.customize_help'  => "Turn widgets on or off. A hidden widget isn't rendered — switch it back on anytime.",

    // --- Account home ---
    'core.account.title'             => 'My Account',
    'core.account.lead'              => 'Your subscription, licenses, and profile.',
    'core.account.empty_lead'        => 'Your account details will appear here as you add subscriptions and services.',
];
