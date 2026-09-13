<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Admin_Badge — resolve a registry item's `badge` to a count, fail-soft.
 *
 * Shared by Tiger_Admin_Nav and Tiger_Admin_Header. A `badge` is an int or, preferably, a callable:
 * the menus render on every admin page, so a count must be computed at render, for the signed-in
 * user, and only for an item that survived the ACL filter — a callable defers all three. It must be
 * cheap (an indexed count, a cached file read); never a network call. One that throws renders no
 * badge rather than breaking the menu.
 *
 * @api
 * @since 1.6.1
 */
class Tiger_Admin_Badge
{
    /**
     * @param  array $item a registry item; reads `badge`
     * @return int   0 means "no badge"
     */
    public static function resolve(array $item)
    {
        $b = $item['badge'] ?? null;
        if ($b === null) { return 0; }
        try {
            $n = is_callable($b) ? $b() : $b;
            return max(0, (int) $n);
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** The text for a pill: the count, capped so a runaway number cannot widen the menu. */
    public static function label($count)
    {
        return $count > 99 ? '99+' : (string) (int) $count;
    }
}
