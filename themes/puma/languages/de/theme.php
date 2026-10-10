<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PUMA theme — public chrome strings (German).
 */
return [
    'theme.nav.docs'      => 'Docs',
    'theme.nav.github'    => 'GitHub',

    'theme.search.placeholder' => 'Suchen…',
    'theme.auth.dashboard'     => 'Dashboard',
    'theme.auth.account'       => 'Mein Konto',
    'theme.auth.signup'        => 'Registrieren',
    'theme.auth.signin'        => 'Anmelden',


    'theme.footer.tagline' => 'die KI-native SaaS-Plattform. Besitze das Geschäft, das deine KI aufbaut.',
    'theme.footer.privacy' => 'Datenschutz',
    'theme.footer.terms'   => 'Nutzungsbedingungen',
    'theme.footer.github'  => 'GitHub',
    'theme.footer.home'    => 'Startseite',

    // ------------------------------------------------------------------
    // Admin-Oberfläche (gemeinsame Shell) — Siezen, wie im Backend üblich
    // ------------------------------------------------------------------

    'theme.a11y.skip_to_content' => 'Zum Inhalt springen',
    'theme.a11y.toggle_nav'      => 'Navigation ein- oder ausblenden',
    'theme.a11y.main_nav'        => 'Hauptnavigation',
    'theme.a11y.sidebar_nav'     => 'Seitenleistennavigation',
    'theme.a11y.primary_nav'     => 'Hauptnavigation',

    'theme.action.save' => 'Speichern',
    'theme.action.copy' => 'Kopieren',

    'theme.admin.title'         => 'Tiger-Verwaltung',
    'theme.admin.site'          => 'Website',
    'theme.a11y.view_site'      => 'Website ansehen (öffnet in einem neuen Tab)',
    'theme.admin.navigation'    => 'Navigation',
    'theme.admin.guest'         => 'Gast',
    'theme.admin.user_fallback' => 'Benutzer',
    'theme.search.label'        => 'Suchen',

    'theme.admin.alerts'                    => 'Meldungen',
    'theme.admin.alert.user_locked'         => 'Benutzer gesperrt',
    'theme.admin.alert.connection_restored' => 'Verbindung wiederhergestellt',
    'theme.admin.time.minutes_ago'          => 'vor %d Minuten',
    'theme.admin.time.hour_ago'             => 'vor %d Stunde',
    'theme.admin.view_all'                  => 'Alle ansehen',

    'theme.admin.menu.profile'     => 'Mein Profil',
    'theme.admin.menu.two_factor'  => 'Zwei-Faktor-Authentifizierung',
    'theme.admin.menu.lock_screen' => 'Bildschirm sperren',
    'theme.admin.menu.sign_out'    => 'Abmelden',

    'theme.aside.label'       => 'Assistenzbereich',
    'theme.aside.activity'    => 'Aktivität',
    'theme.aside.placeholder' => 'Diese optionale Leiste erscheint, sobald eine View %s setzt. Platzieren Sie hier kontextbezogene Widgets: letzte Aktivitäten, Inline-Hilfe, Filter.',

    'theme.agent.label'             => 'KI-Agent',
    'theme.agent.title'             => 'Agent',
    'theme.agent.model'             => 'Modell',
    'theme.agent.new_chat'          => 'Neuer Chat',
    'theme.agent.drop_files'        => 'Dateien hier ablegen, um sie anzuhängen',
    'theme.agent.empty'             => 'Beginnen Sie ein Gespräch — der Agent handelt mit Ihren Berechtigungen.',
    'theme.agent.input_placeholder' => 'Bitten Sie den Agenten, etwas zu bauen, zu ändern oder zu erklären…',
    'theme.agent.attach'            => 'Datei anhängen',
    'theme.agent.mode_title'        => 'Automatisierungsgrad',
    'theme.agent.send'              => 'Senden',
    'theme.agent.open'              => 'KI-Agenten öffnen',

    'theme.switcher.language'    => 'Sprache',
    'theme.switcher.theme'       => 'Erscheinungsbild',
    'theme.switcher.browser'     => 'Browser',
    'theme.switcher.light'       => 'Hell',
    'theme.switcher.dark'        => 'Dunkel',
    'theme.switcher.skin'        => 'Skin',
    'theme.switcher.skin_title'  => 'Skin (Live-Vorschau)',
    'theme.switcher.skin_header' => 'Skin — Live-Vorschau',
    'theme.switcher.custom'      => 'Eigenes',

    'theme.consent.label'      => 'Cookie-Einwilligung',
    'theme.consent.learn_more' => 'Mehr erfahren',
    'theme.consent.gpc'        => 'Global Privacy Control erkannt — Sie sind von Analyse und Datenweitergabe ausgenommen.',

    'theme.auth.title_default' => 'Anmelden — Tiger',

    // Die Bezeichnungen der Bedienelemente von cPanel / Plesk bleiben ABSICHTLICH auf Englisch:
    // genau so stehen sie auf dem cPanel- bzw. Plesk-Bildschirm, den der Nutzer vor sich hat.
    'theme.cron.title'               => 'So richten Sie Cron ein',
    'theme.cron.status.real.title'   => 'Ein echter Cron läuft.',
    'theme.cron.status.real.body'    => 'Tiger hat in den letzten Minuten vom Cron Ihres Servers gehört — die Zeitpläne laufen pünktlich.',
    'theme.cron.status.pseudo.title' => 'Der Pseudo-Cron nach WordPress-Art ist aktiv.',
    'theme.cron.status.pseudo.body'  => 'Es wurde kein echter Cron erkannt, deshalb laufen geplante Aufgaben über den <em>Website-Traffic</em> — für den Anfang in Ordnung, aber auf einer ruhigen Website können Aufgaben zu spät laufen. Richten Sie unten einen echten Cron ein, damit alles zuverlässig und pünktlich läuft.',
    'theme.cron.status.none.title'   => 'Es läuft kein Cron.',
    'theme.cron.status.none.body'    => 'Sowohl der echte Cron als auch die Traffic-gesteuerte Notlösung sind aus — geplante Aufgaben laufen erst, wenn Sie unten einen Cron einrichten.',
    'theme.cron.command_label'       => 'Ihr Cron-Befehl',
    'theme.cron.every_minute_note'   => 'Führen Sie ihn <strong>jede Minute</strong> aus (<code>* * * * *</code>) — Tiger entscheidet intern, welche Aufgaben tatsächlich fällig sind. Ein minütlicher Cron ist also günstig und hält alles pünktlich.',
    'theme.cron.tab.cli'             => 'Kommandozeile',
    'theme.cron.tab.claude'          => 'Claude Code machen lassen',
    'theme.cron.cpanel.step1'        => 'Öffnen Sie in cPanel <strong>Advanced → Cron Jobs</strong>.',
    'theme.cron.cpanel.step2'        => 'Setzen Sie unter <em>Add New Cron Job</em> die <strong>Common Settings</strong> auf <strong>Once Per Minute</strong> (<code>* * * * *</code>).',
    'theme.cron.cpanel.step3'        => 'Fügen Sie den Befehl von oben in <strong>Command</strong> ein.',
    'theme.cron.cpanel.step4'        => 'Klicken Sie auf <strong>Add New Cron Job</strong>. Fertig — diese Karte wird innerhalb von ein bis zwei Minuten grün.',
    'theme.cron.cpanel.php_path'     => 'Wird <code>php</code> nicht gefunden, verwenden Sie statt <code>php</code> den vollständigen PHP-Pfad Ihres Hosters (z. B. <code>/usr/local/bin/ea-php82</code>).',
    'theme.cron.plesk.step1'         => 'Öffnen Sie in Plesk <strong>Websites &amp; Domains → Scheduled Tasks</strong> für Ihre Domain.',
    'theme.cron.plesk.step2'         => 'Klicken Sie auf <strong>Add Task</strong> und wählen Sie <strong>Run a command</strong>.',
    'theme.cron.plesk.step3'         => 'Stellen Sie <strong>Run</strong> auf <strong>Cron style</strong> und tragen Sie <code>* * * * *</code> ein.',
    'theme.cron.plesk.step4'         => 'Fügen Sie den Befehl von oben ein und klicken Sie zum Testen auf <strong>OK</strong> / <strong>Run Now</strong>.',
    'theme.cron.cli.intro'           => 'Verbinden Sie sich per SSH mit dem Server und bearbeiten Sie die Crontab:',
    'theme.cron.cli.add'             => 'Fügen Sie diese Zeile hinzu und speichern Sie:',
    'theme.cron.claude.intro'        => 'Lassen Sie es Claude Code (oder einen beliebigen Shell-Zugang zu diesem Server) für Sie erledigen — fragen Sie einfach:',
    'theme.cron.claude.prompt'       => 'Richte einen minütlichen Cron ein, der Folgendes ausführt:',
    'theme.cron.claude.note'         => 'Claude Code trägt den Crontab-Eintrag ein und bestätigt ihn. Bis dahin hält der Traffic-gesteuerte Pseudo-Cron den Betrieb am Laufen.',

    'theme.schedule.frequency'         => 'Häufigkeit',
    'theme.schedule.time'              => 'Uhrzeit',
    'theme.schedule.day_of_week'       => 'Wochentag',
    'theme.schedule.day_of_month'      => 'Tag des Monats',
    'theme.schedule.enabled'           => 'Aktiviert',
    'theme.schedule.freq.every_minute' => 'Jede Minute',
    'theme.schedule.freq.every_5_min'  => 'Alle 5 Minuten',
    'theme.schedule.freq.every_15_min' => 'Alle 15 Minuten',
    'theme.schedule.freq.hourly'       => 'Stündlich',
    'theme.schedule.freq.daily'        => 'Täglich',
    'theme.schedule.freq.weekly'       => 'Wöchentlich',
    'theme.schedule.freq.monthly'      => 'Monatlich',
    'theme.schedule.day.sunday'        => 'Sonntag',
    'theme.schedule.day.monday'        => 'Montag',
    'theme.schedule.day.tuesday'       => 'Dienstag',
    'theme.schedule.day.wednesday'     => 'Mittwoch',
    'theme.schedule.day.thursday'      => 'Donnerstag',
    'theme.schedule.day.friday'        => 'Freitag',
    'theme.schedule.day.saturday'      => 'Samstag',
];
