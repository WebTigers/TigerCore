<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_SkillsController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Zend_Session;

/**
 * Agent_SkillsController — the Skills manager shell (admin). Thin READ+RENDER: the DataTables grid loads
 * its rows from Agent_Service_Skills::datatable over /api (the service owns the data + ACL). The shell's
 * one load-bearing job is opting into the DataTables assets — WITHOUT $view->useDataTables the admin
 * layout never loads tiger.datatable.js, so tigerDataTable() is undefined and the grid stays empty. This
 * asserts that opt-in (the regression guard for exactly that "no data in the grid" bug).
 */
#[CoversClass(Agent_SkillsController::class)]
final class SkillsControllerTest extends ControllerTestCase
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
    public function index_renders_the_shell_and_opts_into_datatables(): void
    {
        $this->loginAs('admin');
        $res = $this->dispatchAction(Agent_SkillsController::class, 'index', [], 'GET');
        $this->assertSame(200, $res->getHttpResponseCode());

        $view = $this->controller()->view;
        $this->assertSame('Agent Skills — Tiger Admin', $view->title);
        $this->assertTrue($view->useDataTables, 'the grid needs the DataTables assets, or it renders empty');
    }
}
