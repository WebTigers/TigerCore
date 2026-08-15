<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Skill_Source_SkillsDir — the adapter for the common "collection" layout: a repo whose skills live
 * as `<base>/<name>/SKILL.md` folders (e.g. `anthropics/skills` lays them under `skills/`). ONE git-trees call
 * lists every `SKILL.md` path; a raw fetch per skill reads its frontmatter. Provenance only — no vouch.
 *
 * @api
 * @see Tiger_Skill_Source
 */
class Tiger_Skill_Source_SkillsDir extends Tiger_Skill_Source
{
    protected $id;
    protected $label;
    protected $repo;    // owner/repo
    protected $ref;     // branch or tag to scan (skill repos usually publish on a branch, not releases)
    protected $base;    // the dir the skill folders live under (default 'skills')

    /**
     * @param string $id    stable adapter id ([a-z0-9-])
     * @param string $label human provenance label
     * @param string $repo  owner/repo (or a full GitHub URL)
     * @param string $ref   branch/tag to scan (default 'main')
     * @param string $base  base dir holding the skill folders (default 'skills'; '' = repo root)
     */
    public function __construct($id, $label, $repo, $ref = 'main', $base = 'skills')
    {
        $this->id   = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $id));
        $this->label = (string) $label;
        // Accept a full URL or owner/repo.
        if (strpos($repo, 'github.com') !== false) {
            $p = Tiger_Module_Github::parseRepo($repo);
            $repo = ($p && !empty($p['org']) && !empty($p['repo'])) ? $p['org'] . '/' . $p['repo'] : $repo;
        }
        $this->repo = trim((string) $repo, '/');
        $this->ref  = (string) ($ref ?: 'main');
        $this->base = trim((string) $base, '/');
    }

    public function id()    { return $this->id; }
    public function label() { return $this->label; }

    public function scan()
    {
        [$org, $repo] = array_pad(explode('/', $this->repo, 2), 2, '');
        if ($org === '' || $repo === '') { return []; }

        // 1) One recursive git-trees call → every path in the repo (fine for skill-sized repos).
        $body = @Tiger_Module_Github::get('https://api.github.com/repos/' . $org . '/' . $repo . '/git/trees/' . rawurlencode($this->ref) . '?recursive=1');
        $tree = $body ? json_decode((string) $body, true) : null;
        if (!is_array($tree) || empty($tree['tree']) || !is_array($tree['tree'])) { return []; }

        // 2) Keep the SKILL.md paths under our base dir.
        $prefix = $this->base !== '' ? $this->base . '/' : '';
        $mdPaths = [];
        foreach ($tree['tree'] as $node) {
            $path = isset($node['path']) ? (string) $node['path'] : '';
            if (($node['type'] ?? '') !== 'blob' || basename($path) !== 'SKILL.md') { continue; }
            if ($prefix !== '' && strpos($path, $prefix) !== 0) { continue; }
            $mdPaths[] = $path;
        }
        sort($mdPaths);

        // 3) One raw fetch per SKILL.md → frontmatter → a normalized entry.
        $out = [];
        foreach ($mdPaths as $md) {
            $raw = @Tiger_Module_Github::fetchRaw($org, $repo, $this->ref, $md);
            if (!$raw) { continue; }
            $front = self::parseFrontmatter($raw);
            if (empty($front['name']) && empty($front['description'])) { continue; }   // not a real skill
            $out[] = $this->entry($this->repo, $this->ref, dirname($md), $front);
        }
        return $out;
    }
}
