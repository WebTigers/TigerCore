<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Connect — the connect-a-module-to-its-repo input contract (TIGER-334 / TIGER-367).
 *
 * The owner/name charset and the module slug are re-checked in the service (Tiger_Git::repoSlug, the
 * slug guard) because they also gate path/host safety; the form is the field-shape contract. The token
 * is a secret — required, trimmed, never echoed back by the service.
 */
class Git_Form_Connect extends Tiger_Form
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['text', 'module_key', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 100]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 100, 'autocomplete' => 'off'],
            ]],
            ['text', 'repo_owner', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 100]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 100, 'autocomplete' => 'off'],
            ]],
            ['text', 'repo_name', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 100]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 100, 'autocomplete' => 'off'],
            ]],
            ['text', 'default_branch', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 0, 'max' => 191]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 191, 'placeholder' => 'main', 'autocomplete' => 'off'],
            ]],
            ['password', 'token', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 255]]],
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'off'],
            ]],
            ['checkbox', 'write_enabled', [
                'required' => false,
                'attribs'  => ['class' => 'form-check-input'],
            ]],
        ];
    }
}
