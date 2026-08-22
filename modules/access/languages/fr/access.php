<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — French strings (language-only locale `fr`). Mirrors en/access.php.
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'Nom',
    'access.label.slug'          => 'Slug',
    'access.label.status'        => 'Statut',
    'access.label.created'       => 'Créé',
    'access.label.email'         => 'E-mail',
    'access.label.username'      => 'Nom d\'utilisateur',
    'access.label.parent'        => 'Parent',
    'access.label.members'       => 'Membres',
    'access.label.roles'         => 'Rôles',
    'access.label.orgs'          => 'Orgs',
    'access.label.actions'       => 'Actions',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'Enregistrer',
    'access.action.cancel'       => 'Annuler',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'Tous les statuts',
    'access.filter.clear'        => 'Effacer',
    'access.filter.clear_title'  => 'Effacer les filtres',

    // --- Status values ---
    'access.status.active'       => 'Actif',
    'access.status.suspended'    => 'Suspendu',

    // --- Users: list ---
    'access.user.list.title'     => 'Utilisateurs',
    'access.user.list.subtitle'  => 'Identités — e-mail, nom d\'utilisateur, statut et appartenance.',
    'access.user.list.new'       => 'Nouvel utilisateur',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'Nouvel utilisateur',
    'access.user.edit.title_edit' => 'Modifier l\'utilisateur',
    'access.user.edit.back'       => 'Retour aux utilisateurs',
    'access.user.field.email_help'          => 'L\'identifiant de connexion canonique. Doit être unique.',
    'access.user.field.username_help'       => 'Facultatif. Unique s\'il est défini.',
    'access.user.field.language'            => 'Langue',
    'access.user.field.language_help'       => 'La langue préférée de l\'utilisateur.',
    'access.user.field.timezone'            => 'Fuseau horaire',
    'access.user.field.timezone_placeholder'=> 'Rechercher par ville, abréviation (EST) ou décalage (-05:00)…',
    'access.user.field.password'            => 'Définir le mot de passe',
    'access.user.field.password_help'       => 'Laisser vide pour conserver le mot de passe actuel. Le définir ici le réinitialise immédiatement.',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'Utilisateur enregistré.',
    'access.user.deleted'        => 'Utilisateur supprimé.',
    'access.user.email_taken'    => 'Cet e-mail est déjà utilisé.',
    'access.user.username_taken' => 'Ce nom d\'utilisateur est déjà utilisé.',
    'access.user.no_self_delete' => 'Vous ne pouvez pas supprimer votre propre compte.',

    // --- Organizations: list ---
    'access.org.list.title'      => 'Organisations',
    'access.org.list.subtitle'   => 'Locataires — nom, slug, hiérarchie et appartenance.',
    'access.org.list.new'        => 'Nouvelle organisation',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'Nouvelle organisation',
    'access.org.edit.title_edit' => 'Modifier l\'organisation',
    'access.org.edit.back'       => 'Retour aux organisations',
    'access.org.field.slug_help'    => 'Identifiant compatible avec les URL. Dérivé du nom s\'il est laissé vide ; doit être unique.',
    'access.org.field.parent'       => 'Organisation parente',
    'access.org.field.parent_help'  => 'Pour les sous-locataires ; laisser sur « aucune » pour une organisation racine.',
    'access.org.parent.none'        => '— aucune (organisation racine) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'Organisation enregistrée.',
    'access.org.deleted'         => 'Organisation supprimée.',
    'access.org.slug_taken'      => 'Ce slug est déjà utilisé.',
    'access.org.slug_required'   => 'Un slug est requis (ou fournissez un nom pour en dériver un).',
    'access.org.parent_self'     => 'Une organisation ne peut pas être son propre parent.',
    'access.org.no_self_delete'  => 'Vous ne pouvez pas supprimer l\'organisation dans laquelle vous agissez actuellement.',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'Rechercher nom / slug…',
    'access.js.search_users'        => 'Rechercher e-mail / nom d\'utilisateur…',
    'access.js.edit'                => 'Modifier',
    'access.js.delete'              => 'Supprimer',
    'access.js.org_no_delete'       => 'Votre organisation active ne peut pas être supprimée',
    'access.js.delete_self'         => 'Vous ne pouvez pas vous supprimer vous-même',
    'access.js.not_permitted'       => 'Non autorisé',
    'access.js.confirm_delete_org'  => 'Supprimer cette organisation ? Elle est supprimée de façon réversible et peut être récupérée.',
    'access.js.confirm_delete_user' => 'Supprimer cet utilisateur ? Il est supprimé de façon réversible et peut être récupéré.',
    'access.js.fix_fields'          => 'Veuillez corriger les champs en surbrillance et réessayer.',
    'access.js.network_error'       => 'Erreur réseau — veuillez réessayer.',
    'access.js.parent_root'         => '— racine —',
];
