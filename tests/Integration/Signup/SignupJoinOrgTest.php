<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Signup;

use PHPUnit\Framework\Attributes\Test;
use Signup_Form_Signup;
use Signup_Service_Signup;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Org;
use Tiger_Model_ResponseObject;
use Tiger_Uuid;
use Zend_Config;
use Zend_Registry;
use Zend_Session;
use Zend_Session_Namespace;

/**
 * The signup form's `setupOrg` mode (TIGER-505).
 *
 * A signup form is built either to set up a new org for the new user (the default — every user gets
 * their own org) or to add the new user to the org of the site the form is shown on. The mode is fixed
 * by the code that renders the form and pinned in the visitor's session by bind(); the submit reads it
 * from there, so a posted field can never move someone into an org.
 */
final class SignupJoinOrgTest extends IntegrationTestCase
{
    /** user_ids committed for real (outside the base txn) — purged in tearDown. */
    private array $_users = [];

    /** org ids committed for real — purged in tearDown. */
    private array $_orgs = [];

    private ?Zend_Config $_priorConfig = null;

    private bool $_priorUnitTest = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_priorUnitTest = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;   // array-backed session under CLI
        // An earlier test in the suite may have write-closed the session, and start() won't reopen one
        // that already started. A request always has a writable session, so mirror that directly —
        // and restore the previous flag in tearDown.
        $this->_priorWritable = $this->_writable();
        $this->_writable(true);
        (new Zend_Session_Namespace(Signup_Form_Signup::MODE_NAMESPACE))->unsetAll();
        $this->_priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['password' => ['min_length' => 8, 'require_complexity' => 0, 'history' => 0, 'max_age_days' => 0]],
        ]));
    }

    protected function tearDown(): void
    {
        foreach ($this->_users as $userId) {
            foreach (['org_user', 'user_credential', 'user_address', 'user_contact', 'auth_challenge'] as $t) {
                $this->db->query("DELETE FROM $t WHERE user_id = ?", [$userId]);
            }
            $this->db->query('DELETE FROM user WHERE user_id = ?', [$userId]);
        }
        foreach ($this->_orgs as $orgId) {
            foreach (['org_address', 'org_contact', 'org_user'] as $t) {
                $this->db->query("DELETE FROM $t WHERE org_id = ?", [$orgId]);
            }
            $this->db->query('DELETE FROM org WHERE org_id = ?', [$orgId]);
        }
        $this->_users = $this->_orgs = [];
        (new Zend_Session_Namespace(Signup_Form_Signup::MODE_NAMESPACE))->unsetAll();
        (new \ReflectionProperty(Tiger_Model_Org::class, '_siteOrgId'))->setValue(null, null);   // back to unresolved
        Zend_Registry::set('tiger.auth.stateless', false);
        Zend_Registry::set('Zend_Config', $this->_priorConfig ?? new Zend_Config([]));
        Zend_Session::$_unitTestEnabled = $this->_priorUnitTest;
        $this->_writable($this->_priorWritable);
        parent::tearDown();
    }

    private bool $_priorWritable = false;

    /** Read (no arg) or set Zend_Session's writable flag (a protected static on Zend_Session_Abstract). */
    private function _writable(?bool $set = null): bool
    {
        $p = new \ReflectionProperty(\Zend_Session_Abstract::class, '_writable');
        if ($set !== null) { $p->setValue(null, $set); }
        return (bool) $p->getValue();
    }

    /** End the base txn (create() opens its own) and commit a store org to sign up into. */
    private function commitStoreOrg(): string
    {
        try { $this->db->rollBack(); } catch (\Throwable $e) { /* already closed */ }
        $id = Tiger_Uuid::v7();
        $this->db->insert('org', [
            'org_id' => $id, 'name' => 'Store', 'slug' => 'store-' . substr($id, -12),
            'status' => 'active', 'deleted' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->_orgs[] = $id;
        return $id;
    }

    private function params(array $overrides = []): array
    {
        $tag = bin2hex(random_bytes(6));
        return array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'company' => 'Acme ' . $tag,
            'username' => 'jane' . $tag, 'password' => 'SuperSecret123', 'email' => 'jane' . $tag . '@example.test',
            'street' => '1 Main St', 'city' => 'Springfield', 'region' => 'IL', 'postal' => '62704',
            'country' => 'US', 'phone_type' => 'mobile', 'phone' => '+1 555 123 4567',
        ], $overrides);
    }

    private function submit(array $params): Tiger_Model_ResponseObject
    {
        Zend_Registry::set('tiger.auth.stateless', true);   // CLI has no CSRF session; see SignupServiceTest
        $res = (new Signup_Service_Signup(['action' => 'create'] + $params))->getResponse();
        $userId = $this->db->fetchOne('SELECT user_id FROM user WHERE email = ?', [strtolower($params['email'])]);
        if ($userId) {
            $this->_users[] = $userId;
            foreach ($this->db->fetchCol('SELECT org_id FROM org_user WHERE user_id = ?', [$userId]) as $o) {
                if (!in_array($o, $this->_orgs, true)) { $this->_orgs[] = $o; }
            }
        }
        return $res;
    }

    #[Test]
    public function a_join_mode_form_adds_the_user_to_the_site_org_and_creates_no_org(): void
    {
        $store = $this->commitStoreOrg();
        Tiger_Model_Org::setSiteOrgId($store);
        (new Signup_Form_Signup(['setupOrg' => false]))->bind();
        $orgsBefore = (int) $this->db->fetchOne('SELECT COUNT(*) FROM org');

        $p   = $this->params(['company' => '']);   // a join-mode form has no company field
        $res = $this->submit($p);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $userId = $this->db->fetchOne('SELECT user_id FROM user WHERE email = ?', [strtolower($p['email'])]);
        $this->assertSame([$store], $this->db->fetchCol('SELECT org_id FROM org_user WHERE user_id = ?', [$userId]));
        $this->assertSame('user', $this->db->fetchOne('SELECT role FROM org_user WHERE user_id = ?', [$userId]));
        $this->assertSame($orgsBefore, (int) $this->db->fetchOne('SELECT COUNT(*) FROM org'), 'no org is created');
        $this->assertFalse((bool) $this->db->fetchOne('SELECT 1 FROM org_address WHERE org_id = ?', [$store]), 'the store org gets no address');
        $this->assertFalse((bool) $this->db->fetchOne('SELECT 1 FROM org_contact WHERE org_id = ?', [$store]), 'the store org gets no contact');
        $this->assertTrue((bool) $this->db->fetchOne('SELECT 1 FROM user_address WHERE user_id = ?', [$userId]), 'the address is the user\'s');
    }

    #[Test]
    public function the_default_form_still_sets_up_a_new_org(): void
    {
        $store = $this->commitStoreOrg();
        Tiger_Model_Org::setSiteOrgId($store);
        (new Signup_Form_Signup())->bind();

        $p   = $this->params();
        $res = $this->submit($p);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $userId = $this->db->fetchOne('SELECT user_id FROM user WHERE email = ?', [strtolower($p['email'])]);
        $orgId  = $this->db->fetchOne('SELECT org_id FROM org_user WHERE user_id = ?', [$userId]);
        $this->assertNotSame($store, $orgId, 'the user gets their own org');
        $this->assertSame($p['company'], $this->db->fetchOne('SELECT name FROM org WHERE org_id = ?', [$orgId]));
    }

    #[Test]
    public function a_posted_org_id_or_mode_cannot_move_the_user_into_an_org(): void
    {
        $store = $this->commitStoreOrg();
        Tiger_Model_Org::setSiteOrgId($store);
        (new Signup_Form_Signup())->bind();   // the page rendered a setup-org form

        // A tampered post tries to steer the user into the store org.
        $p   = $this->params(['org_id' => $store, 'setupOrg' => '0', 'setup_org' => '0']);
        $res = $this->submit($p);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $userId = $this->db->fetchOne('SELECT user_id FROM user WHERE email = ?', [strtolower($p['email'])]);
        $this->assertFalse((bool) $this->db->fetchOne('SELECT 1 FROM org_user WHERE user_id = ? AND org_id = ?', [$userId, $store]),
            'nothing in the request places the user in the store org');
    }

    #[Test]
    public function with_nothing_bound_a_signup_sets_up_a_new_org(): void
    {
        $store = $this->commitStoreOrg();
        Tiger_Model_Org::setSiteOrgId($store);

        $p   = $this->params(['org_id' => $store]);
        $res = $this->submit($p);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $userId = $this->db->fetchOne('SELECT user_id FROM user WHERE email = ?', [strtolower($p['email'])]);
        $this->assertFalse((bool) $this->db->fetchOne('SELECT 1 FROM org_user WHERE user_id = ? AND org_id = ?', [$userId, $store]));
    }

    #[Test]
    public function a_join_mode_form_refuses_when_its_org_is_not_active(): void
    {
        $store = $this->commitStoreOrg();
        $this->db->update('org', ['status' => 'suspended'], $this->db->quoteInto('org_id = ?', $store));
        Tiger_Model_Org::setSiteOrgId($store);
        (new Signup_Form_Signup(['setupOrg' => false]))->bind();

        $p   = $this->params(['company' => '']);
        $res = $this->submit($p);

        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('signup.error.org', json_encode($res->messages));
        $this->assertFalse((bool) $this->db->fetchOne('SELECT 1 FROM user WHERE email = ?', [strtolower($p['email'])]), 'no account is created');
    }

    #[Test]
    public function the_company_field_is_required_only_when_setting_up_an_org(): void
    {
        $this->assertTrue((new Signup_Form_Signup())->getElement('company')->isRequired());
        $this->assertFalse((new Signup_Form_Signup(['setupOrg' => false]))->getElement('company')->isRequired());
    }
}
