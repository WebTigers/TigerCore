<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_Stars — render a 1-5 star rating with HALF-STAR precision.
 *
 * `<?= $this->stars(4.3) ?>` → four full stars, one half, and the numeric average as text.
 *
 * Half stars come from AVERAGING, not from a half-star picker (COMMENTS.md §4): input is whole
 * stars, and only the average lands between them.
 *
 * **Accessibility is not optional here.** A row of glyphs conveys nothing to a screen reader and
 * nothing to anyone who can't distinguish the fill state, so the row is `role="img"` with a real
 * `aria-label`, and the numeric value is rendered as text beside it rather than encoded only in
 * icons. Stars alone are not an accessible rating.
 *
 * Pure markup + Font Awesome classes — no build step, and a skin restyles it via `--bs-*` like
 * everything else.
 *
 * @api
 * @since 1.5.0
 */
class Tiger_View_Helper_Stars extends Zend_View_Helper_Abstract
{
    /**
     * Render the star row.
     *
     * @param  float $average the rating average (0-5)
     * @param  array $options `count` (int, shown as "(12)"), `size` ('sm'|''), `show_value` (bool,
     *                        default true), `class` (extra classes on the wrapper)
     * @return string         the HTML ('' when there is nothing to show and no zero-state wanted)
     */
    public function stars($average = 0.0, array $options = [])
    {
        $avg   = Tiger_Comment::halfStar($average);
        $count = isset($options['count']) ? (int) $options['count'] : null;
        $show  = !isset($options['show_value']) || $options['show_value'];
        $size  = ($options['size'] ?? '') === 'sm' ? ' small' : '';
        $extra = trim((string) ($options['class'] ?? ''));

        $label = $count !== null
            ? sprintf('%s out of 5 stars, from %d ratings', $this->_num($avg), $count)
            : sprintf('%s out of 5 stars', $this->_num($avg));

        $icons = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($avg >= $i)            { $icons .= '<i class="fa-solid fa-star"></i>'; }
            elseif ($avg >= $i - 0.5)  { $icons .= '<i class="fa-solid fa-star-half-stroke"></i>'; }
            else                       { $icons .= '<i class="fa-regular fa-star"></i>'; }
        }

        $html = '<span class="tiger-stars text-warning" role="img" aria-label="'
              . $this->view->escape($label) . '">' . $icons . '</span>';

        if ($show) {
            $html .= '<span class="tiger-stars-value ms-1">' . $this->view->escape($this->_num($avg)) . '</span>';
        }
        if ($count !== null) {
            $html .= '<span class="tiger-stars-count text-body-secondary ms-1">('
                   . $this->view->escape((string) $count) . ')</span>';
        }

        $cls = trim('tiger-stars-wrap d-inline-flex align-items-center' . $size . ' ' . $extra);
        return '<span class="' . $this->view->escape($cls) . '">' . $html . '</span>';
    }

    /** One decimal place, but no trailing `.0` — "4.5" and "4", never "4.0". */
    protected function _num($avg)
    {
        return rtrim(rtrim(number_format((float) $avg, 1), '0'), '.');
    }
}
