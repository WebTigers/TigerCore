<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'schedule.saved'          => 'Planification enregistrée.',
    'schedule.ran'            => 'Tâche exécutée.',
    'schedule.run_failed'     => 'Échec de l\'exécution de la tâche.',
    'schedule.unknown_job'    => 'Cette tâche planifiée n\'existe pas.',
    'schedule.bad_frequency'  => 'Veuillez choisir une fréquence valide.',
    'schedule.bad_time'       => 'Veuillez saisir une heure au format HH:MM (24 heures).',

    // Frequency labels
    'schedule.freq.every_minute' => 'Chaque minute',
    'schedule.freq.every_5_min'  => 'Toutes les 5 minutes',
    'schedule.freq.every_15_min' => 'Toutes les 15 minutes',
    'schedule.freq.hourly'       => 'Toutes les heures',
    'schedule.freq.daily'        => 'Chaque jour',
    'schedule.freq.weekly'       => 'Chaque semaine',
    'schedule.freq.monthly'      => 'Chaque mois',

    // Day names
    'schedule.day.sunday'    => 'Dimanche',
    'schedule.day.monday'    => 'Lundi',
    'schedule.day.tuesday'   => 'Mardi',
    'schedule.day.wednesday' => 'Mercredi',
    'schedule.day.thursday'  => 'Jeudi',
    'schedule.day.friday'    => 'Vendredi',
    'schedule.day.saturday'  => 'Samedi',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s à %s',
    'schedule.summary.weekly'  => '%s le %s à %s',
    'schedule.summary.monthly' => '%s le jour %s à %s',

    // Run outcome badges
    'schedule.outcome.ok'      => 'Réussite',
    'schedule.outcome.error'   => 'Échec',
    'schedule.outcome.running' => 'En cours',
    'schedule.outcome.skipped' => 'Ignorée',

    // Screen
    'schedule.title'    => 'Planificateur',
    'schedule.subtitle' => 'Tâches en arrière-plan et quand elles s\'exécutent. N\'importe quel module peut en enregistrer une — Backup, nettoyages, rapports.',
    'schedule.card.jobs' => 'Tâches planifiées',
    'schedule.empty'     => 'Aucune tâche n\'est encore enregistrée.',
    'schedule.col.job'      => 'Tâche',
    'schedule.col.schedule' => 'Planification',
    'schedule.col.next_run' => 'Prochaine exécution',
    'schedule.col.last_run' => 'Dernière exécution',
    'schedule.col.actions'  => 'Actions',
    'schedule.badge.disabled' => 'Désactivée',
    'schedule.last.never'     => 'Jamais',
    'schedule.action.edit_title' => 'Modifier la planification',
    'schedule.action.run_now'    => 'Exécuter maintenant',
    'schedule.nav.label' => 'Planificateur',
];
