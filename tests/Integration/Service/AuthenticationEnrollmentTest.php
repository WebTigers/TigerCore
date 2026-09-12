<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Auth_Totp;
use Tiger_Model_AuthChallenge;
use Tiger_Model_Org;
use Tiger_Model_OrgUser;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Tiger_Service_Authentication;
use Tiger_Uuid;
use Zend_Auth;
use Zend_Auth_Storage_Session;
use Zend_Config;
use Zend_Registry;
use Zend_Session;

/**
 * Tiger_Service_Authentication — the branches Wave 3's AuthenticationTest left uncovered: the self-service
 * TOTP ENROLLMENT wizard (begin → activate → disable), the emailed screen-unlock code path
 * (requestUnlockCode / unlockWithCode), live-role refresh after an unlock, the STATELESS token identity
 * (identityFromToken), and the auto-logout poller surface (autologoutConfig / sessionStatus). Same
 * real-DB + real-session harness as the sibling suite; drives the genuine _enrollNs/_lockNs/_activityNs
 * session paths rather than stubbing them.
 */
#[CoversClass(Tiger_Service_Authentication::class)]
final class AuthenticationEnrollmentTest extends IntegrationTestCase
{
    private const KEY    = 'ERERERERERERERERERERERERERERERERERERERERERE='; // 32 × 0x11, base64
    private const PEPPER = 'cGVwcGVyLUEtMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA='; // arbitrary valid base64

    private Tiger_Service_Authentication $auth;
    private bool $priorUnitTestMode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->priorUnitTestMode = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        Zend_Auth::getInstance()->setStorage(new Zend_Auth_Storage_Session());
        Zend_Auth::getInstance()->clearIdentity();

