<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — German strings (locale `de`). Mirrors en/agent.php key-for-key.
 */
return [
    // Settings screen
    'agent.settings.title'        => 'KI-Agent',
    'agent.settings.subtitle'     => 'Verbinden Sie Ihr eigenes KI-Konto und lassen Sie den Agenten innerhalb Ihrer Website arbeiten.',
    'agent.settings.saved'        => 'Agenten-Einstellungen gespeichert.',
    'agent.settings.save'         => 'Speichern',
    'agent.settings.provider'     => 'Anbieter',
    'agent.settings.model'        => 'Modell',
    'agent.settings.model.ph'     => 'z. B. claude-sonnet-5',
    'agent.settings.model.refresh' => 'Modellliste aktualisieren',
    'agent.settings.key'          => 'API-Schlüssel',
    'agent.settings.key.ph'       => 'Fügen Sie einen Schlüssel zum Verbinden ein (leer lassen, um den aktuellen zu behalten)',
    'agent.settings.enabled'      => 'KI-Agent aktivieren',
    'agent.settings.connected'    => 'Verbunden — ein Schlüssel ist gespeichert (verschlüsselt).',
    'agent.settings.disconnected' => 'Nicht verbunden — fügen Sie einen API-Schlüssel ein, um den Agenten zu aktivieren.',
    'agent.settings.connection'   => 'Verbindung',
    'agent.settings.crypto_missing' => 'Die Verschlüsselung ist nicht konfiguriert (<code>tiger.crypto.key</code>), daher kann ein API-Schlüssel noch nicht sicher gespeichert werden.',
    'agent.settings.mode_max'     => 'Automatisierungsobergrenze',
    'agent.settings.mode_max.help' => 'Die höchste Automatisierungsstufe, die hier jemand verwenden darf. Benutzer können sie reduzieren, aber nie überschreiten.',
    'agent.settings.mode.ask'     => 'Fragen — jede Änderung genehmigen (am sichersten)',
    'agent.settings.mode.auto'    => 'Auto — Routineänderungen laufen automatisch; Code/Dateien fragen weiterhin',
    'agent.settings.mode.yolo'    => 'YOLO — alles, was die Rolle erlaubt, läuft automatisch',
    'agent.settings.how.title'    => 'So funktioniert es',
    'agent.settings.how.body1'    => 'Der Agent handelt <strong>als Sie</strong> — er kann nie mehr tun, als Ihre Rolle erlaubt. Lesevorgänge laufen von selbst; Änderungen werden zuerst zu Ihrer Genehmigung angezeigt.',
    'agent.settings.how.body2'    => '<strong>Bringen Sie Ihr eigenes Konto mit:</strong> der Schlüssel, den Sie einfügen, gehört Ihnen, wird auf diesem Server verschlüsselt gespeichert und nie geteilt. Ihr KI-Anbieter rechnet direkt mit Ihnen ab.',

    // Aside modes
    'agent.mode.ask'              => 'Fragen',
    'agent.mode.auto'            => 'Auto',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'Jede Änderung genehmigen',
    'agent.mode.auto.hint'      => 'Routineänderungen laufen von selbst; Code/Dateien fragen',
    'agent.mode.yolo.hint'      => 'Alles läuft von selbst — halten Sie sich fest',

    // Turn results
    'agent.turn.ok'             => 'Fertig.',
    'agent.approve.ok'          => 'Aktionen abgeschlossen.',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'Datei angehängt.',
    'agent.file.type'           => 'Dieser Dateityp wird nicht unterstützt.',
    'agent.file.too_large'      => 'Diese Datei ist zu groß.',
    'agent.file.failed'         => 'Die Datei konnte nicht angehängt werden. Bitte versuchen Sie es erneut.',

    // Errors
    'agent.error.empty'         => 'Geben Sie eine Nachricht für den Agenten ein.',
    'agent.error.unconfigured'  => 'Der KI-Agent ist noch nicht verbunden. Fügen Sie unter Einstellungen → KI-Agent einen API-Schlüssel hinzu.',
    'agent.error.provider'      => 'Der KI-Anbieter konnte nicht erreicht werden. Prüfen Sie den Schlüssel und versuchen Sie es erneut.',
    'agent.error.run_missing'   => 'Diese Unterhaltung oder dieser Schritt ist nicht mehr verfügbar.',

    // Aside UI
    'agent.aside.title'         => 'Agent',
    'agent.aside.placeholder'   => 'Bitten Sie den Agenten, etwas zu erstellen, zu ändern oder zu erklären…',
    'agent.aside.new'           => 'Neuer Chat',
    'agent.aside.send'          => 'Senden',
    'agent.aside.approve'       => 'Genehmigen',
    'agent.aside.approve_all'   => 'Alle genehmigen',
    'agent.aside.thinking'      => 'Wird bearbeitet…',
    'agent.aside.empty'         => 'Beginnen Sie eine Unterhaltung — der Agent handelt mit Ihren Berechtigungen.',

    // Skills (messages)
    'agent.skills.installed'      => 'Skill installiert.',
    'agent.skills.install_failed' => 'Dieser Skill konnte nicht installiert werden.',
    'agent.skills.none_found'     => 'Unter dieser URL wurde keine SKILL.md gefunden.',
    'agent.skills.enabled'        => 'Skill aktiviert.',
    'agent.skills.disabled'       => 'Skill deaktiviert.',
    'agent.skills.removed'        => 'Skill entfernt.',

    // Skills (admin screen)
    'agent.skills.title'          => 'Agenten-Skills',
    'agent.skills.subtitle'       => 'Installierbares Know-how für den KI-Agenten. Tiger durchsucht diese Repositories — es bürgt nicht für sie; prüfen Sie die Quelle eines Skills, bevor Sie ihn installieren und aktivieren. Installierte Skills werden oben angeheftet.',
    'agent.skills.rescan'         => 'Erneut scannen',
    'agent.skills.rescan.title'   => 'Die Quellen erneut scannen',
    'agent.skills.add_url'        => 'Von einer GitHub-URL hinzufügen',
    'agent.skills.url.ph'         => 'https://github.com/owner/repo (oder ein Unterordner / eine SKILL.md)',
    'agent.skills.install'        => 'Installieren',
    'agent.skills.add_url.help'   => 'Jedes Repository, jeder Branch, jeder Unterordner oder ein direkter Link zu einer SKILL.md — nicht nur die aufgelisteten Quellen.',
    'agent.skills.col.skill'      => 'Skill',
    'agent.skills.col.description' => 'Beschreibung',
    'agent.skills.col.source'     => 'Quelle',
    'agent.skills.col.status'     => 'Status',
    'agent.skills.col.actions'    => 'Aktionen',
    'agent.skills.src.title'      => 'SKILL.md',
    'agent.skills.src.note'       => 'Nur Herkunft — vor der Installation prüfen.',
    'agent.skills.close'          => 'Schließen',

    // MCP connections (outbound) — messages
    'agent.mcp.saved'     => 'Verbindung gespeichert.',
    'agent.mcp.removed'   => 'Verbindung entfernt.',
    'agent.mcp.bad_url'   => 'Geben Sie eine gültige http(s)-URL für den MCP-Server ein.',
    'agent.mcp.bad_label' => 'Geben Sie der Verbindung einen Namen.',
    'agent.mcp.not_found' => 'Diese Verbindung ist nicht verfügbar.',

    // MCP connections (outbound) — admin screen
    'agent.mcp.title'         => 'MCP-Verbindungen',
    'agent.mcp.subtitle'      => 'Verbinden Sie externe <strong>MCP-Server</strong>, damit der KI-Agent deren Tools zusätzlich zu seinen eigenen nutzen kann. Ein Tool-Aufruf läuft auf dem entfernten Server und ist wie jeder Agenten-Schreibvorgang genehmigungspflichtig. Nur für Administratoren.',
    'agent.mcp.add'           => 'Eine Verbindung hinzufügen',
    'agent.mcp.name'          => 'Name',
    'agent.mcp.name.ph'       => 'z. B. GitHub, Linear, Weather',
    'agent.mcp.url'           => 'Server-URL (Streamable HTTP)',
    'agent.mcp.token'         => 'Bearer-Token',
    'agent.mcp.token.optional' => '(optional; verschlüsselt gespeichert)',
    'agent.mcp.token.ph'      => 'leer lassen, um den aktuellen zu behalten',
    'agent.mcp.enabled'       => 'Aktiviert',
    'agent.mcp.save'          => 'Speichern',
    'agent.mcp.cancel'        => 'Abbrechen',
    'agent.mcp.connected'     => 'Verbundene Server',
    'agent.mcp.empty'         => 'Noch keine Verbindungen — fügen Sie links eine hinzu.',
    'agent.js.models_live' => 'Live aus Ihrem Konto.',
    'agent.js.models_static' => 'Gängige Modelle — verbinden Sie einen Schlüssel für die Live-Liste.',
    'agent.js.settings_saved' => 'Einstellungen gespeichert.',
    'agent.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'agent.js.connection_saved' => 'Verbindung gespeichert.',
    'agent.js.remove_connection_title' => 'Verbindung entfernen',
    'agent.js.remove_connection_body' => 'Der Agent verliert den Zugriff auf seine Tools.',
    'agent.js.remove_label' => 'Entfernen',
    'agent.js.remove_skill_title' => 'Skill entfernen',
    'agent.js.remove_skill_body' => 'Diesen Skill und seine Dateien entfernen? (Er bleibt zur erneuten Installation im Katalog.)',
    'agent.nav.label' => 'KI-Agent',
    'agent.nav.skills' => 'Agenten-Skills',
    'agent.nav.mcp' => 'MCP-Verbindungen',
];
