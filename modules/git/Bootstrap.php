<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Bootstrap — the Tiger_Git feature module (TIGER-334).
 *
 * The module that gives a Tiger install first-class git/GitHub: version a custom module in a private
 * repo, cut a release through QA, and receive approved updates — with no local machine and no terminal,
 * working on shared hosting through the ApiTransport when the git binary is unavailable.
 *
 * Phase 1 (this version) ships the SUBSTRATE only — the facade (Tiger_Git), the transport/provider
 * interfaces, capability detection (Tiger_Git_Environment), the credential manager (Tiger_Git_Credential),
 * and the data model (migration 20261021000001). The transports, the /api services, and the TigerPanel
 * UI land in Phase 2+. There is deliberately nothing to register here yet; the module exists so its
 * migrations are discovered by `tiger migrate` and so Phase-2 controllers/routes have a home.
 */
class Git_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
