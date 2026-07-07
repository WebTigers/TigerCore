<?php
/**
 * Tiger_Install — first-run bootstrap helpers.
 *
 * Creates the founding org + user + password + membership for a fresh install.
 * This is a SYSTEM/genesis operation: there's no logged-in actor, so the created
 * rows get created_by = NULL. Kept as a class (not inline in bin/tiger) so
 * create-project / a web installer can reuse it. bin/tiger `install:admin` gathers
 * input and calls createOwner().
 *
 * @api
 */
class Tiger_Install
{
    const MIN_PASSWORD = 8;

    /**
     * Create the founding org + owner user + password credential + membership.
     *
     * @param  string      $email
     * @param  string      $password
     * @param  string      $orgName
     * @param  string|null $orgSlug  derived from the org name if null
     * @param  string      $role     the membership role (default 'developer' = god,
     *                               because a fresh install's founder needs full access)
     * @param  string|null $username optional display username (email stays the login id)
     * @return array{org_id:string,user_id:string,org_user_id:string,role:string,email:string,username:?string,org:string,slug:string}
     * @throws RuntimeException on validation error or conflict (existing email/slug/username)
     */
    public static function createOwner($email, $password, $orgName, $orgSlug = null, $role = 'developer', $username = null)
    {
        $email   = trim(strtolower((string) $email));
        $orgName = trim((string) $orgName);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email is required.');
        }
        $violations = (new Tiger_Policy_Password())->validate((string) $password);
        if ($violations) {
            throw new RuntimeException('Password does not meet policy: ' . implode(', ', $violations));
        }
        if ($orgName === '') {
            throw new RuntimeException('An organization name is required.');
        }
        $slug = $orgSlug ? self::slugify($orgSlug) : self::slugify($orgName);

        $userModel = new Tiger_Model_User();
        if ($userModel->findByEmail($email)) {
            throw new RuntimeException("A user with email {$email} already exists.");
        }
        $orgModel = new Tiger_Model_Org();
        if ($orgModel->findBySlug($slug)) {
            throw new RuntimeException("An organization with slug '{$slug}' already exists.");
        }

        // Optional username — must be unique if given (email stays the login id).
        $username = ($username !== null) ? trim((string) $username) : '';
        if ($username !== '' && $userModel->fetchRow($userModel->activeSelect()->where('username = ?', $username))) {
            throw new RuntimeException("A user with username '{$username}' already exists.");
        }

        // Genesis rows (no actor -> created_by NULL).
        $orgId    = $orgModel->insert(['name' => $orgName, 'slug' => $slug]);
        $userData = ['email' => $email];
        if ($username !== '') { $userData['username'] = $username; }
        $userId   = $userModel->insert($userData);
        (new Tiger_Model_UserCredential())->setPassword($userId, (string) $password);
        $ouId = (new Tiger_Model_OrgUser())->insert([
            'org_id'  => $orgId,
            'user_id' => $userId,
            'role'    => $role,
        ]);

        return [
            'org_id'      => $orgId,
            'user_id'     => $userId,
            'org_user_id' => $ouId,
            'role'        => $role,
            'email'       => $email,
            'username'    => $username !== '' ? $username : null,
            'org'         => $orgName,
            'slug'        => $slug,
        ];
    }

    /**
     * Ensure the install's random secrets exist in local.ini — the app encryption key
     * (tiger.crypto.key) and the password/code pepper (tiger.security.pepper). Generates
     * and writes any that are missing/empty; leaves existing values untouched (never
     * rotates a live secret). Idempotent.
     *
     * This is the ONE place secrets are minted at install time: the CLI (`tiger
     * install:secrets`, and install:admin) and a web/cPanel setup form both call it right
     * after writing the DB creds, so the founding password is peppered from the very first
     * hash. Secrets live ONLY in local.ini (gitignored), never in the repo or the DB.
     *
     * @param  string|null $localIniPath  defaults to APPLICATION_PATH/configs/local.ini
     * @return string[] the config keys that were newly generated (empty = all already set)
     */
    public static function provisionSecrets($localIniPath = null)
    {
        $path = $localIniPath ?: (defined('APPLICATION_PATH') ? APPLICATION_PATH . '/configs/local.ini' : null);
        if (!$path) {
            throw new RuntimeException('provisionSecrets: no local.ini path (APPLICATION_PATH undefined).');
        }
        if (!is_file($path)) {
            file_put_contents($path, "[production]\n");   // a base section so Zend_Config_Ini can load it
        }
        $text      = (string) file_get_contents($path);
        $generated = [];
        $secrets   = [
            'tiger.crypto.key'      => ['Tiger_Crypto', 'generateKey'],
            'tiger.security.pepper' => ['Tiger_Security', 'generatePepper'],
        ];
        foreach ($secrets as $key => $generator) {
            if (self::_localKeyIsSet($text, $key)) {
                continue;
            }
            $text = self::_writeLocalKey($text, $key, (string) call_user_func($generator));
            $generated[] = $key;
        }
        if ($generated) {
            file_put_contents($path, $text);
        }
        return $generated;
    }

    /** Is an ini key present with a NON-empty value in the raw text? */
    protected static function _localKeyIsSet($text, $key)
    {
        return (bool) preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*["\']?\S/m', $text);
    }

    /** Write `key = "value"`: replace an empty declaration, else insert under [production]. */
    protected static function _writeLocalKey($text, $key, $value)
    {
        $line = $key . ' = "' . $value . '"';
        $q    = preg_quote($key, '/');
        if (preg_match('/^\s*' . $q . '\s*=.*$/m', $text)) {                       // present but empty -> replace
            return preg_replace('/^\s*' . $q . '\s*=.*$/m', $line, $text, 1);
        }
        if (preg_match('/^\[production\][^\n]*\n/m', $text, $m, PREG_OFFSET_CAPTURE)) {  // insert after [production]
            $pos = $m[0][1] + strlen($m[0][0]);
            return substr($text, 0, $pos) . $line . "\n" . substr($text, $pos);
        }
        return rtrim($text) . "\n" . $line . "\n";                                  // no section -> append
    }

    /** URL-safe slug from a name. */
    public static function slugify($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-') ?: 'org';
    }
}
