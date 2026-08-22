<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'Sicherung abgeschlossen.',
    'backup.failed'           => 'Die Sicherung ist fehlgeschlagen. Prüfen Sie die Protokolle für Details.',
    'backup.deleted'          => 'Sicherung gelöscht.',
    'backup.restore.done'     => 'Wiederherstellung abgeschlossen.',
    'backup.restore.failed'   => 'Die Wiederherstellung ist fehlgeschlagen. Ihre Sicherheitssicherung von vor der Wiederherstellung ist verfügbar.',
    'backup.restore.confirm'  => 'Geben Sie RESTORE ein, um diese destruktive Aktion zu bestätigen.',
    'backup.settings.saved'   => 'Sicherungseinstellungen gespeichert.',
    'backup.bad_component'    => 'Wählen Sie mindestens eine Komponente zum Sichern aus.',
    'backup.bad_disk'         => 'Unbekanntes Sicherungsziel.',
    'backup.bad_email'        => 'Geben Sie gültige E-Mail-Adressen ein, durch Komma getrennt.',
    'backup.not_found'        => 'Diese Sicherung konnte nicht gefunden werden.',
    'backup.upload.failed'    => 'Der Upload wurde nicht abgeschlossen.',
    'backup.upload.invalid'   => 'Diese Datei ist kein TigerBackup-Archiv.',

    // Component labels
    'backup.comp.database'      => 'Datenbank',
    'backup.comp.database_desc' => 'Alle Tabellen — ein portabler SQL-Dump',
    'backup.comp.media'         => 'Medien',
    'backup.comp.media_desc'    => 'Hochgeladene Dateien',
    'backup.comp.modules'       => 'Module',
    'backup.comp.modules_desc'  => 'Ihre App-Module',
    'backup.comp.platform'      => 'Plattform',
    'backup.comp.platform_desc' => 'App-Code + Konfiguration (zum Verschieben einer Website)',

    // Outcome badges
    'backup.outcome.ok'      => 'OK',
    'backup.outcome.error'   => 'Fehlgeschlagen',
    'backup.outcome.running' => 'Läuft',

    // Screen header
    'backup.title'      => 'Sicherung &amp; Wiederherstellung',
    'backup.subtitle'   => 'Archivieren Sie Ihre Website in einer herunterladbaren ZIP-Datei — behalten Sie sie lokal oder übertragen Sie sie in den Cloud-Speicher. Stellen Sie hier wieder her oder verschieben Sie die Website an einen neuen Ort.',
    'backup.action.run' => 'Jetzt sichern',

    // Create card
    'backup.card.create'             => 'Eine Sicherung erstellen',
    'backup.create.include_label'     => 'Was einbeziehen',
    'backup.create.destination_label' => 'Ziel',
    'backup.create.destination_help'  => 'Konfigurieren Sie eine Cloud-<em>Mediendiskette</em> (S3/GCS/Azure), um außerhalb des Servers zu sichern.',
    'backup.create.secrets_label'     => 'Geheimnisse einbeziehen (local.ini)',
    'backup.create.secrets_help'      => 'Erforderlich, um eine Website intakt zu verschieben. Behandeln Sie das Archiv sicher.',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'Aus einer Datei wiederherstellen',
    'backup.restore_file.help'   => 'Laden Sie eine <code>TigerBackup-*.zip</code> hoch, um sie hier wiederherzustellen — so verschieben Sie eine Website auf eine frische Installation. Dies ist <strong>destruktiv</strong>: Es läuft im Wartungsmodus und erstellt zuerst eine Sicherheitssicherung.',
    'backup.action.restore'      => 'Wiederherstellen',

    // History card
    'backup.card.history'         => 'Sicherungen',
    'backup.history.empty'        => 'Noch keine Sicherungen. Wählen Sie aus, was einbezogen werden soll, und klicken Sie auf <strong>Jetzt sichern</strong>.',
    'backup.col.archive'          => 'Archiv',
    'backup.col.size'             => 'Größe',
    'backup.col.includes'         => 'Enthält',
    'backup.col.when'             => 'Wann',
    'backup.col.where'            => 'Wo',
    'backup.col.actions'          => 'Aktionen',
    'backup.pinned_title'         => 'Manuelle Sicherungen werden nie automatisch bereinigt',
    'backup.action.download_title' => 'Herunterladen',
    'backup.action.restore_title'  => 'Diese Sicherung wiederherstellen',
    'backup.action.delete_title'   => 'Löschen',

    // Scheduled backups card
    'backup.card.scheduled'          => 'Geplante Sicherungen',
    'backup.scheduled.help'          => 'Legen Sie einen Rhythmus fest, und Tiger sichert von selbst. Die rollierende Aufbewahrung behält die neuesten <strong>N</strong> geplanten Sicherungen; manuelle Sicherungen werden nie automatisch entfernt.',
    'backup.scheduled.schedule_label' => 'Zeitplan',
    'backup.scheduled.retention_label' => 'Neueste behalten (rollierendes Maximum)',
    'backup.scheduled.retention_help' => '0 = alle behalten.',
    'backup.scheduled.email_label'    => 'Status per E-Mail senden an',
    'backup.scheduled.notify_label'   => 'E-Mail bei Erfolg &amp; Fehlschlag',
    'backup.scheduled.note'           => 'Geplante Sicherungen verwenden die Komponenten &amp; das Ziel, die oben unter <em>Eine Sicherung erstellen</em> ausgewählt wurden, hier gespeichert:',
    'backup.action.save_settings'     => 'Zeitplaneinstellungen speichern',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'Wiederherstellung bestätigen',
    'backup.action.close'                => 'Schließen',
    'backup.restore_modal.body_pre'      => 'Sie sind dabei, wiederherzustellen: ',
    'backup.restore_modal.body_post'     => '. Dies <strong>überschreibt</strong> die aktuelle Datenbank und/oder Dateien und kann nicht rückgängig gemacht werden. Zuerst wird eine Sicherheitssicherung erstellt, und die Website wechselt während der Wiederherstellung in den Wartungsmodus.',
    'backup.restore_modal.confirm_label' => 'Geben Sie <code>RESTORE</code> ein, um zu bestätigen',
    'backup.action.cancel'               => 'Abbrechen',
    'backup.action.restore_now'          => 'Jetzt wiederherstellen',
    'backup.js.select_component'     => 'Wählen Sie mindestens eine Komponente aus.',
    'backup.js.confirm_delete_named' => '%s löschen? Dies entfernt das Archiv dauerhaft.',
    'backup.js.choose_zip'           => 'Wählen Sie zuerst ein .zip-Archiv aus.',
    'backup.nav.label' => 'Sicherung',
];
