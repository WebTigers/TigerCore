<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Update_AutoUpdate — per-module auto-update opt-in (the WordPress "Enable auto-updates" toggle).
 *
 * Auto-update is OFF by default and strictly opt-in, per module: an admin turns it on for the modules
 * they trust to update unattended, and the daily update job then applies those (and only those) when a
 * new version is available — everything else stays manual ("Update now" on the Updates screen). The
 * opt-in set is a single site-wide option (a list of slugs, exactly like WordPress's
 * `auto_update_plugins`), so reading "what's due to auto-update" is one lookup.
 *
 * This is storage + policy only; applying an update is {@see System_Service_Updates}'s job. It names no
 * particular update channel — a module updated over GitHub, a provider ({@see Tiger_Update_Provider}),
 * or any other route is opted in the same way.
 *
 * @api
 */
class Tiger_Update_AutoUpdate
{
    /** The global option holding the opt-in slug list (JSON array). */
    const OPTION_KEY = 'update.autoupdate';

    /**
     * The slugs with auto-update turned on. Best-effort: returns [] when the option store is unavailable.
     *
     * @return string[]
     */
    public static function onSlugs(): array
    {
        try {
            $v = (new Tiger_Model_Option())->getJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPTION_KEY, []);
            return is_array($v) ? array_values(array_filter(array_map('strval', $v), 'strlen')) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Is auto-update turned on for this module slug?
     *
     * @param  string $slug the module slug
     * @return bool
     */
    public static function isOn(string $slug): bool
    {
        $slug = trim($slug);
        return $slug !== '' && in_array($slug, self::onSlugs(), true);
    }

    /**
     * Turn auto-update on or off for a module slug (idempotent). Returns the new state.
     *
     * @param  string $slug the module slug
     * @param  bool   $on    true to enable unattended updates, false to disable
     * @return bool          the resulting state (true = on)
     */
    public static function set(string $slug, bool $on): bool
    {
        $slug = trim($slug);
        if ($slug === '') { return false; }
        $slugs = self::onSlugs();
        $has   = in_array($slug, $slugs, true);
        if ($on && !$has)      { $slugs[] = $slug; }
        elseif (!$on && $has)  { $slugs = array_values(array_diff($slugs, [$slug])); }
        else                   { return $on; }   // no change
        (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPTION_KEY, array_values($slugs));
        return $on;
    }
}
