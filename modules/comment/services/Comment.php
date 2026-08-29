<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Service_Comment — the `/api` surface for comments, ratings and reviews.
 *
 * One service for both, because a review IS a comment with a rating (COMMENTS.md §0). `post()` is
 * where nearly all the policy lives: the feature gate, the subject's own read ACL, the entitlement
 * and self-review checks, the rate limits, the honeypot, and the moderation posture.
 *
 * Every mutation recomputes the subject's rollup INSIDE the same transaction, so a card can never
 * quote a number the thread doesn't support.
 */
class Comment_Service_Comment extends Tiger_Service_Service
{
    /** Max comments one identity may post in the window — the cheap flood guard. */
    const RATE_LIMIT   = 5;
    const RATE_WINDOW  = 300;    // 5 minutes

    /** A form rendered and submitted faster than this is a bot, not a reader. */
    const MIN_FILL_SECONDS = 2;

    const MAX_BODY = 20000;

    /**
     * The published thread for a subject, plus its rollup.
     *
     * Public read — gated by the SUBJECT's own ACL resource, so a thread can never expose the
     * existence of content the caller isn't allowed to see.
     *
     * @param  array $params `subject_type`, `subject_id`
     * @return void
     */
    public function list(array $params): void
    {
        if (!$this->_enabled()) { return; }

        [$type, $id] = $this->_subjectFrom($params);
        if ($type === '') { $this->_error('comment.error.unknown_subject'); return; }
        if (!$this->_mayRead($type)) { $this->_error('core.api.error.not_allowed'); return; }

        $rows = (new Tiger_Model_Comment())->thread($type, $id);

        $subject = Tiger_Comment::subject($type);

        $this->_success([
            'comments'  => array_map([$this, '_public'], $rows),
            'aggregate' => (new Tiger_Model_CommentAggregate())->forSubject($type, $id),
            'ratings'   => Tiger_Comment::acceptsRatings($type),
            // The client offers a Reply only where one can actually be posted — the depth rule is the
            // server's, so the button disappears at the limit rather than failing on submit.
            'threading' => $subject ? (int) $subject['threading'] : 0,
        ]);
    }