        $this->useCrypto();
        $this->auth = new Tiger_Service_Authentication();
    }

    protected function tearDown(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $_SESSION = [];
        Zend_Session::$_unitTestEnabled = $this->priorUnitTestMode;
        parent::tearDown();
    }

    /** Seed the crypto key + pepper (and optionally extra tiger.* config) into the registry. */
    private function useCrypto(array $extraTiger = []): void
    {
        $tiger = array_replace_recursive([
            'crypto'   => ['key' => self::KEY],
            'security' => ['pepper' => self::PEPPER],
        ], $extraTiger);
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger], true));
    }

    private function email(): string
    {
        return 'enroll-' . bin2hex(random_bytes(8)) . '@example.test';
    }

    /** Create an ACTIVE user with a password credential; returns [user_id, email]. */
    private function makeUserWithPassword(string $password): array
    {
        $email = $this->email();
        $uid   = (new Tiger_Model_User())->insert(['email' => $email, 'status' => 'active']);
        (new Tiger_Model_UserCredential())->setPassword($uid, $password);
        return [$uid, $email];
    }

    // ----- TOTP enrollment wizard --------------------------------------------

    #[Test]
    public function begin_totp_enrollment_returns_a_secret_and_otpauth_uri_and_stashes_the_pending_factor(): void
    {
        [, $email] = $this->makeUserWithPassword('enroll me pw 123');
        $this->auth->login($email, 'enroll me pw 123');

        $enroll = $this->auth->beginTotpEnrollment();

        $this->assertIsArray($enroll);
        $this->assertNotSame('', (string) $enroll['secret'], 'a fresh TOTP secret is minted');
        $this->assertStringStartsWith('otpauth://totp/', $enroll['otpauth'], 'the enrollment carries a scannable otpauth URI');
        $this->assertCount(Tiger_Service_Authentication::RECOVERY_COUNT, $enroll['recovery'], 'a full set of recovery codes is offered');
        // The factor is NOT live yet — nothing persisted until a code confirms it.
        $this->assertFalse($this->auth->getTwoFactorStatus()['enabled'], 'begin does not enable 2FA on its own');
    }

    #[Test]
    public function begin_totp_enrollment_is_null_for_a_guest(): void
    {
        $this->assertNull($this->auth->beginTotpEnrollment(), 'no identity => nothing to enroll');
    }

    #[Test]
    public function activate_totp_confirms_the_pending_secret_with_a_live_code_and_goes_live(): void
    {
        [, $email] = $this->makeUserWithPassword('activate pw 1234');
        $this->auth->login($email, 'activate pw 1234');
        $enroll = $this->auth->beginTotpEnrollment();

        $liveCode = Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30));
        $this->assertTrue($this->auth->activateTotp($liveCode), 'a live code confirms the enrollment');
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled'], 'the factor is now live');
    }

    // ---- replacing an existing factor must clear the same bar as removing it (TIGER-67) ----
    //
    // activateTotp() -> replaceTotp() PURGES the current authenticator and every recovery code before
    // writing the new one. disableTotp() rightly demands the current code first; enrollment demanded
    // nothing, so replacement was a way around the protection removal enforces — an unlocked session
    // could swap the owner's authenticator for its own and burn the recovery codes on the way.

    /** Sign in a fresh user and get TOTP live for them. Returns [email, secret]. */
    private function withTotpEnabled(string $pw): array
    {
        [, $email] = $this->makeUserWithPassword($pw);
        $this->auth->login($email, $pw);
        $enroll = $this->auth->beginTotpEnrollment();
        $this->auth->activateTotp(Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30)));
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled'], 'precondition: 2FA is on');
        return [$email, (string) $enroll['secret']];
    }

    #[Test]
    public function re_enrolling_without_the_current_code_is_refused(): void
    {
        [, $secret] = $this->withTotpEnabled('replace me 0001');

        $this->assertNull($this->auth->beginTotpEnrollment(), 'no current code → no new secret issued');
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled'], 'the existing factor survives');

        // And the ORIGINAL authenticator still works — nothing was purged.
        $this->assertTrue($this->auth->getTwoFactorStatus()['recovery'] > 0, 'recovery codes survive too');
        $this->assertNotNull(Tiger_Auth_Totp::codeAt($secret, intdiv(time(), 30)));
    }

    #[Test]
    public function re_enrolling_with_a_wrong_current_code_is_refused(): void
    {
        $this->withTotpEnabled('replace me 0002');
        $this->assertNull($this->auth->beginTotpEnrollment('000000'));
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled']);
    }

    #[Test]
    public function re_enrolling_with_the_current_code_is_allowed(): void
    {
        // The positive control: a legitimate replacement must still work, or the fix has just broken
        // rotating your authenticator.
        [, $secret] = $this->withTotpEnabled('replace me 0003');

        $current = Tiger_Auth_Totp::codeAt($secret, intdiv(time(), 30));
        $enroll  = $this->auth->beginTotpEnrollment($current);
        $this->assertIsArray($enroll, 'a valid current code authorizes the replacement');
        $this->assertNotSame($secret, $enroll['secret'], 'and a NEW secret is issued');

        $this->assertTrue($this->auth->activateTotp(Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30))));
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled']);
    }

    #[Test]
    public function first_time_enrollment_still_needs_no_code(): void
    {
        // The other positive control: this guard must only bite when a factor already exists.
        [, $email] = $this->makeUserWithPassword('first time 0004');
        $this->auth->login($email, 'first time 0004');
        $this->assertIsArray($this->auth->beginTotpEnrollment(), 'nothing to replace → nothing to prove');
    }

    #[Test]
    public function activate_totp_rejects_a_wrong_code_and_stays_disabled(): void
    {
        [, $email] = $this->makeUserWithPassword('activate bad 123');
        $this->auth->login($email, 'activate bad 123');
        $this->auth->beginTotpEnrollment();

        $this->assertFalse($this->auth->activateTotp('000000'), 'a wrong code does not activate');
        $this->assertFalse($this->auth->getTwoFactorStatus()['enabled'], '2FA stays off');
    }

    #[Test]
    public function activate_totp_without_a_pending_enrollment_is_false(): void
    {
        [, $email] = $this->makeUserWithPassword('no pending pw 1');
        $this->auth->login($email, 'no pending pw 1');
        // No beginTotpEnrollment() → no pending secret in the session.
        $this->assertFalse($this->auth->activateTotp('123456'), 'nothing pending => false');
    }

    #[Test]
    public function disable_totp_requires_a_live_code_and_then_removes_the_factor(): void
    {
        [, $email] = $this->makeUserWithPassword('disable pw 1234');
        $this->auth->login($email, 'disable pw 1234');
        $enroll = $this->auth->beginTotpEnrollment();
        $this->auth->activateTotp(Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30)));
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled']);

        $this->assertFalse($this->auth->disableTotp('000000'), 'a wrong code cannot strip the second factor');
        $this->assertTrue($this->auth->getTwoFactorStatus()['enabled'], 'still enabled after a bad attempt');

        $live = Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30));
        $this->assertTrue($this->auth->disableTotp($live), 'a live code disables it');
        $this->assertFalse($this->auth->getTwoFactorStatus()['enabled'], '2FA is off again');
    }

    #[Test]
    public function disable_totp_also_accepts_a_recovery_code(): void
    {
        [, $email] = $this->makeUserWithPassword('recovery off pw1');
        $this->auth->login($email, 'recovery off pw1');
        $enroll = $this->auth->beginTotpEnrollment();
        $this->auth->activateTotp(Tiger_Auth_Totp::codeAt($enroll['secret'], intdiv(time(), 30)));

        // A recovery code (as the UI shows it, with a separator) disables the factor.
        $this->assertTrue($this->auth->disableTotp($enroll['recovery'][0]), 'a recovery code disables 2FA');
        $this->assertFalse($this->auth->getTwoFactorStatus()['enabled']);
    }

    #[Test]
    public function disable_totp_is_false_for_a_guest(): void
    {
        $this->assertFalse($this->auth->disableTotp('123456'));
    }

    // ----- stateless token identity ------------------------------------------

    #[Test]
    public function identity_from_token_resolves_the_owning_user_in_their_primary_org(): void
    {
        $email = $this->email();
        $uid   = (new Tiger_Model_User())->insert(['email' => $email, 'status' => 'active']);
        $orgId = (new Tiger_Model_Org())->insert(['name' => 'Tok Org', 'slug' => 'org-' . bin2hex(random_bytes(6))]);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'admin', 'status' => 'active']);
        $token = (new Tiger_Model_UserCredential())->createToken($uid)['token'];

        $identity = $this->auth->identityFromToken($token);

        $this->assertIsObject($identity, 'a valid token resolves an identity');
        $this->assertSame($uid, $identity->user_id);
        $this->assertSame($orgId, $identity->org_id, 'the primary active org is resolved onto the token identity');
        $this->assertSame('admin', $identity->role);
        // Stateless: nothing was written to the session.
        $this->assertFalse($this->auth->isAuthenticated(), 'token resolution never establishes a session');
    }

    /**
     * The identity says WHICH token authenticated it (TIGER-102).
     *
     * Without this, any limit attributed to a token rather than a user is unenforceable — a scoped
     * MCP token handed to an agent could spend an organisation's whole budget, because by the time a
     * service ran, nothing recorded which key was presented.
     */
    #[Test]
    public function a_token_identity_names_the_credential_it_authenticated_with(): void
    {
        $uid   = (new Tiger_Model_User())->insert(['email' => $this->email(), 'status' => 'active']);
        $orgId = (new Tiger_Model_Org())->insert(['name' => 'Attr Org', 'slug' => 'org-' . bin2hex(random_bytes(6))]);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'admin', 'status' => 'active']);
        $minted = (new Tiger_Model_UserCredential())->createToken($uid);

        $identity = $this->auth->identityFromToken($minted['token']);

        $this->assertSame($minted['credential_id'], $identity->credential_id,
            'a per-token limit needs to know which token this is');
        $this->assertSame($minted['prefix'], $identity->credential_prefix,
            'the handle /mcp/admin shows, so a human can match it to a key they issued');

        // The secret must never ride on an identity — it may be logged, cached or returned.
        foreach (get_object_vars($identity) as $field) {
            $this->assertStringNotContainsString($minted['token'], (string) $field,
                'no property of an identity may contain the token itself');
        }
    }

    /**
     * A session identity sets the fields to NULL rather than omitting them.
     *
     * "Was this a token?" must be answerable by reading a property, not inferred from its absence —
     * absence-as-signal is what quietly becomes wrong the day a third auth path appears.
     */
    #[Test]
    public function a_session_identity_has_the_credential_fields_present_and_null(): void
    {
        $email = $this->email();
        $uid   = (new Tiger_Model_User())->insert(['email' => $email, 'status' => 'active']);
        (new Tiger_Model_UserCredential())->setPassword($uid, 'correct-horse-battery');
        $orgId = (new Tiger_Model_Org())->insert(['name' => 'Sess Org', 'slug' => 'org-' . bin2hex(random_bytes(6))]);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'admin', 'status' => 'active']);

        $this->auth->login($email, 'correct-horse-battery');
        $identity = Zend_Auth::getInstance()->getIdentity();

        $vars = get_object_vars($identity);
        $this->assertArrayHasKey('credential_id', $vars, 'present, so it can be read rather than inferred');
        $this->assertArrayHasKey('credential_prefix', $vars);
        $this->assertNull($identity->credential_id, 'a session is not a token');
        $this->assertNull($identity->credential_prefix);
    }

    #[Test]
    public function a_token_stops_working_once_the_account_is_suspended(): void
    {
        // A token is a credential FOR AN ACCOUNT. Suspending the account previously left the token
        // fully authorized, because identityFromToken() used findById() — which excludes soft-deleted
        // users but happily returns suspended ones. Password login checked status; nothing else did.
        $uid   = (new Tiger_Model_User())->insert(['email' => 'tok-susp@authz.test', 'status' => 'active']);
        $orgId = (new Tiger_Model_Org())->insert(['name' => 'Tok Susp', 'slug' => 'tok-susp']);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'admin', 'status' => 'active']);
        $token = (new Tiger_Model_UserCredential())->createToken($uid)['token'];

        $this->assertIsObject($this->auth->identityFromToken($token), 'valid while the account is active');

        (new Tiger_Model_User())->update(['status' => 'suspended'], ['user_id = ?' => $uid]);

        $this->assertNull($this->auth->identityFromToken($token),
            'a suspended account must not keep authorizing through a still-valid token');
    }

    #[Test]
    public function identity_from_token_is_null_for_a_bogus_token(): void
    {
        $this->assertNull($this->auth->identityFromToken('tgr_deadbeefdead_' . str_repeat('a', 48)), 'unknown token => null');
        $this->assertNull($this->auth->identityFromToken('not-even-a-token'), 'malformed token => null');
    }

    // ----- live-role refresh -------------------------------------------------

    #[Test]
    public function refresh_role_re_resolves_the_live_membership_role(): void
    {
        $email = $this->email();
        $uid   = (new Tiger_Model_User())->insert(['email' => $email, 'status' => 'active']);
        (new Tiger_Model_UserCredential())->setPassword($uid, 'refresh role pw1');
        $orgId = (new Tiger_Model_Org())->insert(['name' => 'RR Org', 'slug' => 'org-' . bin2hex(random_bytes(6))]);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'manager', 'status' => 'active']);

        $this->auth->login($email, 'refresh role pw1');
        // Simulate the while-locked guest downgrade the authorization plugin applies.
        $this->auth->getIdentity()->role = 'guest';

        $this->auth->refreshRole();
        $this->assertSame('manager', $this->auth->getIdentity()->role, 'the live org role is restored');
    }

    #[Test]
    public function refresh_role_is_a_noop_for_a_guest(): void
    {
        $this->auth->refreshRole();   // no identity — must not throw
        $this->assertFalse($this->auth->isAuthenticated());
    }

    // ----- emailed screen-unlock code ----------------------------------------

    #[Test]
    public function request_unlock_code_issues_an_email_login_challenge_for_the_locked_identity(): void
    {
        [$uid, $email] = $this->makeUserWithPassword('unlock code pw12');
        $this->auth->login($email, 'unlock code pw12');
        $this->auth->lock();

        $this->auth->requestUnlockCode();

        $this->assertGreaterThanOrEqual(
            1,
            (new Tiger_Model_AuthChallenge())->countRecent($uid, 'email_login', 3600),
            'a fresh unlock code is issued to the locked user'
        );
    }

    #[Test]
    public function unlock_with_code_clears_the_lock_on_a_valid_code_and_rejects_a_wrong_one(): void
    {
        [$uid, $email] = $this->makeUserWithPassword('unlock with code1');
        $this->auth->login($email, 'unlock with code1');
        $this->auth->lock();
        $this->assertTrue($this->auth->isLocked());

        // Seed a known email_login challenge (requestLoginCode's code is random + hashed).
        (new Tiger_Model_AuthChallenge())->issue($uid, 'email_login', '778899', 600);

        $this->assertFalse($this->auth->unlockWithCode('000000'), 'a wrong code does not unlock');
        $this->assertTrue($this->auth->isLocked(), 'still locked after a bad code');

        $this->assertTrue($this->auth->unlockWithCode('778899'), 'the right code unlocks');
        $this->assertFalse($this->auth->isLocked(), 'the screen is unlocked without a fresh login');
        $this->assertTrue($this->auth->isAuthenticated(), 'identity was never dropped — a lock is not a logout');
    }

    #[Test]
    public function unlock_with_code_is_false_for_a_guest(): void
    {
        $this->assertFalse($this->auth->unlockWithCode('123456'));
    }

    // ----- auto-logout config + session poller -------------------------------

    #[Test]
    public function autologout_config_defaults_when_nothing_is_configured(): void
    {
        $cfg = $this->auth->autologoutConfig();
        $this->assertFalse($cfg['enabled'], 'off by default');
        $this->assertSame(900, $cfg['seconds']);
        $this->assertSame('logout', $cfg['action']);
        $this->assertSame(60, $cfg['warn']);
    }

    #[Test]
    public function autologout_config_reads_the_live_config_cascade(): void
    {
        $this->useCrypto(['session' => ['autologout' => [
            'enabled' => 1, 'seconds' => 45, 'action' => 'lock', 'warn' => 15,
        ]]]);

        $cfg = $this->auth->autologoutConfig();
        $this->assertTrue($cfg['enabled']);
        $this->assertSame(45, $cfg['seconds']);
        $this->assertSame('lock', $cfg['action']);
        $this->assertSame(15, $cfg['warn']);
    }

    #[Test]
    public function autologout_config_floors_seconds_at_thirty(): void
    {
        $this->useCrypto(['session' => ['autologout' => ['enabled' => 1, 'seconds' => 5]]]);
        $this->assertSame(30, $this->auth->autologoutConfig()['seconds'], 'seconds is floored to a sane minimum');
    }

    #[Test]
    public function session_status_reports_unauthenticated_for_a_guest(): void
    {
        $s = $this->auth->sessionStatus();
        $this->assertFalse($s['authenticated']);
        $this->assertSame(0, $s['remaining']);
    }

    #[Test]
    public function session_status_reports_time_left_and_only_resets_the_clock_on_real_activity(): void
    {
        $this->useCrypto(['session' => ['autologout' => ['enabled' => 1, 'seconds' => 600]]]);
        [, $email] = $this->makeUserWithPassword('session poll pw12');
        $this->auth->login($email, 'session poll pw12');

        $first = $this->auth->sessionStatus(false);   // first sighting starts the clock
        $this->assertTrue($first['authenticated']);
        $this->assertTrue($first['enabled']);
        $this->assertLessThanOrEqual(600, $first['remaining']);
        $this->assertGreaterThan(0, $first['remaining']);

        // An active poll resets the inactivity clock to full.
        $active = $this->auth->sessionStatus(true);
        $this->assertSame(600, $active['remaining'], 'genuine interaction resets the clock to the full window');
    }
}
