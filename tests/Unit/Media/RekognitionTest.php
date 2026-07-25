<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Scanner_Rekognition;

/**
 * Tiger_Media_Scanner_Rekognition — the AI-moderation scanner's SDK-absent contract.
 *
 * `aws/aws-sdk-php` is an OPTIONAL dependency and is NOT in the test vendor tree, so both entry
 * points short-circuit on the `class_exists('Aws\Rekognition\RekognitionClient')` guard BEFORE any
 * AWS call:
 *   - `scan()` returns a fail-open `error` verdict (`aws-sdk-php not installed`) — never a throw, so
 *     the orchestrator stores the file with scan_status = skipped;
 *   - `submitVideo()` returns null (nothing submitted).
 * The threshold + region carried into the client are pinned via the constructor. The live
 * detect/rejected/clean branches need the SDK + credentials — see WAVE7-FINDINGS-media.md.
 */
#[CoversClass(Tiger_Media_Scanner_Rekognition::class)]
final class RekognitionTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists('Aws\\Rekognition\\RekognitionClient')) {
            $this->markTestSkipped('aws-sdk is installed — the SDK-absent path can’t be forced.');
        }
    }

    #[Test]
    public function the_constructor_defaults_the_threshold_and_region(): void
    {
        $s = new Tiger_Media_Scanner_Rekognition();
        $this->assertSame(80.0, (new ReflectionProperty($s, '_threshold'))->getValue($s));
        $this->assertSame('us-east-1', (new ReflectionProperty($s, '_region'))->getValue($s));
    }

    #[Test]
    public function the_constructor_carries_an_explicit_threshold_and_region(): void
    {
        $s = new Tiger_Media_Scanner_Rekognition(95.5, 'eu-west-1');
        $this->assertSame(95.5, (new ReflectionProperty($s, '_threshold'))->getValue($s));
        $this->assertSame('eu-west-1', (new ReflectionProperty($s, '_region'))->getValue($s));
    }

    #[Test]
    public function scan_reports_an_error_verdict_without_the_sdk(): void
    {
        $r = (new Tiger_Media_Scanner_Rekognition())->scan('/etc/hosts', 'image/png');

        $this->assertSame('error', $r['status'], 'no SDK → fail-open error, never a throw');
        $this->assertMatchesRegularExpression('/aws-sdk-php not installed/i', (string) $r['reason']);
        $this->assertSame([], $r['meta']);
    }

    #[Test]
    public function submit_video_returns_null_without_the_sdk(): void
    {
        $r = (new Tiger_Media_Scanner_Rekognition())
            ->submitVideo('bucket', 'private/vid.mp4', 'arn:sns:topic', 'arn:iam:role');
        $this->assertNull($r);
    }
}
