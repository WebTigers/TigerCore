<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'schedule.saved'          => 'Agendamento salvo.',
    'schedule.ran'            => 'Tarefa executada.',
    'schedule.run_failed'     => 'A tarefa falhou ao executar.',
    'schedule.unknown_job'    => 'Não existe essa tarefa agendada.',
    'schedule.bad_frequency'  => 'Escolha uma frequência válida.',
    'schedule.bad_time'       => 'Informe a hora como HH:MM (24 horas).',

    // Frequency labels
    'schedule.freq.every_minute' => 'A cada minuto',
    'schedule.freq.every_5_min'  => 'A cada 5 minutos',
    'schedule.freq.every_15_min' => 'A cada 15 minutos',
    'schedule.freq.hourly'       => 'A cada hora',
    'schedule.freq.daily'        => 'Diariamente',
    'schedule.freq.weekly'       => 'Semanalmente',
    'schedule.freq.monthly'      => 'Mensalmente',

    // Day names
    'schedule.day.sunday'    => 'Domingo',
    'schedule.day.monday'    => 'Segunda-feira',
    'schedule.day.tuesday'   => 'Terça-feira',
    'schedule.day.wednesday' => 'Quarta-feira',
    'schedule.day.thursday'  => 'Quinta-feira',
    'schedule.day.friday'    => 'Sexta-feira',
    'schedule.day.saturday'  => 'Sábado',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s às %s',
    'schedule.summary.weekly'  => '%s em %s às %s',
    'schedule.summary.monthly' => '%s no dia %s às %s',

    // Run outcome badges
    'schedule.outcome.ok'      => 'Sucesso',
    'schedule.outcome.error'   => 'Falhou',
    'schedule.outcome.running' => 'Em execução',
    'schedule.outcome.skipped' => 'Ignorada',

    // Screen
    'schedule.title'    => 'Agendador',
    'schedule.subtitle' => 'Tarefas em segundo plano e quando são executadas. Qualquer módulo pode registrar uma — backups, limpezas, relatórios.',
    'schedule.card.jobs' => 'Tarefas agendadas',
    'schedule.empty'     => 'Ainda não há tarefas registradas.',
    'schedule.col.job'      => 'Tarefa',
    'schedule.col.schedule' => 'Agendamento',
    'schedule.col.next_run' => 'Próxima execução',
    'schedule.col.last_run' => 'Última execução',
    'schedule.col.actions'  => 'Ações',
    'schedule.badge.disabled' => 'Desativada',
    'schedule.last.never'     => 'Nunca',
    'schedule.action.edit_title' => 'Editar agendamento',
    'schedule.action.run_now'    => 'Executar agora',
    'schedule.nav.label' => 'Agendador',
];
