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
     * Per-request memo of the resolved default agent, keyed by org scope. Cleared implicitly by
     * process end; a save in the settings service should call reset() so the next read is fresh.
     *
     * @var array<string,array>
     */
    private static $_defaultMemo = [];

    /**
     * Identity of the Zend_Config instance the memo was built against. The config object is stable
     * for the life of a request, so the memo holds across a request; when it is swapped (a settings
     * save rebuilds it, or a test seeds a fresh one) the memo self-invalidates. @var int
     */
    private static $_memoConfigId = -1;

    /**
     * The DEFAULT agent for the current scope, as a normalized array. Reads through the registry
     * (Tiger_Model_Agent): the current org's default, else the global default. While the table is
     * empty — or before the DB is even booted — it falls back to the legacy `tiger.agent.*` config
     * keys, so an install that has never opened the multi-agent UI behaves exactly as the old
     * singleton did. The first save in the settings screen writes a real Default row.
     *
     * @return array{id:?string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}
     */
    public static function default()
    {
        $cfgId = Zend_Registry::isRegistered('Zend_Config')
            ? spl_object_id(Zend_Registry::get('Zend_Config'))
            : 0;
        if ($cfgId !== self::$_memoConfigId) {
            self::$_defaultMemo = [];
            self::$_memoConfigId = $cfgId;
        }

        $org = self::currentOrg();
        if (array_key_exists($org, self::$_defaultMemo)) {
            return self::$_defaultMemo[$org];
        }

        $agent = null;
        try {
            $row = (new Tiger_Model_Agent())->defaultForOrg($org);
            if ($row) {
                $agent = self::fromRow($row);
            }
        } catch (Throwable $e) {
            // DB not booted, or the agent table doesn't exist yet — fall through to legacy config.
        }
        if ($agent === null) {
            $agent = self::fromLegacyConfig();
        }

        return self::$_defaultMemo[$org] = $agent;
    }

    /**
     * One registered agent by id, scoped to the current org, as a normalized array — or null.
     * TigerRoundtable uses this to seat a specific registered agent.
     *
     * @param  string $agentId
     * @return array{id:string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}|null
     */
    public static function get($agentId)
    {
        try {
            $row = (new Tiger_Model_Agent())->findForOrg(self::currentOrg(), $agentId);
            return $row ? self::fromRow($row) : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Every registered agent in the current scope, default first — the roster the settings UI and
     * TigerRoundtable draw from. Empty while the registry is unused (the legacy default is implicit,
     * reachable via default(), and not listed as a row until it is saved).
     *
     * @return array<int,array{id:string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}>
     */
    public static function all()
    {
        try {
            $out = [];
            foreach ((new Tiger_Model_Agent())->allForOrg(self::currentOrg()) as $row) {
                $out[] = self::fromRow($row);
            }
            return $out;
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Forget the memoized default (call after a settings save so the next read reflects it).
     *
     * @return void
     */
    public static function reset()
    {
        self::$_defaultMemo = [];
        self::$_memoConfigId = -1;
    }

    /**
     * Whether the agent feature is switched on for this install — i.e. the default agent is enabled.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return (bool) self::default()['enabled'];
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
        $p = (string) self::default()['provider'];
        return $p !== '' ? $p : 'anthropic';
    }

    /**
     * The configured model id, or the provider's sensible default.
     *
     * @return string
     */
    public static function model()
    {
        $m = (string) self::default()['model'];
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
        $blob = (string) self::default()['api_key_enc'];
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
     * Normalize a registry row into the shape default()/get()/all() return.
     *
     * @param  Zend_Db_Table_Row_Abstract $row
     * @return array{id:string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}
     */
    protected static function fromRow($row)
    {
        return [
            'id'          => (string) $row->agent_id,
            'name'        => (string) $row->name,
            'provider'    => (string) $row->provider,
            'model'       => (string) $row->model,
            'api_key_enc' => (string) $row->api_key_enc,
            'enabled'     => (int) $row->enabled === 1,
        ];
    }

    /**
     * The default agent synthesized from the legacy singleton config keys — the exact behavior the
     * facade had before the registry existed, so an empty table changes nothing.
     *
     * @return array{id:null,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}
     */
    protected static function fromLegacyConfig()
    {
        return [
            'id'          => null,
            'name'        => 'Default',
            'provider'    => (string) self::config(self::CFG_PROVIDER),
            'model'       => (string) self::config(self::CFG_MODEL),
            'api_key_enc' => (string) self::config(self::CFG_KEY_ENC),
            'enabled'     => self::config(self::CFG_ENABLED) === '1',
        ];
    }

    /**
     * The org scope the registry resolves against — the current tenant, or '' for platform/global.
     *
     * @return string
     */
    protected static function currentOrg()
    {
        $org = Tiger_Model_Table::org();
        return $org === null ? '' : (string) $org;
    }

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
