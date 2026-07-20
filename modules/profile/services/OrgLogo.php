<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_OrgLogo — self-service org logo upload for the org profile Basic tab.
 *
 * The org twin of Profile_Service_Avatar: admin-gated and scoped to the CURRENT org. Accepts the
 * ALREADY-CROPPED image (Cropper.js exports the canvas client-side — the server never crops), stores
 * it as a public image through the media subsystem, and links it to the org via the per-ORG `option`
 * tier (`tiger.org.logo` = media_id) — the endorsed home for a logo reference (ARCHITECTURE §7),
 * never a column on the thin org row.
 *
 * (Shares the store/validate shape with Profile_Service_Avatar; a future refactor may hoist the
 * common media plumbing into a shared base once both upload paths are settled.)
 *
 * @api
 */
class Profile_Service_OrgLogo extends Tiger_Service_Service
{
    /** option(scope=org, scope_id=<org_id>) key holding the logo's media_id. */
    const OPTION_KEY = 'tiger.org.logo';

    /** Hard cap — a cropped logo is small; this is just a sanity ceiling. */
    const MAX_BYTES = 5242880;   // 5 MB

    /** Accepted image types → canonical extension. */
    const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * Store the cropped logo for the current org and link it via the option tier.
     *
     * @param  array $params (file rides in $_FILES['file'])
     * @return void
     */
    public function upload(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }

        $file = $_FILES['file'] ?? null;
        if (!$file || !is_uploaded_file((string) $file['tmp_name']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->_error('profile.org.logo.error_upload'); return;
        }
        $tmp  = (string) $file['tmp_name'];
        $size = (int) $file['size'];
        if ($size > self::MAX_BYTES) { $this->_error('profile.org.logo.error_large'); return; }

        $dims = @getimagesize($tmp);
        $mime = $dims ? (string) ($dims['mime'] ?? '') : '';
        if (!$dims || !isset(self::TYPES[$mime])) { $this->_error('profile.org.logo.error_type'); return; }
        $ext  = self::TYPES[$mime];

        $disk = Tiger_Media_Storage::defaultDisk();
        $org  = preg_replace('/[^a-zA-Z0-9-]/', '', $orgId) ?: '_shared';
        $key  = $org . '/' . Tiger_Model_Media::kindFolder(Tiger_Model_Media::KIND_IMAGE)
              . '/logo-' . $orgId . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

        // Store bytes first, then the DB writes; delete the bytes if the DB half fails (media pattern).
        try {
            Tiger_Media_Storage::disk($disk)->put($key, $tmp, Tiger_Model_Media::VISIBILITY_PUBLIC, $mime);
        } catch (Throwable $e) {
            Tiger_Log::error('profile.org.logo.store_failed', ['org' => $orgId, 'key' => $key, 'error' => $e->getMessage()]);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general'); return;
        }

        try {
            $mediaId = $this->_transaction(function () use ($disk, $key, $mime, $ext, $size, $dims, $orgId) {
                $id = (new Tiger_Model_Media())->insert([
                    'org_id'      => $orgId,
                    'locale'      => '',
                    'disk'        => $disk,
                    'storage_key' => $key,
                    'visibility'  => Tiger_Model_Media::VISIBILITY_PUBLIC,
                    'kind'        => Tiger_Model_Media::KIND_IMAGE,
                    'mime_type'   => $mime,
                    'extension'   => $ext,
                    'file_size'   => $size,
                    'width'       => (int) $dims[0],
                    'height'      => (int) $dims[1],
                    'filename'    => 'logo.' . $ext,
                    'title'       => 'Organization logo',
                ]);
                (new Tiger_Model_Option())->set(Tiger_Model_Option::SCOPE_ORG, $orgId, self::OPTION_KEY, $id);
                return $id;
            });
        } catch (Throwable $e) {
            Tiger_Media_Storage::disk($disk)->delete($key, Tiger_Model_Media::VISIBILITY_PUBLIC);
            Tiger_Log::error('profile.org.logo.db_failed', ['org' => $orgId, 'error' => $e->getMessage()]);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general'); return;
        }

        $row = (new Tiger_Model_Media())->findById($mediaId);
        $this->_success(
            ['media_id' => $mediaId, 'url' => $row ? (new Tiger_Model_Media())->url($row->toArray()) : ''],
            'profile.org.logo.saved'
        );
    }

    /**
     * Clear the current org's logo (unlink the option; the media row is left in place).
     *
     * @param  array $params (unused)
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }
        (new Tiger_Model_Option())->forget(Tiger_Model_Option::SCOPE_ORG, $orgId, self::OPTION_KEY);
        $this->_success([], 'profile.org.logo.removed');
    }
}
