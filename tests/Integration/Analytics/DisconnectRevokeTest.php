<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Analytics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto;
use Tiger_Google_Analytics;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Registry;

/**
 * Disconnect REVOKES at Google, then forgets (TIGER-117).
 *
 * The old disconnect() only cleared the local token, so the grant stayed live in the user's Google
 * account — while the published privacy policy said disconnecting revoked it. Google's verification
 * review requires the policy to match practice. The revoke endpoint is a fixed Google host, so these
 * tests point the seam at a dead local port: the fail-soft path is the one that can be exercised
 * without network, and it is the one that matters — Google unreachable must still clear the token.
 */
#[CoversClass(Tiger_Google_Analytics::class)]
final class DisconnectRevokeTest extends IntegrationTestCase
{
    private function connect(): void
    {
        $key = base64_encode(str_repeat("\x22", 32));
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['crypto' => ['key' => $key]]]));
        $enc = Tiger_Crypto::encrypt('refresh-token-to-revoke');
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.analytics.oauth.refresh_token_enc', $enc);
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => [
            'crypto'    => ['key' => $key],
            'analytics' => ['oauth' => ['mode' => 'broker', 'refresh_token_enc' => $enc]],
        ]]));
    }

    private function storedToken(): string
    {
        return (string) $this->db->fetchOne("SELECT config_value FROM config WHERE config_key = 'tiger.analytics.oauth.refresh_token_enc'");
    }

    /** Google unreachable: the local token is STILL cleared, and the caller is told it was not revoked. */
    #[Test]
    public function when_google_cannot_be_reached_the_token_is_still_cleared_and_the_outcome_says_so(): void
    {
        $this->connect();
        $this->assertNotSame('', $this->storedToken(), 'precondition: a token is stored');

        $out = RevokeProbe::disconnect();

        $this->assertFalse($out['revoked'], 'Google was unreachable, so revocation cannot be claimed');
        $this->assertSame('', $this->storedToken(), 'but the local token is gone regardless — disconnect must never be impossible');
        $this->assertFalse(Tiger_Google_Analytics::isConnected());
    }

    /** With no token stored there is nothing to revoke, and no request is made. */
    #[Test]
    public function no_token_means_no_revoke_call(): void
    {
        $key = base64_encode(str_repeat("\x22", 32));
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['crypto' => ['key' => $key], 'analytics' => ['oauth' => ['refresh_token_enc' => '']]]]));
        $server = $this->startRevokeServer();
        if ($server === null) { $this->markTestSkipped('could not start a local PHP server'); }
        [$proc, $url] = $server;
        try {
            RevokeProbe::$url = $url;
            $out = RevokeProbe::disconnect();
            $this->assertFalse($out['revoked'], 'nothing was revoked because nothing was stored');
            $this->assertFalse(file_exists($this->revokeLog), 'and no request reached the server');
        } finally {
            RevokeProbe::$url = null; proc_terminate($proc); proc_close($proc); @unlink($this->revokeLog);
        }
    }

    /**
     * When Google answers 2xx, the outcome says revoked — proven against a REAL HTTP 200, from a
     * throwaway local server, through the parent's own _http(). A probe that simply returned
     * ['revoked' => true] would test the probe.
     */
    #[Test]
    public function a_successful_revoke_is_reported(): void
    {
        $server = $this->startRevokeServer();
        if ($server === null) { $this->markTestSkipped('could not start a local PHP server'); }
        [$proc, $url] = $server;
        try {
            $this->connect();
            RevokeProbe::$url = $url;
            $out = RevokeProbe::disconnect();

            $this->assertTrue($out['revoked'], 'Google (our stand-in) answered 200');
            $this->assertSame('', $this->storedToken(), 'and the local token is cleared');
            $this->assertStringContainsString('token=refresh-token-to-revoke', (string) @file_get_contents($this->revokeLog),
                'the server received the refresh token in the POST body');
        } finally {
            RevokeProbe::$url = null;
            proc_terminate($proc); proc_close($proc);
            @unlink($this->revokeLog);
        }
    }

    private string $revokeLog = '';

    /** `php -S` on a free port with a router that logs the body and answers 200. @return array{resource,string}|null */
    private function startRevokeServer(): ?array
    {
        $port = 18000 + random_int(0, 999);
        $this->revokeLog = sys_get_temp_dir() . '/tiger-revoke-' . getmypid() . '.log';
        $router = sys_get_temp_dir() . '/tiger-revoke-router-' . getmypid() . '.php';
        file_put_contents($router, '<?php file_put_contents(' . var_export($this->revokeLog, true) . ', file_get_contents("php://input")); http_response_code(200); echo "{}";');
        $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $router], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($proc)) { return null; }
        for ($i = 0; $i < 50; $i++) {                       // wait up to ~2.5s for it to listen
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.05);
            if ($fp) { fclose($fp); return [$proc, "http://127.0.0.1:$port/revoke"]; }
            usleep(50000);
        }
        proc_terminate($proc); proc_close($proc);
        return null;
    }
}

/** Overrides ONLY the revoke URL — dead port by default, or a live local server when a test sets one. */
final class RevokeProbe extends Tiger_Google_Analytics
{
    public static ?string $url = null;

    protected static function _revokeUrl()
    {
        return self::$url ?? 'http://127.0.0.1:1/revoke';
    }
}
