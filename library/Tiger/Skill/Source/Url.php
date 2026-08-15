<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Skill_Source_Url — the "paste a GitHub URL" adapter. Accepts a repo, a branch, a subfolder, or a
 * link straight to a SKILL.md, and finds every SKILL.md at/under that point (so a single-skill repo AND a
 * subfolder-of-skills both resolve). Reuses the collection scan; label = the URL itself (pure provenance —
 * Tiger vouches for nothing; the user reviews the SKILL.md before installing).
 *
 * @api
 * @see Tiger_Skill_Source_SkillsDir
 */
class Tiger_Skill_Source_Url extends Tiger_Skill_Source_SkillsDir
{
    /**
     * @param  string $url a github.com repo / tree / blob URL
     * @throws InvalidArgumentException on an unparseable URL
     */
    public function __construct($url)
    {
        $url = trim((string) $url);
        // owner/repo, optionally .../tree|blob/<ref>/<subpath>
        if (!preg_match('#github\.com/([^/\s]+)/([^/\s]+?)(?:\.git)?(?:/(?:tree|blob)/([^/\s]+)/(.+?))?/?$#i', $url, $m)) {
            throw new InvalidArgumentException('Not a GitHub URL: ' . $url);
        }
        $repo    = $m[1] . '/' . $m[2];
        $ref     = ($m[3] ?? '') !== '' ? $m[3] : 'main';
        $subpath = trim($m[4] ?? '', '/');
        // A link to the SKILL.md itself -> scope to its folder.
        if ($subpath !== '' && strtolower(basename($subpath)) === 'skill.md') {
            $subpath = trim(dirname($subpath), '/.');
        }
        $id    = 'url-' . substr(sha1($repo . '/' . $subpath), 0, 8);
        $label = 'From github.com/' . $repo . ($subpath !== '' ? '/' . $subpath : '');
        parent::__construct($id, $label, $repo, $ref, $subpath);
    }
}
