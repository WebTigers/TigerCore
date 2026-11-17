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
     * @apiParam string q The search text. Empty lists every skill.
     * @apiParam boolean refresh Re-scan the sources instead of using the cache.
     * @return void
     */
    public function search(array $params): void
    {
        if (!$this->_isAllowed('search')) { $this->_error('core.api.error.not_allowed'); return; }
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

    /**
     * Installed skills + their active state (drives the "Installed" list).
     *
     * @param  array $params the request payload
     * @apiRequest none
     * @return void
     */
    public function installed(array $params): void
    {
        if (!$this->_isAllowed('installed')) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['skills' => Tiger_Agent_Skills::installed()], null);
    }

    /**
     * DataTables source — the ONE grid: the browse catalog merged with what's installed, so a row's status +
     * action controls tell you whether it's installed (like the Modules screen). Installed skills are pinned
     * to the top (then active-first, then by name). Search filters name/description/provenance; `refresh`
     * re-scans the sources (bypass the per-source cache). Cached scans, merged + paginated in PHP (mixed
     * origins, no shared DB order).
     *
     * @param  array $params DataTables request (+ optional `refresh`)
     * @apiRequest datatable
     * @apiParam boolean refresh Re-scan the sources instead of using the cache.
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }

        $dt      = $this->_dtParams($params);
        $search  = strtolower(trim((string) $dt['search']));
        $refresh = !empty($params['refresh']);

        // Installed skills, indexed two ways: by install key AND by canonical identity (repo|path). The
        // canonical index is what dedups a skill installed via one source (e.g. a pasted URL → key
        // `url__x`) against the SAME skill in a catalog (key `webtigers-skills__x`) — same repo+path, one row.
        $installed = [];
        $byLoc     = [];
        foreach (Tiger_Agent_Skills::installed() as $s) {
            $installed[$s['key']] = $s;
            if ($s['repo'] !== '' && ($s['path'] ?? '') !== '') { $byLoc[self::_loc($s['repo'], $s['path'])] = $s; }
        }

        $items = [];
        $shown = [];   // install keys already emitted (matched to a catalog row) → skip in the tail
        // The browse catalog (per-source cached; only the sources scan hits the network, and only on refresh).
        foreach (Tiger_Skill_Index::all($refresh) as $e) {
            $catKey = Agent_Service_Skills::installKey($e);
            $inst   = $installed[$catKey] ?? ($byLoc[self::_loc((string) $e['repo'], (string) $e['path'])] ?? null);
            $key    = $inst !== null ? $inst['key'] : $catKey;   // actions target the ACTUAL installed dir
            $items[] = $this->_row($key, (string) $e['source'], $e['name'], $e['description'], $e['sourceLabel'],
                $e['repo'], $e['ref'], $e['path'], $e['url'], $inst !== null, $inst !== null && !empty($inst['active']));
            if ($inst !== null) { $shown[$inst['key']] = true; }
        }
        // Installed but not matched to any catalog entry (a pasted-URL install of a repo no source lists).
        foreach ($installed as $key => $s) {
            if (isset($shown[$key])) { continue; }
            $items[] = $this->_row($key, '', $s['name'], $s['description'], $s['sourceLabel'],
                (string) $s['repo'], '', (string) ($s['path'] ?? ''), (string) $s['url'], true, !empty($s['active']));
        }

        if ($search !== '') {
            $items = array_values(array_filter($items, static function ($r) use ($search) {
                return strpos(strtolower($r['name'] . ' ' . $r['description'] . ' ' . $r['sourceLabel']), $search) !== false;
            }));
        }

        // Pin installed to the top → active-first within installed → then by name.
        usort($items, static function ($a, $b) {
            if ($a['installed'] !== $b['installed']) { return $a['installed'] ? -1 : 1; }
            if ($a['installed'] && $a['active'] !== $b['active']) { return $a['active'] ? -1 : 1; }
            return strcasecmp($a['name'], $b['name']);
        });

        $total = count($items);
        $len   = ($dt['length'] > 0) ? $dt['length'] : 25;
        $page  = array_slice($items, (int) $dt['start'], $len);
        $this->_dtResponse($dt['draw'], $total, $total, $page);
    }

    /** Canonical skill identity for dedup: repo + path, normalized (a skill is the same wherever installed from). */
    private static function _loc($repo, $path): string
    {
        return strtolower(trim((string) $repo, '/') . '|' . trim((string) $path, '/'));
    }

    /** One normalized grid row (a catalog entry and/or an installed skill). */
    private function _row($key, $source, $name, $desc, $sourceLabel, $repo, $ref, $path, $url, $installed, $active): array
    {
        return [
            'key'         => $key,
            'source'      => $source,        // adapter id — needed so an Install uses the same key
            'name'        => (string) $name,
            'description' => (string) $desc,
            'sourceLabel' => (string) $sourceLabel,   // provenance, NOT a vouch
            'repo'        => (string) $repo,
            'ref'         => (string) $ref,
            'path'        => (string) $path,
            'url'         => (string) $url,
            'installed'   => (bool) $installed,
            'active'      => (bool) $active,
        ];
    }

    /**
     * Install a skill — from a browse entry (`repo`/`ref`/`path`/`name`/`source`) or a pasted `url`.
     *
     * @param  array $params either a browse entry's fields, or `url`
     * @apiRequest Agent_Form_SkillInstall
     * @apiParam string url A GitHub URL (repo, folder or SKILL.md) to install every skill found at. Replaces the browse fields.
     * @apiParam string repo The browse entry's repository (owner/name). Required when no url is sent.
     * @apiParam string ref The browse entry's branch or tag. Defaults to main.
     * @apiParam string path The skill's folder inside the repository.
     * @apiParam string name The skill's name.
     * @apiParam string source The browse source id the entry came from.
     * @apiParam string sourceLabel The browse source's display label.
     * @return void
     */
    public function install(array $params): void
    {
        if (!$this->_isAllowed('install')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Agent_Form_SkillInstall();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
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

    /**
     * Turn an installed skill on/off.
     *
     * @param  array $params the request payload
     * @apiRequest Agent_Form_SkillKey
     * @apiParam string key required The installed skill's key.
     * @apiParam boolean active Turn it on (true) or off (false).
     * @return void
     */
    public function toggle(array $params): void
    {
        if (!$this->_isAllowed('toggle')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Agent_Form_SkillKey();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $key = (string) $form->getValue('key');
        if (!Tiger_Agent_Skills::isInstalled($key)) { $this->_error('core.api.error.general'); return; }
        $on = !empty($params['active']) && $params['active'] !== '0' && $params['active'] !== 'false';
        Tiger_Agent_Skills::setActive($key, $on);
        $this->_success(['key' => $key, 'active' => $on], $on ? 'agent.skills.enabled' : 'agent.skills.disabled');
    }

    /**
     * Uninstall a skill (files + active set).
     *
     * @param  array $params the request payload
     * @apiRequest Agent_Form_SkillKey
     * @apiParam string key required The installed skill's key.
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAllowed('remove')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Agent_Form_SkillKey();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $key = (string) $form->getValue('key');
        if (!Tiger_Agent_Skills::isInstalled($key)) { $this->_error('core.api.error.general'); return; }
        Tiger_Agent_Skills::remove($key);
        $this->_success(['key' => $key], 'agent.skills.removed');
    }

    /**
     * The SKILL.md source, for the review-before-install modal — an installed one, or a not-yet-installed
     * browse entry fetched live (read-before-run is the whole point).
     *
     * @param  array $params either `key` (installed) or `repo`/`ref`/`path` (browse)
     * @apiParam string key An installed skill's key.
     * @apiParam string repo A browse entry's repository (owner/name), when no installed key is sent.
     * @apiParam string ref The browse entry's branch or tag. Defaults to main.
     * @apiParam string path The skill's folder inside the repository.
     * @return void
     */
    public function source(array $params): void
    {
        if (!$this->_isAllowed('source')) { $this->_error('core.api.error.not_allowed'); return; }
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
