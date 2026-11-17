<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Code;

use Code_Form_Code;
use Code_Service_Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Code;
use Zend_Registry;
use Zend_Session;
use Zend_View;

/**
 * TIGER-528: the Code Area's state changes — activate, deactivate, delete, restore — carry a CSRF token
 * on the browser (cookie-session) path. These toggle server-executed PHP, so before this a cross-site page
 * could make a signed-in superadmin's browser switch a snippet on or delete one. Also pins the `_toggle`
 * privilege fix: the ACL is asked about activate/deactivate, never the helper's name.
 */
#[CoversClass(Code_Service_Code::class)]
final class CodeCsrfTest extends IntegrationTestCase
{
    private bool $priorUnitTest = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTest = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        Zend_Registry::set('tiger.auth.stateless', false);   // cookie mode: CSRF applies
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('tiger.auth.stateless', false);
        Zend_Session::$_unitTestEnabled = $this->priorUnitTest;
        $dir = APPLICATION_ROOT . '/storage/cache/code';
        foreach (glob($dir . '/global.*.php') ?: [] as $f) { @unlink($f); }
        foreach (glob($dir . '/inject.global.*.php') ?: [] as $f) { @unlink($f); }
        foreach (glob(APPLICATION_ROOT . '/public/_code/global.*') ?: [] as $f) { @unlink($f); }
        parent::tearDown();
    }

    /** The token the snippet editor renders (Code_Form_Code) — shared by every Code Area write. */
    private function pageToken(): string
    {
        $el = (new Code_Form_Code())->getElement('_csrf');
        $el->setView(new Zend_View());
        $el->render();
        return (string) $el->getValue();
    }

    private function snippet(): string
    {
        return (string) (new Tiger_Model_Code())->insert([
            'org_id' => '', 'name' => 'csrf-target', 'language' => 'css', 'code' => 'body{}',
            'run_location' => 'global', 'active' => 0,
        ]);
    }

    #[Test]
    public function every_code_write_refuses_a_tokenless_cross_site_request(): void
    {
        $this->loginAs('superadmin');
        $id = $this->snippet();

        foreach (['activate' => [], 'deactivate' => [], 'delete' => [], 'restore' => ['version' => 1]] as $m => $extra) {
            $svc = new Code_Service_Code();
            $svc->$m(['code_id' => $id] + $extra);
            $res = $svc->getResponse();
            $this->assertSame(0, (int) $res->result, "$m without a token is refused");
            $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$m: the CSRF refusal");
        }

        $row = (new Tiger_Model_Code())->findById($id);
        $this->assertNotNull($row, 'not deleted');
        $this->assertSame(0, (int) $row->active, 'not activated');
    }

    #[Test]
    public function the_editor_token_is_accepted(): void
    {
        $this->loginAs('superadmin');
        $id = $this->snippet();

        $svc = new Code_Service_Code();
        $svc->delete(['code_id' => $id, '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result, json_encode($svc->getResponse()->messages));
        $this->assertNull((new Tiger_Model_Code())->findById($id), 'soft-deleted');
    }

    #[Test]
    public function toggle_asks_the_acl_about_the_invoked_method(): void
    {
        Zend_Registry::set('tiger.auth.stateless', true);   // isolate the ACL question from CSRF
        $this->login('u-act', 'org-test', 'user');
        Zend_Registry::get('Zend_Acl')->allow('user', 'Code_Service_Code', 'activate');   // activate ONLY

        $off = new Code_Service_Code();
        $off->deactivate(['code_id' => 'nope']);
        $this->assertStringContainsString('not_allowed', json_encode($off->getResponse()->messages),
            'a role granted only activate may not deactivate');

        $on = new Code_Service_Code();
        $on->activate(['code_id' => 'nope']);
        $this->assertStringNotContainsString('not_allowed', json_encode($on->getResponse()->messages),
            'activate is asked as activate (not the `_toggle` helper name), so the grant applies');
    }
}
