<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Backup;

use Backup_Service_Backup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Backup;

/**
 * Backup_Service_Backup::restore — the EXECUTION arm BackupServiceTest left uncovered (its tests stop at
 * the guards: missing confirm, missing/failed backup). Here a cataloged `ok` backup points at a
 * local-disk archive that does NOT exist on disk, so `Tiger_Backup::fetchToTemp()` throws
 * ("Backup file missing on disk.") — driving restore through its try/catch and the failure envelope
 * WITHOUT performing a real (destructive) restore. This exercises the components-resolve → fetchToTemp →
 * catch → non-'ok' → `_error` path safely.
 *
 * admin-gated; the fixture backup row is a DB write (rolled back per test).
 */
#[CoversClass(Backup_Service_Backup::class)]
final class BackupServiceRestoreTest extends IntegrationTestCase
{
    private function dispatch(array $msg): object
    {
        return (new Backup_Service_Backup($msg))->getResponse();
    }

    private function messages(object $res): string
    {
        return json_encode($res->messages ?? []);
    }

    /** A cataloged, `ok`, local-disk backup whose archive file is absent on disk. */
    private function ghostBackup(): string
    {
        $model = new Tiger_Model_Backup();
        $id = $model->begin('TigerBackup-ghost.zip', 'local', ['database'], 'manual');
        // Mark it ok with a storage_key that resolves to a non-existent local file.
        $model->finish($id, 'ok', ['storage_key' => 'w7-does-not-exist/TigerBackup-ghost.zip', 'size_bytes' => 10]);
        return $id;
    }

    #[Test]
    public function restoring_a_cataloged_backup_whose_file_is_missing_fails_cleanly(): void
    {
        $this->loginAs('admin');
        $id = $this->ghostBackup();

        $res = $this->dispatch(['action' => 'restore', 'backup_id' => $id, 'confirm' => 'RESTORE']);

        $this->assertSame(0, (int) $res->result, 'a missing archive fails the restore, not crashes it');
        // Non-production surfaces the underlying reason; either way it never performed a restore.
        $this->assertMatchesRegularExpression('/Restore failed|missing/i', $this->messages($res));

        // The catalog row is untouched — restore of a broken archive is a no-op on the record.
        $row = (new Tiger_Model_Backup())->findById($id);
        $this->assertSame('ok', $row['outcome']);
    }

    #[Test]
    public function restore_still_requires_the_typed_confirmation_even_for_a_valid_row(): void
    {
        // The guard arm alongside the execution arm: a real `ok` row but the wrong confirmation word.
        $this->loginAs('admin');
        $id = $this->ghostBackup();

        $res = $this->dispatch(['action' => 'restore', 'backup_id' => $id, 'confirm' => 'yes']);
        $this->assertSame(0, (int) $res->result, 'the destructive action needs confirm === RESTORE');
        $this->assertStringContainsString('confirm', $this->messages($res));
    }
}
