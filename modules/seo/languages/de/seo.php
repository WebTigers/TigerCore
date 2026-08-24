<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerSEO module — German strings (seo.*). Formal address (Sie). Same key set as en/seo.php.
 */
return [
    // Service / API messages
    'seo.page.saved'               => 'Social-Card gespeichert.',
    'seo.page.error.unknown_page'  => 'Diese Seite existiert nicht, daher lässt sich ihre Social-Card nicht festlegen.',

    // Admin navigation
    'seo.nav.label'                => 'SEO',

    // Form placeholders
    'seo.page.field.title'         => 'Leer lassen, um den Seitentitel zu verwenden',
    'seo.page.field.description'   => 'Leer lassen, um die Website-Beschreibung zu verwenden',

    // Social Cards screen
    'seo.page.title'               => 'Social-Cards',
    'seo.page.subtitle'            => 'Der Titel, die Beschreibung und das Bild, die erscheinen, wenn eine Ihrer mitgelieferten Seiten in sozialen Netzwerken geteilt oder in Suchergebnissen angezeigt wird.',
    'seo.action.site_defaults'     => 'Website-Standardwerte',

    'seo.card.defaults'            => 'Worauf ein leeres Feld zurückgreift',
    'seo.help.defaults'            => 'Lassen Sie eines der Felder unten leer, übernimmt die Seite diese websiteweiten Werte. Ändern lassen sie sich im Bildschirm „Website-Identität“.',
    'seo.label.default_title'      => 'Standardtitel',
    'seo.label.default_description' => 'Standardbeschreibung',
    'seo.label.default_image'      => 'Standardbild',

    'seo.card.pages'               => 'Mitgelieferte Seiten',
    'seo.help.pages'               => 'Diese Seiten gehören zum Lieferumfang von Tiger und haben deshalb keinen eigenen Inhaltsdatensatz. Legen Sie hier eine Social-Card fest — sie wirkt sofort, ganz ohne Deployment.',
    'seo.col.page'                 => 'Seite',
    'seo.col.url'                  => 'Adresse',
    'seo.col.title'                => 'Titel',
    'seo.col.description'          => 'Beschreibung',
    'seo.col.image'                => 'Bild',
    'seo.col.actions'              => 'Aktionen',
    'seo.state.loading'            => 'Seiten werden geladen…',
    'seo.action.edit'              => 'Bearbeiten',

    // Editor
    'seo.modal.title'              => 'Social-Card',
    'seo.action.close'             => 'Schließen',
    'seo.label.title'              => 'Titel',
    'seo.help.title'               => 'Erscheint als Überschrift des geteilten Links. Leer lassen, um den Seitentitel zu verwenden, andernfalls:',
    'seo.label.description'        => 'Beschreibung',
    'seo.help.description'         => 'Die kurze Zusammenfassung unter der Überschrift. Leer lassen, um Folgendes zu verwenden:',
    'seo.label.image'              => 'Bild',
    'seo.action.choose_image'      => 'Bild auswählen',
    'seo.help.image'               => 'Wählen Sie ein Bild aus der Medienbibliothek — die tatsächliche Größe wird aus der Datei gelesen, damit die Card korrekt dargestellt wird.',
    'seo.label.image_url'          => 'Bildadresse',
    'seo.help.image_url'           => 'Oder verweisen Sie auf ein anderswo gehostetes Bild. Lassen Sie beide Felder leer, um Folgendes zu verwenden:',
    'seo.action.clear'             => 'Alles leeren',
    'seo.action.cancel'            => 'Abbrechen',
    'seo.action.save'              => 'Speichern',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'seo.js.saved'                 => 'Social-Card gespeichert.',
    'seo.js.fix_fields'            => 'Bitte korrigieren Sie die markierten Felder.',
    'seo.js.network_error'         => 'Netzwerkfehler — bitte erneut versuchen.',
    'seo.js.load_error'            => 'Die Seitenliste konnte nicht geladen werden.',
    'seo.js.authored'              => 'Festgelegt',
    'seo.js.using_default'         => 'Website-Standard',
    'seo.js.edit_title'            => 'Social-Card',
    'seo.js.empty'                 => 'Keine mitgelieferten Seiten gefunden.',
];
