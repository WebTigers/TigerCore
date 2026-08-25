<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerCore — Deutsch (de) core strings. Semantische Schlüssel mit Präfix (core.*).
 */
return [
    // --- Antworten der /api-Dienste (Standardwerte) ---
    'core.api.success'               => 'Fertig.',
    'core.api.error.general'         => 'Etwas ist schiefgelaufen. Bitte versuchen Sie es erneut.',
    'core.api.error.form'            => 'Bitte korrigieren Sie die markierten Felder.',
    'core.api.error.csrf'            => 'Hoppla — Ihr Sicherheitstoken ist abgelaufen. Bitte laden Sie die Seite neu, um fortzufahren. (Sie laufen absichtlich ab; schieben Sie es auf die Sicherheitsgremlins.)',
    'core.api.error.invalid_action'  => 'Diese Aktion ist nicht verfügbar.',
    'core.api.error.not_allowed'     => 'Sie haben keine Berechtigung dafür.',
    'core.api.error.login_required'  => 'Bitte melden Sie sich an, um fortzufahren.',
    'core.token.created'          => 'Token erstellt — kopieren Sie es jetzt; es wird nicht erneut angezeigt.',
    'core.token.revoked'          => 'Token widerrufen.',
    'core.api.error.login_failed'    => 'Ungültige E-Mail oder ungültiges Passwort.',
    'core.api.error.missing_module'  => 'Es wurde kein Modul angegeben.',
    'core.api.error.missing_service' => 'Es wurde kein Dienst angegeben.',
    'core.api.error.missing_action'  => 'Es wurde keine Aktion angegeben.',

    // --- Formulare: reCAPTCHA-Validierung ---
    'core.form.recaptcha.missing'    => 'Bitte bestätigen Sie, dass Sie kein Roboter sind.',
    'core.form.recaptcha.failed'     => 'Die reCAPTCHA-Überprüfung ist fehlgeschlagen. Bitte versuchen Sie es erneut.',
    'core.form.recaptcha.error'      => 'reCAPTCHA konnte im Moment nicht überprüft werden. Bitte versuchen Sie es erneut.',

    // --- Zwei-Faktor-Authentifizierung (TOTP) ---
    'core.auth.twofa.enabled'        => 'Die Zwei-Faktor-Authentifizierung ist jetzt aktiviert.',
    'core.auth.twofa.disabled'       => 'Die Zwei-Faktor-Authentifizierung wurde deaktiviert.',
    'core.auth.twofa.bad_code'       => 'Dieser Code ist falsch oder abgelaufen.',
    'core.auth.twofa.unavailable'    => 'Die Zwei-Faktor-Authentifizierung ist auf dieser Installation nicht verfügbar.',

    // --- Formularvalidierung (auf Feldebene) ---
    'core.form.password_mismatch'    => 'Die Passwörter stimmen nicht überein.',

    // --- Passwortrichtlinie (Schlüssel von Tiger_Policy_Password) ---
    'password.too_short'             => 'Das Passwort ist zu kurz — bitte verwenden Sie mindestens 8 Zeichen.',
    'password.needs_complexity'      => 'Fügen Sie Groß- und Kleinbuchstaben, eine Zahl und ein Symbol hinzu.',
    'password.reused'                => 'Sie haben dieses Passwort bereits verwendet — bitte wählen Sie ein neues.',

    // --- Gemeinsame UI-Beschriftungen ---
    'core.common.close'              => 'Schließen',
    'core.common.done'               => 'Fertig',
    'core.common.back_home'          => 'Zurück zur Startseite',

    // --- Fehlerseiten (403 / 404 / 500) ---
    'core.error.badge'               => 'Fehler',
    'core.error.403.title'           => 'Sie haben keinen Zugriff darauf.',
    'core.error.404.title'           => 'Diese Seite existiert nicht.',
    'core.error.500.title'           => 'Etwas ist schiefgelaufen.',
    'core.error.403.sub'             => 'Sie sind angemeldet, aber dieser Bereich ist für Ihr Konto nicht verfügbar.',
    'core.error.404.sub'             => 'Die Seite wurde möglicherweise verschoben oder hat nie existiert. Bringen wir Sie wieder auf den richtigen Weg.',
    'core.error.500.sub'             => 'Auf unserer Seite ist etwas kaputtgegangen. Wir wurden benachrichtigt und kümmern uns darum — bitte versuchen Sie es in Kürze erneut.',
    'core.error.switch_account'      => 'Konto wechseln',

    // --- Authentifizierung: gemeinsame Beschriftungen ---
    'core.auth.email'                => 'E-Mail',
    'core.auth.password'             => 'Passwort',
    'core.auth.email_code'           => 'Mir einen Code per E-Mail senden',
    'core.auth.back_to_login'        => 'Zurück zur Anmeldung',
    'core.auth.return_to'            => 'Zurück zu %s',

    // --- Authentifizierung: anmelden ---
    'core.auth.login.title'          => 'Bei Tiger anmelden',
    'core.auth.login.subtitle'       => 'Willkommen zurück.',
    'core.auth.login.identifier'     => 'E-Mail oder Benutzername',
    'core.auth.login.forgot'         => 'Passwort vergessen?',
    'core.auth.login.submit'         => 'Anmelden',
    'core.auth.login.use_code'       => 'Stattdessen mit einem Code anmelden',

    // --- Authentifizierung: Zwei-Faktor-Abfrage (Anmeldeschritt) ---
    'core.auth.twofa.prompt'         => 'Geben Sie den 6-stelligen Code aus Ihrer Authentifizierungs-App ein.',
    'core.auth.twofa.code_label'     => 'Bestätigungscode',
    'core.auth.twofa.verify'         => 'Überprüfen',
    'core.auth.twofa.use_recovery'   => 'Einen Wiederherstellungscode verwenden',

    // --- Authentifizierung: Sperrbildschirm ---
    'core.auth.lock.title'           => 'Bildschirm gesperrt',
    'core.auth.lock.subtitle'        => 'Bestätigen Sie sich erneut, um fortzufahren.',
    'core.auth.lock.unlock'          => 'Entsperren',
    'core.auth.lock.use_code'        => 'Mit einem Code entsperren',
    'core.auth.lock.email_send_to'   => 'Wir senden einen Einmalcode an',
    'core.auth.lock.use_password'    => 'Stattdessen das Passwort verwenden',
    'core.auth.lock.not_you'         => 'Nicht %s? Abmelden',

    // --- Authentifizierung: Passwort zurücksetzen ---
    'core.auth.reset.title'          => 'Ein neues Passwort festlegen',
    'core.auth.reset.subtitle'       => 'Wählen Sie ein sicheres Passwort, das Sie nirgendwo anders verwenden.',
    'core.auth.reset.new_password'   => 'Neues Passwort',
    'core.auth.reset.confirm_password' => 'Passwort bestätigen',
    'core.auth.reset.submit'         => 'Neues Passwort festlegen',

    // --- Authentifizierung: Passwort vergessen ---
    'core.auth.forgot.title'         => 'Passwort zurücksetzen',
    'core.auth.forgot.subtitle'      => 'Wir senden Ihnen per E-Mail einen Link, um ein neues zu wählen.',
    'core.auth.forgot.submit'        => 'Link zum Zurücksetzen senden',

    // --- Authentifizierung: abgemeldet ---
    'core.auth.logout.title'         => 'Sie wurden abgemeldet.',
    'core.auth.logout.subtitle'      => 'Danke für Ihren Besuch.',
    'core.auth.logout.login_again'   => 'Erneut anmelden',

    // --- Authentifizierung: Anmeldung mit Code (ohne Passwort) ---
    'core.auth.otp.title'            => 'Mit einem Code anmelden',
    'core.auth.otp.subtitle'         => 'Wir senden Ihnen per E-Mail einen Einmalcode — kein Passwort nötig.',
    'core.auth.otp.restart'          => 'Eine andere E-Mail verwenden',
    'core.auth.otp.use_password'     => 'Stattdessen mit einem Passwort anmelden',

    // --- Authentifizierung: Zwei-Faktor-Verwaltung (Sicherheitsbildschirm) ---
    'core.auth.twofa.heading'        => 'Zwei-Faktor-Authentifizierung',
    'core.auth.twofa.lead'           => 'Fügen Sie Ihrer Anmeldung einen Einmalcode aus einer Authentifizierungs-App hinzu.',
    'core.auth.twofa.unavailable_detail' => 'Die Zwei-Faktor-Authentifizierung ist auf dieser Installation noch nicht verfügbar — der App-Verschlüsselungsschlüssel (%s) ist nicht konfiguriert. Bitten Sie einen Administrator, ihn einzurichten.',
    'core.auth.twofa.enabled_badge'  => 'Aktiviert',
    'core.auth.twofa.protected'      => 'Ihre Authentifizierungs-App schützt dieses Konto.',
    'core.auth.twofa.recovery_remaining' => 'Verbleibende Wiederherstellungscodes:',
    'core.auth.twofa.recovery_help'  => 'Mit Wiederherstellungscodes können Sie sich anmelden, wenn Sie Ihr Gerät verlieren. Aktivieren Sie sie erneut, um einen neuen Satz zu erzeugen.',
    'core.auth.twofa.disable_prompt' => 'Um die Zwei-Faktor-Authentifizierung zu deaktivieren, bestätigen Sie mit einem aktuellen Code aus Ihrer App (oder einem Wiederherstellungscode):',
    'core.auth.twofa.disable_btn'    => '2FA deaktivieren',
    'core.auth.twofa.intro'          => 'Schützen Sie Ihr Konto mit einem zeitbasierten Code aus einer App wie Google Authenticator, 1Password, Authy oder Microsoft Authenticator.',
    'core.auth.twofa.enable_btn'     => 'Zwei-Faktor-Authentifizierung aktivieren',
    'core.auth.twofa.step_scan'      => 'Scannen Sie den QR-Code',
    'core.auth.twofa.step_scan_detail' => 'mit Ihrer Authentifizierungs-App — oder geben Sie den Schlüssel von Hand ein.',
    'core.auth.twofa.qr_preview'     => 'QR-Vorschau',
    'core.auth.twofa.setup_key_label' => 'Einrichtungsschlüssel (manuelle Eingabe)',
    'core.auth.twofa.open_in_app'    => 'In App öffnen',
    'core.auth.twofa.step_recovery'  => 'Speichern Sie Ihre Wiederherstellungscodes.',
    'core.auth.twofa.step_recovery_detail' => 'Jeder kann einmal verwendet werden, falls Sie Ihr Gerät verlieren. Bewahren Sie sie an einem sicheren Ort auf.',
    'core.auth.twofa.copy_codes'     => 'Codes kopieren',
    'core.auth.twofa.step_confirm'   => 'Bestätigen.',
    'core.auth.twofa.step_confirm_detail' => 'Geben Sie den 6-stelligen Code ein, den Ihre App jetzt anzeigt:',
    'core.auth.twofa.verify_turn_on' => 'Überprüfen & aktivieren',
    'core.auth.twofa.back_to_admin'  => 'Zurück zur Verwaltung',

    // --- Dashboard (Verwaltungsstartseite) ---
    'core.dashboard.title'           => 'Dashboard',
    'core.dashboard.lead'            => 'Willkommen in der Tiger-Verwaltung.',
    'core.dashboard.customize'       => 'Anpassen',
    'core.dashboard.empty_title'     => 'Noch keine Dashboard-Widgets',
    'core.dashboard.empty_lead'      => 'Module, die ein Dashboard-Widget bereitstellen, erscheinen hier automatisch, sobald sie aktiv sind.',
    'core.dashboard.drag_hint'       => 'Zum Umsortieren ziehen',
    'core.dashboard.collapse_aria'   => 'Widget einklappen',
    'core.dashboard.customize_title' => 'Dashboard anpassen',
    'core.dashboard.customize_help'  => 'Widgets ein- oder ausschalten. Ein ausgeblendetes Widget wird nicht angezeigt — schalten Sie es jederzeit wieder ein.',

    // --- Kontostartseite ---
    'core.account.title'             => 'Mein Konto',
    'core.account.lead'              => 'Ihr Abonnement, Ihre Lizenzen und Ihr Profil.',
    'core.account.empty_lead'        => 'Ihre Kontodetails erscheinen hier, sobald Sie Abonnements und Dienste hinzufügen.',
    'core.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'core.js.recaptcha' => 'Bitte schließen Sie das reCAPTCHA ab und versuchen Sie es erneut.',
    'core.js.incorrect_password' => 'Falsches Passwort.',
    'core.js.code_sent' => 'Wir haben einen 6-stelligen Code an %s gesendet. Geben Sie ihn unten ein.',
    'core.js.code_invalid' => 'Dieser Code ist ungültig oder abgelaufen.',
    'core.js.code_incorrect' => 'Dieser Code ist falsch oder abgelaufen.',
    'core.js.invalid_login' => 'Ungültiger Benutzername oder ungültiges Passwort.',
    'core.js.passwords_mismatch' => 'Die Passwörter stimmen nicht überein.',
    'core.js.reset_failed' => 'Ihr Passwort konnte nicht zurückgesetzt werden — der Link ist möglicherweise abgelaufen.',
    'core.js.twofa_disabled' => 'Zwei-Faktor-Authentifizierung deaktiviert.',
    'core.js.twofa_code_wrong_on' => 'Dieser Code ist falsch. Die Zwei-Faktor-Authentifizierung ist weiterhin aktiviert.',
    'core.js.setup_failed' => 'Die Einrichtung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
    'core.js.twofa_on' => 'Die Zwei-Faktor-Authentifizierung ist aktiviert. 🎉',
    'core.js.twofa_code_wrong' => 'Dieser Code stimmt nicht überein. Prüfen Sie die Uhr Ihrer App und versuchen Sie den aktuellen Code.',
    'core.js.widget_load_error' => 'Dieses Widget konnte nicht geladen werden.',
    'core.nav.dashboard' => 'Dashboard',
    'core.nav.account' => 'Mein Konto',
    'core.nav.content' => 'Inhalt',
    'core.nav.articles' => 'Artikel',
    'core.nav.menus' => 'Menüs',
    'core.nav.media' => 'Medien',
    'core.nav.users' => 'Benutzer',
    'core.nav.orgs' => 'Organisationen',
    'core.nav.code' => 'Code',
    'core.nav.modules' => 'Module',
    'core.nav.settings' => 'Einstellungen',
    'core.datatable.info' => '_START_ bis _END_ von _TOTAL_ Einträgen',
    'core.datatable.info_empty' => '0 bis 0 von 0 Einträgen',
    'core.datatable.info_filtered' => '(gefiltert aus _MAX_ Einträgen insgesamt)',
    'core.datatable.length_menu' => '_MENU_ pro Seite',
    'core.datatable.search_placeholder' => 'Suchen…',
    'core.datatable.zero_records' => 'Keine passenden Einträge gefunden',
    'core.datatable.empty_table' => 'Keine Daten verfügbar',
    'core.datatable.loading' => 'Wird geladen…',
    'core.datatable.processing' => 'Wird verarbeitet…',
    'core.datatable.paginate_first' => 'Erste',
    'core.datatable.paginate_last' => 'Letzte',
    'core.datatable.paginate_next' => 'Weiter',
    'core.datatable.paginate_prev' => 'Zurück',
    'core.nav.modules_manage' => 'Verwalten',

    // Media picker field (Tiger_View_Helper_MediaField) — shared by the Identity, CMS and SEO screens.
    'core.media.field.choose'          => 'Medium auswählen',
    'core.media.field.clear'           => 'Entfernen',
    'core.media.field.preview_alt'     => 'Vorschau des ausgewählten Mediums',
    'core.media.field.file'            => 'Ausgewählte Datei',

    // --- Mail providers (Tiger_Mail_Provider) ---
    'core.mail.provider.help.ses_smtp'         => 'SES-SMTP-Zugangsdaten werden in der SES-Konsole erzeugt — sie sind NICHT Ihre AWS-Zugriffsschlüssel. Tragen Sie sie unten als Benutzername und Passwort ein.',
    'core.mail.provider.help.ses_api'          => 'Versendet über die SES-v2-API mit dem mitgelieferten AWS-SDK.',
    'core.mail.provider.help.ses_api_iam'      => 'Lassen Sie Schlüssel und Secret leer, um die IAM-Rolle der Instanz zu verwenden — dann wird gar kein Zugangsdatum gespeichert.',
    'core.mail.provider.help.sendgrid_smtp'    => 'Verwenden Sie den wörtlichen Benutzernamen „apikey“ und Ihren API-Schlüssel als Passwort.',
    'core.mail.provider.help.postmark_smtp'    => 'Verwenden Sie Ihr Server-API-Token SOWOHL als Benutzernamen ALS AUCH als Passwort.',
    'core.mail.provider.help.resend_smtp'      => 'Verwenden Sie den wörtlichen Benutzernamen „resend“ und Ihren API-Schlüssel als Passwort.',
    'core.mail.provider.help.mailgun_region'   => 'Mailgun betreibt getrennte US- und EU-Regionen; ein Schlüssel funktioniert nur in der Region, in der er erstellt wurde.',
    'core.mail.provider.help.google_smtp'      => 'Erfordert ein App-Passwort bei aktivierter Bestätigung in zwei Schritten — das normale Kontopasswort funktioniert nicht.',
    'core.mail.provider.help.microsoft_smtp'   => 'Microsoft deaktiviert SMTP AUTH standardmäßig und stellt die Basisauthentifizierung ein; möglicherweise müssen Sie sie für dieses Postfach aktivieren.',
    'core.mail.provider.requires.aws_sdk'      => 'Dieser Treiber benötigt das AWS-SDK-Modul (tiger-sdk-aws). Installieren und aktivieren Sie es, oder verwenden Sie Amazon SES (SMTP).',
    'core.mail.provider.requires.generic'      => 'Der Treiber dieses Anbieters ist auf dieser Installation nicht verfügbar.',
];
