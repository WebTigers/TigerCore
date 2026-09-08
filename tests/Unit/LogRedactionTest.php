<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Log;

/**
 * Request-URI redaction before logging (TIGER-71).
 *
 * Password reset carries its secret in the PATH (/auth/reset/cid/<id>/code/<token>), and the error
 * controller logs the raw REQUEST_URI on any 500. A reset link that errored before redemption left a
 * still-valid account-recovery credential in a log — a destination with broader access and longer
 * retention than the credential store it came from.
 */
#[CoversClass(Tiger_Log::class)]
final class LogRedactionTest extends UnitTestCase
{
    #[Test]
    public function a_reset_token_in_the_path_is_redacted(): void
    {
        $this->assertSame(
            '/auth/reset/cid/abc123/code/[redacted]',
            Tiger_Log::redactUri('/auth/reset/cid/abc123/code/deadbeefcafe')
        );
    }

    #[Test]
    public function a_credential_in_the_query_is_redacted(): void
    {
        $this->assertSame('/search?q=hello&token=[redacted]',
            Tiger_Log::redactUri('/search?q=hello&token=SECRET'));
        $this->assertSame('/x?key=[redacted]', Tiger_Log::redactUri('/x?key=abc'));
    }

    #[Test]
    public function path_and_query_secrets_are_both_handled(): void
    {
        $this->assertSame('/auth/reset/cid/a/code/[redacted]?next=/admin',
            Tiger_Log::redactUri('/auth/reset/cid/a/code/tok?next=/admin'));
    }

    #[Test]
    public function an_ordinary_url_is_left_alone(): void
    {
        // A redactor that mangles normal URIs makes logs useless, which is its own outage.
        foreach (['/blog/my-post', '/api?module=cms&service=page&method=save', '/', ''] as $u) {
            $this->assertSame($u, Tiger_Log::redactUri($u));
        }
    }

    #[Test]
    public function a_trailing_sensitive_segment_with_no_value_is_safe(): void
    {
        // /code with nothing after it must not invent a segment or crash.
        $this->assertSame('/auth/reset/code', Tiger_Log::redactUri('/auth/reset/code'));
    }
}
