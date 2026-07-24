<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent attachments — files a user drops onto the agent aside so the agent can see (images,
 * via native vision) or read (documents, via text extraction) them, and act on them with its tools.
 *
 * Its OWN table (never `media`), so nothing here shows up in the Media Manager. The BYTES ride on
 * the SAME storage disk the media system is configured for (`Tiger_Media_Storage` — filesystem or
 * S3/GCS/Azure), under the module's private key prefix `agent/<org_id>/<user_id>/…`, served only
 * in-process (never a public URL). `extract` caches a document's readable text so the turn engine
 * folds it into context without re-parsing. A row is created on upload (unlinked), then linked to
 * the conversation + the user message when that turn is sent — mirroring the roundtable pattern.
 */
return [
    'up' => [
        "CREATE TABLE `agent_attachment` (
            `attachment_id`   CHAR(36)     NOT NULL,
            `conversation_id` CHAR(36)         NULL,               -- linked when the turn is sent
            `message_id`      CHAR(36)         NULL,               -- the user message that carried it
            `user_id`         CHAR(36)     NOT NULL,               -- owner (scopes every read)
            `org_id`          CHAR(36)     NOT NULL,
            `disk`            VARCHAR(64)  NOT NULL DEFAULT 'local', -- the media disk name (shared config)
            `storage_key`     VARCHAR(512)     NULL,               -- agent/<org>/<user>/<id>.<ext> (private)
            `filename`        VARCHAR(255) NOT NULL,
            `mime_type`       VARCHAR(128)     NULL,
            `file_size`       BIGINT           NULL,
            `kind`            VARCHAR(16)  NOT NULL DEFAULT 'file', -- image | file
            `extract`         MEDIUMTEXT       NULL,               -- cached readable text (documents)
            `deleted`         TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`      CHAR(36)         NULL,
            `updated_by`      CHAR(36)         NULL,
            `created_at`      DATETIME     NOT NULL,
            `updated_at`      DATETIME         NULL,
            PRIMARY KEY (`attachment_id`),
            KEY `ix_agent_attach_msg`  (`conversation_id`, `message_id`),
            KEY `ix_agent_attach_user` (`user_id`, `message_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `agent_attachment`",
    ],
];
