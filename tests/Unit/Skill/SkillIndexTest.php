<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Skill;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Skill_Index;
use Tiger_Skill_Source;
use Tiger_Skill_Source_SkillsDir;
use Tiger_Skill_Source_Url;

/**
 * The skill browse engine — the pure, network-free core: SKILL.md frontmatter parsing (spec = name +
 * description only), entry normalization, paste-URL parsing (repo/ref/subpath), and the Index's
 * merge/dedup/search over source adapters (a fake source stands in for the GitHub scan; the built-in
 * source's network scan is short-circuited by a pre-seeded fresh cache so the test never hits the network).
 */
#[CoversClass(Tiger_Skill_Source::class)]
#[CoversClass(Tiger_Skill_Source_Url::class)]
#[CoversClass(Tiger_Skill_Index::class)]
final class SkillIndexTest extends UnitTestCase
{
    private string $builtinCache = '';

    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Skill_Index::clearSources();
        // Short-circuit the built-in Anthropic source's network scan with a FRESH, empty cache.
        $this->builtinCache = APPLICATION_ROOT . '/var/cache/skills/anthropic-skills.json';
        @mkdir(dirname($this->builtinCache), 0775, true);
        file_put_contents($this->builtinCache, json_encode(['at' => time(), 'entries' => []]));
    }

    protected function tearDown(): void
    {
        Tiger_Skill_Index::clearSources();
        @unlink($this->builtinCache);
        parent::tearDown();
    }

    // ----- frontmatter -----------------------------------------------------------------------------

    #[Test]
    public function frontmatter_reads_name_and_description_only(): void
    {
        $md = "---\nname: pdf\ndescription: \"Read + fill PDFs, and when to.\"\nlicense: MIT\n---\nbody here";
        $this->assertSame(['name' => 'pdf', 'description' => 'Read + fill PDFs, and when to.'],
            Tiger_Skill_Source::parseFrontmatter($md), 'reads name+description, ignores extra keys, strips quotes');
    }

    #[Test]
    public function frontmatter_reads_a_yaml_block_scalar_description(): void
    {
        $md = "---\nname: claude-api\ndescription: |-\n  Call the Claude API from Tiger.\n  Handles auth and retries.\n---\nbody";
        $this->assertSame(
            ['name' => 'claude-api', 'description' => 'Call the Claude API from Tiger. Handles auth and retries.'],
            Tiger_Skill_Source::parseFrontmatter($md), 'a |- block scalar is folded to one line');
    }

    #[Test]
    public function frontmatter_absent_yields_empty(): void
    {
        $this->assertSame([], Tiger_Skill_Source::parseFrontmatter("# Just a heading\nno frontmatter"));
        $this->assertSame([], Tiger_Skill_Source::parseFrontmatter(''));
    }

    // ----- paste-URL parsing -----------------------------------------------------------------------

    #[Test]
    public function url_source_parses_repo_ref_and_subpath(): void
    {
        // repo root
        $this->assertSame('From github.com/acme/skills', (new Tiger_Skill_Source_Url('https://github.com/acme/skills'))->label());
        // tree/<ref>/<subpath>
        $this->assertSame('From github.com/acme/skills/packs/seo',
            (new Tiger_Skill_Source_Url('https://github.com/acme/skills/tree/dev/packs/seo'))->label());
        // a link straight to a SKILL.md -> scoped to its folder
        $this->assertSame('From github.com/acme/skills/packs/seo',
            (new Tiger_Skill_Source_Url('https://github.com/acme/skills/blob/main/packs/seo/SKILL.md'))->label());
    }

    #[Test]
    public function url_source_rejects_a_non_github_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Tiger_Skill_Source_Url('https://example.com/not/github');
    }

    // ----- index: sources, merge/dedup, search -----------------------------------------------------

    #[Test]
    public function index_merges_a_source_and_searches_it(): void
    {
        Tiger_Skill_Index::registerSource(new FakeSkillSource('fake', 'Fake Repo', [
            ['name' => 'seo-audit',        'description' => 'Audit a site for SEO issues.'],
            ['name' => 'author-store',     'description' => 'Set up an author storefront.'],
        ]));

        $all = Tiger_Skill_Index::all();
        $names = array_column($all, 'name');
        $this->assertContains('seo-audit', $names);
        $this->assertContains('author-store', $names);

        $one = Tiger_Skill_Index::all()[0];
        $this->assertArrayHasKey('key', $one);
        $this->assertArrayHasKey('sourceLabel', $one, 'entries carry provenance, not a vouch');
        $this->assertSame('Fake Repo', array_values(array_filter($all, fn($e) => $e['name'] === 'seo-audit'))[0]['sourceLabel']);

        $hits = Tiger_Skill_Index::search('storefront');
        $this->assertCount(1, $hits);
        $this->assertSame('author-store', $hits[0]['name']);
    }

    #[Test]
    public function built_in_anthropic_source_is_registered(): void
    {
        $this->assertArrayHasKey('anthropic-skills', Tiger_Skill_Index::sources(), 'the official collection ships as a supported source');
    }
}

/** A network-free source that yields canned entries through the base's normalization. */
final class FakeSkillSource extends Tiger_Skill_Source
{
    public function __construct(private string $id, private string $label, private array $skills) {}
    public function id()    { return $this->id; }
    public function label() { return $this->label; }
    public function scan()
    {
        $out = [];
        foreach ($this->skills as $s) {
            $out[] = $this->entry('acme/skills', 'main', 'skills/' . $s['name'], $s);
        }
        return $out;
    }
}
