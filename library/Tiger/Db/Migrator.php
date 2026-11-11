<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Db_Migrator — a tiny, dependency-free schema migration runner.
 *
 * WHY hand-rolled (not Doctrine/Phinx/etc.): ZF1 has no migration tool, and pulling
 * a heavyweight one in would drag a large dependency into every Tiger app. Migrations
 * are simple enough — ordered, idempotent, tracked — to own directly, and owning them
 * keeps the "zero unnecessary plumbing" promise.
 *
 * HOW IT WORKS:
 *   - A migration is a file named `NNNN_snake_name.php` returning
 *     ['up' => [...sql], 'down' => [...sql]]. `NNNN` is the version (sort key).
 *   - Migrations are discovered across MULTIPLE paths — core ships them in
 *     tiger-core/migrations; an app in application/migrations; a module in
 *     modules/<m>/migrations — and merged into one sequence ordered by version, then name.
 *   - Applied migrations are recorded in `tiger_migration`, keyed by (version, name), so
 *     migrate() only ever runs what's pending, and is safe to run repeatedly.
 *
 * SHARED VERSION NUMBERS (TIGER-477): versions are one namespace across core, the app and
 * every module, and independently-written modules DO collide (two modules both shipping a
 * YYYYMMDD000001 file on the same day). The ledger used to be keyed by version alone, so
 * the second file was silently treated as applied and its schema never created. Now each (version, name) is
 * its own ledger row: both files run, and an install that already skipped one gets it on
 * its next migrate. A pre-existing ledger keyed by version alone is upgraded in place.
 * A file RENAMED after it ran (same version, new name) still counts as applied — but only when
 * no migration file ANYWHERE on the install claims the old (version, name), judged against
 * every migrations dir (Tiger_Module_Installer::migrationPaths()), not just the dirs this
 * migrator was scoped to, so a module install/purge can never mistake another module's
 * same-version migration for a rename. status() flags every shared version. Authors should still use
 * a time-of-day suffix (YYYYMMDDHHMMSS) rather than a bare YYYYMMDD000001.
 *
 * CAVEAT (MySQL/MariaDB): DDL statements auto-commit — a CREATE/ALTER can't be
 * rolled back inside a transaction. So keep each migration to ONE logical change;
 * if a migration half-applies, fix forward. We record a migration as applied only
 * after ALL its statements succeed, so a failure leaves it un-recorded and it will
 * be retried on the next run.
 *
 * @api
 */
