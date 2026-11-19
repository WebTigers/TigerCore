<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Tools;
use Tiger_Ajax_ServiceFactory;
use Tiger_Mcp_Server;
use Tiger_Service_Locator;
use Zend_Controller_Request_Http;

/**
 * TIGER-531 — a two-word service (Profile_Service_OrgAddress) round-trips by its lower-cased MCP/OpenAPI name.
 * Every caller used to rebuild the class with ucfirst(): `orgaddress` → `Profile_Service_Orgaddress`, an ACL
 * resource that doesn't exist (case-sensitive), so the op was silently dropped from the agent/MCP catalog and
 * refused at /api. Tiger_Service_Locator resolves the REAL class from the module's own services/ dir.
 */
#[CoversClass(Tiger_Service_Locator::class)]
#[CoversClass(Tiger_Agent_Tools::class)]
final class ServiceLocatorTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Service_Locator::reset();
    }

    #[Test]
    public function a_two_word_service_resolves_to_its_real_class_whatever_the_casing(): void
    {
        $this->assertSame('Profile_Service_OrgAddress', Tiger_Service_Locator::className('profile', 'orgaddress'));
        $this->assertSame('Profile_Service_OrgAddress', Tiger_Service_Locator::className('profile', 'OrgAddress'));
        $this->assertSame('Profile_Service_OrgContact', Tiger_Service_Locator::className('profile', 'ORGCONTACT'));
    }

    #[Test]
    public function a_one_word_service_and_an_unknown_one_keep_the_plain_rebuild(): void
    {
        $this->assertSame('Cms_Service_Page', Tiger_Service_Locator::className('cms', 'page'));
        $this->assertSame('Profile_Service_Nosuchthing', Tiger_Service_Locator::className('profile', 'nosuchthing'),
            'no matching file → the ucfirst name, so the caller\'s not-found / ACL-denied path is unchanged');
    }

    #[Test]
    public function a_malformed_name_never_resolves_outside_its_module(): void
    {
        $this->assertSame('Profile_Service_../cms/page', Tiger_Service_Locator::className('profile', '../cms/page'),
            'a non-alphanumeric name is never looked up on disk');
        $this->assertSame('Cms_Service_Orgaddress', Tiger_Service_Locator::className('cms', 'orgaddress'),
            'a name only resolves within ITS module — profile\'s OrgAddress is not cms\'s');
    }

    #[Test]
    public function the_catalog_and_mcp_list_include_the_two_word_services(): void
    {
        $this->loginAs('admin');
        $ops = array_map(fn($o) => $o['service'] . '/' . $o['method'], Tiger_Agent_Tools::catalog('admin', ['profile'], null)['profile'] ?? []);
        $this->assertContains('orgaddress/save', $ops);
        $this->assertContains('orgcontact/save', $ops);

        $out   = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'], 'admin', fn() => null, ['profile']);
        $names = array_column($out['result']['tools'], 'name');
        $this->assertContains('profile__orgaddress__save', $names);
        $this->assertContains('profile__orgaddress__delete', $names);
    }

    #[Test]
    public function api_dispatch_by_the_lower_cased_name_reaches_the_service(): void
    {
        $this->login('u-531', 'org-test', 'admin');
        $req = new Zend_Controller_Request_Http();
        $req->setParam('svc_module', 'profile');
        $req->setParam('svc_service', 'orgaddress');   // the MCP/OpenAPI casing
        $req->setParam('svc_action', 'delete');
        $env  = (new Tiger_Ajax_ServiceFactory($req))->getResponse();
        $keys = array_map(fn($m) => is_object($m) ? (string) $m->message : (string) $m, (array) $env->messages);

        $this->assertNotContains('core.api.error.not_allowed', $keys, 'the admin is allowed on Profile_Service_OrgAddress');
        $this->assertNotContains('core.api.error.general', $keys, 'the class is found by its real name');
        $this->assertSame(0, (int) $env->result, 'it reached the service, which refuses the empty delete on its form');
    }
}
