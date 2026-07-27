<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Service_Settings — /api service for the Media Library settings screen.
 *
 * Validates Media_Form_Settings, then writes the filename-obfuscation choices to the `config`
 * table via Tiger_Model_Config — scoped to the acting ORG (else global for a single-tenant
 * install), so each tenant controls its own naming with no deploy. Config store only, no
 * separate settings table (config-discipline). ACL: admin+ (configs/acl.ini).
 *
 * @api
 */
class Media_Service_Settings extends Tiger_Service_Service
{
    /** The single admin-configurable cloud disk name + its config-key prefix (folds into media.disks.cloud.*). */
    const CLOUD_NAME   = 'cloud';
    const CLOUD_PREFIX = 'media.disks.cloud';

    /** Per-adapter persisted field lists (only these keys are written / read back). `cdn` = the public
     *  host (CloudFront / Cloud CDN / Azure CDN / custom domain) that PUBLIC media URLs are served from. */
    const STORAGE_SCHEMA = [
        's3'    => ['bucket', 'region', 'key', 'secret', 'endpoint', 'use_path_style', 'cdn'],
        'gcs'   => ['bucket', 'project_id', 'key_file', 'cdn'],
        'azure' => ['account', 'key', 'container', 'endpoint', 'cdn'],
    ];

    /** Fields required before a cloud adapter can be saved. */
    const STORAGE_REQUIRED = [
        's3'    => ['bucket'],
        'gcs'   => ['bucket'],
        'azure' => ['account', 'container'],
    ];

    /** Secret-bearing fields — never echoed to the browser, and preserved when re-saved blank. */
    const STORAGE_SECRETS = ['secret', 'key'];

    /**
     * Validate the settings form and persist the obfuscation flags (per visibility, org-scoped).
     *
     * @param  array $params the posted settings form values
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Media_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            list($scope, $sid) = Tiger_Model_Media::settingScope((string) ($this->_org_id ?? ''));
            $cfg->set($scope, $sid, Tiger_Model_Media::CFG_OBFUSCATE . 'public',  ((string) $v['obfuscate_public']  === '1') ? '1' : '0');
            $cfg->set($scope, $sid, Tiger_Model_Media::CFG_OBFUSCATE . 'private', ((string) $v['obfuscate_private'] === '1') ? '1' : '0');

            $this->_success([], 'media.settings.saved', '/media/admin/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Relocate existing media to another storage disk (after switching the default disk, or to leave a
     * cloud backend). Copy by default; `move` deletes each original after a verified copy. Synchronous +
     * resumable — a re-run continues where a prior run left off. Install-wide; admin-gated.
     *
     * @param  array $params expects `to` (target disk) + optional `move` ('1' to delete originals)
     * @return void
     */
    public function migrateDisk(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $to   = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($params['to'] ?? ''));
        $move = (string) ($params['move'] ?? '') === '1';
        if ($to === '') { $this->_error('media.migrate.no_disk'); return; }
        try {
            Tiger_Media_Storage::disk($to);   // validates the target disk is configured (throws otherwise)
        } catch (Throwable $e) {
            $this->_error('media.migrate.bad_disk'); return;
        }

