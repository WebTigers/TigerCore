<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Application_Bootstrap;
use Tiger_Model_Org;
use Tiger_Uuid;
use Zend_Auth;
use Zend_Config;

/**
 * Whose org config tier a request folds (TIGER-505).
 *
 * Every install has an org, so every request gets an org tier: the signed-in user's org, else the tenant
 * org the request host maps to, else the install's own org (`tiger.site.org_id`, else the founding org).
 * Before this, an anonymous visitor, a webhook or a scheduler ping on a single-site install folded the
 * global tier only, so a setting a module saved at its org silently never reached them.
 */
#[CoversClass(Tiger_Application_Bootstrap::class)]
#[CoversClass(Tiger_Model_Org::class)]
final class ConfigOrgTierTest extends IntegrationTestCase
{
    /** Insert an active org dated before anything else in the test DB, so it is the founding org. */
    private function seedFoundingOrg(): string
    {
        $id = Tiger_Uuid::v7();
        $this->db->insert('org', [
            'org_id'     => $id,
            'name'       => 'Founding',
            'slug'       => 'founding-' . substr($id, -12),
            'status'     => 'active',
            'deleted'    => 0,
            'created_at' => '1970-01-02 00:00:00',
        ]);
        return $id;
    }

    /** Call the protected resolver on a bootstrap built without the application. */
    private function configOrgId(Zend_Config $config, ?string $siteOrg = null): ?string
    {
        $ref  = new ReflectionClass(Tiger_Application_Bootstrap::class);
        $boot = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('_siteOrg')->setValue($boot, $siteOrg);
        return $ref->getMethod('_configOrgId')->invoke($boot, $config);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }

    #[Test]
    public function install_org_is_the_configured_site_org_when_set(): void
    {
        $this->seedFoundingOrg();
        $this->assertSame('org-configured', Tiger_Model_Org::installOrgId('org-configured'));
    }

    #[Test]
    public function install_org_falls_back_to_the_founding_org(): void
    {
        $founding = $this->seedFoundingOrg();
        $this->assertSame($founding, Tiger_Model_Org::installOrgId(''));
    }

    #[Test]
    public function an_anonymous_request_on_a_single_site_install_folds_the_install_org(): void
    {
        $founding = $this->seedFoundingOrg();
        Zend_Auth::getInstance()->clearIdentity();

        // No identity, no mapped host: the install's own org, not "global only".
        $this->assertSame($founding, $this->configOrgId(new Zend_Config([])));
    }

    #[Test]
    public function the_configured_site_org_wins_over_the_founding_org(): void
    {
        $this->seedFoundingOrg();
        Zend_Auth::getInstance()->clearIdentity();

        $config = new Zend_Config(['tiger' => ['site' => ['org_id' => 'org-site']]]);
        $this->assertSame('org-site', $this->configOrgId($config));
    }

    #[Test]
    public function a_mapped_tenant_host_wins_over_the_install_org(): void
    {
        $this->seedFoundingOrg();
        Zend_Auth::getInstance()->clearIdentity();

        $this->assertSame('org-tenant', $this->configOrgId(new Zend_Config([]), 'org-tenant'));
    }

    #[Test]
    public function the_signed_in_users_org_wins_over_everything(): void
    {
        $this->seedFoundingOrg();
        $this->login(Tiger_Uuid::v7(), 'org-member');

        $this->assertSame('org-member', $this->configOrgId(new Zend_Config([]), 'org-tenant'));
    }
}
