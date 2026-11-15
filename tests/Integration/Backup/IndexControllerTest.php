<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Backup;

use Backup_IndexController;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ModuleControllerTestCase;

/**
 * Backup_IndexController — dispatch coverage via the ControllerTestCase harness.
 *
 * (Was a characterization of a real bug: the controller redeclared the base `_json()` with an
 * incompatible signature, a PHP 8.5 fatal-at-class-load that 500'd every /backup request. Fixed by
 * renaming the helper to `_jsonBody()`; this test now exercises the actual actions.)
 *
 * TIGER-511: the screen is admin, but DOWNLOAD and RESTORE (upload) need the `Tiger_Backup_Data`
 * capability (superadmin) — checked inside the actions, since the harness (like any direct dispatch)
 * does not run the Authorization plugin — and the screen's show/hide flags come from the same ACL.
 */
final class IndexControllerTest extends ModuleControllerTestCase
{
    #[Test]
    public function the_admin_screen_renders_the_backups_list_and_settings(): void
    {
        $this->loginAs('admin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'index');
        $this->assertSame(200, $res->getHttpResponseCode(), 'the backup admin screen dispatches without error');
    }

    #[Test]
    public function the_screen_flags_hide_contents_controls_from_an_admin_and_show_them_to_a_superadmin(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Backup_IndexController::class, 'index');
        $v = $this->controller()->view;
        $this->assertFalse($v->canDownload, 'admin: no download buttons');
        $this->assertFalse($v->canRestore, 'admin: no restore controls');
        $this->assertFalse($v->canIncludeSecrets, 'admin: no secrets switch');

        $this->loginAs('superadmin');
        $this->dispatchAction(Backup_IndexController::class, 'index');
        $v = $this->controller()->view;
        $this->assertTrue($v->canDownload);
        $this->assertTrue($v->canRestore);
        $this->assertTrue($v->canIncludeSecrets);
    }

    #[Test]
    public function an_admin_is_refused_a_download(): void
    {
        $this->loginAs('admin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'download', ['id' => 'no-such-backup']);
        $this->assertSame(403, $res->getHttpResponseCode(), 'download is superadmin — refused before any lookup');
    }

    #[Test]
    public function an_admin_is_refused_an_upload_restore(): void
    {
        $this->loginAs('admin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'upload', ['confirm' => 'RESTORE'], 'POST');
        $this->assertSame(403, $res->getHttpResponseCode());
        $json = json_decode($res->getBody(), true);
        $this->assertSame(0, (int) ($json['result'] ?? -1));
        $this->assertStringNotContainsString('upload', (string) ($json['messages'][0]['message'] ?? ''), 'refused at the gate, not at the file check');
    }

    #[Test]
    public function a_superadmin_download_of_an_unknown_backup_id_is_404_not_403(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'download', ['id' => 'no-such-backup']);
        $this->assertSame(404, $res->getHttpResponseCode(), 'past the gate: no matching row → 404, never a fatal');
    }

    #[Test]
    public function a_restore_without_the_typed_confirmation_is_refused(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'upload', ['confirm' => ''], 'POST');
        $json = json_decode($res->getBody(), true);
        $this->assertSame(0, (int) ($json['result'] ?? -1), 'a restore needs the literal RESTORE confirmation');
    }

    #[Test]
    public function a_confirmed_restore_with_no_uploaded_file_reports_upload_failed(): void
    {
        $this->loginAs('superadmin');
        $res = $this->dispatchAction(Backup_IndexController::class, 'upload', ['confirm' => 'RESTORE'], 'POST');
        $json = json_decode($res->getBody(), true);
        $this->assertSame(0, (int) ($json['result'] ?? -1), 'no file → refused');
        $this->assertStringContainsString('upload', json_encode($json), 'the failure is an upload error');
    }
}
