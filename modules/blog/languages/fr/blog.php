<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module — French strings (blog.*). Same key set as languages/en/blog.php.
 */
return [
    // API responses
    'blog.post.saved'    => 'Article enregistré.',
    'blog.post.deleted'  => 'Article supprimé.',
    'blog.post.restored' => 'Article restauré à la version sélectionnée.',
    'blog.error.slug'          => 'Cet article a besoin d’un titre ou d’un slug.',
    'blog.error.slug_reserved' => 'Ce slug est réservé (post, category, tag, feed). Choisissez-en un autre.',

    // status + locale (form selects + list filter)
    'blog.status.draft'     => 'Brouillon',
    'blog.status.published' => 'Publié',
    'blog.status.archived'  => 'Archivé',
    'blog.locale.en' => 'Anglais',
    'blog.locale.es' => 'Espagnol',

    // public listings — card + archive + index
    'blog.card.min_read'         => 'min de lecture',
    'blog.term.category'         => 'Catégorie',
    'blog.term.tag'              => 'Étiquette',
    'blog.archive.all_articles'  => 'Tous les articles',
    'blog.archive.empty'         => 'Aucun article ici pour l’instant.',
    'blog.index.heading'         => 'Blog',
    'blog.index.rss'             => 'Flux RSS',
    'blog.index.empty'           => 'Aucun article publié pour l’instant.',

    // editor labels
    'blog.editor.kicker'       => 'Surtitre',
    'blog.editor.title'        => 'Titre',
    'blog.editor.subtitle'     => 'Sous-titre',
    'blog.editor.preamble'     => 'Préambule',
    'blog.editor.body'         => 'Article',
    'blog.editor.excerpt'      => 'Extrait',
    'blog.editor.feature'      => 'Image à la une',
    'blog.editor.author'       => 'Auteur',
    'blog.editor.categories'   => 'Catégories',
    'blog.editor.tags'         => 'Étiquettes',
    'blog.editor.status'       => 'Statut',
    'blog.editor.publish_at'   => 'Date de publication',
    'blog.editor.seo'          => 'SEO et réseaux sociaux',
    'blog.editor.seo_title'    => 'Méta-titre',
    'blog.editor.seo_desc'     => 'Méta-description',
    'blog.editor.canonical'    => 'URL canonique',
    'blog.editor.comments'     => 'Autoriser les commentaires',
    'blog.editor.language'     => 'Langue',
    'blog.editor.slug'         => 'Slug',

    // editor — chrome, actions, hints
    'blog.editor.back'            => 'Retour aux articles',
    'blog.editor.edit_article'    => 'Modifier l’article',
    'blog.editor.new_article'     => 'Nouvel article',
    'blog.editor.settings'        => 'Paramètres de l’article',
    'blog.editor.save'            => 'Enregistrer',
    'blog.editor.close'           => 'Fermer',
    'blog.editor.feature_set'     => 'Définir l’image à la une',
    'blog.editor.feature_replace' => 'Remplacer',
    'blog.editor.feature_remove'  => 'Supprimer',
    'blog.editor.publish_hint'    => 'Vide = en ligne maintenant. Une date future la programme.',
    'blog.editor.categories_hint' => 'Séparées par des virgules. Les nouvelles sont créées à l’enregistrement.',
    'blog.editor.tags_hint'       => 'Séparées par des virgules.',
    'blog.editor.excerpt_hint'    => 'Affiché dans les listes et les cartes sociales. À défaut, le sous-titre est utilisé.',
    'blog.editor.slug_hint'       => 'Automatique à partir du titre si vide. Le modifier laisse une redirection 301.',

    // editor — formatting toolbar (title / aria-label)
    'blog.editor.tool.formatting'    => 'Mise en forme',
    'blog.editor.tool.heading'       => 'Titre',
    'blog.editor.tool.subheading'    => 'Sous-titre',
    'blog.editor.tool.body_text'     => 'Texte du corps',
    'blog.editor.tool.bold'          => 'Gras',
    'blog.editor.tool.italic'        => 'Italique',
    'blog.editor.tool.quote'         => 'Citation',
    'blog.editor.tool.bullet_list'   => 'Liste à puces',
    'blog.editor.tool.numbered_list' => 'Liste numérotée',
    'blog.editor.tool.link'          => 'Lien',
    'blog.editor.tool.image'         => 'Insérer une image',
    'blog.editor.tool.source'        => 'Modifier le code HTML',

    // editor — version history
    'blog.editor.versions'    => 'Historique des versions',
    'blog.editor.col_version' => 'Version',
    'blog.editor.col_saved'   => 'Enregistré',
    'blog.editor.untitled'    => '(sans titre)',
    'blog.editor.restore'     => 'Restaurer',

    // placeholders
    'blog.ph.kicker'   => 'Surtitre — un court libellé au-dessus du titre',
    'blog.ph.title'    => 'Titre',
    'blog.ph.subtitle' => 'Ajouter un sous-titre…',
    'blog.ph.preamble' => 'Une ouverture en plus grande police qui attire le lecteur…',
    'blog.ph.body'     => 'Racontez votre histoire…',

    // admin list
    'blog.list.title'       => 'Articles',
    'blog.list.subtitle'    => 'Billets et articles — stockés dans le magasin de contenu du CMS en tant que',
    'blog.list.new'         => 'Nouvel article',
    'blog.list.empty'       => 'Aucun article pour l’instant — écrivez le premier.',
    'blog.list.status_all'  => 'Tous les statuts',
    'blog.list.clear'       => 'Effacer',
    'blog.list.clear_title' => 'Effacer les filtres',
    'blog.list.col_title'   => 'Titre',
    'blog.list.col_slug'    => 'Slug',
    'blog.list.col_lang'    => 'Langue',
    'blog.list.col_status'  => 'Statut',
    'blog.list.col_read'    => 'Lecture',
    'blog.list.col_updated' => 'Mis à jour',
    'blog.list.col_actions' => 'Actions',
    'blog.js.confirm_delete_article' => 'Supprimer cet article ? Il est supprimé de façon réversible et peut être récupéré.',
    'blog.js.media_picker_unavailable' => 'Sélecteur de médias indisponible.',
    'blog.js.fix_fields' => 'Veuillez corriger les champs en surbrillance.',
    'blog.js.network_error' => 'Erreur réseau — veuillez réessayer.',
    'blog.js.confirm_restore' => 'Restaurer la version n° %s ? Le contenu actuel est d’abord enregistré comme une nouvelle version.',
    'blog.js.link_url' => 'URL du lien :',
];
