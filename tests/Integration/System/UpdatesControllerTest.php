<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_UpdatesController;
use Tiger\Tests\Support\ControllerTestCase;
use Zend_Session;

/**
 * System_UpdatesController — the Updates screen shell.
 *
 * The controller is thin READ+RENDER: it offers the pending updates and renders the history GRID's
 * shell. The history rows themselves come from /api (System_Service_Updates::datatable, covered by
 * the service tests) — they are NOT server-rendered, which is the client/server contract this screen
 * was converted to (TIGER-383).
 *
 * The load-bearing assertion is `useDataTables`. Without that flag the DataTables assets never load,
 * the grid renders as an empty table, and the page's own JS dies after the failed init — silently, with
 * a 200 and no console error a casual look would catch. It is the exact failure this screen would hit
 * first, so it gets its own test rather than being trusted to a code review.
 */
#[CoversClass(System_UpdatesController::class)]
final class UpdatesControllerTest extends ControllerTestCase
{
    private bool $priorUnitTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTestMode = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Zend_Session::$_unitTestEnabled = $this->priorUnitTestMode;
        parent::tearDown();
    }

    #[Test]
    public function index_renders_the_updates_shell_and_opts_into_the_dataTables_assets(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatchAction(System_UpdatesController::class, 'index', [], 'GET');
        $this->assertSame(200, $res->getHttpResponseCode());

        $view = $this->controller()->view;
        $this->assertSame('Updates — Tiger Admin', $view->title);
        $this->assertTrue((bool) $view->useDataTables, 'the history grid needs the DataTables assets, or it renders empty');
    }

    #[Test]
    public function the_controller_no_longer_server_renders_the_history_rows(): void
    {
        $this->loginAs('superadmin');
        $this->dispatchAction(System_UpdatesController::class, 'index', [], 'GET');

        // The guard for the regression this screen just came back from: if a `history` view-var
        // reappears, someone has gone back to rendering rows on the server (WEBSERVICES.md §5).
        $this->assertNull($this->controller()->view->history ?? null,
            'history rows come from /api, not a controller view-var');
    }
}
