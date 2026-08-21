<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — Spanish strings (locale `es`). Mirrors en/agent.php key-for-key.
 */
return [
    // Settings screen
    'agent.settings.title'        => 'Agente de IA',
    'agent.settings.subtitle'     => 'Conecta tu propia cuenta de IA y deja que el agente trabaje dentro de tu sitio.',
    'agent.settings.saved'        => 'Configuración del agente guardada.',
    'agent.settings.save'         => 'Guardar',
    'agent.settings.provider'     => 'Proveedor',
    'agent.settings.model'        => 'Modelo',
    'agent.settings.model.ph'     => 'p. ej. claude-sonnet-5',
    'agent.settings.model.refresh' => 'Actualizar la lista de modelos',
    'agent.settings.key'          => 'Clave de API',
    'agent.settings.key.ph'       => 'Pega una clave para conectar (déjalo en blanco para conservar la actual)',
    'agent.settings.enabled'      => 'Activar el agente de IA',
    'agent.settings.connected'    => 'Conectado — hay una clave almacenada (cifrada).',
    'agent.settings.disconnected' => 'No conectado — pega una clave de API para activar el agente.',
    'agent.settings.connection'   => 'Conexión',
    'agent.settings.crypto_missing' => 'El cifrado no está configurado (<code>tiger.crypto.key</code>), por lo que aún no se puede almacenar una clave de API de forma segura.',
    'agent.settings.mode_max'     => 'Límite de automatización',
    'agent.settings.mode_max.help' => 'El nivel de automatización más alto que cualquiera aquí puede usar. Los usuarios pueden bajarlo, nunca superarlo.',
    'agent.settings.mode.ask'     => 'Preguntar — aprueba cada cambio (lo más seguro)',
    'agent.settings.mode.auto'    => 'Auto — los cambios rutinarios se ejecutan automáticamente; el código/los archivos siguen preguntando',
    'agent.settings.mode.yolo'    => 'YOLO — todo lo que el rol permite se ejecuta automáticamente',
    'agent.settings.how.title'    => 'Cómo funciona',
    'agent.settings.how.body1'    => 'El agente actúa <strong>como tú</strong> — nunca puede hacer más de lo que tu rol permite. Las lecturas se ejecutan solas; los cambios se muestran primero para tu aprobación.',
    'agent.settings.how.body2'    => '<strong>Trae tu propia cuenta:</strong> la clave que pegas es tuya, se almacena cifrada en este servidor y nunca se comparte. Tu proveedor de IA te factura directamente.',

    // Aside modes
    'agent.mode.ask'              => 'Preguntar',
    'agent.mode.auto'            => 'Auto',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'Aprobar cada cambio',
    'agent.mode.auto.hint'      => 'Los cambios rutinarios se ejecutan solos; el código/los archivos preguntan',
    'agent.mode.yolo.hint'      => 'Todo se ejecuta solo — agárrate fuerte',

    // Turn results
    'agent.turn.ok'             => 'Listo.',
    'agent.approve.ok'          => 'Acciones completadas.',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'Archivo adjuntado.',
    'agent.file.type'           => 'Ese tipo de archivo no es compatible.',
    'agent.file.too_large'      => 'Ese archivo es demasiado grande.',
    'agent.file.failed'         => 'No se pudo adjuntar el archivo. Inténtalo de nuevo.',

    // Errors
    'agent.error.empty'         => 'Escribe un mensaje para el agente.',
    'agent.error.unconfigured'  => 'El agente de IA aún no está conectado. Añade una clave de API en Configuración → Agente de IA.',
    'agent.error.provider'      => 'No se pudo contactar con el proveedor de IA. Comprueba la clave e inténtalo de nuevo.',
    'agent.error.run_missing'   => 'Esa conversación o turno ya no está disponible.',

    // Aside UI
    'agent.aside.title'         => 'Agente',
    'agent.aside.placeholder'   => 'Pide al agente que construya, cambie o explique algo…',
    'agent.aside.new'           => 'Nuevo chat',
    'agent.aside.send'          => 'Enviar',
    'agent.aside.approve'       => 'Aprobar',
    'agent.aside.approve_all'   => 'Aprobar todo',
    'agent.aside.thinking'      => 'Trabajando…',
    'agent.aside.empty'         => 'Inicia una conversación — el agente actúa con tus permisos.',

    // Skills (messages)
    'agent.skills.installed'      => 'Habilidad instalada.',
    'agent.skills.install_failed' => 'No se pudo instalar esa habilidad.',
    'agent.skills.none_found'     => 'No se encontró ningún SKILL.md en esa URL.',
    'agent.skills.enabled'        => 'Habilidad activada.',
    'agent.skills.disabled'       => 'Habilidad desactivada.',
    'agent.skills.removed'        => 'Habilidad eliminada.',

    // Skills (admin screen)
    'agent.skills.title'          => 'Habilidades del agente',
    'agent.skills.subtitle'       => 'Conocimientos instalables para el agente de IA. Tiger navega por estos repositorios — no los avala; revisa la fuente de una habilidad antes de instalarla y activarla. Las habilidades instaladas se fijan arriba.',
    'agent.skills.rescan'         => 'Reescanear',
    'agent.skills.rescan.title'   => 'Volver a escanear las fuentes',
    'agent.skills.add_url'        => 'Añadir desde una URL de GitHub',
    'agent.skills.url.ph'         => 'https://github.com/owner/repo (o una subcarpeta / un SKILL.md)',
    'agent.skills.install'        => 'Instalar',
    'agent.skills.add_url.help'   => 'Cualquier repositorio, rama, subcarpeta o un enlace directo a un SKILL.md — no solo las fuentes listadas.',
    'agent.skills.col.skill'      => 'Habilidad',
    'agent.skills.col.description' => 'Descripción',
    'agent.skills.col.source'     => 'Fuente',
    'agent.skills.col.status'     => 'Estado',
    'agent.skills.col.actions'    => 'Acciones',
    'agent.skills.src.title'      => 'SKILL.md',
    'agent.skills.src.note'       => 'Solo procedencia — revísalo antes de instalar.',
    'agent.skills.close'          => 'Cerrar',

    // MCP connections (outbound) — messages
    'agent.mcp.saved'     => 'Conexión guardada.',
    'agent.mcp.removed'   => 'Conexión eliminada.',
    'agent.mcp.bad_url'   => 'Introduce una URL http(s) válida para el servidor MCP.',
    'agent.mcp.bad_label' => 'Dale un nombre a la conexión.',
    'agent.mcp.not_found' => 'Esa conexión no está disponible.',

    // MCP connections (outbound) — admin screen
    'agent.mcp.title'         => 'Conexiones MCP',
    'agent.mcp.subtitle'      => 'Conecta <strong>servidores MCP</strong> externos para que el agente de IA pueda usar sus herramientas junto con las suyas. Una llamada a una herramienta se ejecuta en el servidor remoto y requiere aprobación como cualquier escritura del agente. Solo administradores.',
    'agent.mcp.add'           => 'Añadir una conexión',
    'agent.mcp.name'          => 'Nombre',
    'agent.mcp.name.ph'       => 'p. ej. GitHub, Linear, Weather',
    'agent.mcp.url'           => 'URL del servidor (Streamable HTTP)',
    'agent.mcp.token'         => 'Token Bearer',
    'agent.mcp.token.optional' => '(opcional; se almacena cifrado)',
    'agent.mcp.token.ph'      => 'déjalo en blanco para conservar el actual',
    'agent.mcp.enabled'       => 'Activado',
    'agent.mcp.save'          => 'Guardar',
    'agent.mcp.cancel'        => 'Cancelar',
    'agent.mcp.connected'     => 'Servidores conectados',
    'agent.mcp.empty'         => 'Aún no hay conexiones — añade una a la izquierda.',
    'agent.js.models_live' => 'En vivo desde tu cuenta.',
    'agent.js.models_static' => 'Modelos comunes — conecta una clave para la lista en vivo.',
    'agent.js.settings_saved' => 'Configuración guardada.',
    'agent.js.network_error' => 'Error de red — inténtalo de nuevo.',
    'agent.js.connection_saved' => 'Conexión guardada.',
    'agent.js.remove_connection_title' => 'Quitar conexión',
    'agent.js.remove_connection_body' => 'El agente perderá acceso a sus herramientas.',
    'agent.js.remove_label' => 'Quitar',
    'agent.js.remove_skill_title' => 'Quitar skill',
    'agent.js.remove_skill_body' => '¿Quitar este skill y sus archivos? (Permanece en el catálogo para reinstalar.)',
    'agent.nav.label' => 'Agente IA',
    'agent.nav.skills' => 'Habilidades del agente',
    'agent.nav.mcp' => 'Conexiones MCP',
];