class Tiger_Db_Migrator
{
    /**
     * The platform's standard columns on the ledger (TIGER-488), as column name => definition. The ledger
     * is only ever written with an explicit column list, so each one is nullable or defaulted, and the
     * database keeps the timestamps.
     */
    const LEDGER_STANDARD_COLUMNS = [
        'status'     => "`status`     VARCHAR(32) NOT NULL DEFAULT 'active'",
        'deleted'    => '`deleted`    TINYINT(1)  NOT NULL DEFAULT 0',
        'created_by' => '`created_by` CHAR(36)    NULL',
        'updated_by' => '`updated_by` CHAR(36)    NULL',
        'created_at' => '`created_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => '`updated_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];

    /** @var Zend_Db_Adapter_Abstract */
    private $db;

    /** @var string[] directories to scan for migration files, in precedence order */
    private $paths;

    /** @var string ledger table recording applied migrations (default `tiger_migration`) */
    private $ledger;

    /** @var array<string, string[]> version => every file declaring it, filled by discover() when >1 */
    private $duplicates = [];

    /** @var bool the ledger exists with its (version, name) key — checked once per migrator */
    private $ledgerReady = false;

    /** @var string[]|null every migrations dir on the install (the rename check's global view); null = auto */
    private $claimPaths;

    /** @var array<string, array<string, true>>|null version => [name => true] declared by ANY file on the install */
    private $claimed = null;

    /**
     * Construct the migrator over a DB adapter and a set of migration directories.
     *
     * @param Zend_Db_Adapter_Abstract $db
     * @param string[]                 $paths       migration directories (existing ones; missing are ignored)
     * @param string                   $ledgerTable the applied-migrations ledger table. Defaults to the
     *                                              canonical `tiger_migration`; override it to run a migrator
     *                                              against an ISOLATED ledger (so a migration test's fixture
     *                                              versions never collide with — or roll back — the real
     *                                              schema's applied set).
     * @param string[]|null            $claimPaths  every migrations dir on the install, for the rename check
     *                                              (default: Tiger_Module_Installer::migrationPaths() — all of
     *                                              core, the app and every module dir, active or not)
     * @return void
     */
    public function __construct(Zend_Db_Adapter_Abstract $db, array $paths, $ledgerTable = 'tiger_migration', ?array $claimPaths = null)
    {
        $this->db         = $db;
        $this->paths      = array_values(array_filter($paths, 'is_dir'));
        $this->ledger     = (string) $ledgerTable;
        $this->claimPaths = $claimPaths;
    }

    /**
     * Apply all pending migrations in (version, name) order.
     *
     * @param  callable|null $log optional fn(string $message) for progress output
     * @return array         [key => name] of migrations applied this run; key is the version, or
     *                       "<version>_<name>" when the version is shared by more than one file
     */
    public function migrate($log = null)
    {
        $this->ensureTrackingTable();
        $all     = $this->discover();
        $applied = $this->appliedMap();
        $ran     = [];

        foreach ($all as $key => $m) {
            if ($this->isApplied($m, $applied)) {
                continue;
            }
            $this->emit($log, "  migrating {$m['version']}_{$m['name']} ...");
            foreach ($m['up'] as $stmt) {
                $this->run($stmt);
            }
            // Record only after every statement succeeded (see class caveat).
            $this->db->insert($this->ledger, [
                'version'    => $m['version'],
                'name'       => $m['name'],
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $applied[$m['version']][$m['name']] = true;
            $ran[$key] = $m['name'];
        }

        $this->emit($log, $ran ? '  applied ' . count($ran) . ' migration(s).' : '  nothing to migrate.');
        return $ran;
    }

    /**
     * Reverse the most recently applied migrations (highest version first; within a shared version,
     * highest name first).
     *
     * @param  int           $steps how many to roll back
     * @param  callable|null $log
     * @return array         [key => name] rolled back (key as in migrate())
     */
    public function rollback($steps = 1, $log = null)
    {
        $this->ensureTrackingTable();
        $all  = $this->discover();
        $rows = $this->db->fetchAll(
            'SELECT version, name FROM ' . $this->db->quoteIdentifier($this->ledger)
            . ' ORDER BY version DESC, name DESC'
        );
        $target = array_slice($rows, 0, max(0, (int) $steps));
        $done   = [];

        foreach ($target as $row) {
            $version = (string) $row['version'];
            $m       = $this->fileFor($all, $version, (string) $row['name']);
            if ($m === null) {
                $this->emit($log, "  ! no file for applied {$version}_{$row['name']}; skipping");
                continue;
            }
            $this->emit($log, "  rolling back {$version}_{$m['name']} ...");
            foreach ($m['down'] as $stmt) {
                $this->run($stmt);
            }
            $this->db->delete($this->ledger, [
                $this->db->quoteInto('version = ?', $version),
                $this->db->quoteInto('name = ?', (string) $row['name']),
            ]);
            $done[$this->keyFor($version, $m['name'])] = $m['name'];
        }
        return $done;
    }

    /**
     * Run one migration step. A **string** is executed as SQL (the common case). A **callable**
     * (a `function ($db) { … }` — anything non-string that's callable) is invoked with the DB adapter,
     * for DATA migrations SQL can't express cleanly (e.g. transforming a JSON column across rows). The
     * string-vs-callable split is by type, not `is_callable`, so a SQL string that happens to name a
     * PHP function is never mistaken for one.
     *
     * @param  string|callable $stmt an SQL string, or fn(Zend_Db_Adapter_Abstract $db): void
     * @return void
     */
    protected function run($stmt)
    {
        if (!is_string($stmt) && is_callable($stmt)) {
            $stmt($this->db);
            return;
        }
        $this->db->query($stmt);
    }

    /**
     * Discovered vs applied, for a `migrate:status` view. A version shared by more than one file is
     * listed once per file (keyed "<version>_<name>") and each carries `duplicates` — every file that
     * declares that version — so the operator sees the collision.
     *
     * @return array [key => ['version' => string, 'name' => string, 'applied' => bool, 'duplicates'? => string[]]]
     */
    public function status()
    {
        $this->ensureTrackingTable();
        $applied = $this->appliedMap();
        $out     = [];
        foreach ($this->discover() as $key => $m) {
            $out[$key] = ['version' => $m['version'], 'name' => $m['name'], 'applied' => $this->isApplied($m, $applied)];
            if (isset($this->duplicates[$m['version']])) {
                $out[$key]['duplicates'] = $this->duplicates[$m['version']];
            }
        }
        return $out;
    }

    /**
     * Every version declared by more than one migration file across the scanned paths — no DB needed, so
     * a module's CI can check for a collision up front.
     *
     * @return array<string, string[]> version => the files declaring it (path tails, e.g. "hosting/migrations/x.php")
     */
    public function duplicates()
    {
        $this->discover();
        return $this->duplicates;
    }

    // --- internals ---------------------------------------------------------

    /**
     * Scan all paths for migration files and merge them into one (version, name)-ordered map.
     *
     * @return array [key => ['version' => string, 'name' => string, 'up' => array, 'down' => array]]
     *               key = version, or "<version>_<name>" when the version is shared
     */
    private function discover()
    {
        $found = [];
        $files = [];
        foreach ($this->paths as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $base = basename($file, '.php');           // e.g. "0001_create_org"
                if (!preg_match('/^(\d+)_(.+)$/', $base, $mm)) {
                    continue;                              // ignore non-conforming files
                }
                list(, $version, $name) = $mm;
                $files[$version][] = self::displayPath($file);
                $spec = include $file;
                $found[$version . "\0" . $name] = [
                    'version' => $version,
                    'name'    => $name,
                    'up'      => isset($spec['up'])   ? (array) $spec['up']   : [],
                    'down'    => isset($spec['down']) ? (array) $spec['down'] : [],
                ];
            }
        }
        ksort($found, SORT_STRING);   // zero-padded versions sort correctly as strings; NUL sorts before any name char

        $this->duplicates = array_filter($files, static function ($list) { return count($list) > 1; });
        ksort($this->duplicates, SORT_STRING);

        $out = [];
        foreach ($found as $m) {
            $out[$this->keyFor($m['version'], $m['name'])] = $m;
        }
        return $out;
    }

    /** The public key for a migration: its version, or "<version>_<name>" when the version is shared. */
    private function keyFor($version, $name)
    {
        return isset($this->duplicates[$version]) ? $version . '_' . $name : (string) $version;
    }

    /** @return array<string, array<string, true>> version => [name => true] of applied migrations */
    private function appliedMap()
    {
        $map = [];
        foreach ($this->db->fetchAll('SELECT version, name FROM ' . $this->db->quoteIdentifier($this->ledger)) as $r) {
            $map[(string) $r['version']][(string) $r['name']] = true;
        }
        return $map;
    }

    /**
     * Is this migration already applied? Exactly (version, name) in the ledger — or the file was RENAMED after
     * it ran: it's the only file at its version here, and the ledger holds a row at that version whose name no
     * file on the whole install declares any more.
     */
    private function isApplied(array $m, array $applied)
    {
        $rows = $applied[$m['version']] ?? [];
        if (!$rows) {
            return false;
        }
        if (isset($rows[$m['name']])) {
            return true;
        }
        if (isset($this->duplicates[$m['version']])) {
            return false;
        }
        foreach (array_keys($rows) as $ledgerName) {
            if ($this->isOrphanRow($m['version'], (string) $ledgerName)) {
                return true;   // a renamed file — its old name is claimed by nothing
            }
        }
        return false;          // every ledger row at this version belongs to another live file
    }

    /**
     * The discovered file for a ledger row: exact (version, name); else, when the row is an orphan (no file on
     * the install declares it) and this migrator sees exactly one file at that version, that file (a rename).
     */
    private function fileFor(array $all, $version, $name)
    {
        $matches = [];
        foreach ($all as $m) {
            if ($m['version'] === $version) {
                if ($m['name'] === $name) {
                    return $m;
                }
                $matches[] = $m;
            }
        }
        return (count($matches) === 1 && $this->isOrphanRow($version, $name)) ? $matches[0] : null;
    }

    /** True when no migration file on the whole install declares this (version, name). */
    private function isOrphanRow($version, $name)
    {
        if ($this->claimed === null) {
            $paths = $this->claimPaths;
            if ($paths === null) {
                $paths = class_exists('Tiger_Module_Installer') ? Tiger_Module_Installer::migrationPaths() : [];
            }
            $this->claimed = [];
            foreach (array_merge($this->paths, $paths) as $dir) {
                foreach (glob(rtrim((string) $dir, '/') . '/*.php') ?: [] as $file) {
                    if (preg_match('/^(\d+)_(.+)$/', basename($file, '.php'), $mm)) {
                        $this->claimed[$mm[1]][$mm[2]] = true;
                    }
                }
            }
        }
        return !isset($this->claimed[(string) $version][(string) $name]);
    }

    /**
     * Create the tracking table if it doesn't exist, keyed by (version, name). A ledger created by an
     * older core (PRIMARY KEY on version alone) is upgraded in place — a tiny table, one ALTER, once.
     */
    private function ensureTrackingTable()
    {
        if ($this->ledgerReady) {
            return;
        }
        $table = $this->db->quoteIdentifier($this->ledger);
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS ' . $table . ' (
                `version`    VARCHAR(64)  NOT NULL,
                `name`       VARCHAR(191) NOT NULL,
                `applied_at` DATETIME     NOT NULL,
                ' . implode(",\n                ", self::LEDGER_STANDARD_COLUMNS) . ',
                PRIMARY KEY (`version`, `name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        // A ledger created by an older core lacks the standard columns (TIGER-488): add whichever are
        // missing, in place. Done here rather than in a migration because the ledger is this class's own
        // table — its name is configurable (tests use isolated ledgers), so a migration could not name it.
        $have = $this->db->fetchCol(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$this->ledger]
        );
        $add = [];
        foreach (self::LEDGER_STANDARD_COLUMNS as $col => $def) {
            if (!in_array($col, $have, true)) { $add[] = 'ADD COLUMN ' . $def; }
        }
        if ($add) {
            $this->db->query('ALTER TABLE ' . $table . ' ' . implode(', ', $add));
        }
        $pk = $this->db->fetchCol(
            'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$this->ledger, 'PRIMARY']
        );
        if ($pk === ['version']) {
            $this->db->query('ALTER TABLE ' . $table . ' DROP PRIMARY KEY, ADD PRIMARY KEY (`version`, `name`)');
        }
        $this->ledgerReady = true;
    }

    /** The last three path segments of a migration file (e.g. "hosting/migrations/2026…_x.php"), for messages. */
    private static function displayPath($file)
    {
        $parts = explode('/', str_replace('\\', '/', (string) $file));
        return implode('/', array_slice($parts, -3));
    }

    private function emit($log, $message)
    {
        if (is_callable($log)) {
            $log($message);
        }
    }
}
