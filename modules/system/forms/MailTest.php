<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

/**
 * System_Form_MailTest — the Settings screen's "Send test" (System_Service_Settings::mailTest).
 *
 * The test sends real mail with the stored credentials to the address in `to`, so without a token a cross-site
 * page could make a signed-in admin's browser send mail through the install (TIGER-528). It shares the Settings
 * form's CSRF salt, so the one token the Settings screen already renders covers both Save and Send test.
 *
 * Only `to` is declared with a validator: the mail-transport fields are the same ones System_Form_Settings
 * carries and are read (and blank-to-stored resolved) by the service exactly as before.
 *
 * @api
 */
class System_Form_MailTest extends Tiger_Form
{
    /**
     * The Settings screen's salt — one rendered token covers Save and Send test.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'System_Form_Settings';
    }

    /**
     * The test recipient.
     *
     * @return array
     */
    protected function elements(): array
    {
        return [
            ['text', 'to', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['NotEmpty', true, ['messages' => ['isEmpty' => $this->_t('system.settings.smtp.test_bad_address')]]],
                    ['EmailAddress', true, ['messages' => [
                        Zend_Validate_EmailAddress::INVALID        => $this->_t('system.settings.smtp.test_bad_address'),
                        Zend_Validate_EmailAddress::INVALID_FORMAT => $this->_t('system.settings.smtp.test_bad_address'),
                    ]]],
                ],
            ]],
        ];
    }
}