        try {
            $summary = Tiger_Media_Migrator::migrate($to, $move);
            $this->_success(['summary' => $summary], $move ? 'media.migrate.moved' : 'media.migrate.copied');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Configure the cloud storage adapter (S3 / GCS / Azure) and which disk new uploads land on.
     *
     * Writes the chosen adapter + its fields to the `config` tier as `media.disks.cloud.*` (which the
     * storage factory reads and the migration screen lists) plus `media.default_disk`. `adapter=none`
     * means "local only" — it just points new uploads back at the local disk and leaves any cloud
     * config inert. Secret fields left blank are preserved (the browser never receives the stored
     * secret), so an admin can re-save without re-typing keys — and on AWS the S3 key/secret can stay
     * blank entirely to use the server's IAM instance role. Install-wide; admin-gated.
     *
     * @param  array $params expects `adapter` (none|s3|gcs|azure), that adapter's fields, and `default_disk`
     * @return void
     */
    public function saveStorage(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $adapter = $this->_storageAdapter($params);
        if ($adapter === null) { $this->_error('media.storage.bad_adapter'); return; }

        try {
            $cfg = new Tiger_Model_Config();
            $g   = Tiger_Model_Config::SCOPE_GLOBAL;

            if ($adapter === 'none') {
                // Local only: route new uploads to the local disk; leave any cloud config in place (inert).
                $cfg->set($g, '', 'media.default_disk', 'local');
                Tiger_Media_Storage::reset();
                $this->_success(['default_disk' => 'local'], 'media.storage.saved');
                return;
            }

            foreach (self::STORAGE_REQUIRED[$adapter] as $r) {
                if (trim((string) ($params[$r] ?? '')) === '') { $this->_error('media.storage.missing_field'); return; }
            }

            $storedAdapter = (string) $cfg->get($g, '', self::CLOUD_PREFIX . '.adapter');
            $cfg->set($g, '', self::CLOUD_PREFIX . '.adapter', $adapter);
            foreach (self::STORAGE_SCHEMA[$adapter] as $f) {
                $val = (string) ($params[$f] ?? '');
                // CDN is a bare host — strip any scheme/trailing slash so the adapter's https:// prefix is clean.
                if ($f === 'cdn') { $val = rtrim(preg_replace('#^https?://#i', '', trim($val)), '/'); }
                // A blank secret keeps the stored one (only when the adapter is unchanged) — don't wipe it.
                if ($val === '' && in_array($f, self::STORAGE_SECRETS, true) && $storedAdapter === $adapter) { continue; }
                $cfg->set($g, '', self::CLOUD_PREFIX . '.' . $f, $val);
            }

            $default = (string) ($params['default_disk'] ?? 'local') === self::CLOUD_NAME ? self::CLOUD_NAME : 'local';
            $cfg->set($g, '', 'media.default_disk', $default);

            Tiger_Media_Storage::reset();
            $this->_success(['adapter' => $adapter, 'default_disk' => $default], 'media.storage.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Test the storage adapter defined by the posted settings with a live round-trip (write→exists→delete
     * a tiny private probe object). Uses the entered values as-is, merging a stored secret in for a blank
     * secret field so an admin editing an existing disk needn't re-type it. Admin-gated; nothing persists.
     *
     * @param  array $params expects `adapter` + that adapter's fields (same shape as saveStorage)
     * @return void
     */
    public function testConnection(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $settings = $this->_storageSettings($params);
        if ($settings === null) { $this->_error('media.storage.bad_adapter'); return; }

        try {
            $disk  = Tiger_Media_Storage::make($settings);
            $probe = 'tiger-conn-test/' . uniqid('t', true) . '.txt';
            $vis   = Tiger_Model_Media::VISIBILITY_PRIVATE;
            $disk->write($probe, 'tiger-connection-test', $vis, 'text/plain');
            $ok = $disk->exists($probe, $vis);
            try { $disk->delete($probe, $vis); } catch (Throwable $e) { /* best-effort cleanup */ }
            if (!$ok) { $this->_error('media.storage.test_failed'); return; }
            $this->_success([], 'media.storage.test_ok');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'media.storage.test_failed');
        }
    }

    /** Sanitize + validate the posted adapter name ('none'|'s3'|'gcs'|'azure'), or null if unknown. */
    private function _storageAdapter(array $params): ?string
    {
        $adapter = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) ($params['adapter'] ?? '')));
        if ($adapter === 'none') { return 'none'; }
        return isset(self::STORAGE_SCHEMA[$adapter]) ? $adapter : null;
    }

    /**
     * Build a settings array (for make()) from posted params — only the adapter's known fields, with a
     * blank secret backfilled from the stored value when the adapter matches. Null on an unknown adapter.
     */
    private function _storageSettings(array $params): ?array
    {
        $adapter = $this->_storageAdapter($params);
        if ($adapter === null || $adapter === 'none') { return null; }

        $cfg           = new Tiger_Model_Config();
        $g             = Tiger_Model_Config::SCOPE_GLOBAL;
        $storedAdapter = (string) $cfg->get($g, '', self::CLOUD_PREFIX . '.adapter');

        $settings = ['adapter' => $adapter];
        foreach (self::STORAGE_SCHEMA[$adapter] as $f) {
            $val = (string) ($params[$f] ?? '');
            if ($val === '' && in_array($f, self::STORAGE_SECRETS, true) && $storedAdapter === $adapter) {
                $val = (string) $cfg->get($g, '', self::CLOUD_PREFIX . '.' . $f);
            }
            if ($val !== '') { $settings[$f] = $val; }
        }
        return $settings;
    }
}
