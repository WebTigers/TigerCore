<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'schedule.saved'          => 'Programación guardada.',
    'schedule.ran'            => 'Tarea ejecutada.',
    'schedule.run_failed'     => 'La tarea falló al ejecutarse.',
    'schedule.unknown_job'    => 'No existe esa tarea programada.',
    'schedule.bad_frequency'  => 'Elige una frecuencia válida.',
    'schedule.bad_time'       => 'Indica la hora como HH:MM (24 horas).',

    // Frequency labels
    'schedule.freq.every_minute' => 'Cada minuto',
    'schedule.freq.every_5_min'  => 'Cada 5 minutos',
    'schedule.freq.every_15_min' => 'Cada 15 minutos',
    'schedule.freq.hourly'       => 'Cada hora',
    'schedule.freq.daily'        => 'Diariamente',
    'schedule.freq.weekly'       => 'Semanalmente',
    'schedule.freq.monthly'      => 'Mensualmente',

    // Day names
    'schedule.day.sunday'    => 'Domingo',
    'schedule.day.monday'    => 'Lunes',
    'schedule.day.tuesday'   => 'Martes',
    'schedule.day.wednesday' => 'Miércoles',
    'schedule.day.thursday'  => 'Jueves',
    'schedule.day.friday'    => 'Viernes',
    'schedule.day.saturday'  => 'Sábado',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s a las %s',
    'schedule.summary.weekly'  => '%s el %s a las %s',
    'schedule.summary.monthly' => '%s el día %s a las %s',

    // Run outcome badges
    'schedule.outcome.ok'      => 'Éxito',
    'schedule.outcome.error'   => 'Falló',
    'schedule.outcome.running' => 'En ejecución',
    'schedule.outcome.skipped' => 'Omitida',

    // Screen
    'schedule.title'    => 'Programador',
    'schedule.subtitle' => 'Tareas en segundo plano y cuándo se ejecutan. Cualquier módulo puede registrar una — copias de seguridad, limpiezas, informes.',
    'schedule.card.jobs' => 'Tareas programadas',
    'schedule.empty'     => 'Aún no hay tareas registradas.',
    'schedule.col.job'      => 'Tarea',
    'schedule.col.schedule' => 'Programación',
    'schedule.col.next_run' => 'Próxima ejecución',
    'schedule.col.last_run' => 'Última ejecución',
    'schedule.col.actions'  => 'Acciones',
    'schedule.badge.disabled' => 'Desactivada',
    'schedule.last.never'     => 'Nunca',
    'schedule.action.edit_title' => 'Editar programación',
    'schedule.action.run_now'    => 'Ejecutar ahora',
];
