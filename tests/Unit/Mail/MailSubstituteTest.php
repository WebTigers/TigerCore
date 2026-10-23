<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Mail;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Mail placeholder substitution + the preview renderer (TIGER-267).
 *
 * The authored subject/body of a DB-override template carry `{{var}}` placeholders. These exercise the
 * substitution contract through the public preview path (renderTemplate with an unsaved draft — no DB):
 * values are HTML-escaped, the authored markup is preserved raw, and an unmatched placeholder is
 * stripped rather than mailed verbatim.
 */
#[CoversClass(Tiger_Mail::class)]
final class MailSubstituteTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['site' => ['name' => 'Acme Corp', 'url' => 'https://acme.example.com']],
            'mail'  => ['from' => ['email' => 'no-reply@acme.example.com', 'name' => 'Acme']],
        ], true));
    }

    #[Test]
    public function a_draft_body_substitutes_escapes_and_strips(): void
    {
        $out = Tiger_Mail::renderTemplate(
            'reset',
            ['username' => '<b>Ada</b>'],
            '',
            'Welcome {{username}}',
            '<p class="x">Hi {{username}} — {{unknown}}</p>'
        );

        // The subject's value is substituted AND escaped.
        $this->assertSame('Welcome &lt;b&gt;Ada&lt;/b&gt;', $out['subject']);

        // The body's value is escaped...
        $this->assertStringContainsString('Hi &lt;b&gt;Ada&lt;/b&gt;', $out['html']);
        $this->assertStringNotContainsString('<b>Ada</b>', $out['html']);
        // ...the authored markup is preserved raw (only VALUES are escaped, not the template)...
        $this->assertStringContainsString('<p class="x">', $out['html']);
        // ...an unmatched placeholder is stripped, never mailed verbatim...
        $this->assertStringNotContainsString('{{unknown}}', $out['html']);
        // ...and the whole thing is wrapped in the shared branded layout.
        $this->assertStringContainsString('Acme Corp', $out['html']);
        $this->assertStringContainsString('<html', $out['html']);
    }

    #[Test]
    public function registered_templates_round_trip_through_the_registry(): void
    {
        Tiger_Mail::registerTemplate([
            'key'   => 'zz-unit-probe',
            'label' => 'Probe',
            'vars'  => ['x' => 'an x'],
        ]);
        $all = Tiger_Mail::registeredTemplates();
        $this->assertArrayHasKey('zz-unit-probe', $all);
        $this->assertSame('Probe', $all['zz-unit-probe']['label']);
        $this->assertSame(['x' => 'an x'], $all['zz-unit-probe']['vars']);
        $this->assertSame('general', $all['zz-unit-probe']['group'], 'group defaults to general');
    }
}
