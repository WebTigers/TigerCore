<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'schedule.saved'          => 'Zeitplan gespeichert.',
    'schedule.ran'            => 'Auftrag ausgeführt.',
    'schedule.run_failed'     => 'Der Auftrag konnte nicht ausgeführt werden.',
    'schedule.unknown_job'    => 'Diesen geplanten Auftrag gibt es nicht.',
    'schedule.bad_frequency'  => 'Bitte wählen Sie eine gültige Frequenz.',
    'schedule.bad_time'       => 'Bitte geben Sie eine Uhrzeit als HH:MM (24-Stunden) ein.',

    // Frequency labels
    'schedule.freq.every_minute' => 'Jede Minute',
    'schedule.freq.every_5_min'  => 'Alle 5 Minuten',
    'schedule.freq.every_15_min' => 'Alle 15 Minuten',
    'schedule.freq.hourly'       => 'Stündlich',
    'schedule.freq.daily'        => 'Täglich',
    'schedule.freq.weekly'       => 'Wöchentlich',
    'schedule.freq.monthly'      => 'Monatlich',

    // Day names
    'schedule.day.sunday'    => 'Sonntag',
    'schedule.day.monday'    => 'Montag',
    'schedule.day.tuesday'   => 'Dienstag',
    'schedule.day.wednesday' => 'Mittwoch',
    'schedule.day.thursday'  => 'Donnerstag',
    'schedule.day.friday'    => 'Freitag',
    'schedule.day.saturday'  => 'Samstag',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s um %s',
    'schedule.summary.weekly'  => '%s am %s um %s',
    'schedule.summary.monthly' => '%s am Tag %s um %s',

    // Run outcome badges
    'schedule.outcome.ok'      => 'Erfolg',
    'schedule.outcome.error'   => 'Fehlgeschlagen',
    'schedule.outcome.running' => 'Läuft',
    'schedule.outcome.skipped' => 'Übersprungen',

    // Screen
    'schedule.title'    => 'Planer',
    'schedule.subtitle' => 'Hintergrundaufträge und wann sie ausgeführt werden. Jedes Modul kann einen registrieren — Backup, Bereinigungen, Berichte.',
    'schedule.card.jobs' => 'Geplante Aufträge',
    'schedule.empty'     => 'Es sind noch keine Aufträge registriert.',
    'schedule.col.job'      => 'Auftrag',
    'schedule.col.schedule' => 'Zeitplan',
    'schedule.col.next_run' => 'Nächste Ausführung',
    'schedule.col.last_run' => 'Letzte Ausführung',
    'schedule.col.actions'  => 'Aktionen',
    'schedule.badge.disabled' => 'Deaktiviert',
    'schedule.last.never'     => 'Nie',
    'schedule.action.edit_title' => 'Zeitplan bearbeiten',
    'schedule.action.run_now'    => 'Jetzt ausführen',
    'schedule.nav.label' => 'Planer',
];
