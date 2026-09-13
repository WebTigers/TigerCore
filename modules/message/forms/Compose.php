<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Form_Compose — the send-a-message input contract (TIGER-114).
 *
 * Subject and body only. Recipients are validated in the service, not here, because "is this an
 * active member of MY org" needs the caller's identity, which a form does not have.
 */
class Message_Form_Compose extends Tiger_Form
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['text', 'subject', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => Tiger_Model_Message::MAX_SUBJECT]]],
                'attribs'    => ['class' => 'form-control', 'maxlength' => Tiger_Model_Message::MAX_SUBJECT, 'autocomplete' => 'off'],
            ]],
            ['textarea', 'body', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, ['min' => 1, 'max' => Tiger_Model_Message::MAX_BODY]]],
                'attribs'    => ['class' => 'form-control', 'rows' => 8],
            ]],
        ];
    }
}
