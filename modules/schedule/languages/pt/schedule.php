<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
// STUB — pt: English placeholders; translate the values.
return [
    // Service / API messages
    'schedule.saved'          => 'Schedule saved.',
    'schedule.ran'            => 'Job ran.',
    'schedule.run_failed'     => 'The job failed to run.',
    'schedule.unknown_job'    => 'No such scheduled job.',
    'schedule.bad_frequency'  => 'Please choose a valid frequency.',
    'schedule.bad_time'       => 'Please enter a time as HH:MM (24-hour).',

    // Frequency labels
    'schedule.freq.every_minute' => 'Every minute',
    'schedule.freq.every_5_min'  => 'Every 5 minutes',
    'schedule.freq.every_15_min' => 'Every 15 minutes',
    'schedule.freq.hourly'       => 'Hourly',
    'schedule.freq.daily'        => 'Daily',
    'schedule.freq.weekly'       => 'Weekly',
    'schedule.freq.monthly'      => 'Monthly',

    // Day names
    'schedule.day.sunday'    => 'Sunday',
    'schedule.day.monday'    => 'Monday',
    'schedule.day.tuesday'   => 'Tuesday',
    'schedule.day.wednesday' => 'Wednesday',
    'schedule.day.thursday'  => 'Thursday',
    'schedule.day.friday'    => 'Friday',
    'schedule.day.saturday'  => 'Saturday',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s at %s',
    'schedule.summary.weekly'  => '%s on %s at %s',
    'schedule.summary.monthly' => '%s on day %s at %s',

    // Run outcome badges
    'schedule.outcome.ok'      => 'Success',
    'schedule.outcome.error'   => 'Failed',
    'schedule.outcome.running' => 'Running',
    'schedule.outcome.skipped' => 'Skipped',

    // Screen
    'schedule.title'    => 'Scheduler',
    'schedule.subtitle' => 'Background jobs and when they run. Any module can register one — Backup, cleanups, reports.',
    'schedule.card.jobs' => 'Scheduled jobs',
    'schedule.empty'     => 'No jobs are registered yet.',
    'schedule.col.job'      => 'Job',
    'schedule.col.schedule' => 'Schedule',
    'schedule.col.next_run' => 'Next run',
    'schedule.col.last_run' => 'Last run',
    'schedule.col.actions'  => 'Actions',
    'schedule.badge.disabled' => 'Disabled',
    'schedule.last.never'     => 'Never',
    'schedule.action.edit_title' => 'Edit schedule',
    'schedule.action.run_now'    => 'Run now',
];
