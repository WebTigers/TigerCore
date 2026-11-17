<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Service_Themecontent — the Theme content screen's /api (TIGER-455): what this server holds from the remote
 * theme-content source, and "Update now".
 *
 * Theme content (a theme's `content/` pages) publishes to S3/CloudFront the moment it is pushed, but a server only
 * picks it up on the scheduled `theme.content_fetch` tick (every 15 minutes) — so a just-published edit looked
 * broken for up to a quarter hour. `fetch()` runs that same `Tiger_Theme_Remote::sync()` now, on THIS server (the
 * scheduled job keeps every other node current on its own tick, as with TigerDocs' "Fetch content").
 *
 * `status()` reads only the local manifest — never the network; a fetch only ever happens on the explicit action.
 *
 * @api
 */
class System_Service_Themecontent extends Tiger_Service_Service
{
    /**
     * What this server currently serves: whether a remote source is configured, its URL, the files + bytes held,
     * the publish stamp of the manifest last installed, and when it was fetched.
     *
     * @param  array $params the request payload (unused)
     * @apiRequest none
     * @return void responds with {enabled,url,files,bytes,generated,synced_at,error}
     */
    public function status(array $params): void
    {
        if (!$this->_isAllowed('status', self::class)) { $this->_error('core.api.error.not_allowed'); return; }
        try {
            $this->_success(self::summary($this->_remote()));
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Fetch the theme content from the remote source into THIS server now ("Update now"). Reports the files checked,
     * updated and removed, and the manifest's publish stamp — so a server can't look current while serving older
     * bytes. Refused when no source is configured, and when a fetch is already running (the scheduled tick).
     *
     * @param  array $params the request payload
     * @apiRequest System_Form_ThemeContent
     * @return void responds with {files,changed,removed,generated,synced_at}
     */
    public function fetch(array $params): void
    {
        if (!$this->_isAllowed('fetch', self::class)) { $this->_error('core.api.error.not_allowed'); return; }

        // A fetch rewrites the theme files every public page renders — the screen's token is required.
        $form = new System_Form_ThemeContent();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        try {
            $remote = $this->_remote();
            if (!$remote->isEnabled()) { $this->_error('system.theme_content.no_source'); return; }

            $res = $remote->sync((string) ($params['force'] ?? '') === '1');
            if (!empty($res['busy']))      { $this->_error('system.theme_content.busy'); return; }
            if ((string) $res['error'] !== '') {
                $this->_error(APPLICATION_ENV !== 'production' ? (string) $res['error'] : 'system.theme_content.fetch_failed');
                return;
            }
            $status = self::summary($remote);
            $this->_success([
                'files'     => (int) $res['files'],
                'changed'   => (int) $res['changed'],
                'removed'   => (int) $res['removed'],
                'generated' => (string) $res['generated'],
                'synced_at' => (int) $status['synced_at'],
            ], 'system.theme_content.fetched_msg');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The screen's view of a remote: the status fields, without the server's filesystem path.
     *
     * @param  Tiger_Theme_Remote $remote
     * @return array{enabled:bool,url:string,files:int,bytes:int,generated:string,synced_at:int,error:string}
     */
    public static function summary(Tiger_Theme_Remote $remote): array
    {
        $s = $remote->status();
        return [
            'enabled'   => (bool) $s['enabled'],
            'url'       => (string) $s['url'],
            'files'     => (int) $s['files'],
            'bytes'     => (int) $s['bytes'],
            'generated' => (string) $s['generated'],
            'synced_at' => (int) $s['synced_at'],
            'error'     => (string) $s['error'],
        ];
    }

    /** The remote content source (a seam: tests substitute a double that never touches the network). */
    protected function _remote(): Tiger_Theme_Remote
    {
        return new Tiger_Theme_Remote();
    }
}
