<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail;
use Tiger_Model_EmailTemplate;
use Zend_Config;
use Zend_Registry;

/**
 * The email-template DB-override tier (TIGER-267) — Tiger_Model_EmailTemplate + Tiger_Mail::template().
 *
 * An authored row OVERRIDES the code-shipped `emails/<key>.phtml` default: a tenant row wins over the
 * global one (the config cascade), a send renders the authored body (with `{{var}}` substitution) when a
 * row exists and falls back to the file when it doesn't, and reset → re-author works despite the
 * active-only unique index (the revive path).
 */
#[CoversClass(Tiger_Model_EmailTemplate::class)]
#[CoversClass(Tiger_Mail::class)]
final class EmailTemplateOverrideTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['site' => ['name' => 'Acme Corp', 'url' => 'https://acme.example.com']],
            'mail'  => ['from' => ['email' => 'no-reply@acme.example.com', 'name' => 'Acme']],
        ], true));
    }

    /** The HTML body Tiger_Mail would send, without sending it. */
    private function render(string $template, array $vars, string $orgId = ''): string
    {
        $mail = new Tiger_Mail();
        $mail->forOrg($orgId)->to('x@example.com')->subject('')->template($template, $vars);
        return (string) (new ReflectionProperty(Tiger_Mail::class, '_html'))->getValue($mail);
    }

    #[Test]
    public function an_org_override_wins_over_the_global_one(): void
    {
        $m = new Tiger_Model_EmailTemplate();
        $m->store('reset', '', 'Global subject', '<p>global body</p>');
        $m->store('reset', 'org-A', 'Org subject', '<p>org body</p>');

        $this->assertSame('<p>global body</p>', (string) $m->resolve('reset', '')->body);
        $this->assertSame('<p>org body</p>',    (string) $m->resolve('reset', 'org-A')->body);
        // An org with no override of its own falls back to the global row.
        $this->assertSame('<p>global body</p>', (string) $m->resolve('reset', 'org-B')->body);
    }

    #[Test]
    public function template_renders_the_override_with_substitution_else_the_file_default(): void
    {
        (new Tiger_Model_EmailTemplate())->store(
            'reset', '', 'Reset', '<p>Authored reset — open {{url}}</p>'
        );

        $overridden = $this->render('reset', ['url' => 'https://acme.example.com/r/abc']);
        $this->assertStringContainsString('Authored reset — open https://acme.example.com/r/abc', $overridden);
        $this->assertStringContainsString('Acme Corp', $overridden, 'wrapped in the branded layout');

        // A key with no override falls back to the shipped file template.
        $fallback = $this->render('otp', ['code' => '424242']);
        $this->assertStringContainsString('424242', $fallback, 'the file default still renders');
        $this->assertStringNotContainsString('Authored reset', $fallback);
    }

    #[Test]
    public function reset_removes_the_override_and_the_file_default_returns(): void
    {
        $m = new Tiger_Model_EmailTemplate();
        $m->store('reset', '', 'Reset', '<p>Authored {{url}}</p>');
        $this->assertStringContainsString('Authored', $this->render('reset', ['url' => 'u']));

        $this->assertTrue($m->reset('reset', ''));
        $this->assertNull($m->resolve('reset', ''), 'the override is gone');
        $this->assertStringNotContainsString('Authored', $this->render('reset', ['url' => 'u']),
            'the file default renders once the override is reset');
    }

    #[Test]
    public function re_authoring_after_a_reset_revives_the_row_without_a_unique_collision(): void
    {
        $m = new Tiger_Model_EmailTemplate();
        $id1 = $m->store('reset', '', 'First', '<p>first</p>');
        $m->reset('reset', '');                                   // soft-delete
        $id2 = $m->store('reset', '', 'Second', '<p>second</p>'); // must NOT hit the active-only unique index

        $this->assertSame($id1, $id2, 'the soft-deleted row is revived, not duplicated');
        $row = $m->resolve('reset', '');
        $this->assertNotNull($row);
        $this->assertSame('<p>second</p>', (string) $row->body);
    }

    #[Test]
    public function render_template_preview_uses_the_stored_override(): void
    {
        (new Tiger_Model_EmailTemplate())->store(
            'reset', '', 'Reset {{url}}', '<p>body {{url}}</p>'
        );

        $out = Tiger_Mail::renderTemplate('reset', ['url' => 'https://acme.example.com/r/z']);
        $this->assertSame('Reset https://acme.example.com/r/z', $out['subject']);
        $this->assertStringContainsString('body https://acme.example.com/r/z', $out['html']);
    }
}
