<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_OrgProfile — the org Basic tab (name + URL-safe slug).
 *
 * Deliberately thin (base-SaaS): identity only. Slug uniqueness (excluding the current org) is a
 * dynamic check, so it lives in Profile_Service_Org::save, not here. The view hand-renders the
 * inputs; this form validates + provides the CSRF token.
 */
class Profile_Form_OrgProfile extends Tiger_Form
{
    /**
     * @return array the Tiger_Form element definitions
     */
    protected function elements(): array
    {
        return [
            ['text', 'name', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', true, ['min' => 1, 'max' => 255]]],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['text', 'slug', [
                'filters' => ['StringTrim'],
                'attribs' => ['class' => 'form-control', 'maxlength' => 191],
            ]],
        ];
    }
}
