<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_AdminWrite — the shared base for the agent's ADMIN writes (TIGER-528): the agent
 * registry + governance (Settings screen), MCP Connections, and the Skills manager.
 *
 * One CSRF salt for all of them. Each screen renders one token and posts several endpoints from it
 * (the Settings screen saves an agent, deletes one, and saves the mode ceiling), and
 * `Tiger_Form::csrfSalt()` defaults to the form class — separate salts would fail the second call
 * with "your security token expired". (Before this base, the Settings screen rendered
 * Agent_Form_Settings' token while `agents/save` validated Agent_Form_Agent's, so a browser save
 * could not pass.) The chat aside keeps its own salt (`Agent`, on Send/Approve/Resume).
 *
 * @api
 */
abstract class Agent_Form_AdminWrite extends Tiger_Form
{
    /**
     * One token for every agent admin write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Agent_Form_AdminWrite';
    }
}
