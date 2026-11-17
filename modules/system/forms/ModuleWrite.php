<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ModuleWrite — the shared base for every Module Manager write (TIGER-528).
 *
 * The Modules screen (`/system/modules`) and the Add Module screen (`/system/modules/add`) drive a
 * dozen System_Service_Modules endpoints from one page each — activate, deactivate, auto-update,
 * delete, the TigerPASS tab, the marketplace sources, and every install path. Each screen renders ONE
 * `_csrf` token; `Tiger_Form::csrfSalt()` defaults to the form class, which would mint a different
 * token per form and fail the second call with "your security token expired". So the whole module
 * manager shares one salt here (the documented use of that override — see `Desk_Form_TicketWrite`).
 *
 * The concrete forms declare the fields their methods read, every one OPTIONAL and unfiltered: the
 * service keeps its own refusals (an unknown slug, a bad UUID, a non-http source URL …) with the
 * error keys the screens already render, so this conversion changes what is checked (the token) and
 * not what is accepted. An element that can arrive as an array carries no `StringTrim` (it would cast
 * the array with a warning).
 */
abstract class System_Form_ModuleWrite extends Tiger_Form
{
    /** The salt the module-manager screens share (one rendered token serves every call on the page). */
    const SALT = 'System_Form_Module';

    /**
     * One token for every module-manager write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return self::SALT;
    }

    /**
     * An optional, unfiltered, unvalidated field — the service owns its normalization + refusal.
     *
     * @param  string $name the element/param name
     * @return array        [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
