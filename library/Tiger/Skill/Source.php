<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Skill_Source — a browse adapter for one supported skill repo (scan + normalize, NOT endorse).
 *
 * Tiger is **not a trust authority**. Each supported well-known skill repo (which lay their skills out
 * differently) gets an adapter that knows THAT repo's shape, scans it, and normalizes its skills into one
 * internal, searchable list — so the user can review + install. A source's `label()` is **provenance**
 * ("from Anthropic Skills", "from this URL"), never a vouch: the user reads the SKILL.md and decides.
 *
 * A normalized entry (what `list()` yields):
 *   [ 'key'         => '<sourceId>:<name>',        // stable id used to install/dedup
 *     'name'        => 'pdf',                        // SKILL.md frontmatter `name`
 *     'description' => '…what it does + when…',      // SKILL.md frontmatter `description` (spec: 2 fields only)
 *     'source'      => 'anthropic-skills',           // the adapter id() — PROVENANCE
 *     'sourceLabel' => 'Anthropic Skills',           // human provenance label (NOT an endorsement)
 *     'repo'        => 'anthropics/skills',           // owner/repo
 *     'ref'         => 'main',                         // branch or tag scanned
 *     'path'        => 'skills/pdf',                   // the skill folder in the repo
 *     'url'         => 'https://github.com/anthropics/skills/tree/main/skills/pdf' ]
 *
 * @api
 * @see Tiger_Skill_Index  the multi-source aggregator that runs adapters + caches + searches
 */
abstract class Tiger_Skill_Source
{
    /** Stable adapter id ([a-z0-9-]); the cache namespace + the entry `source`. */
    abstract public function id();

    /** Human PROVENANCE label (e.g. "Anthropic Skills") — names where a skill came from, never a vouch. */
    abstract public function label();

    /**
     * Scan the repo and return normalized skill entries (the network read — the Index caches it).
     *
     * @return array<int,array<string,string>>
     */
    abstract public function scan();

    /**
     * Parse a `SKILL.md`'s leading YAML frontmatter. Per the Agent Skills spec the frontmatter carries
     * only **`name`** + **`description`** (the description does double duty as "what it does AND when to
     * use it"); we tolerate extra keys but read those two. Returns [] if there's no usable frontmatter.
     *
     * @param  string $raw the SKILL.md contents
     * @return array{name?:string,description?:string}
     */
    public static function parseFrontmatter($raw)
    {
        $raw = (string) $raw;
        // Frontmatter = a leading `---\n … \n---` block.
        if (!preg_match('/^\x{FEFF}?\s*---[ \t]*\r?\n(.*?)\r?\n---[ \t]*\r?\n/su', $raw, $m)) {
            return [];
        }
        $out   = [];
        $lines = preg_split('/\r?\n/', $m[1]);
        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            if (!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', $lines[$i], $kv)) { continue; }
            $key = strtolower($kv[1]);
            if ($key !== 'name' && $key !== 'description') { continue; }
            $val = trim($kv[2]);

            if (preg_match('/^[|>][+-]?$/', $val)) {
                // YAML block scalar (`|`, `|-`, `>`, …): gather the following more-indented lines, fold to one line.
                $block = [];
                $j = $i + 1;
                for (; $j < $n; $j++) {
                    if (trim($lines[$j]) === '') { continue; }        // blank line within the block
                    if (!preg_match('/^\s/', $lines[$j])) { break; }   // dedent to column 0 → next key ends the block
                    $block[] = trim($lines[$j]);
                }
                $i = $j - 1;
                $val = implode(' ', $block);
            } elseif (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && substr($val, -1) === $val[0]) {
                $val = substr($val, 1, -1);   // strip matching surrounding quotes
            }
            if ($val !== '') { $out[$key] = $val; }
        }
        return $out;
    }

    /** Assemble a normalized entry from a repo, its scanned skill folder, and parsed frontmatter. */
    protected function entry($repo, $ref, $path, array $front)
    {
        $name = $front['name'] ?? basename($path);
        return [
            'key'         => $this->id() . ':' . $name,
            'name'        => $name,
            'description' => $front['description'] ?? '',
            'source'      => $this->id(),
            'sourceLabel' => $this->label(),
            'repo'        => $repo,
            'ref'         => $ref,
            'path'        => $path,
            'url'         => 'https://github.com/' . $repo . '/tree/' . $ref . '/' . $path,
        ];
    }
}
