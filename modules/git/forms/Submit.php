<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Submit — the submit-a-release-to-QA input contract (TIGER-334 / TIGER-367).
 *
 * The version is re-checked against Tiger_Git::isSemver in the service (the authoritative semver gate);
 * here the shape is enforced. `commit_sha` is the 7–40 hex of the tagged commit (the owner gets it from
 * the `tag` response). `qa_report_ref` is an optional pointer to the QA evidence.
 */
class Git_Form_Submit extends Tiger_Form
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
            ['text', 'version', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => 64]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 64, 'placeholder' => '1.0.0', 'autocomplete' => 'off'],
            ]],
            ['text', 'commit_sha', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '/^[0-9a-fA-F]{7,40}$/']]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 40, 'autocomplete' => 'off'],
            ]],
            ['text', 'qa_report_ref', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 0, 'max' => 191]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => 191, 'autocomplete' => 'off'],
            ]],
        ];
    }
}
