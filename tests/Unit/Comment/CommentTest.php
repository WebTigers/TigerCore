<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Comment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Comment;

/**
 * Tiger_Comment — the subject registry and policy gate.
 *
 * The registry is what lets a thread hang off anything without core learning what a "shop product"
 * is; these assert the parts that keep that safe — an unregistered type is never trusted, a
 * resolver that throws degrades to "gone" rather than exploding a moderation queue, and an
 * entitlement check that errors can never mint a verified badge.
 */
#[CoversClass(Tiger_Comment::class)]
final class CommentTest extends UnitTestCase
{
    protected function setUp(): void { parent::setUp(); Tiger_Comment::reset(); }
    protected function tearDown(): void { Tiger_Comment::reset(); parent::tearDown(); }

    private function provider(array $over = []): array
    {
        return $over + [
            'key'     => 'shop.product',
            'label'   => 'Product',
            'resolve' => static fn ($id) => ['title' => 'Blue Widget', 'url' => '/shop/blue', 'exists' => true],
            'ratings' => true,
        ];
    }

    #[Test]
    public function registersAndResolvesASubject(): void
    {
        Tiger_Comment::registerSubject($this->provider());

        $s = Tiger_Comment::subject('shop.product');
        $this->assertSame('Product', $s['label']);
        $this->assertTrue($s['ratings']);
        $this->assertSame(1, $s['threading'], 'threading defaults to one reply level');
    }

    #[Test]
    public function aProviderWithoutAKeyOrLabelIsRefused(): void
    {
        Tiger_Comment::registerSubject(['label' => 'No key']);
        Tiger_Comment::registerSubject(['key' => 'no.label']);

        $this->assertSame([], Tiger_Comment::subjects());
    }

    #[Test]
    public function anUnregisteredTypeIsNeverTrusted(): void
    {
        $this->assertNull(Tiger_Comment::subject('made.up'));
        $this->assertFalse(Tiger_Comment::acceptsRatings('made.up'));
        $this->assertSame(
            ['title' => '', 'url' => '', 'exists' => false],
            Tiger_Comment::resolve('made.up', '1')
        );
    }

    #[Test]
    public function ratingsArePerSubjectNotGlobal(): void
    {
        Tiger_Comment::registerSubject($this->provider());
        Tiger_Comment::registerSubject($this->provider(['key' => 'page', 'ratings' => false]));

        $this->assertTrue(Tiger_Comment::acceptsRatings('shop.product'));
        $this->assertFalse(Tiger_Comment::acceptsRatings('page'), 'a CMS page takes discussion, not stars');
    }

    #[Test]
    public function resolveReturnsTheProvidersAnswer(): void
    {
        Tiger_Comment::registerSubject($this->provider());

        $this->assertSame(
            ['title' => 'Blue Widget', 'url' => '/shop/blue', 'exists' => true],
            Tiger_Comment::resolve('shop.product', 'abc')
        );
    }

    /** A moderation queue must still render the row whose subject blew up — that's when you need it. */
    #[Test]
    public function aThrowingResolverDegradesToGone(): void
    {
        Tiger_Comment::registerSubject($this->provider([
            'resolve' => static function () { throw new RuntimeException('db down'); },
        ]));

        $this->assertFalse(Tiger_Comment::resolve('shop.product', 'abc')['exists']);
    }

    #[Test]
    public function verifiedReviewerConsultsTheEntitlementGate(): void
    {
        Tiger_Comment::registerSubject($this->provider([
            'may_review' => static fn ($id, $userId) => $userId === 'buyer',
        ]));

        $this->assertTrue(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', 'buyer'));
        $this->assertFalse(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', 'stranger'));
    }

    #[Test]
    public function aGuestIsNeverVerified(): void
    {
        Tiger_Comment::registerSubject($this->provider(['may_review' => static fn () => true]));

        $this->assertFalse(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', null));
        $this->assertFalse(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', ''));
    }

    /** A failing entitlement check must not mint a trust badge. */
    #[Test]
    public function aThrowingEntitlementGateIsNotVerified(): void
    {
        Tiger_Comment::registerSubject($this->provider([
            'may_review' => static function () { throw new RuntimeException('authority down'); },
        ]));

        $this->assertFalse(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', 'buyer'));
    }

    #[Test]
    public function aSubjectWithNoEntitlementGateVerifiesNobody(): void
    {
        Tiger_Comment::registerSubject($this->provider());   // no may_review — a blog post has none

        $this->assertFalse(Tiger_Comment::isVerifiedReviewer('shop.product', 'x', 'buyer'));
    }

    #[Test]
    public function ownershipGatesSelfReview(): void
    {
        Tiger_Comment::registerSubject($this->provider([
            'owns' => static fn ($id, $userId) => $userId === 'vendor',
        ]));

        $this->assertTrue(Tiger_Comment::ownsSubject('shop.product', 'x', 'vendor'));
        $this->assertFalse(Tiger_Comment::ownsSubject('shop.product', 'x', 'shopper'));
    }

    #[Test]
    public function aLaterRegistrationOfTheSameKeyWins(): void
    {
        Tiger_Comment::registerSubject($this->provider(['label' => 'First']));
        Tiger_Comment::registerSubject($this->provider(['label' => 'Second']));

        $this->assertSame('Second', Tiger_Comment::subject('shop.product')['label']);
        $this->assertCount(1, Tiger_Comment::subjects());
    }

    /** Halves come from AVERAGING, never from a half-star picker (COMMENTS.md §4). */
    #[Test]
    public function halfStarSnapsToTheNearestHalf(): void
    {
        $this->assertSame(4.5, Tiger_Comment::halfStar(4.3));
        $this->assertSame(4.0, Tiger_Comment::halfStar(4.2));
        $this->assertSame(5.0, Tiger_Comment::halfStar(4.9));
        $this->assertSame(0.0, Tiger_Comment::halfStar(0));
        $this->assertSame(3.5, Tiger_Comment::halfStar(3.5));
    }

    #[Test]
    public function halfStarClampsOutOfRangeInput(): void
    {
        $this->assertSame(5.0, Tiger_Comment::halfStar(9.9));
        $this->assertSame(0.0, Tiger_Comment::halfStar(-2));
    }
}
