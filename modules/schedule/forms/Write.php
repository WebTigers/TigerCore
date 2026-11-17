<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Schedule_Form_Write — the shared base for the scheduler writes (TIGER-528).
 *
 * The Scheduler screen drives setSchedule and runNow for many jobs from one page, and the reusable
 * schedule-control partial embeds the same writes in other screens (Backup), so both forms share one
 * CSRF salt — `Tiger_Form::csrfSalt()` defaults to the class, which would make the second call fail
 * "your security token expired" (see `Desk_Form_TicketWrite`).
 *
 * Fields are optional and filter-free: the service sanitizes the job key and answers an unknown job,
 * a bad frequency and a bad time with its own keys (`schedule.unknown_job`, `schedule.bad_frequency`,
 * `schedule.bad_time`) — top-level messages its callers already key on.
 *
 * @api
 */
abstract class Schedule_Form_Write extends Tiger_Form
{
    /**
     * One token for every scheduler write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Schedule_Form_Write';
    }

    /**
     * An optional, filter-free field.
     *
     * @param  string $name the field name
     * @return array  [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
