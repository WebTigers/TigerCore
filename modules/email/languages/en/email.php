<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email Templates module — English strings (TIGER-267). Owner-prefixed `email.*` keys; the module owns
 * its own i18n and ships its translations (other locales mirror this file).
 */
return [
    // Navigation / Settings tree
    'email.nav.settings' => 'Email Templates',

    // List screen
    'email.list.heading'  => 'Email Templates',
    'email.list.subtitle' => 'Customize the transactional emails Tiger sends. An edited template overrides the branded default — no deploy.',
    'email.col.template'  => 'Email',
    'email.col.key'       => 'Key',
    'email.col.group'     => 'Group',
    'email.col.status'    => 'Status',
    'email.col.actions'   => 'Actions',
    'email.status.customized' => 'Customized',
    'email.status.default'    => 'Default',
    'email.list.confirm_reset_title' => 'Reset to default?',
    'email.list.confirm_reset_body'  => 'This removes your customized version and restores the shipped default. This cannot be undone.',

    // Editor screen
    'email.edit.card_content'    => 'Content',
    'email.edit.label_subject'   => 'Subject',
    'email.edit.label_body'      => 'HTML body',
    'email.edit.help_body'       => 'HTML is wrapped in the branded email layout. Use {{variable}} placeholders — insert them from the panel on the right.',
    'email.edit.card_variables'  => 'Variables',
    'email.edit.variables_help'  => 'Click to insert a placeholder at the cursor. It is replaced with the real value when the email is sent.',
    'email.edit.card_preview'    => 'Preview',
    'email.edit.preview_subject' => 'Subject:',

    // Actions
    'email.action.save'    => 'Save',
    'email.action.reset'   => 'Reset to default',
    'email.action.edit'    => 'Edit',
    'email.action.back'    => 'Back',
    'email.action.refresh' => 'Refresh',

    // Service results
    'email.saved' => 'Template saved.',
    'email.reset' => 'Template reset to its default.',
    'email.error.unknown_template' => 'That email template does not exist.',

    // JS feedback
    'email.js.network_error' => 'Network error — please try again.',
    'email.js.fix_fields'    => 'Please correct the highlighted fields.',

    // Shipped template events (labels + descriptions)
    'email.tpl.reset.label'           => 'Password reset',
    'email.tpl.reset.desc'            => 'Sent when someone requests a password reset link.',
    'email.tpl.otp.label'             => 'Sign-in code',
    'email.tpl.otp.desc'              => 'The one-time code for passwordless sign-in.',
    'email.tpl.verify.label'          => 'Email verification',
    'email.tpl.verify.desc'           => 'Confirms a user\'s email address.',
    'email.tpl.register_verify.label' => 'Signup verification',
    'email.tpl.register_verify.desc'  => 'Verifies the email address given at signup.',
    'email.tpl.backup.label'          => 'Backup report',
    'email.tpl.backup.desc'           => 'The result of a scheduled or manual backup.',

    // Template variable descriptions
    'email.var.reset_url'   => 'The password-reset link.',
    'email.var.verify_url'  => 'The verification link.',
    'email.var.code'        => 'The one-time sign-in code.',
    'email.var.domain'      => 'The site domain being verified.',
    'email.var.filename'    => 'The backup archive filename.',
    'email.var.size'        => 'The backup archive size.',
    'email.var.components'  => 'What the backup included.',
    'email.var.error'       => 'The failure reason (on a failed backup).',
    'email.var.site_name'   => 'The site name.',
    'email.var.site_url'    => 'The site URL.',
];
