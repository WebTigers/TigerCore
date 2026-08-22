<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Register module — German strings. Owner-prefixed keys (register.*). */
return [
    // /api response messages
    'register.registered'      => 'Ihre Website ist registriert — prüfen Sie Ihre E-Mails, um die Verifizierung abzuschließen.',
    'register.domain_verified' => 'Ihre Domain ist verifiziert.',
    'register.domain_pending'  => 'Ihre Domain ist noch nicht verifiziert — wir versuchen es weiter; Sie können es auch jetzt erneut versuchen.',
    'register.email_sent'      => 'Verifizierungs-E-Mail gesendet.',
    'register.error.no_domain'            => 'Die Domain dieser Website konnte nicht ermittelt werden.',
    'register.error.registry_unreachable' => 'Das Tiger-Netzwerk ist derzeit nicht erreichbar — bitte versuchen Sie es in Kürze erneut.',
    'register.error.not_registered'       => 'Diese Website ist nicht registriert.',

    // Settings → Registration screen
    'register.title'        => 'Registrierung',
    'register.subtitle'     => 'Optional. Registrieren Sie diese Website für eine verifizierte Site-ID und um dem Tiger-Netzwerk beizutreten — es aktiviert oder deaktiviert nichts.',
    'register.status'       => 'Status',
    'register.verified'     => 'Verifiziert',
    'register.not_registered' => 'Nicht registriert',
    'register.field.domain' => 'Domain',
    'register.field.email'  => 'E-Mail',
    'register.field.tsid'   => 'Site-ID (TSID)',
    'register.intro_body'   => 'Die Registrierung verifiziert Ihre <strong>Domain</strong> (automatisch bereitgestellt — nichts hochzuladen) und Ihre <strong>E-Mail</strong>. Wir teilen ausschließlich Ihre Domain, diese E-Mail und Ihre Tiger-/PHP-Versionen. Nicht gewünscht? Lassen Sie es — oder schalten Sie das Registrierungs-Widget aus / deaktivieren Sie dieses Modul.',
    'register.admin_email'  => 'Admin-E-Mail',
    'register.register_btn' => 'Registrieren',
    'register.badge.domain' => 'Domain',
    'register.badge.email'  => 'E-Mail',
    'register.state.verified' => 'verifiziert',
    'register.state.pending'  => 'ausstehend',
    'register.verify_domain'  => 'Domain verifizieren',
    'register.resend_email'   => 'Verifizierungs-E-Mail erneut senden',
    'register.net_error'      => 'Netzwerkfehler — bitte versuchen Sie es erneut.',

    // Public email-verify landing
    'register.verify.ok_title'   => 'Ihre Website ist verifiziert',
    'register.verify.ok_body'    => 'Danke — Ihre E-Mail ist bestätigt.',
    'register.verify.ok_cta'     => 'Zum Dashboard',
    'register.verify.fail_title' => 'Dieser Link hat nicht funktioniert',
    'register.verify.fail_body'  => 'Er ist möglicherweise abgelaufen oder wurde bereits verwendet. Senden Sie ihn über das Registrierungs-Widget oder die Einstellungen erneut.',
    'register.verify.fail_cta'   => 'Zur Registrierung',
    'register.nav.label' => 'Registrierung',
    'register.widget.title' => 'Registrierung',
    'register.widget.registered' => 'Ihre Website ist registriert',
    'register.widget.site_id' => 'Site-ID',
    'register.widget.intro' => 'Registrieren Sie diese Website für eine verifizierte Site-ID und um dem Tiger-Netzwerk beizutreten — optional, und es aktiviert oder deaktiviert nichts. Wir teilen ausschließlich Ihre Domain, diese E-Mail und Ihre Tiger-/PHP-Versionen.',
    'register.widget.register' => 'Registrieren',
    'register.widget.confirming' => 'Wir bestätigen, dass Sie %s kontrollieren.',
    'register.widget.last_step' => 'Letzter Schritt: Klicken Sie auf den Link, den wir an %s gesendet haben.',
    'register.widget.resend' => 'E-Mail erneut senden',
];
