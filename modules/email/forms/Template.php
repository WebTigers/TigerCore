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
 * @api
 */
class Email_Form_Template extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            // The template key — which registered event this override is for. Hidden; the editor sets it.
            ['hidden', 'template_key', [
                'required' => true,
                'filters'  => ['StringTrim'],
            ]],
            // The scope the override is stored at ('' = the global/platform default). Hidden; '' in v1.
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
