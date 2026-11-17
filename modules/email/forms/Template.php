<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email_Form_Template — the authored subject + HTML body of a transactional-email override (TIGER-267).
 *
 * Values are stored in the `email_template` table (the DB-override tier) by Email_Service_Template — the
 * live-override pattern, no deploy. The body is author HTML carrying `{{var}}` placeholders; the service
 * substitutes + wraps it in the shared branded layout at send time. ViewHelper-only decorators (the view
 * owns markup, the editor is AJAX-submitted).
 *
 * Shares the module's CSRF salt (Email_Form_Write, TIGER-528), so the editor's one token covers reset too.
 *
 * @api
 */
class Email_Form_Template extends Email_Form_Write
{
    protected function elements(): array
    {
        return [
            // The template key — which registered event this override is for. Hidden; the editor sets it.
            ['hidden', 'template_key', [
                'required' => true,
                'filters'  => ['StringTrim'],
            ]],
            // Legacy, IGNORED on save: the override is always stored at the caller's org (TIGER-511) —
            // a client-sent scope could otherwise write another org's (or the global) template.
            ['hidden', 'scope', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
            ['text', 'subject', [
                'required' => true,
                'filters'  => ['StringTrim'],
                'attribs'  => ['class' => 'form-control', 'id' => 'email-tpl-subject', 'maxlength' => 255],
            ]],
            ['textarea', 'body', [
                'required' => true,
                'attribs'  => ['class' => 'form-control font-monospace', 'id' => 'email-tpl-body', 'rows' => 18],
            ]],
        ];
    }
}
