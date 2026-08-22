<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity module — German strings (identity.*).
 */
return [
    // Service / API messages
    'identity.saved'            => 'Website-Identität gespeichert.',

    // Form placeholders
    'identity.field.site_name'  => 'z. B. Acme, Inc.',
    'identity.field.tagline'    => 'Eine kurze Zeile unter dem Namen',

    // Site Identity screen
    'identity.page.title'       => 'Website-Identität',
    'identity.page.subtitle'    => 'Der Name, das Logo, das Favicon und die sozialen Profile Ihrer Website — die Marke, die in Browser-Tabs, Suchergebnissen und geteilten Beiträgen erscheint.',
    'identity.action.save'      => 'Speichern',
    'identity.card.identity'    => 'Identität',
    'identity.label.site_name'  => 'Website-Name',
    'identity.help.site_name'   => 'Wird im Website-Header und im Browser-Tab angezeigt und als Standard-Seitentitel und Markenname in den Suchergebnissen verwendet.',
    'identity.label.tagline'    => 'Slogan',
    'identity.help.tagline'     => 'Eine kurze Zeile, die die Website beschreibt (optional).',
    'identity.card.logo_favicon' => 'Logo & Favicon',
    'identity.label.logo'       => 'Logo',
    'identity.label.favicon'    => 'Favicon',
    'identity.help.logo'        => 'Wird für Ihre Marke in den Suchergebnissen (Organization-Schema) verwendet und steht Themes zur Verfügung.',
    'identity.help.favicon'     => 'Das kleine Symbol im Browser-Tab. Verwenden Sie ein <strong>quadratisches</strong> Bild — 512&times;512 oder größer ist ideal; der Browser skaliert es auf jede benötigte Größe herunter.',
    'identity.card.social'      => 'Soziale Profile',
    'identity.help.social'      => 'Vollständige URLs zu Ihren offiziellen Profilen. Diese werden als die verifizierten Links Ihrer Marke veröffentlicht (schema.org <code>sameAs</code>) — lassen Sie beliebige leer.',
    'identity.social.twitter'   => 'X / Twitter',
    'identity.social.facebook'  => 'Facebook',
    'identity.social.instagram' => 'Instagram',
    'identity.social.linkedin'  => 'LinkedIn',
    'identity.social.youtube'   => 'YouTube',
    'identity.social.github'    => 'GitHub',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'identity.js.saved'         => 'Website-Identität gespeichert.',
    'identity.js.fix_fields'    => 'Bitte korrigieren Sie die markierten Felder.',
    'identity.js.network_error' => 'Netzwerkfehler — bitte versuchen Sie es erneut.',
    'identity.nav.label' => 'Website-Identität',
];
