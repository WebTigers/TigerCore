<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Skill_Source_Marketplace — the adapter for a repo that publishes a machine-readable
 * **`.claude-plugin/marketplace.json`** manifest (the Claude plugin-marketplace standard). ONE fetch
 * yields every skill's `name` + `description` + `source` path, so a large collection is scanned in a
 * single HTTP call instead of one raw fetch per `SKILL.md` (the SkillsDir path). This is the source kind
 * that scales — a 100+-skill community repo browses instantly and can't time out the inline scan.
 *
 * A manifest is `{ plugins: [ {name, description, source, skills?[]}, … ] }`. Two entry shapes are handled:
 * a **flat** plugin (`source` = the skill folder → one entry), and a **grouped** plugin (`skills[]` = many
 * folder paths → one entry each, sharing the group description). `source`/`skills[]` paths are resolved
 * against a configurable repo `root` (they're usually `./name` relative to the manifest's collection dir).
 *
 * Provenance only — Tiger reads the manifest, it does not endorse the skills. The user reviews each
 * SKILL.md before installing (the same wall as every other source).
 *
 * @api
 * @see Tiger_Skill_Source
 * @see Tiger_Skill_Source_SkillsDir  the raw scan-every-SKILL.md adapter (for repos with no manifest)
 */
class Tiger_Skill_Source_Marketplace extends Tiger_Skill_Source
{
    protected $id;
    protected $label;
    protected $repo;      // owner/repo
    protected $ref;       // branch or tag
    protected $manifest;  // path to the marketplace.json within the repo
    protected $root;      // repo dir that plugin `source`/`skills[]` paths resolve against ('' = repo root)

    /**
     * @param string $id       stable adapter id ([a-z0-9-])
     * @param string $label    human provenance label
     * @param string $repo     owner/repo (or a full GitHub URL)
     * @param string $ref      branch/tag to read (default 'main')
     * @param string $manifest path to the manifest in the repo (default '.claude-plugin/marketplace.json')
     * @param string $root     base dir the plugin source paths resolve against (default '' = repo root)
     */
    public function __construct($id, $label, $repo, $ref = 'main', $manifest = '.claude-plugin/marketplace.json', $root = '')
    {
        $this->id    = preg_replace('/[^a-z0-9-]/', '', strtolower((string) $id));
        $this->label = (string) $label;
        if (strpos($repo, 'github.com') !== false) {
            $p = Tiger_Module_Github::parseRepo($repo);
            $repo = ($p && !empty($p['org']) && !empty($p['repo'])) ? $p['org'] . '/' . $p['repo'] : $repo;
        }
        $this->repo     = trim((string) $repo, '/');
        $this->ref      = (string) ($ref ?: 'main');
        $this->manifest = ltrim(trim((string) $manifest), '/');
        $this->root     = trim((string) $root, '/');
    }

    public function id()    { return $this->id; }
    public function label() { return $this->label; }

    public function scan()
    {
        $raw = $this->_manifestRaw();
        $data = $raw ? json_decode((string) $raw, true) : null;
        if (!is_array($data) || empty($data['plugins']) || !is_array($data['plugins'])) { return []; }

        $out = [];
        foreach ($data['plugins'] as $plugin) {
            if (!is_array($plugin)) { continue; }
            $desc = (string) ($plugin['description'] ?? '');

            // Grouped plugin: skills[] lists many folder paths, each a skill (shares the group description).
            if (!empty($plugin['skills']) && is_array($plugin['skills'])) {
                foreach ($plugin['skills'] as $s) {
                    $path = $this->_resolve((string) $s);
                    if ($path === '') { continue; }
                    $out[] = $this->entry($this->repo, $this->ref, $path, ['name' => basename($path), 'description' => $desc]);
                }
                continue;
            }
            // Flat plugin: a single source folder; the plugin name is the skill name.
            if (!empty($plugin['source'])) {
                $path = $this->_resolve((string) $plugin['source']);
                if ($path === '') { continue; }
                $name = !empty($plugin['name']) ? (string) $plugin['name'] : basename($path);
                $out[] = $this->entry($this->repo, $this->ref, $path, ['name' => $name, 'description' => $desc]);
            }
        }
        return $out;
    }

    /** Resolve a manifest `source`/`skills[]` path (usually `./name`) to a repo-relative folder path. */
    protected function _resolve($src)
    {
        $src = trim((string) $src);
        if ($src === '') { return ''; }
        $src = preg_replace('#^\./#', '', $src);          // strip a leading ./
        $src = trim($src, '/');
        if ($src === '' || strpos($src, '..') !== false) { return ''; }   // no traversal
        return $this->root !== '' ? $this->root . '/' . $src : $src;
    }

    /** Network seam (overridden in tests): fetch the manifest's raw bytes. */
    protected function _manifestRaw()
    {
        [$org, $repo] = array_pad(explode('/', $this->repo, 2), 2, '');
        if ($org === '' || $repo === '' || $this->manifest === '') { return ''; }
        return (string) @Tiger_Module_Github::fetchRaw($org, $repo, $this->ref, $this->manifest);
    }
}
