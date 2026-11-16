<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Analytics;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Analytics_Adapter_Matomo;
use Tiger_Version;

/**
 * The Matomo adapter's outbound request: it names its client, keeps the token in the body, and never follows
 * a redirect. A request with no User-Agent is refused by common edge WAFs (the AWS managed Common Rule Set's
 * NoUserAgent_HEADER rule blocked the reporting calls in front of a real Matomo).
 */
final class MatomoCurlOptionsTest extends UnitTestCase
{
    #[Test]
    public function the_request_names_its_client(): void
    {
        $o = Tiger_Analytics_Adapter_Matomo::curlOptions(['module' => 'API']);
        $this->assertSame('Tiger_Analytics/' . Tiger_Version::VERSION, $o[CURLOPT_USERAGENT] ?? null);
    }

    #[Test]
    public function the_token_rides_in_the_post_body_and_redirects_are_not_followed(): void
    {
        $o = Tiger_Analytics_Adapter_Matomo::curlOptions(['token_auth' => 'abc123', 'module' => 'API']);
        $this->assertTrue($o[CURLOPT_POST]);
        $this->assertStringContainsString('token_auth=abc123', (string) $o[CURLOPT_POSTFIELDS]);
        $this->assertFalse($o[CURLOPT_FOLLOWLOCATION]);
    }
}
