<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Comment;

use Comment_Plugin_Register;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Comment;

/**
 * Comments switched on in the `config` table must actually turn the surface on.
 *
 * That tier reaches Zend_Config only after module bootstraps have run, so the registration has to read
 * the flag per request (Comment_Plugin_Register), never inside Comment_Bootstrap — where it used to,
 * and where an install that enabled comments the usual way got no subjects and no shortcodes.
 */
#[CoversClass(Comment_Plugin_Register::class)]
final class CommentRegistrationTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Comment::reset();
        Comment_Plugin_Register::reset();
    }

    protected function tearDown(): void
    {
        Tiger_Comment::reset();
        Comment_Plugin_Register::reset();
        parent::tearDown();
    }

    #[Test]
    public function the_bootstrap_does_not_decide_the_flag(): void
    {
        $src = (string) file_get_contents(TIGER_CORE_PATH . '/modules/comment/Bootstrap.php');
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);   // comments may mention it; code may not

        $this->assertStringNotContainsString('isEnabled', $code,
            'a module bootstrap runs before the config table is folded in, so it must not read the flag');
        $this->assertStringContainsString('Comment_Plugin_Register', $code);
    }

    #[Test]
    public function nothing_registers_while_comments_are_off(): void
    {
        $this->setConfig(['tiger' => ['comment' => ['enabled' => '0']]]);

        $this->assertFalse(Comment_Plugin_Register::registerAll());
        $this->assertNull(Tiger_Comment::subject('page'), 'off means no comment surface at all');
    }

    #[Test]
    public function the_surface_registers_once_the_flag_is_on(): void
    {
        $this->setConfig(['tiger' => ['comment' => ['enabled' => '1']]]);

        $this->assertTrue(Comment_Plugin_Register::registerAll());
        $this->assertNotNull(Tiger_Comment::subject('page'), 'the built-in page subject is registered');

        $this->assertTrue(Comment_Plugin_Register::registerAll(), 'a second request is a no-op');
        $this->assertCount(1, array_filter(array_keys(Tiger_Comment::subjects()), fn ($k) => $k === 'page'));
    }
}
