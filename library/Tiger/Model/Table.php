<?php
/**
 * Base table-gateway for Tiger models.
 *
 * Extends ZF1's Zend_Db_Table_Abstract (the Table Data Gateway pattern) and adds
 * the two things every Tiger table wants:
 *
 *   1. UUID primary keys, generated in PHP on insert (v7 by default — see
 *      Tiger_Uuid for why). Subclasses that need opaque timing set
 *      $_uuidVersion = 4.
 *   2. created_at / updated_at timestamps, maintained automatically (only if the
 *      table actually has those columns — so this base is safe for tables that
 *      don't).
 *
 * Because the PK is a client-generated UUID (not an auto-increment), we override
 * insert() to (a) mint the id and (b) return the UUID string — ZF1's default
 * insert() returns the DB's lastInsertId(), which is meaningless for UUIDs.
 *
 * Subclasses declare $_name and $_primary and (optionally) domain helper methods.
 * Metadata is lazy-loaded from the default adapter on first use, so this class is
 * cheap to load and doesn't need a DB connection until you actually query.
 *
 * @api
 */
abstract class Tiger_Model_Table extends Zend_Db_Table_Abstract
{
    /**
     * UUID version for this table's primary key. 7 (time-ordered) is the default
     * and correct for entities; override to 4 in a subclass for tables whose ids
     * must not leak creation time (tokens, secrets).
     *
     * @var int
     */
    protected $_uuidVersion = 7;

    /** Cached column list for this table (populated lazily). @var string[]|null */
    private $_colsCache = null;

    /**
     * Insert a row, minting the UUID primary key and stamping created_at/updated_at.
     *
     * @param  array $data column => value (omit the PK — we generate it)
     * @return string the generated UUID primary key
     */
    public function insert(array $data)
    {
        $pk = $this->_primaryColumn();
        if (empty($data[$pk])) {
            $data[$pk] = ($this->_uuidVersion === 4) ? Tiger_Uuid::v4() : Tiger_Uuid::v7();
        }

        $now = $this->_now();
        if ($this->_hasColumn('created_at') && empty($data['created_at'])) {
            $data['created_at'] = $now;
        }
        if ($this->_hasColumn('updated_at') && !array_key_exists('updated_at', $data)) {
            $data['updated_at'] = $now;
        }

        parent::insert($data);

        // Return the UUID we generated — NOT parent::insert()'s lastInsertId(),
        // which is empty/irrelevant for a client-generated string PK.
        return $data[$pk];
    }

    /**
     * Update rows, refreshing updated_at automatically.
     *
     * @param  array        $data
     * @param  array|string $where
     * @return int affected rows
     */
    public function update(array $data, $where)
    {
        if ($this->_hasColumn('updated_at')) {
            $data['updated_at'] = $this->_now();
        }
        return parent::update($data, $where);
    }

    /**
     * Fetch a single row by primary key, or null. Convenience over find()->current().
     *
     * @param  string $id
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findById($id)
    {
        $row = $this->find($id)->current();
        return $row ?: null;
    }

    /** The (single) primary-key column name. */
    protected function _primaryColumn()
    {
        // Zend stores _primary as a 1-indexed array; we assume single-column PKs.
        $primary = (array) $this->_primary;
        return reset($primary);
    }

    /** Does this table have the given column? (Cached.) */
    protected function _hasColumn($column)
    {
        if ($this->_colsCache === null) {
            $this->_colsCache = $this->info(self::COLS);
        }
        return in_array($column, $this->_colsCache, true);
    }

    /** Current timestamp in MySQL DATETIME format. */
    protected function _now()
    {
        return date('Y-m-d H:i:s');
    }
}
