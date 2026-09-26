<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Auth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Auth_Credential;
use Tiger_Auth_Credential_Adapter_Abstract;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Auth_Credential — the config-selected, provider-agnostic password-factor seam.
 *
 * The invariants that keep this safe for the ~all installs that never opt in, and correct for the one
 * that does (TigerServer's system credential): an unset/`db` provider resolves to NO adapter (the
 * default DB path is untouched); a configured adapter is consulted ONLY for the users it `appliesTo`
 * (the provider chain — a declined user falls back to DB); an unregistered name or a throwing adapter
 * fails safe to DB rather than breaking login.
 */
#[CoversClass(Tiger_Auth_Credential::class)]
final class CredentialTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        Tiger_Auth_Credential::reset();
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }
        parent::tearDown();
    }

    /** Point the config at a given provider name (blank = leave it unset). */
    private function configureProvider(string $name = ''): void
    {
        $tiger = $name === '' ? [] : ['auth' => ['credential' => ['provider' => $name]]];
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger]));
    }

    private function user()
    {
        return (object) ['user_id' => '01a00000-0000-7000-8000-000000000000', 'email' => 'owner@example.test'];
    }

    #[Test]
    public function unset_or_db_provider_uses_the_default_db_path(): void
    {
        $this->configureProvider('');   // unset
        $this->assertSame('db', Tiger_Auth_Credential::providerName());
        $this->assertNull(Tiger_Auth_Credential::providerFor($this->user()), 'unset → default DB path');

        $this->configureProvider('db');
        $this->assertNull(Tiger_Auth_Credential::providerFor($this->user()), 'explicit db → default DB path');
    }

    #[Test]
    public function db_and_blank_names_can_never_be_registered_as_adapters(): void
    {
        Tiger_Auth_Credential::register('db', FakeAlwaysAdapter::class);
        Tiger_Auth_Credential::register('', FakeAlwaysAdapter::class);
        $this->configureProvider('db');
        $this->assertNull(Tiger_Auth_Credential::providerFor($this->user()), 'db stays the built-in default');
    }

    #[Test]
    public function a_configured_adapter_owns_only_the_users_it_applies_to(): void
    {
        Tiger_Auth_Credential::register('server', FakeOwnerAdapter::class);
        $this->configureProvider('server');

        $owner = (object) ['user_id' => 'x', 'email' => 'owner@example.test'];
        $other = (object) ['user_id' => 'y', 'email' => 'invited@example.test'];

        $this->assertInstanceOf(Tiger_Auth_Credential_Adapter_Abstract::class, Tiger_Auth_Credential::providerFor($owner), 'owns the owner');
        $this->assertNull(Tiger_Auth_Credential::providerFor($other), 'declines a non-owner → falls back to DB');
    }

    #[Test]
    public function an_unregistered_provider_name_falls_back_to_db(): void
    {
        $this->configureProvider('server');   // selected but never registered
        $this->assertNull(Tiger_Auth_Credential::providerFor($this->user()));
    }

    #[Test]
    public function a_throwing_adapter_fails_safe_to_db(): void
    {
        Tiger_Auth_Credential::register('server', FakeThrowingAdapter::class);
        $this->configureProvider('server');
        $this->assertNull(Tiger_Auth_Credential::providerFor($this->user()), 'appliesTo throwing → null, never break login');
    }
}

/** Applies to everyone. */
class FakeAlwaysAdapter extends Tiger_Auth_Credential_Adapter_Abstract
{
    public function appliesTo($user): bool { return true; }
    public function verify($user, string $password): bool { return $password === 'right'; }
}

/** Applies only to the account owner (by email here, standing in for "is the system user"). */
class FakeOwnerAdapter extends Tiger_Auth_Credential_Adapter_Abstract
{
    public function appliesTo($user): bool { return isset($user->email) && $user->email === 'owner@example.test'; }
    public function verify($user, string $password): bool { return true; }
}

/** Throws in appliesTo — the seam must swallow it and fall back to DB. */
class FakeThrowingAdapter extends Tiger_Auth_Credential_Adapter_Abstract
{
    public function appliesTo($user): bool { throw new \RuntimeException('boom'); }
    public function verify($user, string $password): bool { return false; }
}
