<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_PageField — read a custom field value (Tiger_Fields) from a page on the front end.
 *
 * The render half of the custom-fields feature (the editor + Cms_Service_Page write the values into
 * `page.meta.fields.<group>.<field>`; this reads them back). Values are returned raw — escape at the
 * point of output, exactly like any other dynamic value in a view.
 *
 *   <?= $this->escape($this->pageField($page, 'listing.price')) ?>
 *   <?php if ($this->pageField($page, 'listing.featured')): ?> … <?php endif; ?>
 *
 * @api
 */
class Tiger_View_Helper_PageField extends Zend_View_Helper_Abstract
{
    /**
     * Read `<group>.<field>` from a page row's stored custom fields, or the default.
     *
     * @param  array|object $page    a page row (or its ->toArray())
     * @param  string       $path    "<group>.<field>"
     * @param  mixed        $default returned when unset
     * @return mixed
     */
    public function pageField($page, $path, $default = '')
    {
        if (!class_exists('Tiger_Fields')) {
            return $default;
        }
        return Tiger_Fields::value($page, $path, $default);
    }
}
