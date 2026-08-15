<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Service_Skills — the /api behind the Skills admin: search the browse catalog, install, list
 * installed, toggle on/off, remove, and view a SKILL.md source. Thin + ACL-gated (admin+); the engine is
 * Tiger_Skill_Index (browse) + Tiger_Agent_Skills (installed side). Tiger is not a trust authority — search
 * returns PROVENANCE, and the user reviews the source before installing (TIGERSKILLS.md §2, §5).
 *
 * @api
 */
class Agent_Service_Skills extends Tiger_Service_Service
{
    /**
     * Search the supported sources' catalog; flag which results are already installed.
     *
     * @param  array $params `q` (query; '' = all), optional `refresh`
     * @return void
     */
    public function search(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $installed = [];
        foreach (Tiger_Agent_Skills::installed() as $s) { $installed[$s['key']] = true; }

        $rows = [];
        foreach (Tiger_Skill_Index::search((string) ($params['q'] ?? ''), !empty($params['refresh'])) as $e) {
            $key = Agent_Service_Skills::installKey($e);
            $rows[] = [
                'name'        => $e['name'],
                'description' => $e['description'],
                'sourceLabel' => $e['sourceLabel'],   // provenance, NOT a vouch
                'repo'        => $e['repo'],
                'ref'         => $e['ref'],
                'path'        => $e['path'],
                'url'         => $e['url'],
                'installed'   => isset($installed[$key]),
            ];
        }
        $this->_success(['skills' => $rows, 'sources' => array_map(static function ($s) {
            return ['id' => $s->id(), 'label' => $s->label()];
        }, array_values(Tiger_Skill_Index::sources()))], null);
    }

    /** Installed skills + their active state (drives the "Installed" list). */
    public function installed(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['skills' => Tiger_Agent_Skills::installed()], null);
    }

    /**
     * Install a skill — from a browse entry (`repo`/`ref`/`path`/`name`/`source`) or a pasted `url`.
     *
     * @param  array $params either a browse entry's fields, or `url`
     * @return void
     */
    public function install(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        try {
            $keys = [];
            if (!empty($params['url'])) {
                foreach ((new Tiger_Skill_Source_Url((string) $params['url']))->scan() as $e) {
                    $keys[] = Tiger_Agent_Skills::install($e);
                }
                if (!$keys) { $this->_error('agent.skills.none_found'); return; }
            } else {
                $entry = [
                    'source'      => (string) ($params['source'] ?? 'url'),
                    'sourceLabel' => (string) ($params['sourceLabel'] ?? ''),
                    'name'        => (string) ($params['name'] ?? ''),
                    'repo'        => (string) ($params['repo'] ?? ''),
                    'ref'         => (string) ($params['ref'] ?? 'main'),
                    'path'        => (string) ($params['path'] ?? ''),
                    'url'         => (string) ($params['url'] ?? ''),
                ];
                if ($entry['repo'] === '') { $this->_error('core.api.error.general'); return; }
                $keys[] = Tiger_Agent_Skills::install($entry);
            }
            $this->_success(['installed' => $keys, 'skills' => Tiger_Agent_Skills::installed()], 'agent.skills.installed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'agent.skills.install_failed');
        }
    }

    /** Turn an installed skill on/off. */
    public function toggle(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $key = (string) ($params['key'] ?? '');
        if ($key === '' || !Tiger_Agent_Skills::isInstalled($key)) { $this->_error('core.api.error.general'); return; }
        $on = !empty($params['active']) && $params['active'] !== '0' && $params['active'] !== 'false';
        Tiger_Agent_Skills::setActive($key, $on);
        $this->_success(['key' => $key, 'active' => $on], $on ? 'agent.skills.enabled' : 'agent.skills.disabled');
    }

    /** Uninstall a skill (files + active set). */
    public function remove(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $key = (string) ($params['key'] ?? '');
        if ($key === '' || !Tiger_Agent_Skills::isInstalled($key)) { $this->_error('core.api.error.general'); return; }
        Tiger_Agent_Skills::remove($key);
        $this->_success(['key' => $key], 'agent.skills.removed');
    }

    /**
     * The SKILL.md source, for the review-before-install modal — an installed one, or a not-yet-installed
     * browse entry fetched live (read-before-run is the whole point).
     *
     * @param  array $params either `key` (installed) or `repo`/`ref`/`path` (browse)
     * @return void
     */
    public function source(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $key = (string) ($params['key'] ?? '');
        if ($key !== '' && Tiger_Agent_Skills::isInstalled($key)) {
            $this->_success(['source' => Tiger_Agent_Skills::body($key)], null);
            return;
        }
        $repo = (string) ($params['repo'] ?? '');
        [$org, $rname] = array_pad(explode('/', $repo, 2), 2, '');
        $path = trim((string) ($params['path'] ?? ''), '/');
        if ($org === '' || $rname === '') { $this->_error('core.api.error.general'); return; }
        $raw = @Tiger_Module_Github::fetchRaw($org, $rname, (string) ($params['ref'] ?? 'main'), $path . '/SKILL.md');
        $this->_success(['source' => $raw !== false ? (string) $raw : ''], null);
    }

    /** The install key a browse entry would become (mirrors Tiger_Agent_Skills::_safeKey(source__name)). */
    public static function installKey(array $entry)
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', ($entry['source'] ?? 'src') . '__' . ($entry['name'] ?? ''));
    }
}
