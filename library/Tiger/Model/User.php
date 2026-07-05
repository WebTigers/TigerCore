<?php
/**
 * User — a person / identity. Deliberately THIN.
 *
 * A User row is PURE IDENTITY: id, email, optional username, and status. That's it.
 * NO credentials, NO profile, NO org, NO role — none of those belong on the user:
 *
 *   - CREDENTIALS (password, SMS/phone, TOTP, passkeys, SSO) live in
 *     `user_credential` (Tiger_Model_UserCredential), 1-to-many, because auth is
 *     multi-factor and a credential is not identity.
 *   - Profile/richness (name, avatar, phone-as-contact, preferences) belongs to an
 *     Account MODULE that extends User via its own FK-linked table, so the platform
 *     updates without colliding with app-specific profile shapes.
 *   - A user's relationship to a tenant — and their ROLE — lives on org_user
 *     (Tiger_Model_OrgUser), because a user can belong to many orgs with a different
 *     role in each. A role on the user would force one global role and break
 *     multi-tenancy.
 *
 * @api
 */
class Tiger_Model_User extends Tiger_Model_Table
{
    protected $_name    = 'user';
    protected $_primary = 'user_id';

    /**
     * Find a user by email (the canonical login identifier; unique).
     *
     * @param  string $email
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findByEmail($email)
    {
        return $this->fetchRow($this->activeSelect()->where('email = ?', $email)) ?: null;
    }
}
