<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Register module — French strings. Owner-prefixed keys (register.*). */
return [
    // /api response messages
    'register.registered'      => 'Votre site est enregistré — consultez votre e-mail pour terminer la vérification.',
    'register.domain_verified' => 'Votre domaine est vérifié.',
    'register.domain_pending'  => 'Votre domaine n’est pas encore vérifié — nous continuons d’essayer ; vous pouvez aussi réessayer maintenant.',
    'register.email_sent'      => 'E-mail de vérification envoyé.',
    'register.error.no_domain'            => 'Impossible de détecter le domaine de ce site.',
    'register.error.registry_unreachable' => 'Le réseau Tiger est injoignable pour le moment — veuillez réessayer sous peu.',
    'register.error.not_registered'       => 'Ce site n’est pas enregistré.',

    // Settings → Registration screen
    'register.title'        => 'Enregistrement',
    'register.subtitle'     => 'Facultatif. Enregistrez ce site pour obtenir un Site ID vérifié et rejoindre le réseau Tiger — cela n’active ni ne désactive rien.',
    'register.status'       => 'Statut',
    'register.verified'     => 'Vérifié',
    'register.not_registered' => 'Non enregistré',
    'register.field.domain' => 'Domaine',
    'register.field.email'  => 'E-mail',
    'register.field.tsid'   => 'Site ID (TSID)',
    'register.intro_body'   => 'L’enregistrement vérifie votre <strong>domaine</strong> (servi automatiquement — rien à téléverser) et votre <strong>e-mail</strong>. Nous ne partageons que votre domaine, cet e-mail et vos versions de Tiger/PHP. Vous ne voulez pas ? Laissez tomber — ou désactivez le widget d’Enregistrement / désactivez ce module.',
    'register.admin_email'  => 'E-mail de l’administrateur',
    'register.register_btn' => 'Enregistrer',
    'register.badge.domain' => 'Domaine',
    'register.badge.email'  => 'E-mail',
    'register.state.verified' => 'vérifié',
    'register.state.pending'  => 'en attente',
    'register.verify_domain'  => 'Vérifier le domaine',
    'register.resend_email'   => 'Renvoyer l’e-mail de vérification',
    'register.net_error'      => 'Erreur réseau — veuillez réessayer.',

    // Public email-verify landing
    'register.verify.ok_title'   => 'Votre site est vérifié',
    'register.verify.ok_body'    => 'Merci — votre e-mail est confirmé.',
    'register.verify.ok_cta'     => 'Accéder à votre tableau de bord',
    'register.verify.fail_title' => 'Ce lien n’a pas fonctionné',
    'register.verify.fail_body'  => 'Il a peut-être expiré ou déjà été utilisé. Renvoyez-le depuis le widget d’Enregistrement ou les Paramètres.',
    'register.verify.fail_cta'   => 'Accéder à l’Enregistrement',
    'register.nav.label' => 'Enregistrement',
    'register.widget.title' => 'Enregistrement',
    'register.widget.registered' => 'Votre site est enregistré',
    'register.widget.site_id' => 'Site ID',
    'register.widget.intro' => 'Enregistrez ce site pour obtenir un Site ID vérifié et rejoindre le réseau Tiger — facultatif, et cela n’active ni ne désactive rien. Nous ne partageons que votre domaine, cet e-mail et vos versions de Tiger/PHP.',
    'register.widget.register' => 'Enregistrer',
    'register.widget.confirming' => 'Confirmation que vous contrôlez %s.',
    'register.widget.last_step' => 'Dernière étape : cliquez sur le lien que nous avons envoyé à %s.',
    'register.widget.resend' => 'Renvoyer l’e-mail',
];
