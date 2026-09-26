<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_Security — the /api service behind the profile Security tab.
 *
 * Self-service and STRICTLY self-scoped ($this->_user_id). A logged-in user sets a new password
 * outright — NO current-password step: someone who forgot their password logs in via OTP, so
 * demanding the old one would lock them out of the screen that fixes it. The authenticated session
 * is the authority (same shape as the admin reset in Access_Service_User). Password policy +
 * reuse-prevention come from Tiger_Validate_Password (in the form); history archival is handled by
 * Tiger_Model_UserCredential::setPassword.
 *
 * @api
 */
class Profile_Service_Security extends Tiger_Service_Service
{
    /**
     * Set the current user's password (new + confirm; no current-password step — see the class note).
     *
     * @param  array $params new_password, confirm_password
     * @return void
     */
    public function changePassword(array $params): void
    {
        $userId = (string) $this->_user_id;
        if ($userId === '') {
            $this->_error('core.api.error.not_allowed');
            return;
        }

        $form = new Profile_Form_Password($userId);
        if (!$form->isValid($params)) {
            $this->_formErrors($form);
            return;
        }

        $new = (string) $form->getValue('new_password');
        try {
            // Route through the auth service's write seam so a registered credential provider (e.g.
            // TigerServer's system credential) is honoured — changing the password here rewrites the
            // OS password, not an ignored DB row. Falls back to the DB credential when no provider
            // owns this user. False = provider write failed / a read-only (externally managed) authority.
            $ok = $this->_transaction(function () use ($userId, $new) {
                return (new Tiger_Service_Authentication())->setPasswordFor($userId, $new);
            });
            if (!$ok) {
                $this->_error('profile.security.password_change_failed');
                return;
            }
            $this->_success([], 'profile.security.password_changed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
