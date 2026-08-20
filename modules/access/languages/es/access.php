<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — Spanish strings (language-only locale `es`). Mirrors en/access.php.
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'Nombre',
    'access.label.slug'          => 'Identificador',
    'access.label.status'        => 'Estado',
    'access.label.created'       => 'Creado',
    'access.label.email'         => 'Correo electrónico',
    'access.label.username'      => 'Nombre de usuario',
    'access.label.parent'        => 'Padre',
    'access.label.members'       => 'Miembros',
    'access.label.roles'         => 'Roles',
    'access.label.orgs'          => 'Organizaciones',
    'access.label.actions'       => 'Acciones',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'Guardar',
    'access.action.cancel'       => 'Cancelar',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'Todos los estados',
    'access.filter.clear'        => 'Limpiar',
    'access.filter.clear_title'  => 'Limpiar filtros',

    // --- Status values ---
    'access.status.active'       => 'Activo',
    'access.status.suspended'    => 'Suspendido',

    // --- Users: list ---
    'access.user.list.title'     => 'Usuarios',
    'access.user.list.subtitle'  => 'Identidades: correo electrónico, nombre de usuario, estado y membresía.',
    'access.user.list.new'       => 'Nuevo usuario',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'Nuevo usuario',
    'access.user.edit.title_edit' => 'Editar usuario',
    'access.user.edit.back'       => 'Volver a los usuarios',
    'access.user.field.email_help'          => 'El identificador de inicio de sesión canónico. Debe ser único.',
    'access.user.field.username_help'       => 'Opcional. Único si se establece.',
    'access.user.field.language'            => 'Idioma',
    'access.user.field.language_help'       => 'El idioma preferido del usuario.',
    'access.user.field.timezone'            => 'Zona horaria',
    'access.user.field.timezone_placeholder'=> 'Busca por ciudad, abreviatura (EST) o desfase (-05:00)…',
    'access.user.field.password'            => 'Establecer contraseña',
    'access.user.field.password_help'       => 'Déjalo en blanco para mantener la contraseña actual. Establecerla aquí la restablece de inmediato.',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'Usuario guardado.',
    'access.user.deleted'        => 'Usuario eliminado.',
    'access.user.email_taken'    => 'Ese correo electrónico ya está en uso.',
    'access.user.username_taken' => 'Ese nombre de usuario ya está en uso.',
    'access.user.no_self_delete' => 'No puedes eliminar tu propia cuenta.',

    // --- Organizations: list ---
    'access.org.list.title'      => 'Organizaciones',
    'access.org.list.subtitle'   => 'Inquilinos: nombre, identificador, jerarquía y membresía.',
    'access.org.list.new'        => 'Nueva organización',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'Nueva organización',
    'access.org.edit.title_edit' => 'Editar organización',
    'access.org.edit.back'       => 'Volver a las organizaciones',
    'access.org.field.slug_help'    => 'Identificador apto para URL. Se deriva del nombre si se deja en blanco; debe ser único.',
    'access.org.field.parent'       => 'Organización padre',
    'access.org.field.parent_help'  => 'Para subinquilinos; deja «ninguna» para una organización raíz.',
    'access.org.parent.none'        => '— ninguna (organización raíz) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'Organización guardada.',
    'access.org.deleted'         => 'Organización eliminada.',
    'access.org.slug_taken'      => 'Ese identificador (slug) ya está en uso.',
    'access.org.slug_required'   => 'Se requiere un identificador (o proporciona un nombre para derivarlo).',
    'access.org.parent_self'     => 'Una organización no puede ser su propio padre.',
    'access.org.no_self_delete'  => 'No puedes eliminar la organización en la que estás actuando.',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'Buscar nombre / identificador…',
    'access.js.search_users'        => 'Buscar correo electrónico / nombre de usuario…',
    'access.js.edit'                => 'Editar',
    'access.js.delete'              => 'Eliminar',
    'access.js.org_no_delete'       => 'No se puede eliminar tu organización activa',
    'access.js.delete_self'         => 'No puedes eliminarte a ti mismo',
    'access.js.not_permitted'       => 'No permitido',
    'access.js.confirm_delete_org'  => '¿Eliminar esta organización? Se elimina de forma reversible y se puede recuperar.',
    'access.js.confirm_delete_user' => '¿Eliminar este usuario? Se elimina de forma reversible y se puede recuperar.',
    'access.js.fix_fields'          => 'Corrige los campos resaltados e inténtalo de nuevo.',
    'access.js.network_error'       => 'Error de red: inténtalo de nuevo.',
    'access.js.parent_root'         => '— raíz —',
];
