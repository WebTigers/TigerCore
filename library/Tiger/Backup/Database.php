<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Backup_Database — a portable, shell-free SQL dump + restore over the app's DB adapter.
 *
 * No `mysqldump`, no shell — it reads schema (`SHOW CREATE TABLE`) and rows through the live Zend_Db
 * adapter and emits standard SQL, so it works on locked-down cPanel/shared hosting exactly where a
 * shell tool wouldn't. The dump stays plain-`mysql`-import compatible (statement boundaries are
 * carried on `--` comment lines, which mysql ignores) while our own restore splits on a per-dump
 * random token — collision-proof against anything a row's data could contain.
 *
 * @api
 */
class Tiger_Backup_Database
{
    /** Rows per INSERT statement (bounds statement size / memory). */
    const CHUNK = 200;

    /**
     * Dump the whole database to a .sql file.
     *
     * @param  string $path destination file
     * @return array  ['tables' => int, 'rows' => int, 'token' => string]
     * @throws RuntimeException on a write failure
     */
    public static function dump($path)
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db) { throw new RuntimeException('Tiger_Backup_Database: no DB adapter.'); }

        $token = bin2hex(random_bytes(6));
        $sep   = "\n-- @" . $token . "@\n";

        $fh = @fopen($path, 'wb');
        if (!$fh) { throw new RuntimeException('Tiger_Backup_Database: cannot write ' . $path); }

        // A CONSISTENT SNAPSHOT for the whole dump. Without it this walks a live database across
        // hundreds of independent queries: concurrent writes can put a parent and its child, or an
        // order and its payment, in the archive from different moments, and rows inserted or deleted
        // mid-pagination are duplicated or skipped outright. The archive still says "ok".
        //
        // REPEATABLE READ + START TRANSACTION WITH CONSISTENT SNAPSHOT gives every SELECT below one
        // frozen view, with no locks taken and no shell required — so it holds on shared hosting.
        // Honest limit: this covers transactional (InnoDB) tables. A MyISAM table is not covered by
        // any snapshot, and a host that refuses the statement is logged and dumped without one
        // rather than failing the backup outright.
        // NEVER open one inside a caller's transaction. MySQL treats START TRANSACTION as an IMPLICIT
        // COMMIT, so doing it here would silently commit whatever the caller had in flight — their work
        // becomes permanent and their rollback has nothing left to undo. (Caught exactly that way: it
        // committed the test harness's wrapping transaction and leaked rows into the shared database.)
        // If a transaction is already open we skip our own; the caller's transaction is itself a
        // consistent read view, so consistency is not lost — only our control of it.
        $snapshot = false;
        $inTxn    = false;
        try {
            $conn  = $db->getConnection();
            $inTxn = ($conn instanceof PDO) && $conn->inTransaction();
        } catch (Throwable $e) { $inTxn = false; }

        if ($inTxn) {
            if (class_exists('Tiger_Log')) {
                Tiger_Log::info('backup.db.snapshot_deferred', ['reason' => 'caller already in a transaction']);
            }
        } else {
            try {
                $db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT');
                $snapshot = true;
            } catch (Throwable $e) {
                if (class_exists('Tiger_Log')) {
                    Tiger_Log::warn('backup.db.snapshot_unavailable', ['error' => $e->getMessage()]);
                }
            }
        }

        try {
            self::_w($fh, "-- TigerBackup SQL dump (" . date('Y-m-d H:i:s') . ")\n-- TIGER_STMT_TOKEN: {$token}\n", $path);
            self::_w($fh, "-- consistent_snapshot: " . ($snapshot ? 'yes' : 'no') . "\n", $path);
            foreach (["SET NAMES utf8mb4", "SET FOREIGN_KEY_CHECKS=0", "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'"] as $st) {
                self::_w($fh, $st . ';' . $sep, $path);
            }

            $tableCount = 0; $rowCount = 0;
            foreach ($db->listTables() as $table) {
                $create = $db->fetchRow('SHOW CREATE TABLE ' . $db->quoteIdentifier($table));
                $ddl    = $create['Create Table'] ?? ($create['Create View'] ?? null);
                if (!$ddl) { continue; }
                $isView = !isset($create['Create Table']);

                self::_w($fh, 'DROP ' . ($isView ? 'VIEW' : 'TABLE') . ' IF EXISTS ' . $db->quoteIdentifier($table) . ';' . $sep, $path);
                self::_w($fh, $ddl . ';' . $sep, $path);
                $tableCount++;
                if ($isView) { continue; }   // no rows to dump for a view

                $meta    = $db->describeTable($table);
                $cols    = array_keys($meta);
                $colList = implode(',', array_map([$db, 'quoteIdentifier'], $cols));
                $qTable  = $db->quoteIdentifier($table);
                // Deterministic pagination. LIMIT/OFFSET without ORDER BY has no guaranteed order, so
                // a row can be returned in two chunks or none. Order by the primary key where there
                // is one; the snapshot covers the rest.
                $order   = self::_pkOrder($db, $meta);
                $offset  = 0;

                do {
                    $rows = $db->fetchAll(sprintf(
                        'SELECT * FROM %s%s LIMIT %d OFFSET %d', $qTable, $order, self::CHUNK, $offset
                    ));
                    if (!$rows) { break; }
                    $values = [];
                    foreach ($rows as $row) {
                        $cells = [];
                        foreach ($cols as $c) {
                            $v = $row[$c] ?? null;
                            $cells[] = $v === null ? 'NULL' : $db->quote($v);
                        }
                        $values[] = '(' . implode(',', $cells) . ')';
                    }
                    self::_w($fh, 'INSERT INTO ' . $qTable . ' (' . $colList . ") VALUES\n" . implode(",\n", $values) . ';' . $sep, $path);
                    $rowCount += count($rows);
                    $offset   += self::CHUNK;
                } while (count($rows) === self::CHUNK);
            }

            self::_w($fh, "SET FOREIGN_KEY_CHECKS=1;" . $sep, $path);

            // fclose can fail on a full disk while every fwrite appeared to succeed (buffering), so a
            // dump is not complete until the handle closes cleanly.
            if (!fclose($fh)) {
                $fh = null;
                throw new RuntimeException('Tiger_Backup_Database: failed to close ' . $path . ' — the dump may be truncated.');
            }
            $fh = null;
            if ($snapshot) { try { $db->query('COMMIT'); } catch (Throwable $e) {} }

            return ['tables' => $tableCount, 'rows' => $rowCount, 'token' => $token, 'snapshot' => $snapshot];
        } catch (Throwable $e) {
            if (is_resource($fh)) { @fclose($fh); }
            if ($snapshot) { try { $db->query('ROLLBACK'); } catch (Throwable $e2) {} }
            @unlink($path);   // never leave a truncated dump where a caller might archive it
            throw $e;
        }
    }

    /**
     * Write, or throw. fwrite() returns the byte count actually written and reports a short write
     * rather than raising — so an unchecked call turns a full disk into a silently truncated archive
     * that still reports success.
     *
     * @throws RuntimeException on a short or failed write
     */
    protected static function _w($fh, $chunk, $path)
    {
        $expect  = strlen($chunk);
        $written = @fwrite($fh, $chunk);
        if ($written === false || $written !== $expect) {
            throw new RuntimeException(sprintf(
                'Tiger_Backup_Database: short write to %s (%s of %d bytes) — out of disk space?',
                $path, $written === false ? 'failed' : (string) $written, $expect
            ));
        }
    }

    /** ` ORDER BY <pk...>` for a table that has a primary key, else '' (the snapshot still applies). */
    protected static function _pkOrder($db, array $meta)
    {
        $pk = [];
        foreach ($meta as $name => $col) {
            if (!empty($col['PRIMARY'])) { $pk[(int) ($col['PRIMARY_POSITION'] ?? 0)] = $name; }
        }
        if (!$pk) { return ''; }
        ksort($pk);
        return ' ORDER BY ' . implode(',', array_map([$db, 'quoteIdentifier'], $pk));
    }

    /**
     * Restore a .sql file produced by dump() into the current database (destructive — drops/recreates
     * the dumped tables). Wrapped in FK-checks-off so table order never matters.
     *
     * @param  string $path the .sql file
     * @return int    statements executed
     * @throws RuntimeException on a malformed dump or a failed statement
     */
    public static function import($path)
    {
        $sql = @file_get_contents($path);
        if ($sql === false) { throw new RuntimeException('Tiger_Backup_Database: cannot read ' . $path); }
        if (!preg_match('/^-- TIGER_STMT_TOKEN: ([0-9a-f]+)/m', $sql, $m)) {
            throw new RuntimeException('Tiger_Backup_Database: not a TigerBackup SQL dump (no statement token).');
        }
        $sep   = "\n-- @" . $m[1] . "@\n";
        $stmts = explode($sep, $sql);

        $db  = Zend_Db_Table_Abstract::getDefaultAdapter();
        $pdo = $db->getConnection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $n = 0;
        try {
            foreach ($stmts as $stmt) {
                $stmt = trim($stmt);
                // Skip blanks and pure-comment lines (the header).
                if ($stmt === '' || preg_match('/^--[^\n]*$/', $stmt)) { continue; }
                $pdo->exec($stmt);
                $n++;
            }
        } catch (Throwable $e) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            throw new RuntimeException('Tiger_Backup_Database: restore failed at statement ' . ($n + 1) . ': ' . $e->getMessage(), 0, $e);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        return $n;
    }
}
