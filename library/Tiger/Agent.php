<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent — the TigerAgent facade (config + availability + capability).
 *
 * TigerAgent is the in-platform AI agent: a role-filtered `/api` client that talks to a
 * BYO AI account, runs a turn per aside POST (no daemons), and lets the Forge act on the
 * model's structured response — DB writes, public-module file writes, code, scaffolds —
 * always bounded by the acting user's ACL. See TIGERAGENT.md for the design of record.
 *
 * This class is the small, dependency-light front door the theme + module ask three
 * things of:
 *   - is the agent AVAILABLE here? (feature on + a provider key present + the viewer may chat)
 *   - what PROVIDER/MODEL/creds are configured? (BYO — the org connects its own AI account)
 *   - what can THIS user do? (the capability tiers, expressed purely as ACL, never a role
 *     string compare — TIGERAGENT.md §2a)
 *
 * All settings live in the eager `config` tier (`tiger.agent.*`), written by
 * Agent_Service_Settings, so a change is effective next request with no deploy. The API key
 * is stored encrypted (`tiger.agent.api_key_enc`, Tiger_Crypto) and never leaves the server.
 *
 * @api
 */
class Tiger_Agent
{
    /** Config keys (the eager `config` tier). */
    const CFG_ENABLED  = 'tiger.agent.enabled';
    const CFG_PROVIDER = 'tiger.agent.provider';
    const CFG_MODEL    = 'tiger.agent.model';
    const CFG_KEY_ENC  = 'tiger.agent.api_key_enc';
    const CFG_MODE_MAX = 'tiger.agent.mode_max';   // the install-wide auto-mode CEILING

    /** The /api service the aside talks to — the resource that gates "may this user chat at all". */
    const RESOURCE_CHAT = 'Agent_Service_Agent';

    /** The Forge resource whose privileges gate the escalating write tiers (TIGERAGENT.md §2a). */
    const RESOURCE_FORGE = 'Tiger_Agent_Forge';

    /** The Scout resource whose privileges gate the read tiers (TIGERAGENT.md §2b). */
    const RESOURCE_SCOUT = 'Tiger_Agent_Scout';

    /** Auto-mode ordering (ask < auto < yolo). */
    const MODES = ['ask' => 0, 'auto' => 1, 'yolo' => 2];

    /**
     * Whether the agent feature is switched on for this install.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return self::config(self::CFG_ENABLED) === '1';
    }

    /**
     * Whether a usable AI provider is connected (a key is stored AND crypto can read it).
     *
     * @return bool
     */
    public static function isConnected()
    {
        return self::apiKey() !== '';
    }

    /**
     * Whether the current viewer may open the aside: feature on + provider connected + the
     * viewer's role is allowed the chat service. This is the single gate the admin shell
     * uses to decide whether to render the agent rail (TIGERAGENT.md §4).
     *
     * @return bool
     */
    public static function isAvailable()
    {
        return self::isEnabled() && self::isConnected() && self::userCanChat();
    }

    /**
     * Whether the current identity's role is allowed the chat service (deny-by-default).
     *
     * @return bool
     */
    public static function userCanChat()
    {
        return self::allowed(self::RESOURCE_CHAT, 'send');
    }

    /**
     * The configured provider key (`anthropic` by default).
     *
     * @return string
     */
    public static function provider()
    {
        $p = (string) self::config(self::CFG_PROVIDER);
        return $p !== '' ? $p : 'anthropic';
    }

    /**
     * The configured model id, or the provider's sensible default.
     *
     * @return string
     */
    public static function model()
    {
        $m = (string) self::config(self::CFG_MODEL);
        if ($m !== '') {
            return $m;
        }
        return Tiger_Agent_Provider_Factory::defaultModel(self::provider());
    }

    /**
     * The decrypted API key, or '' when unset/unreadable (a rotated crypto key, say).
     *
     * @return string
     */
    public static function apiKey()
    {
        $blob = (string) self::config(self::CFG_KEY_ENC);
        if ($blob === '') {
            return '';
        }
        try {
            return Tiger_Crypto::decrypt($blob);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * The capability tiers this identity's role unlocks — computed live from the ACL, so it
     * is always the honest answer to "what can the agent do as me right now". The model is
     * told this in its system prompt so it never proposes an action it can't run.
     *
     * The tiers escalate (TIGERAGENT.md §2a): every allowed role can drive `/api` reads/writes
     * bounded by that call's own ACL; `code` (executable PHP via the Code Area) and `file`
     * (writing into public app modules) and `module` (scaffolding) are the sharper Forge
     * privileges granted only to superadmin/developer.
     *
     * @return array{api:bool,code:bool,file:bool,module:bool}
     */
    public static function capabilities()
    {
        return [
            'inventory' => self::allowed(self::RESOURCE_SCOUT, 'inventory'), // the system map (admin+)
            'read'      => self::allowed(self::RESOURCE_SCOUT, 'read'),      // tree/file/grep (superadmin+)
            'dom'       => self::userCanChat(),                             // read/write page editor targets (client-side)
            'api'       => self::userCanChat(),                             // reach the /api surface (per-call ACL)
            'code'      => self::allowed('Code_Service_Code', 'save'),      // executable PHP (superadmin+)
            'file'      => self::allowed(self::RESOURCE_FORGE, 'file'),     // write public-module files (superadmin+)
            'module'    => self::allowed(self::RESOURCE_FORGE, 'module'),   // scaffold a module (developer)
        ];
    }

    /**
     * The install-wide auto-mode CEILING (admin governance): the highest mode any user may use.
     * Defaults to `auto` — routine writes flow, but YOLO (auto-running code/file/module) must be
     * deliberately switched on for the install.
     *
     * @return string ask | auto | yolo
     */
    public static function modeMax()
    {
        $m = (string) self::config(self::CFG_MODE_MAX);
        return isset(self::MODES[$m]) ? $m : 'auto';
    }

    /**
     * Clamp a user-requested mode to the install ceiling. So a user can dial DOWN (always allowed)
     * but never past what the admin permits.
     *
     * @param  string $requested the mode the user asked for
     * @return string            the effective, clamped mode
     */
    public static function clampMode($requested)
    {
        $req = self::MODES[$requested] ?? 0;
        $max = self::MODES[self::modeMax()] ?? 1;
        $eff = min($req, $max);
        return array_search($eff, self::MODES, true) ?: 'ask';
    }

    // ----- internals ---------------------------------------------------------

    /**
     * Read a value from the merged config cascade (Zend_Config in the registry).
     *
     * @param  string $dotKey a dot-notation config key
     * @return string|null
     */
    protected static function config($dotKey)
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return null;
        }
        $node = Zend_Registry::get('Zend_Config');
        foreach (explode('.', $dotKey) as $seg) {
            if (!($node instanceof Zend_Config) || $node->{$seg} === null) {
                return null;
            }
            $node = $node->{$seg};
        }
        return is_scalar($node) ? (string) $node : null;
    }

    /**
     * ACL gate for the current identity's role — never a role-string compare.
     *
     * @param  string      $resource  the resource class
     * @param  string|null $privilege the privilege (method), or null for resource-level
     * @return bool
     */
    protected static function allowed($resource, $privilege = null)
    {
        if (!Zend_Registry::isRegistered('Zend_Acl')) {
            return false;
        }
        $acl  = Zend_Registry::get('Zend_Acl');
        $role = Zend_Auth::getInstance()->getIdentity()->role ?? 'guest';
        if (!$acl->has($resource)) {
            return false;
        }
        return $acl->isAllowed($role, $resource, $privilege);
    }
}
