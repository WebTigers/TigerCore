<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'Sauvegarde terminée.',
    'backup.failed'           => 'La sauvegarde a échoué. Consultez les journaux pour plus de détails.',
    'backup.deleted'          => 'Sauvegarde supprimée.',
    'backup.restore.done'     => 'Restauration terminée.',
    'backup.restore.failed'   => 'La restauration a échoué. Votre sauvegarde de sécurité pré-restauration est disponible.',
    'backup.restore.confirm'  => 'Saisissez RESTORE pour confirmer cette action destructive.',
    'backup.settings.saved'   => 'Paramètres de sauvegarde enregistrés.',
    'backup.bad_component'    => 'Sélectionnez au moins un composant à sauvegarder.',
    'backup.bad_disk'         => 'Destination de sauvegarde inconnue.',
    'backup.bad_email'        => 'Saisissez une ou plusieurs adresses e-mail valides, séparées par des virgules.',
    'backup.not_found'        => 'Cette sauvegarde est introuvable.',
    'backup.upload.failed'    => 'Le téléversement ne s\'est pas terminé.',
    'backup.upload.invalid'   => 'Ce fichier n\'est pas une archive TigerBackup.',

    // Component labels
    'backup.comp.database'      => 'Base de données',
    'backup.comp.database_desc' => 'Toutes les tables — un dump SQL portable',
    'backup.comp.media'         => 'Médias',
    'backup.comp.media_desc'    => 'Fichiers téléversés',
    'backup.comp.modules'       => 'Modules',
    'backup.comp.modules_desc'  => 'Les modules de votre application',
    'backup.comp.platform'      => 'Plateforme',
    'backup.comp.platform_desc' => 'Code de l\'application + configuration (pour déplacer un site)',

    // Outcome badges
    'backup.outcome.ok'      => 'OK',
    'backup.outcome.error'   => 'Échec',
    'backup.outcome.running' => 'En cours',

    // Screen header
    'backup.title'      => 'Sauvegarde &amp; restauration',
    'backup.subtitle'   => 'Archivez votre site dans un zip téléchargeable — gardez-le en local ou envoyez-le vers un stockage cloud. Restaurez ici, ou déplacez le site vers un nouvel emplacement.',
    'backup.action.run' => 'Sauvegarder maintenant',

    // Create card
    'backup.card.create'             => 'Créer une sauvegarde',
    'backup.create.include_label'     => 'Que sauvegarder',
    'backup.create.destination_label' => 'Destination',
    'backup.create.destination_help'  => 'Configurez un <em>disque de médias</em> cloud (S3/GCS/Azure) pour sauvegarder hors du serveur.',
    'backup.create.secrets_label'     => 'Inclure les secrets (local.ini)',
    'backup.create.secrets_help'      => 'Nécessaire pour déplacer un site à l\'identique. Manipulez l\'archive en toute sécurité.',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'Restaurer depuis un fichier',
    'backup.restore_file.help'   => 'Téléversez un <code>TigerBackup-*.zip</code> pour le restaurer ici — la façon de déplacer un site vers une installation neuve. Ceci est <strong>destructif</strong> : cela s\'exécute en mode maintenance et prend d\'abord une sauvegarde de sécurité.',
    'backup.action.restore'      => 'Restaurer',

    // History card
    'backup.card.history'         => 'Sauvegardes',
    'backup.history.empty'        => 'Aucune sauvegarde pour l\'instant. Choisissez quoi inclure et cliquez sur <strong>Sauvegarder maintenant</strong>.',
    'backup.col.archive'          => 'Archive',
    'backup.col.size'             => 'Taille',
    'backup.col.includes'         => 'Contient',
    'backup.col.when'             => 'Quand',
    'backup.col.where'            => 'Où',
    'backup.col.actions'          => 'Actions',
    'backup.pinned_title'         => 'Les sauvegardes manuelles ne sont jamais purgées automatiquement',
    'backup.action.download_title' => 'Télécharger',
    'backup.action.restore_title'  => 'Restaurer cette sauvegarde',
    'backup.action.delete_title'   => 'Supprimer',

    // Scheduled backups card
    'backup.card.scheduled'          => 'Sauvegardes planifiées',
    'backup.scheduled.help'          => 'Définissez une cadence et Tiger sauvegarde tout seul. La rétention glissante conserve les <strong>N</strong> sauvegardes planifiées les plus récentes ; les sauvegardes manuelles ne sont jamais supprimées automatiquement.',
    'backup.scheduled.schedule_label' => 'Planification',
    'backup.scheduled.retention_label' => 'Conserver les plus récentes (maximum glissant)',
    'backup.scheduled.retention_help' => '0 = tout conserver.',
    'backup.scheduled.email_label'    => 'Envoyer le statut par e-mail à',
    'backup.scheduled.notify_label'   => 'E-mail en cas de succès &amp; d\'échec',
    'backup.scheduled.note'           => 'Les sauvegardes planifiées utilisent les composants &amp; la destination sélectionnés dans <em>Créer une sauvegarde</em> ci-dessus, enregistrés ici :',
    'backup.action.save_settings'     => 'Enregistrer les paramètres de planification',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'Confirmer la restauration',
    'backup.action.close'                => 'Fermer',
    'backup.restore_modal.body_pre'      => 'Vous êtes sur le point de restaurer ',
    'backup.restore_modal.body_post'     => '. Ceci <strong>écrase</strong> la base de données et/ou les fichiers actuels et ne peut pas être annulé. Une sauvegarde de sécurité est prise en premier, et le site passe en mode maintenance pendant la restauration.',
    'backup.restore_modal.confirm_label' => 'Saisissez <code>RESTORE</code> pour confirmer',
    'backup.action.cancel'               => 'Annuler',
    'backup.action.restore_now'          => 'Restaurer maintenant',
    'backup.js.select_component'     => 'Sélectionnez au moins un composant.',
    'backup.js.confirm_delete_named' => 'Supprimer %s ? Cela retire définitivement l\'archive.',
    'backup.js.choose_zip'           => 'Choisissez d\'abord une archive .zip.',
    'backup.nav.label' => 'Sauvegarde',
];
