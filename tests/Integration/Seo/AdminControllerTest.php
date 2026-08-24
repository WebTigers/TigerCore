<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Seo_AdminController;
use Tiger\Tests\Support\ModuleControllerTestCase;

/**
 * Seo_AdminController — the Social Cards screen. Thin by design (ADMIN.md): it renders the shell and
 * the reusable per-page editor form; the page LIST is fetched from `/api` (Seo_Service_Social::pages),
 * never server-rendered. The harness dispatches index with rendering off and asserts that view model.
 */
#[CoversClass(Seo_AdminController::class)]
final class AdminControllerTest extends ModuleControllerTestCase
{
    #[Test]
    public function index_renders_the_screen_with_the_page_editor_form(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Seo_AdminController::class, 'index', [], 'GET');

        $view = $this->controller()->view;
        $this->assertStringContainsString('Social Cards', (string) $view->title);
        $this->assertInstanceOf(\Seo_Form_Page::class, $view->form);

        // The form is the /api input contract (Seo_Service_Social::save @apiRequest) — every field the
        // editor posts must be declared on it.
        foreach (['page_key', 'title', 'description', 'image_media_id', 'image_url'] as $field) {
            $this->assertNotNull($view->form->getElement($field), $field . ' is declared');
        }
    }
}
