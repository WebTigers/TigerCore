<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git_Credential;

/**
 * Tiger_Git_Credential::generateDeployKey — the Ed25519 deploy-key generator for the ShellTransport
 * path. It must emit a correctly-framed OpenSSH PUBLIC key line (the one the owner pastes into GitHub)
 * and a SHA256 fingerprint, and never repeat a keypair. Pure (sodium only) — no DB, no crypto config.
 */
#[CoversClass(Tiger_Git_Credential::class)]
final class CredentialKeygenTest extends UnitTestCase
{
    #[Test]
    public function it_emits_a_well_formed_openssh_public_key(): void
    {
        $key = (new Tiger_Git_Credential())->generateDeployKey('tiger:my_module');

        $this->assertStringStartsWith('ssh-ed25519 ', $key['public_openssh']);
        $this->assertStringEndsWith(' tiger:my_module', $key['public_openssh']);
        $this->assertStringStartsWith('SHA256:', $key['fingerprint']);
        $this->assertNotSame('', $key['secret']);

        // The base64 blob between the type and the comment must decode to: string "ssh-ed25519" + a
        // 32-byte public key (the OpenSSH wire format).
        $b64  = explode(' ', $key['public_openssh'])[1];
        $blob = base64_decode($b64, true);
        $this->assertNotFalse($blob);

        $typeLen = unpack('N', substr($blob, 0, 4))[1];
        $this->assertSame(11, $typeLen);
        $this->assertSame('ssh-ed25519', substr($blob, 4, 11));
        $keyLen = unpack('N', substr($blob, 15, 4))[1];
        $this->assertSame(32, $keyLen, 'an ed25519 public key is 32 bytes');
    }

    #[Test]
    public function every_keypair_is_unique(): void
    {
        $cred = new Tiger_Git_Credential();
        $a = $cred->generateDeployKey();
        $b = $cred->generateDeployKey();
        $this->assertNotSame($a['public_openssh'], $b['public_openssh']);
        $this->assertNotSame($a['secret'], $b['secret']);
        $this->assertNotSame($a['fingerprint'], $b['fingerprint']);
    }
}
