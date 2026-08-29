<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_Comment — the one comment/review store (migration 0045).
 *
 * A review IS a comment with a rating: `rating` null = a plain comment, 1-5 = a review, and a reply
 * is a row with `parent_id` set. One table, so one moderation queue and one spam path.
 *
 * Reads are scoped by `subject_type` + `subject_id` and exclude soft-deleted rows via
 * `activeSelect()`; a public thread additionally filters to `approved`.
 *
 * @api
 * @since 1.5.0
 */
class Tiger_Model_Comment extends Tiger_Model_Table
{
    protected $_name    = 'comment';
    protected $_primary = 'comment_id';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_SPAM     = 'spam';
    const STATUS_REJECTED = 'rejected';

    /** The statuses a moderator may set. */
    const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_SPAM, self::STATUS_REJECTED];

    /**
     * The published thread for a subject, oldest first.
     *
     * @param  string $type  the subject type
     * @param  string $id    the subject id
     * @param  int    $limit max rows (0 = no cap)
     * @return array<int,array> the approved comments
     */
    public function thread($type, $id, $limit = 0)
    {
        $select = $this->activeSelect()
            ->where('subject_type = ?', (string) $type)
            ->where('subject_id = ?', (string) $id)
            ->where('status = ?', self::STATUS_APPROVED)
            ->order('created_at ASC');

        if ((int) $limit > 0) { $select->limit((int) $limit); }

        return $this->fetchAll($select)->toArray();
    }

    /**
     * This user's existing rating row for a subject, if any.
     *
     * One rating per user per subject is enforced by editing in place rather than by a unique index:
     * the index would also have to admit unlimited un-rated replies from the same person, which is a
     * partial-index the storage engine can't express portably.
     *
     * @param  string $type   the subject type
     * @param  string $id     the subject id
     * @param  string $userId the user
     * @return array|null     the row
     */
    public function ratingBy($type, $id, $userId)
    {
        if ((string) $userId === '') { return null; }

        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('subject_type = ?', (string) $type)
                ->where('subject_id = ?', (string) $id)
                ->where('user_id = ?', (string) $userId)
                ->where('rating IS NOT NULL')
        );
        return $row ? $row->toArray() : null;
    }

    /**
     * Count a user's recent comments — the rate-limit read.
     *
     * @param  string $userId  the user
     * @param  int    $seconds the window
     * @return int             how many they posted in it
     */
    public function recentCountByUser($userId, $seconds)
    {
        if ((string) $userId === '') { return 0; }
        return $this->_recentCount('user_id', (string) $userId, (int) $seconds);
    }

    /**
     * Count recent comments from an IP — the rate limit that also covers guests.
     *
     * @param  string $ip      the address
     * @param  int    $seconds the window
     * @return int             how many came from it
     */
    public function recentCountByIp($ip, $seconds)
    {
        if ((string) $ip === '') { return 0; }
        return $this->_recentCount('ip', (string) $ip, (int) $seconds);
    }

    /**
     * The rating tallies for a subject, over APPROVED rows only.
     *
     * Pending and spam rows must not move a public average — otherwise a spammer changes a score
     * merely by posting, before anyone moderates.
     *
     * @param  string $type the subject type
     * @param  string $id   the subject id
     * @return array{comment_count:int,rating_count:int,rating_sum:int,stars:array<int,int>}
     */
    public function tallies($type, $id)
    {
        $db  = $this->getAdapter();
        $out = ['comment_count' => 0, 'rating_count' => 0, 'rating_sum' => 0,
                'stars' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];

        // Built on the ADAPTER, not activeSelect(): Tiger_Model_Table::select() omits the FROM part by
        // default (the table adds it at fetch time), so a grouped aggregate has to name its own table.
        $rows = $db->fetchAll(
            $db->select()
                ->from($this->_name, ['rating', 'n' => new Zend_Db_Expr('COUNT(*)')])
                ->where('deleted = ?', 0)
                ->where('subject_type = ?', (string) $type)
                ->where('subject_id = ?', (string) $id)
                ->where('status = ?', self::STATUS_APPROVED)
                ->group('rating')
        );

        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $out['comment_count'] += $n;
            if ($row['rating'] === null) { continue; }

            $star = (int) $row['rating'];
            if ($star < 1 || $star > 5) { continue; }
            $out['rating_count']  += $n;
            $out['rating_sum']    += $star * $n;
            $out['stars'][$star]  += $n;
        }

        return $out;
    }

    /**
     * Rows awaiting moderation, newest first.
     *
     * @param  string $status the status to list
     * @param  int    $limit  max rows
     * @return array<int,array>
     */
    public function byStatus($status, $limit = 100)
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('status = ?', (string) $status)
                ->order('created_at DESC')
                ->limit(max(1, (int) $limit))
        )->toArray();
    }

    /** Shared body of the two rate-limit reads. */
    protected function _recentCount($column, $value, $seconds)
    {
        $db    = $this->getAdapter();
        $since = date('Y-m-d H:i:s', time() - max(1, (int) $seconds));

        return (int) $db->fetchOne(
            $db->select()
                ->from($this->_name, ['n' => new Zend_Db_Expr('COUNT(*)')])
                ->where('deleted = ?', 0)
                ->where($db->quoteIdentifier($column) . ' = ?', $value)
                ->where('created_at >= ?', $since)
        );
    }
}
