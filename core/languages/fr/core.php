<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerCore — Français (fr) core strings. Clés sémantiques avec préfixe (core.*).
 */
return [
    // --- Réponses des services /api (valeurs par défaut) ---
    'core.api.success'               => 'Terminé.',
    'core.api.error.general'         => 'Une erreur est survenue. Veuillez réessayer.',
    'core.api.error.form'            => 'Veuillez corriger les champs en surbrillance.',
    'core.api.error.csrf'            => 'Oups — votre jeton de sécurité a expiré. Veuillez actualiser la page pour continuer. (Ils expirent volontairement ; blâmez les gremlins de la sécurité.)',
    'core.api.error.invalid_action'  => 'Cette action n’est pas disponible.',
    'core.api.error.not_allowed'     => 'Vous n’avez pas la permission de faire cela.',
    'core.api.error.login_required'  => 'Veuillez vous connecter pour continuer.',
    'core.token.created'          => 'Jeton créé — copiez-le maintenant ; il ne sera plus affiché.',
    'core.token.revoked'          => 'Jeton révoqué.',
    'core.api.error.login_failed'    => 'E-mail ou mot de passe invalide.',
    'core.api.error.missing_module'  => 'Aucun module n’a été spécifié.',
    'core.api.error.missing_service' => 'Aucun service n’a été spécifié.',
    'core.api.error.missing_action'  => 'Aucune action n’a été spécifiée.',

    // --- Formulaires : validation reCAPTCHA ---
    'core.form.recaptcha.missing'    => 'Veuillez confirmer que vous n’êtes pas un robot.',
    'core.form.recaptcha.failed'     => 'La vérification reCAPTCHA a échoué. Veuillez réessayer.',
    'core.form.recaptcha.error'      => 'Impossible de vérifier reCAPTCHA pour le moment. Veuillez réessayer.',

    // --- Authentification à deux facteurs (TOTP) ---
    'core.auth.twofa.enabled'        => 'L’authentification à deux facteurs est maintenant activée.',
    'core.auth.twofa.disabled'       => 'L’authentification à deux facteurs a été désactivée.',
    'core.auth.twofa.bad_code'       => 'Ce code est incorrect ou a expiré.',
    'core.auth.twofa.unavailable'    => 'L’authentification à deux facteurs n’est pas disponible sur cette installation.',

    // --- Validation des formulaires (au niveau du champ) ---
    'core.form.password_mismatch'    => 'Les mots de passe ne correspondent pas.',

    // --- Politique de mot de passe (clés de Tiger_Policy_Password) ---
    'password.too_short'             => 'Le mot de passe est trop court — veuillez utiliser au moins 8 caractères.',
    'password.needs_complexity'      => 'Ajoutez des lettres majuscules et minuscules, un chiffre et un symbole.',
    'password.reused'                => 'Vous avez déjà utilisé ce mot de passe — veuillez en choisir un nouveau.',

    // --- Libellés d’interface communs ---
    'core.common.close'              => 'Fermer',
    'core.common.done'               => 'Terminé',
    'core.common.back_home'          => 'Retour à l’accueil',

    // --- Pages d’erreur (403 / 404 / 500) ---
    'core.error.badge'               => 'Erreur',
    'core.error.403.title'           => 'Vous n’avez pas accès à cela.',
    'core.error.404.title'           => 'Cette page n’existe pas.',
    'core.error.500.title'           => 'Une erreur est survenue.',
    'core.error.403.sub'             => 'Vous êtes connecté, mais cette zone n’est pas disponible pour votre compte.',
    'core.error.404.sub'             => 'La page a peut-être été déplacée, ou n’a jamais existé. Reprenons le bon chemin.',
    'core.error.500.sub'             => 'Quelque chose s’est cassé de notre côté. Nous avons été notifiés et nous examinons cela — veuillez réessayer sous peu.',
    'core.error.switch_account'      => 'Changer de compte',

    // --- Authentification : libellés partagés ---
    'core.auth.email'                => 'E-mail',
    'core.auth.password'             => 'Mot de passe',
    'core.auth.email_code'           => 'M’envoyer un code par e-mail',
    'core.auth.back_to_login'        => 'Retour à la connexion',
    'core.auth.return_to'            => 'Retour à %s',

    // --- Authentification : connexion ---
    'core.auth.login.title'          => 'Se connecter à Tiger',
    'core.auth.login.subtitle'       => 'Bon retour parmi nous.',
    'core.auth.login.identifier'     => 'E-mail ou nom d’utilisateur',
    'core.auth.login.forgot'         => 'Mot de passe oublié ?',
    'core.auth.login.submit'         => 'Se connecter',
    'core.auth.login.use_code'       => 'Se connecter plutôt avec un code',

    // --- Authentification : invite à deux facteurs (étape de connexion) ---
    'core.auth.twofa.prompt'         => 'Saisissez le code à 6 chiffres de votre application d’authentification.',
    'core.auth.twofa.code_label'     => 'Code de vérification',
    'core.auth.twofa.verify'         => 'Vérifier',
    'core.auth.twofa.use_recovery'   => 'Utiliser un code de récupération',

    // --- Authentification : écran verrouillé ---
    'core.auth.lock.title'           => 'Écran verrouillé',
    'core.auth.lock.subtitle'        => 'Vérifiez-vous à nouveau pour continuer.',
    'core.auth.lock.unlock'          => 'Déverrouiller',
    'core.auth.lock.use_code'        => 'Déverrouiller avec un code',
    'core.auth.lock.email_send_to'   => 'Nous enverrons un code à usage unique à',
    'core.auth.lock.use_password'    => 'Utiliser plutôt le mot de passe',
    'core.auth.lock.not_you'         => 'Vous n’êtes pas %s ? Se déconnecter',

    // --- Authentification : réinitialiser le mot de passe ---
    'core.auth.reset.title'          => 'Définir un nouveau mot de passe',
    'core.auth.reset.subtitle'       => 'Choisissez un mot de passe fort que vous n’utilisez nulle part ailleurs.',
    'core.auth.reset.new_password'   => 'Nouveau mot de passe',
    'core.auth.reset.confirm_password' => 'Confirmer le mot de passe',
    'core.auth.reset.submit'         => 'Définir le nouveau mot de passe',

    // --- Authentification : mot de passe oublié ---
    'core.auth.forgot.title'         => 'Réinitialiser votre mot de passe',
    'core.auth.forgot.subtitle'      => 'Nous vous enverrons par e-mail un lien pour en choisir un nouveau.',
    'core.auth.forgot.submit'        => 'Envoyer le lien de réinitialisation',

    // --- Authentification : déconnecté ---
    'core.auth.logout.title'         => 'Vous avez été déconnecté.',
    'core.auth.logout.subtitle'      => 'Merci de votre visite.',
    'core.auth.logout.login_again'   => 'Se reconnecter',

    // --- Authentification : connexion par code (sans mot de passe) ---
    'core.auth.otp.title'            => 'Se connecter avec un code',
    'core.auth.otp.subtitle'         => 'Nous vous enverrons par e-mail un code à usage unique — sans mot de passe.',
    'core.auth.otp.restart'          => 'Utiliser un autre e-mail',
    'core.auth.otp.use_password'     => 'Se connecter plutôt avec un mot de passe',

    // --- Authentification : gestion des deux facteurs (écran de sécurité) ---
    'core.auth.twofa.heading'        => 'Authentification à deux facteurs',
    'core.auth.twofa.lead'           => 'Ajoutez à votre connexion un code à usage unique d’une application d’authentification.',
    'core.auth.twofa.unavailable_detail' => 'L’authentification à deux facteurs n’est pas encore disponible sur cette installation — la clé de chiffrement de l’application (%s) n’est pas configurée. Demandez à un administrateur de la configurer.',
    'core.auth.twofa.enabled_badge'  => 'Activée',
    'core.auth.twofa.protected'      => 'Votre application d’authentification protège ce compte.',
    'core.auth.twofa.recovery_remaining' => 'Codes de récupération restants :',
    'core.auth.twofa.recovery_help'  => 'Les codes de récupération vous permettent de vous connecter si vous perdez votre appareil. Réactivez pour générer un nouveau jeu.',
    'core.auth.twofa.disable_prompt' => 'Pour désactiver l’authentification à deux facteurs, confirmez avec un code actuel de votre application (ou un code de récupération) :',
    'core.auth.twofa.disable_btn'    => 'Désactiver la 2FA',
    'core.auth.twofa.intro'          => 'Protégez votre compte avec un code temporaire d’une application comme Google Authenticator, 1Password, Authy ou Microsoft Authenticator.',
    'core.auth.twofa.enable_btn'     => 'Activer l’authentification à deux facteurs',
    'core.auth.twofa.step_scan'      => 'Scannez le code QR',
    'core.auth.twofa.step_scan_detail' => 'avec votre application d’authentification — ou saisissez la clé à la main.',
    'core.auth.twofa.qr_preview'     => 'Aperçu du QR',
    'core.auth.twofa.setup_key_label' => 'Clé de configuration (saisie manuelle)',
    'core.auth.twofa.open_in_app'    => 'Ouvrir dans l’application',
    'core.auth.twofa.step_recovery'  => 'Enregistrez vos codes de récupération.',
    'core.auth.twofa.step_recovery_detail' => 'Chacun peut être utilisé une fois si vous perdez votre appareil. Conservez-les en lieu sûr.',
    'core.auth.twofa.copy_codes'     => 'Copier les codes',
    'core.auth.twofa.step_confirm'   => 'Confirmer.',
    'core.auth.twofa.step_confirm_detail' => 'Saisissez le code à 6 chiffres que votre application affiche maintenant :',
    'core.auth.twofa.verify_turn_on' => 'Vérifier et activer',
    'core.auth.twofa.back_to_admin'  => 'Retour à l’administration',

    // --- Tableau de bord (accueil de l’administration) ---
    'core.dashboard.title'           => 'Tableau de bord',
    'core.dashboard.lead'            => 'Bienvenue dans l’administration de Tiger.',
    'core.dashboard.customize'       => 'Personnaliser',
    'core.dashboard.empty_title'     => 'Aucun widget de tableau de bord pour l’instant',
    'core.dashboard.empty_lead'      => 'Les modules qui fournissent un widget de tableau de bord apparaîtront ici automatiquement une fois actifs.',
    'core.dashboard.drag_hint'       => 'Glisser pour réorganiser',
    'core.dashboard.collapse_aria'   => 'Réduire le widget',
    'core.dashboard.customize_title' => 'Personnaliser le tableau de bord',
    'core.dashboard.customize_help'  => 'Activez ou désactivez des widgets. Un widget masqué n’est pas affiché — réactivez-le quand vous voulez.',

    // --- Accueil du compte ---
    'core.account.title'             => 'Mon compte',
    'core.account.lead'              => 'Votre abonnement, vos licences et votre profil.',
    'core.account.empty_lead'        => 'Les détails de votre compte apparaîtront ici à mesure que vous ajoutez des abonnements et des services.',
    'core.js.network_error' => 'Erreur réseau — veuillez réessayer.',
    'core.js.recaptcha' => 'Veuillez compléter le reCAPTCHA et réessayer.',
    'core.js.incorrect_password' => 'Mot de passe incorrect.',
    'core.js.code_sent' => 'Nous avons envoyé un code à 6 chiffres à %s. Saisissez-le ci-dessous.',
    'core.js.code_invalid' => 'Ce code est invalide ou a expiré.',
    'core.js.code_incorrect' => 'Ce code est incorrect ou a expiré.',
    'core.js.invalid_login' => 'Identifiant ou mot de passe invalide.',
    'core.js.passwords_mismatch' => 'Les mots de passe ne correspondent pas.',
    'core.js.reset_failed' => 'Impossible de réinitialiser votre mot de passe — le lien a peut-être expiré.',
    'core.js.twofa_disabled' => 'Authentification à deux facteurs désactivée.',
    'core.js.twofa_code_wrong_on' => 'Ce code est incorrect. L’authentification à deux facteurs reste activée.',
    'core.js.setup_failed' => 'Impossible de démarrer la configuration. Veuillez réessayer.',
    'core.js.twofa_on' => 'L’authentification à deux facteurs est activée. 🎉',
    'core.js.twofa_code_wrong' => 'Ce code ne correspond pas. Vérifiez l’horloge de votre application et essayez le code actuel.',
    'core.js.widget_load_error' => 'Impossible de charger ce widget.',
    'core.nav.dashboard' => 'Tableau de bord',
    'core.nav.account' => 'Mon compte',
    'core.nav.content' => 'Contenu',
    'core.nav.articles' => 'Articles',
    'core.nav.menus' => 'Menus',
    'core.nav.media' => 'Médias',
    'core.nav.users' => 'Utilisateurs',
    'core.nav.orgs' => 'Organisations',
    'core.nav.code' => 'Code',
    'core.nav.modules' => 'Modules',
    'core.nav.settings' => 'Paramètres',
    'core.datatable.info' => 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
    'core.datatable.info_empty' => 'Affichage de 0 à 0 sur 0 entrée',
    'core.datatable.info_filtered' => '(filtré à partir de _MAX_ entrées au total)',
    'core.datatable.length_menu' => '_MENU_ par page',
    'core.datatable.search_placeholder' => 'Rechercher…',
    'core.datatable.zero_records' => 'Aucun enregistrement correspondant trouvé',
    'core.datatable.empty_table' => 'Aucune donnée disponible',
    'core.datatable.loading' => 'Chargement…',
    'core.datatable.processing' => 'Traitement…',
    'core.datatable.paginate_first' => 'Premier',
    'core.datatable.paginate_last' => 'Dernier',
    'core.datatable.paginate_next' => 'Suivant',
    'core.datatable.paginate_prev' => 'Précédent',
    'core.nav.modules_manage' => 'Gérer',

    // Media picker field (Tiger_View_Helper_MediaField) — shared by the Identity, CMS and SEO screens.
    'core.media.field.choose'          => 'Choisir un média',
    'core.media.field.clear'           => 'Retirer',
    'core.media.field.preview_alt'     => 'Aperçu du média sélectionné',
    'core.media.field.file'            => 'Fichier sélectionné',

    // --- Mail providers (Tiger_Mail_Provider) ---
    'core.mail.provider.help.ses_smtp'         => 'Les identifiants SMTP SES se génèrent dans la console SES — ce ne sont PAS vos clés d\'accès AWS. Saisissez-les ci-dessous comme nom d\'utilisateur et mot de passe.',
    'core.mail.provider.help.ses_api'          => 'Envoie via l\'API SES v2 à l\'aide du SDK AWS fourni.',
    'core.mail.provider.help.ses_api_iam'      => 'Laissez la clé et le secret vides pour utiliser le rôle IAM de l\'instance — aucun identifiant n\'est alors stocké.',
    'core.mail.provider.help.sendgrid_smtp'    => 'Utilisez le nom d\'utilisateur littéral « apikey » et votre clé d\'API comme mot de passe.',
    'core.mail.provider.help.postmark_smtp'    => 'Utilisez votre jeton Server API À LA FOIS comme nom d\'utilisateur et comme mot de passe.',
    'core.mail.provider.help.resend_smtp'      => 'Utilisez le nom d\'utilisateur littéral « resend » et votre clé d\'API comme mot de passe.',
    'core.mail.provider.help.mailgun_region'   => 'Mailgun exploite des régions US et UE distinctes ; une clé ne fonctionne que dans la région où elle a été créée.',
    'core.mail.provider.help.google_smtp'      => 'Nécessite un mot de passe d\'application avec la validation en deux étapes activée — le mot de passe habituel du compte ne fonctionnera pas.',
    'core.mail.provider.help.microsoft_smtp'   => 'Microsoft désactive SMTP AUTH par défaut et retire l\'authentification de base ; vous devrez peut-être l\'activer pour cette boîte aux lettres.',
    'core.mail.provider.requires.aws_sdk'      => 'Ce pilote nécessite le module SDK AWS (tiger-sdk-aws). Installez-le et activez-le, ou utilisez Amazon SES (SMTP).',
    'core.mail.provider.requires.generic'      => 'Le pilote de ce fournisseur n\'est pas disponible sur cette installation.',
];
