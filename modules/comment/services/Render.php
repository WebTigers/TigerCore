<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Service_Render — the server-rendered shells the shortcodes emit.
 *
 * Renders the *container* (and, for stars/summary, the whole thing), never the thread itself: the
 * comments load from `/api` like every other list in Tiger, so a cached CMS page never bakes in a
 * stale thread. `[stars]` and `[rating_summary]` DO render server-side — they read one aggregate
 * row, they're what a crawler should see, and they must not flash in after paint.
 *
 * Not a `Tiger_Service_Service`: shortcodes call this in-process; the browser talks to
 * `Comment_Service_Comment`.
 */
class Comment_Service_Render
{
    /**
     * The comment thread mount point — an empty container the module's JS fills from `/api`.
     *
     * @param  string $subject "type:id"
     * @param  array  $options `title` (heading override)
     * @return string          the HTML ('' when the subject is unknown)
     */
    public function thread($subject, array $options = [])
    {
        [$type, $id] = $this->_split($subject);
        if ($type === '') { return ''; }

        $view  = $this->_view();
        $title = $options['title'] ?? $this->_t($type === '' || !Tiger_Comment::acceptsRatings($type)
            ? 'comment.heading.comments' : 'comment.heading.reviews');

        return '<section class="tiger-comments" data-comment-subject="' . $view->escape($type . ':' . $id) . '">'
             . '<h2 class="h5 mb-3">' . $view->escape((string) $title) . '</h2>'
             . '<div class="tiger-comments-feedback"></div>'
             . '<div class="tiger-comments-list"></div>'
             . '<div class="tiger-comments-form"></div>'
             . '</section>';
    }

    /**
     * A star row for a subject — rendered server-side from the aggregate.
     *
     * @param  string $subject "type:id"
     * @return string          the HTML ('' when unknown or unrated)
     */
    public function stars($subject)
    {
        [$type, $id] = $this->_split($subject);
        if ($type === '') { return ''; }

        $agg = (new Tiger_Model_CommentAggregate())->forSubject($type, $id);
        if ($agg['rating_count'] < 1) { return ''; }

        return $this->_view()->stars($agg['rating_avg'], ['count' => $agg['rating_count']]);
    }

    /**
     * The average + the 5→1 histogram, the block that sits beside a review list.
     *
     * @param  string $subject "type:id"
     * @return string          the HTML ('' when unknown or unrated)
     */
    public function summary($subject)
    {
        [$type, $id] = $this->_split($subject);
        if ($type === '') { return ''; }

        $agg = (new Tiger_Model_CommentAggregate())->forSubject($type, $id);
        if ($agg['rating_count'] < 1) { return ''; }

        $view  = $this->_view();
        $total = max(1, $agg['rating_count']);

        $bars = '';
        for ($star = 5; $star >= 1; $star--) {
            $n   = (int) $agg['stars'][$star];
            $pct = round(($n / $total) * 100);
            $bars .= '<div class="d-flex align-items-center gap-2 mb-1">'
                   . '<span class="text-body-secondary" style="width:3.5rem">' . $star . ' <i class="fa-solid fa-star"></i></span>'
                   . '<div class="progress flex-grow-1" role="progressbar"'
                   . ' aria-label="' . $view->escape($star . ' star') . '"'
                   . ' aria-valuenow="' . $view->escape((string) $pct) . '" aria-valuemin="0" aria-valuemax="100"'
                   . ' style="height:.5rem">'
                   . '<div class="progress-bar bg-warning" style="width:' . $view->escape((string) $pct) . '%"></div>'
                   . '</div>'
                   . '<span class="text-body-secondary" style="width:2.5rem">' . $view->escape((string) $n) . '</span>'
                   . '</div>';
        }

        return '<div class="tiger-rating-summary">'
             . '<div class="mb-2">' . $view->stars($agg['rating_avg'], ['count' => $agg['rating_count']]) . '</div>'
             . $bars
             . '</div>';
    }

    /** Split "type:id", refusing an unregistered type. @return array{0:string,1:string} */
    protected function _split($subject)
    {
        $parts = explode(':', (string) $subject, 2);
        $type  = $parts[0] ?? '';
        $id    = $parts[1] ?? '';
        if ($type === '' || $id === '' || !Tiger_Comment::subject($type)) { return ['', '']; }
        return [$type, $id];
    }

    /**
     * The themed view (helpers + escaping), or a bare one outside a request.
     *
     * A bare `Zend_View` does NOT know Tiger's helpers, so `stars()` would throw "Plugin by name
     * 'Stars' was not found" anywhere this runs without a booted front controller — a CLI render, a
     * queued job, a test. Register the path explicitly rather than assuming the bootstrap ran.
     */
    protected function _view()
    {
        if (Zend_Registry::isRegistered('Tiger_View')) { return Zend_Registry::get('Tiger_View'); }

        $view = new Zend_View();
        if (defined('TIGER_CORE_PATH')) {
            $view->addHelperPath(TIGER_CORE_PATH . '/library/Tiger/View/Helper', 'Tiger_View_Helper');
        }
        return $view;
    }

    /** Translate a key, falling back to the key itself. */
    protected function _t($key)
    {
        if (!Zend_Registry::isRegistered('Zend_Translate')) { return $key; }
        $t = Zend_Registry::get('Zend_Translate');
        return $t->isTranslated($key) ? $t->translate($key) : $key;
    }
}
