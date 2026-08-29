<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_CommentAggregate — the denormalized per-subject rollup (migration 0046).
 *
 * A card in a 60-item grid cannot average N comment rows; it reads one row from here. The rollup is
 * recomputed from the APPROVED comments inside the same transaction that changes one, so the number
 * a card shows can never disagree with the thread a visitor opens.
 *
 * `comment_count` and `rating_count` are separate: ratings are optional, so a subject with 40
 * comments and 3 ratings must not claim 40 ratings.
 *
 * @api
 * @since 1.5.0
 */
class Tiger_Model_CommentAggregate extends Tiger_Model_Table
{
    protected $_name    = 'comment_aggregate';
    protected $_primary = 'comment_aggregate_id';

    /** A zero rollup — what an unrated, uncommented subject looks like. */
    const EMPTY_ROLLUP = [
        'comment_count' => 0, 'rating_count' => 0, 'rating_avg' => 0.0,
        'stars' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
    ];

    /**
     * The rollup for one subject, or the zero rollup when it has none.
     *
     * @param  string $type the subject type
     * @param  string $id   the subject id
     * @return array        the rollup
     */
    public function forSubject($type, $id)
    {
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('subject_type = ?', (string) $type)
                ->where('subject_id = ?', (string) $id)
        );
        return $row ? self::shape($row->toArray()) : self::EMPTY_ROLLUP;
    }

    /**
     * Rollups for many subjects of one type, keyed by subject id — the BATCH read a listing grid
     * needs. One query for N cards; the whole reason this table exists.
     *
     * @param  string             $type the subject type
     * @param  array<int,string>  $ids  the subject ids
     * @return array<string,array>      subject id => rollup (missing subjects are simply absent)
     */
    public function forSubjects($type, array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids), 'strlen')));
        if (!$ids) { return []; }

        $rows = $this->fetchAll(
            $this->activeSelect()
                ->where('subject_type = ?', (string) $type)
                ->where('subject_id IN (?)', $ids)
        )->toArray();

        $out = [];
        foreach ($rows as $row) { $out[(string) $row['subject_id']] = self::shape($row); }
        return $out;
    }

    /**
     * Recompute and store a subject's rollup from its approved comments.
     *
     * Call inside the caller's transaction — the write that changed a comment and this recompute must
     * commit or roll back together, or a card ends up quoting a number the thread doesn't support.
     *
     * @param  string $type the subject type
     * @param  string $id   the subject id
     * @return array        the freshly stored rollup
     */
    public function recompute($type, $id)
    {
        $t   = (new Tiger_Model_Comment())->tallies($type, $id);
        $avg = $t['rating_count'] > 0 ? round($t['rating_sum'] / $t['rating_count'], 2) : 0.0;

        $values = [
            'comment_count' => $t['comment_count'],
            'rating_count'  => $t['rating_count'],
            'rating_avg'    => $avg,
            'star_1'        => $t['stars'][1],
            'star_2'        => $t['stars'][2],
            'star_3'        => $t['stars'][3],
            'star_4'        => $t['stars'][4],
            'star_5'        => $t['stars'][5],
        ];

        $existing = $this->fetchRow(
            $this->select()
                ->where('subject_type = ?', (string) $type)
                ->where('subject_id = ?', (string) $id)
        );

        if ($existing) {
            // A previously emptied subject may have been soft-deleted; revive rather than duplicate,
            // because the UNIQUE (subject_type, subject_id) index still holds that row.
            if ((int) $existing->deleted === 1) { $values['deleted'] = 0; }
            $this->update($values, $this->getAdapter()->quoteInto('comment_aggregate_id = ?', $existing->comment_aggregate_id));
        } else {
            $this->insert($values + ['subject_type' => (string) $type, 'subject_id' => (string) $id]);
        }

        return self::shape($values);
    }

    /**
     * Normalize a stored row into the rollup shape callers use.
     *
     * @param  array $row the raw row
     * @return array      the rollup
     */
    public static function shape(array $row)
    {
        return [
            'comment_count' => (int) ($row['comment_count'] ?? 0),
            'rating_count'  => (int) ($row['rating_count'] ?? 0),
            'rating_avg'    => (float) ($row['rating_avg'] ?? 0),
            'stars'         => [
                1 => (int) ($row['star_1'] ?? 0), 2 => (int) ($row['star_2'] ?? 0),
                3 => (int) ($row['star_3'] ?? 0), 4 => (int) ($row['star_4'] ?? 0),
                5 => (int) ($row['star_5'] ?? 0),
            ],
        ];
    }
}