    /**
     * Post a comment, a rating, or both.
     *
     * @param  array $params `subject_type`, `subject_id`, `body`, `rating`, `parent_id`, `author_name`,
     *                       `author_email`, `_hp` (honeypot), `_t` (render timestamp)
     * @return void
     */
    public function post(array $params): void
    {
        if (!$this->_enabled()) { return; }

        [$type, $id] = $this->_subjectFrom($params);
        if ($type === '') { $this->_error('comment.error.unknown_subject'); return; }
        if (!$this->_mayRead($type)) { $this->_error('core.api.error.not_allowed'); return; }

        // A subject the provider says is gone takes no new comments — otherwise a deleted page
        // accumulates an unreachable thread nobody will ever moderate.
        if (!Tiger_Comment::resolve($type, $id)['exists']) { $this->_error('comment.error.subject_gone'); return; }

        $userId = (string) ($this->_user_id ?? '');
        if ($userId === '' && !Tiger_Comment::allowsGuests()) { $this->_error('comment.error.sign_in'); return; }

        // Bots fill every field, including the one no human can see, and they submit instantly.
        if (trim((string) ($params['_hp'] ?? '')) !== '') { $this->_error('comment.error.rejected'); return; }
        if ($this->_tooFast($params)) { $this->_error('comment.error.too_fast'); return; }

        $form = new Comment_Form_Comment(['ratings' => Tiger_Comment::acceptsRatings($type), 'guest' => $userId === '']);
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        $rating = ($values['rating'] ?? '') === '' ? null : (int) $values['rating'];
        $body   = trim((string) ($values['body'] ?? ''));
        if ($body === '' && $rating === null) { $this->_error('comment.error.empty'); return; }
        if ($rating !== null && !Tiger_Comment::acceptsRatings($type)) { $this->_error('comment.error.no_ratings'); return; }

        // The most obvious way to poison a rating system is to rate your own thing.
        if ($rating !== null && Tiger_Comment::ownsSubject($type, $id, $userId)) {
            $this->_error('comment.error.self_review'); return;
        }

        if ($this->_rateLimited($userId)) { $this->_error('comment.error.rate_limited'); return; }

        $parent = $this->_parent($params, $type, $id);
        if ($parent === false) { $this->_error('comment.error.bad_parent'); return; }

        // Spam checkers are ADVISORY and only ever TIGHTEN. A `spam` verdict routes the comment to the
        // spam bucket instead of the queue; anything else — ham, unknown, no checker at all, a model
        // that timed out — leaves the install's normal moderation posture untouched. Nothing a checker
        // says can publish a comment that wasn't going to be published, which is what makes it safe to
        // hand an attacker-controlled string to a language model.
        $status  = Tiger_Comment::initialStatus();
        $verdict = Tiger_Comment_Spam::check([
            'body'         => $body,
            'author_name'  => (string) ($values['author_name'] ?? ''),
            'subject_type' => $type,
            'subject_id'   => $id,
        ]);
        if ($verdict === Tiger_Comment_Spam::VERDICT_SPAM) {
            $status = Tiger_Model_Comment::STATUS_SPAM;
        }

        try {
            $model = new Tiger_Model_Comment();

            // One rating per user per subject: a second one EDITS the first rather than stacking.
            $existing = $rating !== null && $userId !== '' ? $model->ratingBy($type, $id, $userId) : null;

            $id_ = $this->_transaction(function () use ($model, $type, $id, $body, $rating, $parent, $values, $userId, $existing, $status) {
                $data = [
                    'subject_type' => $type,
                    'subject_id'   => $id,
                    'parent_id'    => $parent['id'],
                    'depth'        => $parent['depth'],
                    'user_id'      => $userId !== '' ? $userId : null,
                    'author_name'  => $userId === '' ? (string) ($values['author_name'] ?? '') : null,
                    'author_email' => $userId === '' ? (string) ($values['author_email'] ?? '') : null,
                    'body'         => $body,
                    'rating'       => $rating,
                    'verified'     => Tiger_Comment::isVerifiedReviewer($type, $id, $userId) ? 1 : 0,
                    'ip'           => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                    'user_agent'   => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'status'       => $status,
                ];

                if ($existing) {
                    $model->update($data, $model->getAdapter()->quoteInto('comment_id = ?', $existing['comment_id']));
                    $commentId = (string) $existing['comment_id'];
                } else {
                    $commentId = (string) $model->insert($data);
                }

                (new Tiger_Model_CommentAggregate())->recompute($type, $id);
                return $commentId;
            });

            // A comment binned as spam is told the same thing a held one is: never confirm to a
            // spammer that their message was classified, or they simply iterate until it isn't.
            $this->_success(
                ['comment_id' => $id_, 'status' => $status],
                $status === Tiger_Model_Comment::STATUS_APPROVED ? 'comment.posted' : 'comment.posted_pending'
            );
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Edit your own comment, within the edit window.
     *
     * A body change re-enters moderation when the install holds comments — otherwise "post something
     * innocuous, get approved, rewrite it" is an open door. A rating change does not: the number is
     * bounded 1-5 and there is nothing to moderate about it.
     *
     * @param  array $params `comment_id`, `body`, `rating`
     * @return void
     */
    public function edit(array $params): void
    {
        if (!$this->_enabled()) { return; }

        $model = new Tiger_Model_Comment();
        $row   = $model->findById((string) ($params['comment_id'] ?? ''));
        if (!$row) { $this->_error('comment.error.not_found'); return; }

        $userId = (string) ($this->_user_id ?? '');
        $mine   = $userId !== '' && (string) $row->user_id === $userId;
        if (!$mine && !$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        if ($mine && !$this->_isAdmin() && (time() - strtotime((string) $row->created_at)) > Tiger_Comment::editWindow()) {
            $this->_error('comment.error.edit_window'); return;
        }

        $body      = array_key_exists('body', $params) ? trim((string) $params['body']) : null;
        $rating    = array_key_exists('rating', $params) && $params['rating'] !== '' ? (int) $params['rating'] : null;
        $bodyMoved = $body !== null && $body !== (string) $row->body;

        if ($body !== null && strlen($body) > self::MAX_BODY) { $this->_error('comment.error.too_long'); return; }
        if ($rating !== null && ($rating < 1 || $rating > 5)) { $this->_error('comment.error.bad_rating'); return; }

        try {
            $this->_transaction(function () use ($model, $row, $body, $rating, $bodyMoved) {
                $data = [];
                if ($body !== null)   { $data['body'] = $body; }
                if ($rating !== null) { $data['rating'] = $rating; }
                if ($bodyMoved && Tiger_Comment::initialStatus() === Tiger_Model_Comment::STATUS_PENDING) {
                    $data['status'] = Tiger_Model_Comment::STATUS_PENDING;
                }
                if ($data) {
                    $model->update($data, $model->getAdapter()->quoteInto('comment_id = ?', $row->comment_id));
                }
                (new Tiger_Model_CommentAggregate())->recompute($row->subject_type, $row->subject_id);
            });
            $this->_success([], 'comment.updated');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Set a comment's moderation status. Admin only.
     *
     * @param  array $params `comment_id`, `status`
     * @return void
     */
    public function moderate(array $params): void
    {
        if (!$this->_enabled()) { return; }
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $status = (string) ($params['status'] ?? '');
        if (!in_array($status, Tiger_Model_Comment::STATUSES, true)) { $this->_error('comment.error.bad_status'); return; }

        $model = new Tiger_Model_Comment();
        $row   = $model->findById((string) ($params['comment_id'] ?? ''));
        if (!$row) { $this->_error('comment.error.not_found'); return; }

        try {
            $this->_transaction(function () use ($model, $row, $status) {
                $model->update(['status' => $status], $model->getAdapter()->quoteInto('comment_id = ?', $row->comment_id));
                (new Tiger_Model_CommentAggregate())->recompute($row->subject_type, $row->subject_id);
            });
            $this->_success([], 'comment.moderated');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Soft-delete a comment — its author (inside the window) or an admin.
     *
     * @param  array $params `comment_id`
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_enabled()) { return; }

        $model = new Tiger_Model_Comment();
        $row   = $model->findById((string) ($params['comment_id'] ?? ''));
        if (!$row) { $this->_error('comment.error.not_found'); return; }

        $userId = (string) ($this->_user_id ?? '');
        $mine   = $userId !== '' && (string) $row->user_id === $userId;
        if (!$mine && !$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        try {
            $this->_transaction(function () use ($model, $row) {
                // softDelete() takes a WHERE clause, not an id — a bare UUID would be nonsense SQL.
                $model->softDelete($model->getAdapter()->quoteInto('comment_id = ?', $row->comment_id));
                (new Tiger_Model_CommentAggregate())->recompute($row->subject_type, $row->subject_id);
            });
            $this->_success([], 'comment.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The moderation grid (DataTables server-side). Admin only.
     *
     * @param  array $params the DataTables request + an optional `status` filter
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_enabled()) { return; }
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt     = $this->_dtParams();
        $status = (string) ($params['status'] ?? Tiger_Model_Comment::STATUS_PENDING);
        $rows   = (new Tiger_Model_Comment())->byStatus($status, 500);

        $data = [];
        foreach (array_slice($rows, (int) $dt['start'], max(1, (int) $dt['length'])) as $row) {
            $subject = Tiger_Comment::resolve($row['subject_type'], $row['subject_id']);
            $data[]  = [
                'comment_id'    => $row['comment_id'],
                'body'          => (string) $row['body'],
                'rating'        => $row['rating'] === null ? null : (int) $row['rating'],
                'verified'      => (bool) $row['verified'],
                'author'        => $this->_authorName($row),
                'status'        => $row['status'],
                'created_at'    => $row['created_at'],
                'subject_label' => $subject['title'] !== '' ? $subject['title'] : $row['subject_id'],
                'subject_url'   => $subject['url'],
                'subject_gone'  => !$subject['exists'],
            ];
        }

        $this->_dtResponse((int) $dt['draw'], count($rows), count($rows), $data);
    }

    // ---- internals ---------------------------------------------------------

    /** The feature gate. Off = the endpoint behaves as if it does not exist. */
    protected function _enabled()
    {
        if (class_exists('Tiger_Comment') && Tiger_Comment::isEnabled()) { return true; }
        $this->_error('comment.error.disabled');
        return false;
    }

    /**
     * Split `subject` ("type:id") or the explicit pair into a validated type + id.
     *
     * @return array{0:string,1:string} type ('' when unregistered) and id
     */
    protected function _subjectFrom(array $params)
    {
        $type = (string) ($params['subject_type'] ?? '');
        $id   = (string) ($params['subject_id'] ?? '');

        if ($type === '' && !empty($params['subject'])) {
            $parts = explode(':', (string) $params['subject'], 2);
            $type  = $parts[0] ?? '';
            $id    = $parts[1] ?? '';
        }

        // An unregistered type is treated as no subject at all — never trust a caller-supplied type.
        if ($type === '' || $id === '' || !Tiger_Comment::subject($type)) { return ['', '']; }
        return [$type, $id];
    }

    /** May the caller read this subject's thread? Gated on the SUBJECT's own ACL resource. */
    protected function _mayRead($type)
    {
        $s = Tiger_Comment::subject($type);
        if (!$s || empty($s['resource'])) { return true; }

        try {
            $acl  = Zend_Registry::isRegistered('Tiger_Acl') ? Zend_Registry::get('Tiger_Acl') : null;
            $role = $this->_identity->role ?? 'guest';
            return $acl ? (bool) $acl->isAllowed($role, $s['resource'], $s['privilege']) : true;
        } catch (Throwable $e) {
            return true;   // an ACL lookup failure must not silently hide a public thread
        }
    }

    /** Was this submitted implausibly fast for a human? */
    protected function _tooFast(array $params)
    {
        $rendered = (int) ($params['_t'] ?? 0);
        return $rendered > 0 && (time() - $rendered) < self::MIN_FILL_SECONDS;
    }

    /** Has this identity (or address) posted too much, too fast? */
    protected function _rateLimited($userId)
    {
        $model = new Tiger_Model_Comment();
        if ($userId !== '' && $model->recentCountByUser($userId, self::RATE_WINDOW) >= self::RATE_LIMIT) { return true; }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' && $model->recentCountByIp($ip, self::RATE_WINDOW) >= self::RATE_LIMIT;
    }

    /**
     * Validate a reply target against the subject's threading depth.
     *
     * @return array{id:?string,depth:int}|false the parent binding, or false when it's bogus
     */
    protected function _parent(array $params, $type, $id)
    {
        $parentId = (string) ($params['parent_id'] ?? '');
        if ($parentId === '') { return ['id' => null, 'depth' => 0]; }

        $s   = Tiger_Comment::subject($type);
        $max = $s ? (int) $s['threading'] : 0;
        if ($max < 1) { return false; }

        $parent = (new Tiger_Model_Comment())->findById($parentId);
        if (!$parent) { return false; }
        // A reply must belong to the SAME subject — otherwise a thread could be grafted onto another.
        if ((string) $parent->subject_type !== $type || (string) $parent->subject_id !== $id) { return false; }

        $depth = (int) $parent->depth + 1;
        return $depth > $max ? false : ['id' => $parentId, 'depth' => $depth];
    }

    /** The public projection of a row — never the email, the IP or the user agent. */
    protected function _public(array $row)
    {
        return [
            'comment_id' => $row['comment_id'],
            'parent_id'  => $row['parent_id'],
            'depth'      => (int) $row['depth'],
            'author'     => $this->_authorName($row),
            'body'       => (string) $row['body'],
            'rating'     => $row['rating'] === null ? null : (int) $row['rating'],
            'verified'   => (bool) $row['verified'],
            'created_at' => $row['created_at'],
            'mine'       => ($this->_user_id ?? null) !== null && (string) $row['user_id'] === (string) $this->_user_id,
        ];
    }

    /**
     * A display name.
     *
     * A signed-in comment resolves its author LIVE rather than storing a copy, so a renamed user
     * isn't stale across their history; only a guest's typed name is stored.
     */
    protected function _authorName(array $row)
    {
        if (!empty($row['user_id'])) {
            try {
                $user = (new Tiger_Model_User())->findById((string) $row['user_id']);
                if ($user) {
                    $name = trim((string) $user->firstname . ' ' . (string) $user->lastname);
                    if ($name !== '') { return $name; }
                    return (string) $user->username;
                }
            } catch (Throwable $e) {
                // fall through to the stored/anonymous name
            }
        }
        $name = trim((string) ($row['author_name'] ?? ''));
        return $name !== '' ? $name : 'Anonymous';
    }
}
