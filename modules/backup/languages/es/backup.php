<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
return [
    // Service / API messages
    'backup.done'             => 'Copia de seguridad completada.',
    'backup.failed'           => 'La copia de seguridad falló. Revisa los registros para más detalles.',
    'backup.deleted'          => 'Copia de seguridad eliminada.',
    'backup.restore.done'     => 'Restauración completada.',
    'backup.restore.failed'   => 'La restauración falló. Tu copia de seguridad previa está disponible.',
    'backup.restore.confirm'  => 'Escribe RESTORE para confirmar esta acción destructiva.',
    'backup.settings.saved'   => 'Ajustes de copia de seguridad guardados.',
    'backup.bad_component'    => 'Selecciona al menos un componente para respaldar.',
    'backup.bad_disk'         => 'Destino de copia de seguridad desconocido.',
    'backup.bad_email'        => 'Introduce direcciones de correo válidas, separadas por comas.',
    'backup.not_found'        => 'No se encontró esa copia de seguridad.',
    'backup.upload.failed'    => 'La subida no se completó.',
    'backup.upload.invalid'   => 'Ese archivo no es un archivo de TigerBackup.',

    // Component labels
    'backup.comp.database'      => 'Base de datos',
    'backup.comp.database_desc' => 'Todas las tablas — un volcado SQL portable',
    'backup.comp.media'         => 'Medios',
    'backup.comp.media_desc'    => 'Archivos subidos',
    'backup.comp.modules'       => 'Módulos',
    'backup.comp.modules_desc'  => 'Los módulos de tu aplicación',
    'backup.comp.platform'      => 'Plataforma',
    'backup.comp.platform_desc' => 'Código de la aplicación + configuración (para mover un sitio)',

    // Outcome badges
    'backup.outcome.ok'      => 'OK',
    'backup.outcome.error'   => 'Falló',
    'backup.outcome.running' => 'En ejecución',

    // Screen header
    'backup.title'      => 'Copia de seguridad y restauración',
    'backup.subtitle'   => 'Archiva tu sitio en un zip descargable — mantenlo local o envíalo al almacenamiento en la nube. Restaura aquí, o traslada el sitio a un nuevo hogar.',
    'backup.action.run' => 'Hacer copia ahora',

    // Create card
    'backup.card.create'             => 'Crear una copia de seguridad',
    'backup.create.include_label'     => 'Qué incluir',
    'backup.create.destination_label' => 'Destino',
    'backup.create.destination_help'  => 'Configura un <em>disco de medios</em> en la nube (S3/GCS/Azure) para respaldar fuera del servidor.',
    'backup.create.secrets_label'     => 'Incluir secretos (local.ini)',
    'backup.create.secrets_help'      => 'Necesario para mover un sitio intacto. Maneja el archivo de forma segura.',

    // Restore-from-a-file card
    'backup.card.restore_file'   => 'Restaurar desde un archivo',
    'backup.restore_file.help'   => 'Sube un <code>TigerBackup-*.zip</code> para restaurarlo aquí — la forma de trasladar un sitio a una instalación nueva. Esto es <strong>destructivo</strong>: se ejecuta en modo de mantenimiento y toma una copia de seguridad de respaldo primero.',
    'backup.action.restore'      => 'Restaurar',

    // History card
    'backup.card.history'         => 'Copias de seguridad',
    'backup.history.empty'        => 'Aún no hay copias de seguridad. Elige qué incluir y haz clic en <strong>Hacer copia ahora</strong>.',
    'backup.col.archive'          => 'Archivo',
    'backup.col.size'             => 'Tamaño',
    'backup.col.includes'         => 'Incluye',
    'backup.col.when'             => 'Cuándo',
    'backup.col.where'            => 'Dónde',
    'backup.col.actions'          => 'Acciones',
    'backup.pinned_title'         => 'Las copias manuales nunca se eliminan automáticamente',
    'backup.action.download_title' => 'Descargar',
    'backup.action.restore_title'  => 'Restaurar esta copia de seguridad',
    'backup.action.delete_title'   => 'Eliminar',

    // Scheduled backups card
    'backup.card.scheduled'          => 'Copias de seguridad programadas',
    'backup.scheduled.help'          => 'Define una cadencia y Tiger hace copias por sí solo. La retención continua conserva las <strong>N</strong> copias programadas más recientes; las copias manuales nunca se eliminan automáticamente.',
    'backup.scheduled.schedule_label' => 'Programación',
    'backup.scheduled.retention_label' => 'Conservar las más recientes (máximo continuo)',
    'backup.scheduled.retention_help' => '0 = conservar todas.',
    'backup.scheduled.email_label'    => 'Enviar estado por correo a',
    'backup.scheduled.notify_label'   => 'Enviar correo en caso de éxito y de fallo',
    'backup.scheduled.note'           => 'Las copias programadas usan los componentes y el destino seleccionados en <em>Crear una copia de seguridad</em> arriba, guardados aquí:',
    'backup.action.save_settings'     => 'Guardar ajustes de programación',

    // Restore confirm modal
    'backup.restore_modal.title'         => 'Confirmar restauración',
    'backup.action.close'                => 'Cerrar',
    'backup.restore_modal.body_pre'      => 'Estás a punto de restaurar ',
    'backup.restore_modal.body_post'     => '. Esto <strong>sobrescribe</strong> la base de datos y/o los archivos actuales y no se puede deshacer. Primero se toma una copia de seguridad de respaldo, y el sitio entra en modo de mantenimiento durante la restauración.',
    'backup.restore_modal.confirm_label' => 'Escribe <code>RESTORE</code> para confirmar',
    'backup.action.cancel'               => 'Cancelar',
    'backup.action.restore_now'          => 'Restaurar ahora',
];
