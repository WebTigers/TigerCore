<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Form_Password — the change-password form on the profile Security tab.
 *
 * The new password (validated against the platform policy AND the user's own history via
 * Tiger_Validate_Password scoped to the user id) and a confirm that must match. There is
 * DELIBERATELY no "current password" field: a user who forgot their password signs in with an
 * OTP code, so demanding the old password would lock them out of the very screen that fixes it.
 * A logged-in session is authority enough to set a new password (same as the admin reset path).
 */
class Profile_Form_Password extends Tiger_Form
{
    /** @var string|null the user whose history scopes reuse-prevention */
    protected $_userId;

    /**
     * @param  string|null $userId the acting user (enables password-reuse prevention)
     * @param  mixed       $options passed through to Zend_Form
     * @return void
     */
    public function __construct($userId = null, $options = null)
    {
        // An explicit id wins (the submit path in Profile_Service_Security passes it). When none
        // is given — which is exactly how the convenience/blur path builds this form, since
        // Tiger_Service_Validate does a bare `new $class()` — fall back to the authenticated
        // actor so reuse-prevention runs on blur with the SAME user context it has at submit.
        // Resolve BEFORE the parent ctor: init()->elements() reads $this->_userId.
        $this->_userId = $userId ?: self::_currentUserId();
        parent::__construct($options);
    }

    /**
     * The authenticated actor's user id, or null when there's no identity (e.g. a guest, or a
     * unit test with no session). Lets the convenience path scope reuse-prevention to the user
     * whose password is being changed without the generic validate service having to know it.
     *
     * @return string|null the current user id, or null when unauthenticated
     */
    protected static function _currentUserId()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        return ($identity && isset($identity->user_id) && $identity->user_id !== '')
            ? (string) $identity->user_id
            : null;
    }

    /**
     * The change-password element schema.
     *
     * @return array the Tiger_Form element definitions
     */
    protected function elements(): array
    {
        return [
            ['password', 'new_password', [
                'required'   => true,
                'validators' => [new Tiger_Validate_Password($this->_userId)],
                // The live strength meter (data-tiger-strength) is a hint; the policy is the authority.
                // NO data-no-validate here: TigerValidateJS must run the real policy (min-length + reuse)
                // on blur too, so the min-length change and a reused password fail live, not only at submit.
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'new-password', 'data-tiger-strength' => '1'],
            ]],
            ['password', 'confirm_password', [
                'required'   => true,
                // A clear, localized "Passwords do not match." instead of Zend's default
                // "The two given tokens do not match" (the key is translated in _formErrors).
                'validators' => [['Identical', false, [
                    'token'    => 'new_password',
                    'messages' => ['notSame' => 'core.form.password_mismatch'],
                ]]],
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'new-password'],
            ]],
        ];
    }
}
