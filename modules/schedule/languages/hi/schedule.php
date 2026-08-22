<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'schedule.saved'          => 'शेड्यूल सहेजा गया।',
    'schedule.ran'            => 'जॉब चला।',
    'schedule.run_failed'     => 'जॉब चलाने में विफल रहा।',
    'schedule.unknown_job'    => 'ऐसा कोई शेड्यूल किया गया जॉब नहीं है।',
    'schedule.bad_frequency'  => 'कृपया एक मान्य आवृत्ति चुनें।',
    'schedule.bad_time'       => 'कृपया समय HH:MM (24-घंटे) के रूप में दर्ज करें।',

    // Frequency labels
    'schedule.freq.every_minute' => 'हर मिनट',
    'schedule.freq.every_5_min'  => 'हर 5 मिनट',
    'schedule.freq.every_15_min' => 'हर 15 मिनट',
    'schedule.freq.hourly'       => 'हर घंटे',
    'schedule.freq.daily'        => 'दैनिक',
    'schedule.freq.weekly'       => 'साप्ताहिक',
    'schedule.freq.monthly'      => 'मासिक',

    // Day names
    'schedule.day.sunday'    => 'रविवार',
    'schedule.day.monday'    => 'सोमवार',
    'schedule.day.tuesday'   => 'मंगलवार',
    'schedule.day.wednesday' => 'बुधवार',
    'schedule.day.thursday'  => 'गुरुवार',
    'schedule.day.friday'    => 'शुक्रवार',
    'schedule.day.saturday'  => 'शनिवार',

    // Cadence summaries (%s placeholders: frequency, day/time)
    'schedule.summary.daily'   => '%s को %s बजे',
    'schedule.summary.weekly'  => '%s, %s को %s बजे',
    'schedule.summary.monthly' => '%s, %s तारीख को %s बजे',

    // Run outcome badges
    'schedule.outcome.ok'      => 'सफल',
    'schedule.outcome.error'   => 'विफल',
    'schedule.outcome.running' => 'चल रहा है',
    'schedule.outcome.skipped' => 'छोड़ा गया',

    // Screen
    'schedule.title'    => 'शेड्यूलर',
    'schedule.subtitle' => 'बैकग्राउंड जॉब और वे कब चलते हैं। कोई भी मॉड्यूल एक रजिस्टर कर सकता है — Backup, सफाई, रिपोर्ट।',
    'schedule.card.jobs' => 'शेड्यूल किए गए जॉब',
    'schedule.empty'     => 'अभी तक कोई जॉब रजिस्टर नहीं हुआ है।',
    'schedule.col.job'      => 'जॉब',
    'schedule.col.schedule' => 'शेड्यूल',
    'schedule.col.next_run' => 'अगली बार',
    'schedule.col.last_run' => 'पिछली बार',
    'schedule.col.actions'  => 'क्रियाएँ',
    'schedule.badge.disabled' => 'अक्षम',
    'schedule.last.never'     => 'कभी नहीं',
    'schedule.action.edit_title' => 'शेड्यूल संपादित करें',
    'schedule.action.run_now'    => 'अभी चलाएँ',
    'schedule.nav.label' => 'शेड्यूलर',
];
