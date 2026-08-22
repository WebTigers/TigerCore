<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup module — French strings (signup.*).
 */
return [
    // Service / API messages
    'signup.disabled'        => 'L’inscription publique est actuellement désactivée.',
    'signup.error.recaptcha' => 'Impossible de vérifier que vous êtes humain — veuillez réessayer.',
    'signup.check_email'     => 'Compte créé — consultez votre e-mail pour le vérifier, puis connectez-vous.',
    'signup.verified'        => 'Votre e-mail est vérifié et votre compte est actif.',
    'signup.invalid_link'    => 'Ce lien de vérification est invalide ou a expiré.',

    // Signup form view
    'signup.form.heading'          => 'Créez votre compte',
    'signup.form.subheading'       => 'Lancez votre espace de travail Tiger — cela prend une minute.',
    'signup.form.label.first_name' => 'Prénom',
    'signup.form.label.last_name'  => 'Nom',
    'signup.form.label.company'    => 'Entreprise',
    'signup.form.label.username'   => 'Nom d’utilisateur',
    'signup.form.label.password'   => 'Mot de passe',
    'signup.form.aria.show_password' => 'Afficher le mot de passe',
    'signup.form.label.email'      => 'E-mail',
    'signup.form.label.street'     => 'Adresse',
    'signup.form.label.city'       => 'Ville',
    'signup.form.label.region'     => 'État / Province',
    'signup.form.label.postal'     => 'Code postal',
    'signup.form.label.country'    => 'Pays',
    'signup.form.option.select'    => '— Sélectionner —',
    'signup.form.group.frequent'   => 'Fréquents',
    'signup.form.group.all'        => 'Tous les pays',
    'signup.form.label.phone_type' => 'Type de téléphone',
    'signup.form.label.phone'      => 'Téléphone',
    'signup.form.submit'           => 'Créer le compte',
    'signup.form.have_account'     => 'Vous avez déjà un compte ? Connectez-vous',

    // Email-verification result view
    'signup.verify.heading'        => 'Vérification de l’e-mail',
    'signup.verify.success.body'   => 'Votre e-mail est vérifié et votre compte est actif. Vous pouvez vous connecter maintenant.',
    'signup.verify.action.signin'  => 'Connexion',
    'signup.verify.invalid.body'   => 'Ce lien de vérification est invalide ou a expiré. Vous pouvez vous réinscrire, ou contacter le support si vous êtes déjà inscrit.',
    'signup.verify.action.back'    => 'Retour à l’inscription',

    // JS-facing strings (registered via $this->i18n, resolved by Tiger.t)
    'signup.js.verify_sent'   => '<strong>Presque terminé.</strong> Nous vous avons envoyé un lien de vérification pour activer votre compte — cliquez dessus, puis connectez-vous.',
    'signup.js.fix_fields'    => 'Veuillez corriger les champs en surbrillance.',
    'signup.js.check_field'   => 'Veuillez vérifier ce champ.',
    'signup.js.went_wrong'    => 'Une erreur s’est produite. Veuillez réessayer.',
    'signup.js.network_error' => 'Erreur réseau — veuillez réessayer.',
];
