<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup module — German strings (signup.*).
 */
return [
    // Service / API messages
    'signup.disabled'        => 'Die öffentliche Registrierung ist derzeit deaktiviert.',
    'signup.error.recaptcha' => 'Wir konnten nicht bestätigen, dass Sie ein Mensch sind — bitte versuchen Sie es erneut.',
    'signup.check_email'     => 'Konto erstellt — prüfen Sie Ihre E-Mails zur Verifizierung und melden Sie sich dann an.',
    'signup.verified'        => 'Ihre E-Mail ist verifiziert und Ihr Konto ist aktiv.',
    'signup.invalid_link'    => 'Dieser Verifizierungslink ist ungültig oder abgelaufen.',

    // Signup form view
    'signup.form.heading'          => 'Konto erstellen',
    'signup.form.subheading'       => 'Starten Sie Ihren Tiger-Arbeitsbereich — es dauert nur eine Minute.',
    'signup.form.label.first_name' => 'Vorname',
    'signup.form.label.last_name'  => 'Nachname',
    'signup.form.label.company'    => 'Unternehmen',
    'signup.form.label.username'   => 'Benutzername',
    'signup.form.label.password'   => 'Passwort',
    'signup.form.aria.show_password' => 'Passwort anzeigen',
    'signup.form.label.email'      => 'E-Mail',
    'signup.form.label.street'     => 'Straße',
    'signup.form.label.city'       => 'Stadt',
    'signup.form.label.region'     => 'Bundesland / Provinz',
    'signup.form.label.postal'     => 'Postleitzahl',
    'signup.form.label.country'    => 'Land',
    'signup.form.option.select'    => '— Auswählen —',
    'signup.form.group.frequent'   => 'Häufig',
    'signup.form.group.all'        => 'Alle Länder',
    'signup.form.label.phone_type' => 'Telefontyp',
    'signup.form.label.phone'      => 'Telefon',
    'signup.form.submit'           => 'Konto erstellen',
    'signup.form.have_account'     => 'Sie haben bereits ein Konto? Anmelden',

    // Email-verification result view
    'signup.verify.heading'        => 'E-Mail-Verifizierung',
    'signup.verify.success.body'   => 'Ihre E-Mail ist verifiziert und Ihr Konto ist aktiv. Sie können sich jetzt anmelden.',
    'signup.verify.action.signin'  => 'Anmelden',
    'signup.verify.invalid.body'   => 'Dieser Verifizierungslink ist ungültig oder abgelaufen. Sie können sich erneut registrieren oder den Support kontaktieren, falls Sie bereits registriert sind.',
    'signup.verify.action.back'    => 'Zurück zur Registrierung',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'signup.js.verify_sent'   => '<strong>Fast geschafft.</strong> Wir haben Ihnen einen Verifizierungslink zur Aktivierung Ihres Kontos gesendet — klicken Sie darauf und melden Sie sich dann an.',
    'signup.js.fix_fields'    => 'Bitte korrigieren Sie die markierten Felder.',
    'signup.js.check_field'   => 'Bitte überprüfen Sie dieses Feld.',
    'signup.js.went_wrong'    => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
    'signup.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
];
