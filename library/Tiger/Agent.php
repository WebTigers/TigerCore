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
 * AI KEYS ARE ORG-LEVEL ONLY (TIGER-511). Every agent — the registry rows (Tiger_Model_Agent) and the
 * legacy single-agent keys (`tiger.agent.*`, written at the org by Agent_Service_Settings) — belongs to
 * one org, and an org resolves only its own: never another org's, never a platform agent, never the
 * install's key. An org with no agent has none, and AI features degrade. default()/apiKey()/… answer for
 * the SIGNED-IN org; forOrg() answers for a named org (background jobs, public paths — TIGER-513). The
 * key is stored encrypted (Tiger_Crypto) and never leaves the server.
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
     * The DEFAULT agent for the signed-in org, as a normalized array — the request-scoped answer the
     * aside, the chat service and the admin screens use. AI keys are ORG-LEVEL ONLY (TIGER-511): this is
     * the org's own agent (see forOrg) or, when it has none, an empty, disabled, unconnected agent — never
     * another org's, a platform row, or the install's key. With no signed-in org (a guest, a webhook, a
     * CLI job) there is no agent here at all: code acting for an org it was not signed in as names the
     * org with forOrg().
     *
     * @return array{id:?string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}
     */
    public static function default()
    {
        return self::_resolve(self::currentOrg()) ?? self::_none();
    }

    /**
     * An org's OWN default agent, resolved for that org explicitly — the seam a background job that loops
     * over orgs (TigerDesk triage, TIGER-513) or a public-path feature acting for the site's org uses
     * instead of the signed-in-org facade. No fallback of any kind: not another org, not a platform
     * (`org_id = ''`) agent, not the install org's key. Resolution, in order:
     *   1. the org's default row in the agent registry (Tiger_Model_Agent);
     *   2. else the org's own legacy `tiger.agent.*` config rows (the single-agent settings form) — for the
     *      INSTALL org only, those may also come from the install-wide config (the global row, else the
     *      ini), since the install's own settings ARE the install org's;
     *   3. else null — the org has no agent, and AI features degrade exactly as when none is configured.
     *
     * The returned key is DECRYPTED — keep it server-side, never echo it to a browser.
     *
     * @api
     * @since 1.60.0
     * @param  string $orgId the org to resolve for ('' = none)
     * @return array{org_id:string,id:?string,name:string,provider:string,model:string,api_key:string,enabled:bool,connected:bool,available:bool}|null
     *         null when the org has no agent of its own
     */
    public static function forOrg($orgId)
    {
        $orgId = (string) $orgId;
        $agent = self::_resolve($orgId);
        if ($agent === null) {
            return null;
        }
        $provider = $agent['provider'] !== '' ? $agent['provider'] : 'anthropic';
        $model    = $agent['model'] !== '' ? $agent['model'] : Tiger_Agent_Provider_Factory::defaultModel($provider);
        $key      = self::_decrypt($agent['api_key_enc']);
        return [
            'org_id'    => $orgId,
            'id'        => $agent['id'],
            'name'      => $agent['name'],
            'provider'  => $provider,
            'model'     => $model,
            'api_key'   => $key,
            'enabled'   => $agent['enabled'],
            'connected' => $key !== '',
            'available' => $agent['enabled'] && $key !== '',
        ];
    }

    /**
     * Whether an org has a usable agent of its own (enabled + a readable key). Not a permission check —
     * whether a USER may chat is userCanChat().
     *
     * @api
     * @since 1.60.0
     * @param  string $orgId the org
     * @return bool
     */
    public static function isAvailableForOrg($orgId)
    {
        $agent = self::forOrg($orgId);
        return $agent !== null && $agent['available'];
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
        if (self::currentOrg() === '') {
            return null;   // no org, no agents (a legacy '' row is never reachable)
        }
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
        if (self::currentOrg() === '') {
            return [];
        }
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
        return self::_decrypt((string) self::default()['api_key_enc']);
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
     * The signed-in org's auto-mode CEILING (admin governance): the highest mode any of its users may
     * use. Written at the org (Agent_Service_Settings::mode); an org that never set one inherits the
     * install-wide value (it is governance, not a secret), else `auto` — routine writes flow, but YOLO
     * (auto-running code/file/module) must be deliberately switched on.
     *
     * @return string ask | auto | yolo
     */
    public static function modeMax()
    {
        $m = (string) Tiger_Model_Config::orgValue(self::currentOrg(), self::CFG_MODE_MAX, '');
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
     * An org's own agent from its legacy single-agent config keys (`tiger.agent.*`), or null when the org
     * set none. A non-install org reads ONLY its own org rows (Tiger_Model_Config::orgOwnValue) — the
     * merged Zend_Config is never consulted, because it folds the install-wide tier underneath. The
     * install org may also read the install-wide tier (global row, else ini): those are its own settings.
     *
     * @param  string $orgId the org
     * @return array{id:null,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}|null
     */
    protected static function fromLegacyConfig($orgId)
    {
        $install = Tiger_Model_Org::isInstallOrg($orgId);
        $read = static function ($key) use ($orgId, $install) {
            $v = $install
                ? Tiger_Model_Config::orgValue($orgId, $key, '')
                : Tiger_Model_Config::orgOwnValue($orgId, $key, '');
            return is_scalar($v) ? (string) $v : '';
        };
        $agent = [
            'id'          => null,
            'name'        => 'Default',
            'provider'    => $read(self::CFG_PROVIDER),
            'model'       => $read(self::CFG_MODEL),
            'api_key_enc' => $read(self::CFG_KEY_ENC),
            'enabled'     => $read(self::CFG_ENABLED) === '1',
        ];
        if ($agent['provider'] === '' && $agent['model'] === '' && $agent['api_key_enc'] === '' && !$agent['enabled']) {
            return null;   // the org configured nothing
        }
        return $agent;
    }

    /**
     * One org's own default agent (raw — the key still encrypted), memoized per request; null when the
     * org has none. The memo self-invalidates when the registered Zend_Config instance changes (a
     * settings save rebuilds it; a test seeds a fresh one); reset() clears it explicitly.
     *
     * @param  string $orgId the org ('' = none)
     * @return array{id:?string,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}|null
     */
    protected static function _resolve($orgId)
    {
        $orgId = (string) $orgId;
        if ($orgId === '') {
            return null;   // no org, no agent — never a platform/install fallback
        }

        $cfgId = Zend_Registry::isRegistered('Zend_Config')
            ? spl_object_id(Zend_Registry::get('Zend_Config'))
            : 0;
        if ($cfgId !== self::$_memoConfigId) {
            self::$_defaultMemo  = [];
            self::$_memoConfigId = $cfgId;
        }
        if (array_key_exists($orgId, self::$_defaultMemo)) {
            return self::$_defaultMemo[$orgId];
        }

        $agent = null;
        try {
            $row = (new Tiger_Model_Agent())->defaultForOrg($orgId);
            if ($row) {
                $agent = self::fromRow($row);
            }
        } catch (Throwable $e) {
            // DB not booted, or the agent table doesn't exist yet — the org's config keys may still answer.
        }
        if ($agent === null) {
            $agent = self::fromLegacyConfig($orgId);
        }
        return self::$_defaultMemo[$orgId] = $agent;
    }

    /**
     * The "no agent" shape default() returns for an org with none: disabled, unconnected, keyless.
     *
     * @return array{id:null,name:string,provider:string,model:string,api_key_enc:string,enabled:bool}
     */
    protected static function _none()
    {
        return ['id' => null, 'name' => 'Default', 'provider' => '', 'model' => '', 'api_key_enc' => '', 'enabled' => false];
    }

    /**
     * Decrypt a stored key blob, or '' when empty/unreadable (a rotated crypto key, say).
     *
     * @param  string $blob the Tiger_Crypto ciphertext
     * @return string
     */
    protected static function _decrypt($blob)
    {
        $blob = (string) $blob;
        if ($blob === '') {
            return '';
        }
        try {
            return (string) Tiger_Crypto::decrypt($blob);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * The signed-in org — the scope the request-scoped facade (default/get/all/apiKey) resolves against,
     * or '' with none (no agent).
     *
     * @return string
     */
    protected static function currentOrg()
    {
        $org = Tiger_Model_Table::org();
        return $org === null ? '' : (string) $org;
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
