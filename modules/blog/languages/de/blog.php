<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog module — German strings (blog.*). Same key set as languages/en/blog.php.
 */
return [
    // API responses
    'blog.post.saved'    => 'Artikel gespeichert.',
    'blog.post.deleted'  => 'Artikel gelöscht.',
    'blog.post.restored' => 'Artikel auf die ausgewählte Version zurückgesetzt.',
    'blog.error.slug'          => 'Dieser Artikel benötigt einen Titel oder einen Slug.',
    'blog.error.slug_reserved' => 'Dieser Slug ist reserviert (post, category, tag, feed). Wählen Sie einen anderen.',

    // status + locale (form selects + list filter)
    'blog.status.draft'     => 'Entwurf',
    'blog.status.published' => 'Veröffentlicht',
    'blog.status.archived'  => 'Archiviert',
    'blog.locale.en' => 'Englisch',
    'blog.locale.es' => 'Spanisch',

    // public listings — card + archive + index
    'blog.card.min_read'         => 'Min. Lesezeit',
    'blog.term.category'         => 'Kategorie',
    'blog.term.tag'              => 'Schlagwort',
    'blog.archive.all_articles'  => 'Alle Artikel',
    'blog.archive.empty'         => 'Hier gibt es noch keine Artikel.',
    'blog.index.heading'         => 'Blog',
    'blog.index.rss'             => 'RSS-Feed',
    'blog.index.empty'           => 'Noch keine Artikel veröffentlicht.',

    // editor labels
    'blog.editor.kicker'       => 'Dachzeile',
    'blog.editor.title'        => 'Titel',
    'blog.editor.subtitle'     => 'Untertitel',
    'blog.editor.preamble'     => 'Einleitung',
    'blog.editor.body'         => 'Artikel',
    'blog.editor.excerpt'      => 'Auszug',
    'blog.editor.feature'      => 'Beitragsbild',
    'blog.editor.author'       => 'Autor',
    'blog.editor.categories'   => 'Kategorien',
    'blog.editor.tags'         => 'Schlagwörter',
    'blog.editor.status'       => 'Status',
    'blog.editor.publish_at'   => 'Veröffentlichungsdatum',
    'blog.editor.seo'          => 'SEO & Social Media',
    'blog.editor.seo_title'    => 'Meta-Titel',
    'blog.editor.seo_desc'     => 'Meta-Beschreibung',
    'blog.editor.canonical'    => 'Kanonische URL',
    'blog.editor.comments'     => 'Kommentare zulassen',
    'blog.editor.language'     => 'Sprache',
    'blog.editor.slug'         => 'Slug',

    // editor — chrome, actions, hints
    'blog.editor.back'            => 'Zurück zu den Artikeln',
    'blog.editor.edit_article'    => 'Artikel bearbeiten',
    'blog.editor.new_article'     => 'Neuer Artikel',
    'blog.editor.settings'        => 'Beitragseinstellungen',
    'blog.editor.save'            => 'Speichern',
    'blog.editor.close'           => 'Schließen',
    'blog.editor.feature_set'     => 'Beitragsbild festlegen',
    'blog.editor.feature_replace' => 'Ersetzen',
    'blog.editor.feature_remove'  => 'Entfernen',
    'blog.editor.publish_hint'    => 'Leer = jetzt live. Ein zukünftiger Zeitpunkt plant die Veröffentlichung.',
    'blog.editor.categories_hint' => 'Durch Kommas getrennt. Neue werden beim Speichern erstellt.',
    'blog.editor.tags_hint'       => 'Durch Kommas getrennt.',
    'blog.editor.excerpt_hint'    => 'Wird in Listen und Social-Cards angezeigt. Fällt auf den Untertitel zurück.',
    'blog.editor.slug_hint'       => 'Automatisch aus dem Titel, wenn leer. Eine Änderung hinterlässt eine 301-Weiterleitung.',

    // editor — formatting toolbar (title / aria-label)
    'blog.editor.tool.formatting'    => 'Formatierung',
    'blog.editor.tool.heading'       => 'Überschrift',
    'blog.editor.tool.subheading'    => 'Zwischenüberschrift',
    'blog.editor.tool.body_text'     => 'Fließtext',
    'blog.editor.tool.bold'          => 'Fett',
    'blog.editor.tool.italic'        => 'Kursiv',
    'blog.editor.tool.quote'         => 'Zitat',
    'blog.editor.tool.bullet_list'   => 'Aufzählungsliste',
    'blog.editor.tool.numbered_list' => 'Nummerierte Liste',
    'blog.editor.tool.link'          => 'Link',
    'blog.editor.tool.image'         => 'Bild einfügen',
    'blog.editor.tool.source'        => 'HTML-Quellcode bearbeiten',

    // editor — version history
    'blog.editor.versions'    => 'Versionsverlauf',
    'blog.editor.col_version' => 'Version',
    'blog.editor.col_saved'   => 'Gespeichert',
    'blog.editor.untitled'    => '(ohne Titel)',
    'blog.editor.restore'     => 'Wiederherstellen',

    // placeholders
    'blog.ph.kicker'   => 'Dachzeile — ein kurzes Label über dem Titel',
    'blog.ph.title'    => 'Titel',
    'blog.ph.subtitle' => 'Untertitel hinzufügen…',
    'blog.ph.preamble' => 'Ein größerer Einstieg, der den Leser hineinzieht…',
    'blog.ph.body'     => 'Erzählen Sie Ihre Geschichte…',

    // admin list
    'blog.list.title'       => 'Artikel',
    'blog.list.subtitle'    => 'Beiträge und Artikel — im CMS-Inhaltsspeicher abgelegt als',
    'blog.list.new'         => 'Neuer Artikel',
    'blog.list.empty'       => 'Noch keine Artikel — schreiben Sie Ihren ersten.',
    'blog.list.status_all'  => 'Alle Status',
    'blog.list.clear'       => 'Zurücksetzen',
    'blog.list.clear_title' => 'Filter zurücksetzen',
    'blog.list.col_title'   => 'Titel',
    'blog.list.col_slug'    => 'Slug',
    'blog.list.col_lang'    => 'Spr.',
    'blog.list.col_status'  => 'Status',
    'blog.list.col_read'    => 'Lesezeit',
    'blog.list.col_updated' => 'Aktualisiert',
    'blog.list.col_actions' => 'Aktionen',
    'blog.js.confirm_delete_article' => 'Diesen Artikel löschen? Er wird per Soft-Delete gelöscht und kann wiederhergestellt werden.',
    'blog.js.media_picker_unavailable' => 'Medienauswahl nicht verfügbar.',
    'blog.js.fix_fields' => 'Bitte korrigieren Sie die markierten Felder.',
    'blog.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'blog.js.confirm_restore' => 'Version #%s wiederherstellen? Der aktuelle Inhalt wird zuerst als neue Version gespeichert.',
    'blog.js.link_url' => 'Link-URL:',
];
