<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Session_SaveHandler_DbTable — DB-backed PHP session handler.
 *
 * Ported from AskLevi's Levi_Session_SaveHandler_DbTable. Extends ZF1's
 * Zend_Session_SaveHandler_DbTable to (a) validate the session id, (b) stamp the
 * current user/role/org/ip onto the row (for auditing + admin session views), and
 * (c) apply a tiered, config-driven idle TTL. GC reaps expired rows.
 *
 * @api
 */
class Tiger_Session_SaveHandler_DbTable extends Zend_Session_SaveHandler_DbTable
{
    /**
     * The capability that puts a session on the short (sensitive) TTL tier (TIGER-511) — a named ACL
     * resource, not a role list. Core's acl.ini grants privilege `short_ttl` at `admin` (superadmin +
     * developer inherit), the same roles the old hardcoded list named.
     */
    const RESOURCE_PRIVILEGED = 'Tiger_Session_Privileged';

    /** The privilege on RESOURCE_PRIVILEGED that selects the short tier. */
    const PRIVILEGE_SHORT_TTL = 'short_ttl';

    /**
     * Write the session row, stamping identity context + a role-based lifetime.
     *
     * @param  string $id   the session id
     * @param  string $data the serialized session payload
     * @return bool          true on success (or a skipped empty guest session)
     */
    public function write($id, $data): bool
    {
        // Reject anything that isn't a well-formed session id.
        if (!preg_match('/^[a-zA-Z0-9,\-]{1,128}$/', $id)) {
            return false;
        }

        $ctx      = $this->_identityContext();
        $lifetime = $this->_lifetimeForRole($ctx['role'], !empty($ctx['user_id']));
        $row      = $this->find($id)->current();

        if (empty($row)) {
            // Don't persist empty anonymous sessions (avoids a row per guest hit).
            if (empty($ctx['user_id']) && trim((string) $data) === '') {
                return true;
            }
            $row = $this->createRow();
            $row->session_id = $id;
        }

        $row->{$this->_modifiedColumn} = time();
        $row->{$this->_dataColumn}     = (string) $data;
        $row->{$this->_lifetimeColumn} = $lifetime;
        $row->user_id    = $ctx['user_id'];
        $row->username   = $ctx['username'];
        $row->role       = $ctx['role'];
        $row->org_id     = $ctx['org_id'];
        $row->ip_address = $ctx['ip_address'];
        $row->save();

        return true;
    }

    /**
     * Reap expired sessions. Never lets a GC failure break the request.
     *
     * @param  int $maxlifetime PHP's configured max session lifetime (unused; TTL is per-row)
     * @return bool             always true
     */
    public function gc($maxlifetime): bool
    {
        try {
            (new Tiger_Model_Session())->gc();
        } catch (Throwable $e) {
            error_log('Tiger session GC failed: ' . $e->getMessage());
        }
        return true;
    }

    // -------------------------------------------------------------------------

    /** user/username/role/org/ip from the current identity (guest defaults). */
    protected function _identityContext()
    {
        $ctx = [
            'user_id'    => null,
            'username'   => null,
            'role'       => 'guest',
            'org_id'     => null,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
        ];

        $auth = Zend_Auth::getInstance();
        if ($auth->hasIdentity()) {
            $identity          = $auth->getIdentity();
            $ctx['user_id']    = $identity->user_id ?? null;
            $ctx['username']   = $identity->username ?? null;
            $ctx['role']       = $identity->role ?? 'guest';
            $ctx['org_id']     = $identity->org_id ?? null;
        }
        return $ctx;
    }

    /**
     * Idle TTL (seconds) stamped as `lifetime`; GC reaps once (now - modified) >
     * lifetime. Tiered + config-driven (tiger.session.ttl.*), tunable live via the
     * `config` table:
     *   - a role holding Tiger_Session_Privileged/short_ttl → privileged (short, sensitive)
     *   - no signed-in user                                 → guest (short-ish)
     *   - any other authenticated session                   → authed (long)
     *
     * The tier is decided by the ACL (TIGER-511), never a role list. A session is written at the END of
     * the request, and on an early-failing request (bootstrap error, CLI, install) the ACL may not be
     * built yet — then an authenticated session gets the SHORTER of the privileged and authed tiers: a
     * session we can't classify is treated as sensitive, never handed the long tier by default.
     *
     * @param  string $role   the identity's role
     * @param  bool   $authed whether a user is signed in (false → the guest tier)
     * @return int            seconds
     */
    protected function _lifetimeForRole($role, $authed = true)
    {
        $ttl = $this->_ttlConfig();
        if (!$authed) {
            return $ttl['guest'];
        }
        if (!Tiger_Acl_Acl::isAvailable()) {
            return min($ttl['privileged'], $ttl['authed']);   // can't ask → the safer, shorter tier
        }
        if (Tiger_Acl_Acl::roleIsAllowed((string) $role, self::RESOURCE_PRIVILEGED, self::PRIVILEGE_SHORT_TTL)) {
            return $ttl['privileged'];
        }
        return $ttl['authed'];
    }

    protected function _ttlConfig()
    {
        $node = null;
        if (Zend_Registry::isRegistered('Zend_Config')) {
            $c = Zend_Registry::get('Zend_Config');
            if ($c->get('tiger') && $c->tiger->get('session')) {
                $node = $c->tiger->session->get('ttl');
            }
        }
        $get = function ($key, $default) use ($node) {
            $v = $node ? $node->get($key) : null;
            return ($v !== null && (int) $v > 0) ? (int) $v : $default;
        };
        return [
            'privileged' => $get('privileged', 28800),   // 8h
            'authed'     => $get('authed', 604800),      // 7d
            'guest'      => $get('guest', 86400),        // 1d
        ];
    }
}
