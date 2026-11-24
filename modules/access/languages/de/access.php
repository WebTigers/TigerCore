<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — German strings (language-only locale `de`). Mirrors en/access.php.
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'Name',
    'access.label.slug'          => 'Slug',
    'access.label.status'        => 'Status',
    'access.label.created'       => 'Erstellt',
    'access.label.email'         => 'E-Mail',
    'access.label.username'      => 'Benutzername',
    'access.label.parent'        => 'Übergeordnet',
    'access.label.members'       => 'Mitglieder',
    'access.label.roles'         => 'Rollen',
    'access.label.orgs'          => 'Organisationen',
    'access.label.actions'       => 'Aktionen',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'Speichern',
    'access.action.cancel'       => 'Abbrechen',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'Alle Status',
    'access.filter.clear'        => 'Zurücksetzen',
    'access.filter.clear_title'  => 'Filter zurücksetzen',

    // --- Status values ---
    'access.status.active'       => 'Aktiv',
    'access.status.suspended'    => 'Gesperrt',

    // --- Users: list ---
    'access.user.list.title'     => 'Benutzer',
    'access.user.list.subtitle'  => 'Identitäten — E-Mail, Benutzername, Status und Mitgliedschaft.',
    'access.user.list.new'       => 'Neuer Benutzer',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'Neuer Benutzer',
    'access.user.edit.title_edit' => 'Benutzer bearbeiten',
    'access.user.edit.back'       => 'Zurück zu den Benutzern',
    'access.user.field.email_help'          => 'Die kanonische Anmelde-Kennung. Muss eindeutig sein.',
    'access.user.field.username_help'       => 'Optional. Eindeutig, falls gesetzt.',
    'access.user.field.language'            => 'Sprache',
    'access.user.field.language_help'       => 'Die bevorzugte Sprache des Benutzers.',
    'access.user.field.timezone'            => 'Zeitzone',
    'access.user.field.timezone_placeholder'=> 'Suche nach Stadt, Abkürzung (EST) oder Zeitverschiebung (-05:00)…',
    'access.user.field.password'            => 'Passwort festlegen',
    'access.user.field.password_help'       => 'Leer lassen, um das aktuelle Passwort beizubehalten. Ein hier gesetztes Passwort wird sofort zurückgesetzt.',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'Benutzer gespeichert.',
    'access.user.deleted'        => 'Benutzer gelöscht.',
    'access.user.email_taken'    => 'Diese E-Mail wird bereits verwendet.',
    'access.user.username_taken' => 'Dieser Benutzername wird bereits verwendet.',
    'access.user.no_self_delete' => 'Sie können Ihr eigenes Konto nicht löschen.',
    'access.user.role_not_manageable' => 'Sie können keinen Benutzer verwalten, der eine Rolle innehat, die Sie nicht verwalten dürfen.',

    // --- Organizations: list ---
    'access.org.list.title'      => 'Organisationen',
    'access.org.list.subtitle'   => 'Mandanten — Name, Slug, Hierarchie und Mitgliedschaft.',
    'access.org.list.new'        => 'Neue Organisation',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'Neue Organisation',
    'access.org.edit.title_edit' => 'Organisation bearbeiten',
    'access.org.edit.back'       => 'Zurück zu den Organisationen',
    'access.org.field.slug_help'    => 'URL-sichere Kennung. Wird aus dem Namen abgeleitet, wenn leer gelassen; muss eindeutig sein.',
    'access.org.field.parent'       => 'Übergeordnete Organisation',
    'access.org.field.parent_help'  => 'Für Untermandanten; „keine“ lassen für eine Stammorganisation.',
    'access.org.parent.none'        => '— keine (Stammorganisation) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'Organisation gespeichert.',
    'access.org.deleted'         => 'Organisation gelöscht.',
    'access.org.slug_taken'      => 'Dieser Slug wird bereits verwendet.',
    'access.org.slug_required'   => 'Ein Slug ist erforderlich (oder geben Sie einen Namen an, aus dem einer abgeleitet wird).',
    'access.org.parent_self'     => 'Eine Organisation kann sich nicht selbst übergeordnet sein.',
    'access.org.no_self_delete'  => 'Sie können die Organisation, in der Sie gerade agieren, nicht löschen.',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'Name / Slug suchen…',
    'access.js.search_users'        => 'E-Mail / Benutzername suchen…',
    'access.js.edit'                => 'Bearbeiten',
    'access.js.delete'              => 'Löschen',
    'access.js.org_no_delete'       => 'Ihre aktive Organisation kann nicht gelöscht werden',
    'access.js.delete_self'         => 'Sie können sich nicht selbst löschen',
    'access.js.not_permitted'       => 'Nicht erlaubt',
    'access.js.confirm_delete_org'  => 'Diese Organisation löschen? Sie wird per Soft-Delete entfernt und kann wiederhergestellt werden.',
    'access.js.confirm_delete_user' => 'Diesen Benutzer löschen? Er wird per Soft-Delete entfernt und kann wiederhergestellt werden.',
    'access.js.fix_fields'          => 'Bitte korrigieren Sie die markierten Felder und versuchen Sie es erneut.',
    'access.js.network_error'       => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'access.js.parent_root'         => '— Stamm —',

    // --- Users: add/edit modal (memberships, deleted, welcome email) ---
    'access.status.deleted' => 'Gelöscht',
    'access.user.not_found' => 'Diesen Benutzer gibt es nicht mehr.',
    'access.user.email_deleted' => 'Ein gelöschter Benutzer hat diese E-Mail – zeige gelöschte Benutzer an und stelle ihn wieder her.',
    'access.user.username_deleted' => 'Ein gelöschter Benutzer hat diesen Benutzernamen – stelle ihn stattdessen wieder her.',
    'access.user.no_self_suspend' => 'Du kannst dein eigenes Konto nicht sperren.',
    'access.user.no_self_memberships' => 'Du kannst deine eigenen Mitgliedschaften nicht ändern – frag einen anderen Admin.',
    'access.user.membership.invalid' => 'Die Mitgliedschaften konnten nicht gelesen werden – neu laden und erneut versuchen.',
    'access.user.membership.required' => 'Füge mindestens eine Organisationsmitgliedschaft hinzu.',
    'access.user.membership.org' => 'Eine dieser Organisationen ist nicht verfügbar.',
    'access.user.membership.role' => 'Du kannst eine dieser Rollen nicht vergeben.',
    'access.user.created' => 'Benutzer angelegt.',
    'access.user.created_invited' => 'Benutzer angelegt – er hat einen Link erhalten, um sein Passwort festzulegen.',
    'access.user.modal.title_new' => 'Benutzer hinzufügen',
    'access.user.modal.title_edit' => 'Benutzer bearbeiten',
    'access.user.tab.details' => 'Details',
    'access.user.tab.signin' => 'Anmeldung & Status',
    'access.user.tab.memberships' => 'Mitgliedschaften',
    'access.user.field.deleted' => 'Gelöscht',
    'access.user.field.deleted_help' => 'Ein gelöschter Benutzer kann sich nicht anmelden und ist ausgeblendet. Ausschalten zum Wiederherstellen.',
    'access.user.field.password_new_help' => 'Leer lassen, um einen Link zum Festlegen des Passworts zu senden.',
    'access.user.membership.org_label' => 'Organisation',
    'access.user.membership.role_label' => 'Rolle',
    'access.user.membership.add' => 'Mitgliedschaft hinzufügen',
    'access.user.membership.none' => 'Noch keine Mitgliedschaften.',
    'access.user.membership.self_note' => 'Du kannst deine eigenen Mitgliedschaften nicht ändern.',
    'access.js.membership_remove' => 'Entfernen',
    'access.js.org_search' => 'Organisationen suchen…',
    'access.js.pick_org' => 'Wähle zuerst eine Organisation.',
    'access.js.loading' => 'Wird geladen…',
];
