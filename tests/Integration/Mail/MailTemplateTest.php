<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mail;
use Zend_Config;
use Zend_Registry;

/**
 * The shared transactional email layout — `Tiger_Mail::template()`.
 *
 * Every email Tiger sends (reset, sign-in code, signup verification, site registration, backup
 * reports, the SMTP test) renders through one layout, so these assert the properties that make
 * that safe rather than re-testing each template's copy.
 *
 * The one that earns its keep is `the_body_is_rendered_into_the_layout`. Zend_View does NOT extract
 * assigned variables into local scope, so a bare `$content` in the layout silently produces a
 * perfectly valid, perfectly EMPTY email — every template the same byte size, no error, and nobody
 * notices until a customer gets a blank password reset. That is exactly what happened while
 * building this, caught only by rendering the output.
 */
#[CoversClass(Tiger_Mail::class)]
final class MailTemplateTest extends IntegrationTestCase
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
    private function render(string $template, array $vars = []): string
    {
        $mail = new Tiger_Mail();
        $mail->to('someone@example.com')->subject('t')->template($template, $vars);
        return (string) (new ReflectionProperty(Tiger_Mail::class, '_html'))->getValue($mail);
    }

    #[Test]
    public function the_body_is_rendered_into_the_layout(): void
    {
        $html = $this->render('reset', ['url' => 'https://acme.example.com/r/abc123']);

        $this->assertStringContainsString('Reset your password', $html, 'the template body is present');
        $this->assertStringContainsString('https://acme.example.com/r/abc123', $html, 'its variables are interpolated');
        $this->assertStringContainsString('Acme Corp', $html, 'and the layout wraps it');
    }

    #[Test]
    public function templates_produce_genuinely_different_bodies(): void
    {
        // The empty-shell bug made every email identical in length. Comparing two templates catches
        // a layout that renders but drops its content.
        $reset = $this->render('reset', ['url' => 'https://acme.example.com/r/1']);
        $otp   = $this->render('otp',   ['code' => '481902']);

        $this->assertNotSame($reset, $otp, 'different templates must not render the same document');
        $this->assertStringContainsString('481902', $otp, 'the code reaches the body');
        $this->assertStringNotContainsString('481902', $reset, 'and does not leak into another template');
    }

    #[Test]
    public function every_shipped_template_renders(): void
    {
        $cases = [
            'reset'           => ['url' => 'https://acme.example.com/r/1'],
            'otp'             => ['code' => '481902'],
            'verify'          => ['url' => 'https://acme.example.com/v/1'],
            'register-verify' => ['url' => 'https://acme.example.com/rv/1', 'domain' => 'acme.example.com'],
            'backup'          => ['ok' => true, 'filename' => 'b.zip', 'size' => '1 MB', 'components' => 'database'],
            'test'            => ['via' => 'smtp.example.com:587', 'sentAt' => '2026-01-01 00:00 UTC'],
        ];

        foreach ($cases as $name => $vars) {
            $html = $this->render($name, $vars);
            $this->assertStringContainsString('<html', $html, "$name renders a full document");
            $this->assertStringContainsString('</body>', $html, "$name closes cleanly");
            $this->assertGreaterThan(1500, strlen($html), "$name has real content, not an empty shell");
        }
    }

    #[Test]
    public function the_layout_is_built_for_email_clients_not_browsers(): void
    {
        $html = $this->render('reset', ['url' => 'https://acme.example.com/r/1']);

        // Inline styles are load-bearing: Gmail strips <head><style> in several contexts.
        $this->assertGreaterThan(10, substr_count($html, 'style="'), 'styling is inlined, not only in <style>');
        // Tables, because flex/grid are unusable in Outlook's Word renderer.
        $this->assertStringContainsString('role="presentation"', $html, 'layout tables are marked presentational');
        $this->assertStringNotContainsString('display:flex', $html, 'no flexbox — Outlook cannot render it');
        $this->assertStringNotContainsString('display:grid', $html, 'no grid, for the same reason');
        // A remote image would render as a broken box on first open in most clients.
        $this->assertStringNotContainsString('<img', $html, 'no remote images; the wordmark is live text');
    }

    #[Test]
    public function a_backup_failure_reports_the_reason_and_omits_the_success_details(): void
    {
        $ok   = $this->render('backup', ['ok' => true, 'filename' => 'b.zip', 'size' => '412 MB', 'components' => 'database, media']);
        $fail = $this->render('backup', ['ok' => false, 'filename' => 'b.zip', 'error' => 'Disk quota exceeded']);

        $this->assertStringContainsString('412 MB', $ok, 'a successful report carries the size');
        $this->assertStringContainsString('Disk quota exceeded', $fail, 'a failure carries the reason');
        $this->assertStringNotContainsString('412 MB', $fail, 'and does not show size details it does not have');
    }

    #[Test]
    public function template_variables_are_escaped(): void
    {
        // Email bodies interpolate user-influenced values (a domain, a filename), so the templates
        // escape rather than trusting the caller.
        $html = $this->render('register-verify', [
            'url'    => 'https://acme.example.com/v/1',
            'domain' => '<script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'injected markup is not emitted raw');
        $this->assertStringContainsString('&lt;script&gt;', $html, 'it is escaped instead');
    }

    #[Test]
    public function a_preheader_is_hidden_from_the_body_but_present_for_the_inbox(): void
    {
        $html = $this->render('test', ['via' => 'smtp.example.com:587', 'sentAt' => 'now', 'preheader' => 'Mail is working']);

        $this->assertStringContainsString('Mail is working', $html, 'the preheader is in the source for the inbox preview');
        $this->assertStringContainsString('display:none', $html, 'but hidden when the message is opened');
    }
}
