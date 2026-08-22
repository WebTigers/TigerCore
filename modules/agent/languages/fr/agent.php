<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — French strings (locale `fr`). Mirrors en/agent.php key-for-key.
 */
return [
    // Settings screen
    'agent.settings.title'        => 'Agent IA',
    'agent.settings.subtitle'     => 'Connectez votre propre compte IA et laissez l’agent travailler au sein de votre site.',
    'agent.settings.saved'        => 'Paramètres de l’agent enregistrés.',
    'agent.settings.save'         => 'Enregistrer',
    'agent.settings.provider'     => 'Fournisseur',
    'agent.settings.model'        => 'Modèle',
    'agent.settings.model.ph'     => 'ex. claude-sonnet-5',
    'agent.settings.model.refresh' => 'Actualiser la liste des modèles',
    'agent.settings.key'          => 'Clé API',
    'agent.settings.key.ph'       => 'Collez une clé pour vous connecter (laissez vide pour conserver l’actuelle)',
    'agent.settings.enabled'      => 'Activer l’agent IA',
    'agent.settings.connected'    => 'Connecté — une clé est stockée (chiffrée).',
    'agent.settings.disconnected' => 'Non connecté — collez une clé API pour activer l’agent.',
    'agent.settings.connection'   => 'Connexion',
    'agent.settings.crypto_missing' => 'Le chiffrement n’est pas configuré (<code>tiger.crypto.key</code>), une clé API ne peut donc pas encore être stockée en toute sécurité.',
    'agent.settings.mode_max'     => 'Plafond d’automatisation',
    'agent.settings.mode_max.help' => 'Le niveau d’automatisation le plus élevé que quiconque ici peut utiliser. Les utilisateurs peuvent réduire, jamais dépasser.',
    'agent.settings.mode.ask'     => 'Demander — approuver chaque modification (le plus sûr)',
    'agent.settings.mode.auto'    => 'Auto — les modifications de routine s’exécutent automatiquement ; le code/les fichiers demandent toujours',
    'agent.settings.mode.yolo'    => 'YOLO — tout ce que le rôle autorise s’exécute automatiquement',
    'agent.settings.how.title'    => 'Comment ça marche',
    'agent.settings.how.body1'    => 'L’agent agit <strong>en tant que vous</strong> — il ne peut jamais faire plus que ce que votre rôle permet. Les lectures s’exécutent seules ; les modifications sont d’abord présentées pour votre approbation.',
    'agent.settings.how.body2'    => '<strong>Apportez votre propre compte :</strong> la clé que vous collez est la vôtre, stockée chiffrée sur ce serveur et jamais partagée. Votre fournisseur IA vous facture directement.',

    // Aside modes
    'agent.mode.ask'              => 'Demander',
    'agent.mode.auto'            => 'Auto',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'Approuver chaque modification',
    'agent.mode.auto.hint'      => 'Les modifications de routine s’exécutent seules ; le code/les fichiers demandent',
    'agent.mode.yolo.hint'      => 'Tout s’exécute seul — accrochez-vous',

    // Turn results
    'agent.turn.ok'             => 'Terminé.',
    'agent.approve.ok'          => 'Actions terminées.',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'Fichier joint.',
    'agent.file.type'           => 'Ce type de fichier n’est pas pris en charge.',
    'agent.file.too_large'      => 'Ce fichier est trop volumineux.',
    'agent.file.failed'         => 'Le fichier n’a pas pu être joint. Veuillez réessayer.',

    // Errors
    'agent.error.empty'         => 'Saisissez un message pour l’agent.',
    'agent.error.unconfigured'  => 'L’agent IA n’est pas encore connecté. Ajoutez une clé API sous Paramètres → Agent IA.',
    'agent.error.provider'      => 'Impossible de joindre le fournisseur IA. Vérifiez la clé et réessayez.',
    'agent.error.run_missing'   => 'Cette conversation ou cette étape n’est plus disponible.',

    // Aside UI
    'agent.aside.title'         => 'Agent',
    'agent.aside.placeholder'   => 'Demandez à l’agent de créer, modifier ou expliquer quelque chose…',
    'agent.aside.new'           => 'Nouvelle discussion',
    'agent.aside.send'          => 'Envoyer',
    'agent.aside.approve'       => 'Approuver',
    'agent.aside.approve_all'   => 'Tout approuver',
    'agent.aside.thinking'      => 'En cours…',
    'agent.aside.empty'         => 'Démarrez une conversation — l’agent agit avec vos permissions.',

    // Skills (messages)
    'agent.skills.installed'      => 'Compétence installée.',
    'agent.skills.install_failed' => 'Cette compétence n’a pas pu être installée.',
    'agent.skills.none_found'     => 'Aucun SKILL.md trouvé à cette URL.',
    'agent.skills.enabled'        => 'Compétence activée.',
    'agent.skills.disabled'       => 'Compétence désactivée.',
    'agent.skills.removed'        => 'Compétence supprimée.',

    // Skills (admin screen)
    'agent.skills.title'          => 'Compétences de l’agent',
    'agent.skills.subtitle'       => 'Savoir-faire installable pour l’agent IA. Tiger parcourt ces dépôts — il ne les cautionne pas ; examinez la source d’une compétence avant de l’installer et de l’activer. Les compétences installées sont épinglées en haut.',
    'agent.skills.rescan'         => 'Réanalyser',
    'agent.skills.rescan.title'   => 'Réanalyser les sources',
    'agent.skills.add_url'        => 'Ajouter depuis une URL GitHub',
    'agent.skills.url.ph'         => 'https://github.com/owner/repo (ou un sous-dossier / un SKILL.md)',
    'agent.skills.install'        => 'Installer',
    'agent.skills.add_url.help'   => 'N’importe quel dépôt, branche, sous-dossier, ou un lien direct vers un SKILL.md — pas seulement les sources listées.',
    'agent.skills.col.skill'      => 'Compétence',
    'agent.skills.col.description' => 'Description',
    'agent.skills.col.source'     => 'Source',
    'agent.skills.col.status'     => 'Statut',
    'agent.skills.col.actions'    => 'Actions',
    'agent.skills.src.title'      => 'SKILL.md',
    'agent.skills.src.note'       => 'Provenance uniquement — examinez avant d’installer.',
    'agent.skills.close'          => 'Fermer',

    // MCP connections (outbound) — messages
    'agent.mcp.saved'     => 'Connexion enregistrée.',
    'agent.mcp.removed'   => 'Connexion supprimée.',
    'agent.mcp.bad_url'   => 'Saisissez une URL http(s) valide pour le serveur MCP.',
    'agent.mcp.bad_label' => 'Donnez un nom à la connexion.',
    'agent.mcp.not_found' => 'Cette connexion n’est pas disponible.',

    // MCP connections (outbound) — admin screen
    'agent.mcp.title'         => 'Connexions MCP',
    'agent.mcp.subtitle'      => 'Connectez des <strong>serveurs MCP</strong> externes pour que l’agent IA puisse utiliser leurs outils aux côtés des siens. Un appel d’outil s’exécute sur le serveur distant et nécessite une approbation comme toute écriture de l’agent. Administrateurs uniquement.',
    'agent.mcp.add'           => 'Ajouter une connexion',
    'agent.mcp.name'          => 'Nom',
    'agent.mcp.name.ph'       => 'ex. GitHub, Linear, Weather',
    'agent.mcp.url'           => 'URL du serveur (Streamable HTTP)',
    'agent.mcp.token'         => 'Jeton Bearer',
    'agent.mcp.token.optional' => '(facultatif ; stocké chiffré)',
    'agent.mcp.token.ph'      => 'laissez vide pour conserver l’actuel',
    'agent.mcp.enabled'       => 'Activé',
    'agent.mcp.save'          => 'Enregistrer',
    'agent.mcp.cancel'        => 'Annuler',
    'agent.mcp.connected'     => 'Serveurs connectés',
    'agent.mcp.empty'         => 'Aucune connexion pour l’instant — ajoutez-en une à gauche.',
    'agent.js.models_live' => 'En direct depuis votre compte.',
    'agent.js.models_static' => 'Modèles courants — connectez une clé pour la liste en direct.',
    'agent.js.settings_saved' => 'Paramètres enregistrés.',
    'agent.js.network_error' => 'Erreur réseau — veuillez réessayer.',
    'agent.js.connection_saved' => 'Connexion enregistrée.',
    'agent.js.remove_connection_title' => 'Supprimer la connexion',
    'agent.js.remove_connection_body' => 'L’agent perdra l’accès à ses outils.',
    'agent.js.remove_label' => 'Supprimer',
    'agent.js.remove_skill_title' => 'Supprimer la compétence',
    'agent.js.remove_skill_body' => 'Supprimer cette compétence et ses fichiers ? (Elle reste dans le catalogue pour réinstallation.)',
    'agent.nav.label' => 'Agent IA',
    'agent.nav.skills' => 'Compétences de l’agent',
    'agent.nav.mcp' => 'Connexions MCP',
];
