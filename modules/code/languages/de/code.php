<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — German strings (code.*). Same key set as languages/en/code.php.
 */
return [
    // API responses
    'code.saved'       => 'Snippet gespeichert.',
    'code.activated'   => 'Snippet aktiviert — jetzt live.',
    'code.deactivated' => 'Snippet deaktiviert.',
    'code.deleted'     => 'Snippet gelöscht.',
    'code.restored'    => 'Snippet auf die ausgewählte Version zurückgesetzt.',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'Nicht gespeichert —',
    'code.error.saved_not_activated'      => 'Gespeichert, aber nicht aktiviert — es steht im Konflikt mit dem laufenden Satz:',
    'code.error.cannot_activate'          => 'Aktivierung nicht möglich —',
    'code.error.cannot_activate_conflict' => 'Aktivierung nicht möglich — es steht im Konflikt mit dem laufenden Satz:',
    'code.error.snippet_unavailable'      => 'Dieses Snippet ist nicht mehr verfügbar — das Modul wurde möglicherweise entfernt.',

    // admin list
    'code.list.title'  => 'Code',
    'code.list.new'    => 'Neues Snippet',
    'code.list.subtitle_a'       => 'PHP-Snippets, die plattformweit laufen — kompiliert + zwischengespeichert, bei jeder Anfrage ausgeführt. Lokale Snippets werden in der Datenbank gespeichert;',
    'code.list.subtitle_b'       => 'Snippets stammen aus installierten Code-Modulen (lesen Sie den Quellcode, bevor Sie sie aktivieren).',
    'code.list.badge_module'     => 'Modul',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'Name',
    'code.list.col_lang'     => 'Sprache',
    'code.list.col_runs'     => 'Läuft',
    'code.list.col_priority' => 'Priorität',
    'code.list.col_state'    => 'Zustand',
    'code.list.col_updated'  => 'Aktualisiert',
    'code.list.col_actions'  => 'Aktionen',

    // view-source modal
    'code.source.title'    => 'Snippet-Quellcode',
    'code.source.close'    => 'Schließen',
    'code.source.warn'     => 'Beim Aktivieren wird dieses PHP in Ihrer App ausgeführt.',
    'code.source.activate' => 'Aktivieren',

    // snippet editor
    'code.edit.edit_title' => 'Snippet bearbeiten',
    'code.edit.new_title'  => 'Neues Snippet',
    'code.edit.back'       => 'Zurück zum Code',
    'code.edit.cancel'     => 'Abbrechen',
    'code.edit.save'       => 'Speichern',
    'code.edit.warn'       => 'Dieses PHP läuft bei <strong>jeder Anfrage</strong>, sobald es aktiv ist. Es wird beim Speichern geprüft und automatisch deaktiviert, wenn es beim Laden fatal fehlschlägt.',
    'code.edit.name'       => 'Name',
    'code.edit.code'       => 'Code',
    'code.edit.type'       => 'Typ',
    'code.edit.language'   => 'Sprache',
    'code.edit.inject_at'  => 'Einfügen bei',
    'code.edit.inject_hint'      => 'Wo eingefügtes CSS/JS/HTML/PHTML landet.',
    'code.edit.activation'       => 'Aktivierung',
    'code.edit.active_label'     => 'Aktiv — dieses Snippet ausführen',
    'code.edit.priority'         => 'Priorität',
    'code.edit.priority_hint'    => 'Niedrigere Werte werden zuerst geladen. Läuft global (bei jeder Anfrage).',
    'code.edit.notes'            => 'Notizen',
    'code.edit.description'      => 'Beschreibung',
    'code.edit.description_hint' => 'Was dieses Snippet tut (für die Liste).',

    // snippet editor — version history
    'code.edit.versions'       => 'Versionsverlauf',
    'code.edit.col_version'    => 'Version',
    'code.edit.col_name'       => 'Name',
    'code.edit.col_state'      => 'Zustand',
    'code.edit.col_saved'      => 'Gespeichert',
    'code.edit.state_active'   => 'Aktiv',
    'code.edit.state_inactive' => 'Inaktiv',
    'code.edit.untitled'       => '(ohne Titel)',
    'code.edit.restore'        => 'Wiederherstellen',

    // form — language select
    'code.lang.php'   => 'PHP — läuft bei jeder Anfrage (Funktionen/Hooks)',
    'code.lang.phtml' => 'PHTML — gerendert + eingefügt',
    'code.lang.html'  => 'HTML — unverändert eingefügt',
    'code.lang.css'   => 'CSS — als Stylesheet eingefügt',
    'code.lang.js'    => 'JavaScript — als Skript eingefügt',

    // form — inject-at select
    'code.auto.head'   => 'Head',
    'code.auto.footer' => 'Footer',
    'code.js.fix_form' => 'Bitte überprüfen Sie das Formular und versuchen Sie es erneut.',
    'code.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'code.js.confirm_restore' => 'Version #%s wiederherstellen? Der aktuelle Inhalt wird zuvor als neue Version gespeichert.',
    'code.js.confirm_delete_snippet' => 'Dieses Snippet löschen? Es wird sanft gelöscht und kann wiederhergestellt werden.',
];
