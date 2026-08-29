<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Comment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_Stars;
use Zend_View;

/**
 * Tiger_View_Helper_Stars — the visual contract for a rating.
 *
 * The accessibility assertions are the ones that matter: a row of icons conveys nothing to a screen
 * reader, so the label and the numeric text are part of the contract, not decoration.
 */
#[CoversClass(Tiger_View_Helper_Stars::class)]
final class StarsHelperTest extends UnitTestCase
{
    private function helper(): Tiger_View_Helper_Stars
    {
        $helper = new Tiger_View_Helper_Stars();
        $helper->setView(new Zend_View());
        return $helper;
    }

    #[Test]
    public function aWholeAverageRendersFiveSolidStates(): void
    {
        $html = $this->helper()->stars(3.0);

        $this->assertSame(3, substr_count($html, 'fa-solid fa-star"'), 'three filled');
        $this->assertSame(2, substr_count($html, 'fa-regular fa-star'), 'two empty');
        $this->assertStringNotContainsString('fa-star-half-stroke', $html);
    }

    #[Test]
    public function aHalfAverageRendersAHalfStar(): void
    {
        $html = $this->helper()->stars(4.3);   // snaps to 4.5

        $this->assertSame(4, substr_count($html, 'fa-solid fa-star"'));
        $this->assertStringContainsString('fa-star-half-stroke', $html);
    }

    #[Test]
    public function theRowIsLabelledForAssistiveTech(): void
    {
        $html = $this->helper()->stars(4.5);

        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('aria-label="4.5 out of 5 stars"', $html);
    }

    #[Test]
    public function theCountIsIncludedInTheLabelWhenGiven(): void
    {
        $html = $this->helper()->stars(4.0, ['count' => 12]);

        $this->assertStringContainsString('from 12 ratings', $html);
        $this->assertStringContainsString('(12)', $html);
    }

    /** "4", not "4.0" — a trailing zero reads like false precision. */
    #[Test]
    public function aWholeNumberDropsItsTrailingZero(): void
    {
        $this->assertStringContainsString('>4</span>', $this->helper()->stars(4.0));
        $this->assertStringContainsString('>4.5</span>', $this->helper()->stars(4.5));
    }

    #[Test]
    public function theNumericValueCanBeSuppressed(): void
    {
        $html = $this->helper()->stars(4.0, ['show_value' => false]);

        $this->assertStringNotContainsString('tiger-stars-value', $html);
        $this->assertStringContainsString('aria-label', $html, 'the label survives — it is not decoration');
    }

    #[Test]
    public function extraClassesAndSizeAreApplied(): void
    {
        $html = $this->helper()->stars(2.0, ['size' => 'sm', 'class' => 'mb-2']);

        $this->assertStringContainsString('small', $html);
        $this->assertStringContainsString('mb-2', $html);
    }

    #[Test]
    public function aZeroAverageStillRendersFiveEmptyStars(): void
    {
        $html = $this->helper()->stars(0);

        $this->assertSame(5, substr_count($html, 'fa-regular fa-star'));
    }
}
