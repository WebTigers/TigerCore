<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PUMA theme — public chrome strings (French).
 */
return [
    'theme.nav.docs'      => 'Docs',
    'theme.nav.github'    => 'GitHub',

    'theme.search.placeholder' => 'Rechercher…',
    'theme.auth.dashboard'     => 'Tableau de bord',
    'theme.auth.account'       => 'Mon compte',
    'theme.auth.signup'        => 'S’inscrire',
    'theme.auth.signin'        => 'Se connecter',


    'theme.footer.tagline' => 'la plateforme SaaS native pour l’IA. Devenez propriétaire de l’entreprise que votre IA construit.',
    'theme.footer.privacy' => 'Confidentialité',
    'theme.footer.terms'   => 'Conditions',
    'theme.footer.github'  => 'GitHub',
    'theme.footer.home'    => 'Accueil',

    // ------------------------------------------------------------------
    // Interface d'administration (shell partagé)
    // ------------------------------------------------------------------

    'theme.a11y.skip_to_content' => 'Aller au contenu',
    'theme.a11y.toggle_nav'      => 'Afficher ou masquer la navigation',
    'theme.a11y.main_nav'        => 'Navigation principale',
    'theme.a11y.sidebar_nav'     => 'Navigation latérale',
    'theme.a11y.primary_nav'     => 'Navigation principale',

    'theme.action.save' => 'Enregistrer',
    'theme.action.copy' => 'Copier',

    'theme.admin.title'         => 'Administration Tiger',
    'theme.admin.site'          => 'Site',
    'theme.a11y.view_site'      => "Voir le site (s'ouvre dans un nouvel onglet)",
    'theme.admin.navigation'    => 'Navigation',
    'theme.admin.guest'         => 'Invité',
    'theme.admin.user_fallback' => 'Utilisateur',
    'theme.search.label'        => 'Rechercher',

    'theme.admin.alerts'                    => 'Alertes',
    'theme.admin.alert.user_locked'         => 'Utilisateur verrouillé',
    'theme.admin.alert.connection_restored' => 'Connexion rétablie',
    'theme.admin.time.minutes_ago'          => 'il y a %d minutes',
    'theme.admin.time.hour_ago'             => 'il y a %d heure',
    'theme.admin.view_all'                  => 'Tout voir',

    'theme.admin.menu.profile'     => 'Mon profil',
    'theme.admin.menu.two_factor'  => 'Authentification à deux facteurs',
    'theme.admin.menu.lock_screen' => "Verrouiller l'écran",
    'theme.admin.menu.sign_out'    => 'Se déconnecter',

    'theme.aside.label'       => 'Panneau assistant',
    'theme.aside.activity'    => 'Activité',
    'theme.aside.placeholder' => 'Ce panneau optionnel apparaît quand une vue définit %s. Placez-y des widgets contextuels : activité récente, aide en ligne, filtres.',

    'theme.agent.label'             => 'Agent IA',
    'theme.agent.title'             => 'Agent',
    'theme.agent.model'             => 'Modèle',
    'theme.agent.new_chat'          => 'Nouvelle conversation',
    'theme.agent.drop_files'        => 'Déposez des fichiers pour les joindre',
    'theme.agent.empty'             => 'Démarrez une conversation — l’agent agit avec vos permissions.',
    'theme.agent.input_placeholder' => 'Demandez à l’agent de créer, modifier ou expliquer quelque chose…',
    'theme.agent.attach'            => 'Joindre un fichier',
    'theme.agent.mode_title'        => "Niveau d'automatisation",
    'theme.agent.send'              => 'Envoyer',
    'theme.agent.open'              => "Ouvrir l'agent IA",

    'theme.switcher.language'    => 'Langue',
    'theme.switcher.theme'       => 'Thème',
    'theme.switcher.browser'     => 'Navigateur',
    'theme.switcher.light'       => 'Clair',
    'theme.switcher.dark'        => 'Sombre',
    'theme.switcher.skin'        => 'Skin',
    'theme.switcher.skin_title'  => 'Skin (aperçu en direct)',
    'theme.switcher.skin_header' => 'Skin — aperçu en direct',
    'theme.switcher.custom'      => 'Personnalisé',

    'theme.consent.label'      => 'Consentement aux cookies',
    'theme.consent.learn_more' => 'En savoir plus',
    'theme.consent.gpc'        => 'Global Privacy Control détecté — vous êtes exclu de la mesure d’audience et du partage de données.',

    'theme.auth.title_default' => 'Connexion — Tiger',

    // Les libellés des contrôles de cPanel / Plesk restent VOLONTAIREMENT en anglais : c'est sous ce
    // nom que l'utilisateur les trouve sur l'écran cPanel ou Plesk qu'il a sous les yeux.
    'theme.cron.title'               => 'Comment configurer Cron',
    'theme.cron.status.real.title'   => 'Un vrai cron tourne.',
    'theme.cron.status.real.body'    => 'Tiger a reçu un signal du cron de votre serveur ces dernières minutes — les tâches planifiées partent à l’heure.',
    'theme.cron.status.pseudo.title' => 'Le pseudo-cron façon WordPress est actif.',
    'theme.cron.status.pseudo.body'  => 'Aucun vrai cron détecté : les tâches planifiées dépendent du <em>trafic du site</em> — acceptable pour démarrer, mais un site peu visité peut les exécuter en retard. Ajoutez un vrai cron ci-dessous pour une planification fiable et ponctuelle.',
    'theme.cron.status.none.title'   => 'Aucun cron ne tourne.',
    'theme.cron.status.none.body'    => 'Le vrai cron et la solution de repli pilotée par le trafic sont tous deux désactivés — les tâches planifiées ne partiront pas tant que vous n’en aurez pas configuré un ci-dessous.',
    'theme.cron.command_label'       => 'Votre commande cron',
    'theme.cron.every_minute_note'   => 'Lancez-la <strong>toutes les minutes</strong> (<code>* * * * *</code>) — Tiger décide en interne quelles tâches sont réellement dues, donc un cron à la minute coûte peu et garde tout à l’heure.',
    'theme.cron.tab.cli'             => 'Ligne de commande',
    'theme.cron.tab.claude'          => 'Laisser faire Claude Code',
    'theme.cron.cpanel.step1'        => 'Dans cPanel, ouvrez <strong>Advanced → Cron Jobs</strong>.',
    'theme.cron.cpanel.step2'        => 'Sous <em>Add New Cron Job</em>, réglez <strong>Common Settings</strong> sur <strong>Once Per Minute</strong> (<code>* * * * *</code>).',
    'theme.cron.cpanel.step3'        => 'Collez la commande ci-dessus dans <strong>Command</strong>.',
    'theme.cron.cpanel.step4'        => 'Cliquez sur <strong>Add New Cron Job</strong>. C’est fait — cette carte passe au vert d’ici une à deux minutes.',
    'theme.cron.cpanel.php_path'     => 'Si <code>php</code> est introuvable, utilisez le chemin PHP complet de votre hébergeur (par exemple <code>/usr/local/bin/ea-php82</code>) à la place de <code>php</code>.',
    'theme.cron.plesk.step1'         => 'Dans Plesk, ouvrez <strong>Websites &amp; Domains → Scheduled Tasks</strong> pour votre domaine.',
    'theme.cron.plesk.step2'         => 'Cliquez sur <strong>Add Task</strong>, puis choisissez <strong>Run a command</strong>.',
    'theme.cron.plesk.step3'         => 'Réglez <strong>Run</strong> sur <strong>Cron style</strong> et saisissez <code>* * * * *</code>.',
    'theme.cron.plesk.step4'         => 'Collez la commande ci-dessus, puis <strong>OK</strong> / <strong>Run Now</strong> pour tester.',
    'theme.cron.cli.intro'           => 'Connectez-vous au serveur en SSH et éditez la crontab :',
    'theme.cron.cli.add'             => 'Ajoutez cette ligne, puis enregistrez :',
    'theme.cron.claude.intro'        => 'Laissez Claude Code (ou n’importe quel accès shell à ce serveur) le faire pour vous — demandez-lui simplement :',
    'theme.cron.claude.prompt'       => 'Ajoute un cron à la minute qui exécute :',
    'theme.cron.claude.note'         => 'Claude Code ajoutera l’entrée crontab et la confirmera. En attendant, le pseudo-cron piloté par le trafic garde les choses en mouvement.',

    'theme.schedule.frequency'         => 'Fréquence',
    'theme.schedule.time'              => 'Heure',
    'theme.schedule.day_of_week'       => 'Jour de la semaine',
    'theme.schedule.day_of_month'      => 'Jour du mois',
    'theme.schedule.enabled'           => 'Activé',
    'theme.schedule.freq.every_minute' => 'Toutes les minutes',
    'theme.schedule.freq.every_5_min'  => 'Toutes les 5 minutes',
    'theme.schedule.freq.every_15_min' => 'Toutes les 15 minutes',
    'theme.schedule.freq.hourly'       => 'Toutes les heures',
    'theme.schedule.freq.daily'        => 'Tous les jours',
    'theme.schedule.freq.weekly'       => 'Toutes les semaines',
    'theme.schedule.freq.monthly'      => 'Tous les mois',
    'theme.schedule.day.sunday'        => 'Dimanche',
    'theme.schedule.day.monday'        => 'Lundi',
    'theme.schedule.day.tuesday'       => 'Mardi',
    'theme.schedule.day.wednesday'     => 'Mercredi',
    'theme.schedule.day.thursday'      => 'Jeudi',
    'theme.schedule.day.friday'        => 'Vendredi',
    'theme.schedule.day.saturday'      => 'Samedi',
];
