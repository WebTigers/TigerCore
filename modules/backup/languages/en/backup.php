<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'Backup complete.',
    'backup.failed'           => 'The backup failed. Check the logs for details.',
    'backup.deleted'          => 'Backup deleted.',
    'backup.restore.done'     => 'Restore complete.',
    'backup.restore.failed'   => 'The restore failed. Your pre-restore safety backup is available.',
    'backup.restore.confirm'  => 'Type RESTORE to confirm this destructive action.',
    'backup.settings.saved'   => 'Backup settings saved.',
    'backup.bad_component'    => 'Select at least one component to back up.',
    'backup.bad_disk'         => 'Unknown backup destination.',
    'backup.bad_email'        => 'Enter valid email address(es), comma-separated.',
    'backup.not_found'        => 'That backup could not be found.',
    'backup.upload.failed'    => 'The upload did not complete.',
    'backup.upload.invalid'   => 'That file is not a TigerBackup archive.',

    // Component labels
    'backup.comp.database'      => 'Database',
    'backup.comp.database_desc' => 'All tables — a portable SQL dump',
    'backup.comp.media'         => 'Media',
    'backup.comp.media_desc'    => 'Uploaded files',
    'backup.comp.modules'       => 'Modules',
    'backup.comp.modules_desc'  => 'Your app modules',
    'backup.comp.platform'      => 'Platform',
    'backup.comp.platform_desc' => 'App code + config (for moving a site)',

    // Outcome badges
    'backup.outcome.ok'      => 'OK',
    'backup.outcome.error'   => 'Failed',
    'backup.outcome.running' => 'Running',

    // Screen header
    'backup.title'      => 'Backup &amp; Restore',
    'backup.subtitle'   => 'Archive your site to a downloadable zip — keep it local or push it to cloud storage. Restore here, or move the site to a new home.',
    'backup.action.run' => 'Back up now',

    // Create card
    'backup.card.create'             => 'Create a backup',
    'backup.create.include_label'     => 'What to include',
    'backup.create.destination_label' => 'Destination',
    'backup.create.destination_help'  => 'Configure a cloud <em>media disk</em> (S3/GCS/Azure) to back up off-server.',
    'backup.create.secrets_label'     => 'Include secrets (local.ini)',
    'backup.create.secrets_help'      => 'Needed to move a site intact. Handle the archive securely.',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'Restore from a file',
    'backup.restore_file.help'   => 'Upload a <code>TigerBackup-*.zip</code> to restore it here — the way you move a site onto a fresh install. This is <strong>destructive</strong>: it runs under maintenance mode and takes a safety backup first.',
    'backup.action.restore'      => 'Restore',

    // History card
    'backup.card.history'         => 'Backups',
    'backup.history.empty'        => 'No backups yet. Choose what to include and click <strong>Back up now</strong>.',
    'backup.col.archive'          => 'Archive',
    'backup.col.size'             => 'Size',
    'backup.col.includes'         => 'Includes',
    'backup.col.when'             => 'When',
    'backup.col.where'            => 'Where',
    'backup.col.actions'          => 'Actions',
    'backup.pinned_title'         => 'Manual backups are never auto-pruned',
    'backup.action.download_title' => 'Download',
    'backup.action.restore_title'  => 'Restore this backup',
    'backup.action.delete_title'   => 'Delete',

    // Scheduled backups card
    'backup.card.scheduled'          => 'Scheduled backups',
    'backup.scheduled.help'          => 'Set a cadence and Tiger backs up on its own. Rolling retention keeps the newest <strong>N</strong> scheduled backups; manual backups are never auto-removed.',
    'backup.scheduled.schedule_label' => 'Schedule',
    'backup.scheduled.retention_label' => 'Keep newest (rolling max)',
    'backup.scheduled.retention_help' => '0 = keep all.',
    'backup.scheduled.email_label'    => 'Email status to',
    'backup.scheduled.notify_label'   => 'Email on success &amp; failure',
    'backup.scheduled.note'           => 'Scheduled backups use the components &amp; destination selected in <em>Create a backup</em> above, saved here:',
    'backup.action.save_settings'     => 'Save schedule settings',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'Confirm restore',
    'backup.action.close'                => 'Close',
    'backup.restore_modal.body_pre'      => 'You\'re about to restore ',
    'backup.restore_modal.body_post'     => '. This <strong>overwrites</strong> the current database and/or files and can\'t be undone. A safety backup is taken first, and the site enters maintenance mode during the restore.',
    'backup.restore_modal.confirm_label' => 'Type <code>RESTORE</code> to confirm',
    'backup.action.cancel'               => 'Cancel',
    'backup.action.restore_now'          => 'Restore now',
    'backup.js.select_component'     => 'Select at least one component.',
    'backup.js.confirm_delete_named' => 'Delete %s? This removes the archive permanently.',
    'backup.js.choose_zip'           => 'Choose a .zip archive first.',
];
