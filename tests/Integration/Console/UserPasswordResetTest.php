<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_PasswordHistory;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Tiger_Policy_Password;
use Zend_Config;
use Zend_Registry;

/**
 * `tiger user:password` — the console password reset (the recovery path when email can't be
 * delivered, e.g. a fresh install with no MTA, or the only admin is locked out).
 *
 * `bin/tiger` is a procedural script, so these tests exercise the exact SEQUENCE the command
 * performs — resolve the identifier, run the live policy, then write through
 * `Tiger_Model_UserCredential::setPassword` — which is where every guarantee actually lives. The
 * point being pinned down is that the console path is NOT a shortcut around the web reset: the
 * same peppering, the same history archive, and the same reuse-prevention apply, and the old
 * password stops working. `--force` is asserted to bypass the POLICY only, never the hashing.
 */
#[CoversClass(Tiger_Model_UserCredential::class)]
final class UserPasswordResetTest extends IntegrationTestCase
{
    private Tiger_Model_User $users;
    private Tiger_Model_UserCredential $cred;
    private Tiger_Policy_Password $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users  = new Tiger_Model_User();
        $this->cred   = new Tiger_Model_UserCredential();
        $this->policy = new Tiger_Policy_Password();
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['password' => ['min_length' => 8, 'history' => 5]]], true));
    }

    /** A user with a known starting password, as the command expects to find. */
    private function makeUser(string $password): array
    {
        $tag    = bin2hex(random_bytes(6));
        $userId = $this->users->insert([
            'email'    => "cli-{$tag}@example.test",
            'username' => "cli{$tag}",
        ]);
        $this->cred->setPassword($userId, $password);
        return [$userId, "cli-{$tag}@example.test", "cli{$tag}"];
    }

    #[Test]
    public function resolves_the_target_by_email_and_by_username(): void
    {
        [$userId, $email, $username] = $this->makeUser('OriginalPass1');

        $this->assertSame($userId, (string) $this->users->findByIdentifier($email)->user_id,
            'the command accepts an email as the --user identifier');
        $this->assertSame($userId, (string) $this->users->findByIdentifier($username)->user_id,
            'and equally accepts a username');
        $this->assertNull($this->users->findByIdentifier('nobody-' . bin2hex(random_bytes(4))),
            'an unknown identifier resolves to null so the command can exit non-zero');
    }

    #[Test]
    public function the_reset_replaces_the_password_and_the_old_one_stops_working(): void
    {
        [$userId] = $this->makeUser('OriginalPass1');
        $this->assertTrue($this->cred->verifyPassword($userId, 'OriginalPass1'), 'precondition: the original password works');

        $this->cred->setPassword($userId, 'BrandNewPass9');

        $this->assertTrue($this->cred->verifyPassword($userId, 'BrandNewPass9'), 'the new password authenticates');
        $this->assertFalse($this->cred->verifyPassword($userId, 'OriginalPass1'), 'and the old one no longer does');
    }

    #[Test]
    public function only_one_password_row_survives_a_reset(): void
    {
        [$userId] = $this->makeUser('OriginalPass1');
        $this->cred->setPassword($userId, 'BrandNewPass9');

        $rows = $this->cred->fetchAll(
            $this->cred->select()->where('user_id = ?', $userId)->where('type = ?', Tiger_Model_UserCredential::TYPE_PASSWORD)
        );
        $this->assertCount(1, $rows, 'setPassword is idempotent — a reset replaces, it never accumulates rows');
    }

    #[Test]
    public function the_outgoing_password_is_archived_so_reuse_prevention_still_applies(): void
    {
        [$userId] = $this->makeUser('OriginalPass1');
        $this->cred->setPassword($userId, 'BrandNewPass9');

        $this->assertNotEmpty((new Tiger_Model_PasswordHistory())->recentForUser($userId, 5),
            'the replaced hash is archived to history');
        $this->assertContains('password.reused', $this->policy->validate('OriginalPass1', $userId),
            'so the console reset cannot be used to quietly cycle back to a retired password');
    }

    #[Test]
    public function a_policy_failing_password_is_refused_unless_forced(): void
    {
        [$userId] = $this->makeUser('OriginalPass1');

        $errors = $this->policy->validate('short', $userId);
        $this->assertContains('password.too_short', $errors, 'the command refuses a sub-min password by default');

        // --force skips the POLICY only. The write still goes through setPassword, so the value
        // is peppered and hashed exactly as always — never stored raw.
        $this->cred->setPassword($userId, 'short');
        $row = $this->cred->factor($userId, Tiger_Model_UserCredential::TYPE_PASSWORD);
        $this->assertNotSame('short', (string) $row->secret, 'a forced password is never stored in plaintext');
        $this->assertTrue($this->cred->verifyPassword($userId, 'short'), 'and it still authenticates through the normal verifier');
    }
}
