<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Form_Settings;
use Agent_Service_Agents;
use Agent_Service_Mcp;
use Agent_Service_Settings;
use Agent_Service_Skills;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Mcp;
use Tiger_Agent_Skills;
use Tiger_Model_Agent;
use Zend_Registry;
use Zend_Session;
use Zend_View;

/**
 * TIGER-528: every agent ADMIN write (registry delete, the mode ceiling, MCP connections, skills) carries a
 * CSRF token on the browser (cookie-session) path. Before, these hand-validated `$params` with no form, so
 * any page on the internet could make a signed-in admin's browser delete an agent, wire an MCP server, or
 * install a skill. Stateless (Bearer/MCP) calls stay CSRF-free by design; these run as a cookie session.
 */
#[CoversClass(Agent_Service_Agents::class)]
#[CoversClass(Agent_Service_Settings::class)]
#[CoversClass(Agent_Service_Mcp::class)]
#[CoversClass(Agent_Service_Skills::class)]
final class AgentAdminCsrfTest extends IntegrationTestCase
{
    private bool $priorUnitTest = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTest = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;               // array-backed session under CLI
        Zend_Registry::set('tiger.auth.stateless', false);    // cookie mode: CSRF applies
        $this->loginAs('admin');
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Zend_Session::$_unitTestEnabled = $this->priorUnitTest;
        @unlink(APPLICATION_ROOT . '/var/cache/agent-mcp/tools-' . md5('org-test') . '.json');
        parent::tearDown();
    }

    /** The token the Settings screen renders (Agent_Form_Settings) — the one every agent admin write shares. */
    private function pageToken(): string
    {
        $el = (new Agent_Form_Settings())->getElement('_csrf');
        $el->setView(new Zend_View());
        $el->render();
        return (string) $el->getValue();
    }

    private function csrfRefused(object $res, string $what): void
    {
        $this->assertSame(0, (int) $res->result, "$what without a token is refused");
        $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), "$what: the CSRF refusal");
    }

    #[Test]
    public function agent_delete_refuses_a_tokenless_request_and_accepts_the_page_token(): void
    {
        $model = new Tiger_Model_Agent();
        $id = (string) $model->insert(['org_id' => 'org-test', 'name' => 'Target', 'provider' => 'anthropic',
                                       'model' => 'm', 'enabled' => 1, 'is_default' => 1]);

        $svc = new Agent_Service_Agents();
        $svc->delete(['agent_id' => $id]);
        $this->csrfRefused($svc->getResponse(), 'agents/delete');
        $this->assertNotNull($model->findForOrg('org-test', $id), 'nothing deleted');

        $svc = new Agent_Service_Agents();
        $svc->delete(['agent_id' => $id, '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result, 'the Settings page token (shared salt) is accepted');
        $this->assertNull($model->findForOrg('org-test', $id));
    }

    #[Test]
    public function the_mode_ceiling_refuses_a_tokenless_request(): void
    {
        $svc = new Agent_Service_Settings();
        $svc->mode(['mode_max' => 'yolo']);
        $this->csrfRefused($svc->getResponse(), 'settings/mode');

        $svc = new Agent_Service_Settings();
        $svc->mode(['mode_max' => 'yolo', '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result);
        $this->assertSame('yolo', $svc->getResponse()->data['mode_max']);
    }

    #[Test]
    public function mcp_connection_writes_refuse_a_tokenless_request(): void
    {
        $svc = new Agent_Service_Mcp();
        $svc->save(['label' => 'Evil', 'url' => 'http://127.0.0.1:9/mcp', 'enabled' => 1]);
        $this->csrfRefused($svc->getResponse(), 'mcp/save');
        $this->assertSame([], array_filter(Tiger_Agent_Mcp::forAdmin('org-test'), fn($c) => $c['label'] === 'Evil'), 'nothing wired');

        $id = Tiger_Agent_Mcp::save(['label' => 'Keep', 'url' => 'http://127.0.0.1:9/mcp', 'enabled' => true], 'org-test');
        $svc = new Agent_Service_Mcp();
        $svc->remove(['id' => $id]);
        $this->csrfRefused($svc->getResponse(), 'mcp/remove');
        $this->assertNotNull(Tiger_Agent_Mcp::connection($id, 'org-test'), 'not removed');

        $svc = new Agent_Service_Mcp();
        $svc->test(['id' => $id]);
        $this->csrfRefused($svc->getResponse(), 'mcp/test');

        $svc = new Agent_Service_Mcp();
        $svc->remove(['id' => $id, '_csrf' => $this->pageToken()]);
        $this->assertSame(1, (int) $svc->getResponse()->result);
        $this->assertNull(Tiger_Agent_Mcp::connection($id, 'org-test'));
    }

    #[Test]
    public function skill_writes_refuse_a_tokenless_request(): void
    {
        $svc = new Agent_Service_Skills();
        $svc->install(['url' => 'https://github.com/evil/skills']);
        $this->csrfRefused($svc->getResponse(), 'skills/install');

        foreach (['toggle', 'remove'] as $m) {
            $svc = new Agent_Service_Skills();
            $svc->$m(['key' => 'anything__demo', 'active' => 1]);
            $this->csrfRefused($svc->getResponse(), "skills/$m");
        }
        $this->assertFalse(Tiger_Agent_Skills::isActive('anything__demo'));
    }
}
