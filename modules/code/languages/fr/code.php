<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — French strings (code.*). Same key set as languages/en/code.php.
 */
return [
    // API responses
    'code.saved'       => 'Extrait enregistré.',
    'code.activated'   => 'Extrait activé — en ligne maintenant.',
    'code.deactivated' => 'Extrait désactivé.',
    'code.deleted'     => 'Extrait supprimé.',
    'code.restored'    => 'Extrait restauré à la version sélectionnée.',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'Non enregistré —',
    'code.error.saved_not_activated'      => 'Enregistré, mais non activé — il entre en conflit avec le jeu en cours d’exécution :',
    'code.error.cannot_activate'          => 'Impossible d’activer —',
    'code.error.cannot_activate_conflict' => 'Impossible d’activer — il entre en conflit avec le jeu en cours d’exécution :',
    'code.error.snippet_unavailable'      => 'Cet extrait n’est plus disponible — le module a peut-être été supprimé.',

    // admin list
    'code.list.title'  => 'Code',
    'code.list.new'    => 'Nouvel extrait',
    'code.list.subtitle_a'       => 'Extraits PHP qui s’exécutent sur toute la plateforme — compilés + mis en cache, exécutés à chaque requête. Les extraits locaux sont stockés en base de données ;',
    'code.list.subtitle_b'       => 'les extraits proviennent des modules de code installés (lisez le code source avant d’activer).',
    'code.list.badge_module'     => 'module',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'Nom',
    'code.list.col_lang'     => 'Langage',
    'code.list.col_runs'     => 'Exécutions',
    'code.list.col_priority' => 'Priorité',
    'code.list.col_state'    => 'État',
    'code.list.col_updated'  => 'Mis à jour',
    'code.list.col_actions'  => 'Actions',

    // view-source modal
    'code.source.title'    => 'Code source de l’extrait',
    'code.source.close'    => 'Fermer',
    'code.source.warn'     => 'L’activation exécute ce PHP dans votre application.',
    'code.source.activate' => 'Activer',

    // snippet editor
    'code.edit.edit_title' => 'Modifier l’extrait',
    'code.edit.new_title'  => 'Nouvel extrait',
    'code.edit.back'       => 'Retour au code',
    'code.edit.cancel'     => 'Annuler',
    'code.edit.save'       => 'Enregistrer',
    'code.edit.warn'       => 'Ce PHP s’exécute à <strong>chaque requête</strong> une fois actif. Il est vérifié à l’enregistrement et se désactive automatiquement s’il génère une erreur fatale au chargement.',
    'code.edit.name'       => 'Nom',
    'code.edit.code'       => 'Code',
    'code.edit.type'       => 'Type',
    'code.edit.language'   => 'Langage',
    'code.edit.inject_at'  => 'Injecter à',
    'code.edit.inject_hint'      => 'Où le CSS/JS/HTML/PHTML injecté est inséré.',
    'code.edit.activation'       => 'Activation',
    'code.edit.active_label'     => 'Actif — exécuter cet extrait',
    'code.edit.priority'         => 'Priorité',
    'code.edit.priority_hint'    => 'Une valeur plus faible se charge en premier. S’exécute globalement (à chaque requête).',
    'code.edit.notes'            => 'Notes',
    'code.edit.description'      => 'Description',
    'code.edit.description_hint' => 'Ce que fait cet extrait (pour la liste).',

    // snippet editor — version history
    'code.edit.versions'       => 'Historique des versions',
    'code.edit.col_version'    => 'Version',
    'code.edit.col_name'       => 'Nom',
    'code.edit.col_state'      => 'État',
    'code.edit.col_saved'      => 'Enregistré',
    'code.edit.state_active'   => 'Actif',
    'code.edit.state_inactive' => 'Inactif',
    'code.edit.untitled'       => '(sans titre)',
    'code.edit.restore'        => 'Restaurer',

    // form — language select
    'code.lang.php'   => 'PHP — s’exécute à chaque requête (fonctions/hooks)',
    'code.lang.phtml' => 'PHTML — rendu + injecté',
    'code.lang.html'  => 'HTML — injecté tel quel',
    'code.lang.css'   => 'CSS — injecté comme feuille de style',
    'code.lang.js'    => 'JavaScript — injecté comme script',

    // form — inject-at select
    'code.auto.head'   => 'En-tête',
    'code.auto.footer' => 'Pied de page',
    'code.js.fix_form' => 'Veuillez vérifier le formulaire et réessayer.',
    'code.js.network_error' => 'Erreur réseau — veuillez réessayer.',
    'code.js.confirm_restore' => 'Restaurer la version n° %s ? Le contenu actuel est d’abord enregistré comme une nouvelle version.',
    'code.js.confirm_delete_snippet' => 'Supprimer cet extrait ? Il est supprimé de façon réversible et peut être récupéré.',
];
