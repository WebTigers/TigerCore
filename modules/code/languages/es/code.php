<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — Spanish strings (code.*). Same key set as languages/en/code.php.
 */
return [
    // API responses
    'code.saved'       => 'Fragmento guardado.',
    'code.activated'   => 'Fragmento activado: ya está en marcha.',
    'code.deactivated' => 'Fragmento desactivado.',
    'code.deleted'     => 'Fragmento eliminado.',
    'code.restored'    => 'Fragmento restaurado a la versión seleccionada.',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'No se guardó:',
    'code.error.saved_not_activated'      => 'Guardado, pero no activado: entra en conflicto con el conjunto en ejecución:',
    'code.error.cannot_activate'          => 'No se puede activar:',
    'code.error.cannot_activate_conflict' => 'No se puede activar: entra en conflicto con el conjunto en ejecución:',
    'code.error.snippet_unavailable'      => 'Ese fragmento ya no está disponible: es posible que se haya eliminado el módulo.',

    // admin list
    'code.list.title'  => 'Código',
    'code.list.new'    => 'Nuevo fragmento',
    'code.list.subtitle_a'       => 'Fragmentos PHP que se ejecutan en toda la plataforma: compilados + en caché, ejecutados en cada solicitud. Los fragmentos locales se almacenan en la base de datos;',
    'code.list.subtitle_b'       => 'los fragmentos provienen de módulos de código instalados (lee el código fuente antes de activar).',
    'code.list.badge_module'     => 'módulo',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'Nombre',
    'code.list.col_lang'     => 'Idioma',
    'code.list.col_runs'     => 'Se ejecuta',
    'code.list.col_priority' => 'Prioridad',
    'code.list.col_state'    => 'Estado',
    'code.list.col_updated'  => 'Actualizado',
    'code.list.col_actions'  => 'Acciones',

    // view-source modal
    'code.source.title'    => 'Código fuente del fragmento',
    'code.source.close'    => 'Cerrar',
    'code.source.warn'     => 'Al activar, este PHP se ejecuta en tu aplicación.',
    'code.source.activate' => 'Activar',

    // snippet editor
    'code.edit.edit_title' => 'Editar fragmento',
    'code.edit.new_title'  => 'Nuevo fragmento',
    'code.edit.back'       => 'Volver al código',
    'code.edit.cancel'     => 'Cancelar',
    'code.edit.save'       => 'Guardar',
    'code.edit.warn'       => 'Este PHP se ejecuta en <strong>cada solicitud</strong> una vez activo. Se verifica al guardar y se desactiva automáticamente si falla al cargarse.',
    'code.edit.name'       => 'Nombre',
    'code.edit.code'       => 'Código',
    'code.edit.type'       => 'Tipo',
    'code.edit.language'   => 'Idioma',
    'code.edit.inject_at'  => 'Inyectar en',
    'code.edit.inject_hint'      => 'Dónde se inserta el CSS/JS/HTML/PHTML inyectado.',
    'code.edit.activation'       => 'Activación',
    'code.edit.active_label'     => 'Activo: ejecutar este fragmento',
    'code.edit.priority'         => 'Prioridad',
    'code.edit.priority_hint'    => 'Un valor menor se carga primero. Se ejecuta globalmente (en cada solicitud).',
    'code.edit.notes'            => 'Notas',
    'code.edit.description'      => 'Descripción',
    'code.edit.description_hint' => 'Qué hace este fragmento (para el listado).',

    // snippet editor — version history
    'code.edit.versions'       => 'Historial de versiones',
    'code.edit.col_version'    => 'Versión',
    'code.edit.col_name'       => 'Nombre',
    'code.edit.col_state'      => 'Estado',
    'code.edit.col_saved'      => 'Guardado',
    'code.edit.state_active'   => 'Activo',
    'code.edit.state_inactive' => 'Inactivo',
    'code.edit.untitled'       => '(sin título)',
    'code.edit.restore'        => 'Restaurar',

    // form — language select
    'code.lang.php'   => 'PHP: se ejecuta en cada solicitud (funciones/hooks)',
    'code.lang.phtml' => 'PHTML: renderizado + inyectado',
    'code.lang.html'  => 'HTML: inyectado tal cual',
    'code.lang.css'   => 'CSS: inyectado como hoja de estilos',
    'code.lang.js'    => 'JavaScript: inyectado como script',

    // form — inject-at select
    'code.auto.head'   => 'Cabecera',
    'code.auto.footer' => 'Pie de página',
    'code.js.fix_form' => 'Revisa el formulario e inténtalo de nuevo.',
    'code.js.network_error' => 'Error de red — inténtalo de nuevo.',
    'code.js.confirm_restore' => '¿Restaurar la versión n.º %s? El contenido actual se guarda primero como una nueva versión.',
    'code.js.confirm_delete_snippet' => '¿Eliminar este fragmento? Se elimina de forma reversible y se puede recuperar.',
];
