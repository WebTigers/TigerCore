<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backup_Service_Backup — the /api behind the Backup screen.
 *
 * `run` creates a manual backup now; `remove` deletes one (bytes + catalog row); `restore` restores
 * from a cataloged backup (guarded by a typed confirmation); `saveSettings` writes the tiger.backup.*
 * config tier (components/disk/retention/notify) that scheduled backups use. Download and upload-restore
 * are controller actions (file streams, not JSON) — see Backup_IndexController.
 *
 * Authorization (TIGER-511, modules/backup/configs/acl.ini): run/remove/saveSettings are admin; `restore`
 * and INCLUDING SECRETS (`Tiger_Backup_Data` / `include_secrets`) are superadmin — an archive holds every
 * org's data, and a restore replaces the install's.
 *
 * @api
 */
class Backup_Service_Backup extends Tiger_Service_Service
{
    /**
     * The capability over a backup's CONTENTS (superadmin in the shipped ACL): privileges `download`,
     * `restore` and `include_secrets`. Shared with Backup_IndexController, which gates its two stream
     * actions on it and computes the screen's show/hide flags from it.
     */
    const RESOURCE_DATA = 'Tiger_Backup_Data';

    /**
     * May the caller put the install's secrets into an archive? (Protected: not an /api method.)
     *
     * @return bool
     */
    protected function _mayIncludeSecrets(): bool
    {
        return $this->_isAllowed('include_secrets', self::RESOURCE_DATA);
    }

    /**
     * Did the request ask for secrets? (A truthy checkbox value; absent/''/'0'/'false' = no.)
     *
     * @param  array $params
     * @return bool
     */
    protected function _wantsSecrets(array $params): bool
    {
        $v = $params['include_secrets'] ?? '';
        return !empty($v) && $v !== '0' && $v !== 'false';
    }

    /**
     * Create a manual backup now.
     *
     * @param  array $params components[], disk, include_secrets
     * @return void
     */
    public function run(array $params): void
    {
        if (!$this->_isAllowed('run')) { $this->_error('core.api.error.not_allowed'); return; }

        $components = $this->_components($params);
        if (!$components) { $this->_error('backup.bad_component', ['field' => 'components']); return; }
        $disk = $this->_disk($params['disk'] ?? 'local');
        if ($disk === null) { $this->_error('backup.bad_disk', ['field' => 'disk']); return; }
        // Asking for secrets without the capability is REFUSED, never quietly downgraded to "no secrets"
        // — the caller should learn the archive they asked for is not the one they would get.
        $secrets = $this->_wantsSecrets($params);
        if ($secrets && !$this->_mayIncludeSecrets()) {
            $this->_error('backup.secrets.not_allowed', ['field' => 'include_secrets']);
            return;
        }

        @set_time_limit(0);
        $res = Tiger_Backup::create($components, [
            'disk'            => $disk,
            'source'          => 'manual',
            'include_secrets' => $secrets,
        ]);

        if (($res['status'] ?? '') === 'ok') {
            $this->_success(
                ['backup_id' => $res['backup_id'], 'filename' => $res['filename'], 'size' => Tiger_Backup::hsize($res['size'])],
                'backup.done'
            );
        } else {
            $this->_error(APPLICATION_ENV !== 'production' ? ('Backup failed: ' . ($res['error'] ?? '')) : 'backup.failed');
        }
    }

    /**
     * Delete a backup (archive bytes + catalog row).
     *
     * @param  array $params backup_id
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAllowed('remove')) { $this->_error('core.api.error.not_allowed'); return; }
        $id = (string) ($params['backup_id'] ?? '');
        if ($id === '' || !(new Tiger_Model_Backup())->findById($id)) { $this->_error('backup.not_found'); return; }
        Tiger_Backup::delete($id);
        $this->_success([], 'backup.deleted');
    }

    /**
     * Restore from a cataloged backup. Destructive — requires confirm === 'RESTORE'.
     *
     * @param  array $params backup_id, confirm, components[] (optional subset)
     * @return void
     */
    public function restore(array $params): void
    {
        if (!$this->_isAllowed('restore')) { $this->_error('core.api.error.not_allowed'); return; }
        if ((string) ($params['confirm'] ?? '') !== 'RESTORE') { $this->_error('backup.restore.confirm', ['field' => 'confirm']); return; }

        $model = new Tiger_Model_Backup();
        $row   = $model->findById((string) ($params['backup_id'] ?? ''));
        if (!$row) { $this->_error('backup.not_found'); return; }
        if (($row['outcome'] ?? '') !== 'ok') { $this->_error('backup.not_found'); return; }

        $components = $this->_components($params);   // empty = all present in the archive
        @set_time_limit(0);
        $tmp = null;
        try {
            $tmp = Tiger_Backup::fetchToTemp($row);
            $res = Tiger_Backup::restore($tmp, $components);
        } catch (Throwable $e) {
            $res = ['status' => 'error', 'error' => $e->getMessage()];
        }
        // fetchToTemp returns the file in place for local disk — only unlink a streamed cloud temp.
        if ($tmp && ($row['disk'] ?? '') !== 'local' && is_file($tmp)) { @unlink($tmp); }

        if (($res['status'] ?? '') === 'ok') {
            $this->_success(['restored' => $res['restored'], 'safety_id' => $res['safety_id'] ?? null], 'backup.restore.done');
        } else {
            $this->_error(APPLICATION_ENV !== 'production' ? ('Restore failed: ' . ($res['error'] ?? '')) : 'backup.restore.failed');
        }
    }

    /**
     * Save the scheduled-backup settings (config tier — live, no deploy).
     *
     * @param  array $params components[], disk, include_secrets, retention_max, notify_enabled, notify_email
     * @return void
     */
    public function saveSettings(array $params): void
    {
        if (!$this->_isAllowed('saveSettings')) { $this->_error('core.api.error.not_allowed'); return; }

        $components = $this->_components($params);
        $disk = $this->_disk($params['disk'] ?? 'local');
        if ($disk === null) { $this->_error('backup.bad_disk', ['field' => 'disk']); return; }
        // The scheduled backup's secrets switch belongs to whoever holds the capability. Without it, asking
        // to turn secrets ON is refused, and the stored value is left exactly as a superadmin set it (an
        // admin saving retention/notify must not silently flip it either way).
        $maySecrets = $this->_mayIncludeSecrets();
        if (!$maySecrets && $this->_wantsSecrets($params)) {
            $this->_error('backup.secrets.not_allowed', ['field' => 'include_secrets']);
            return;
        }
        $email = trim((string) ($params['notify_email'] ?? ''));
        if ($email !== '') {
            foreach (array_map('trim', explode(',', $email)) as $addr) {
                if ($addr !== '' && !Zend_Validate::is($addr, 'EmailAddress')) { $this->_error('backup.bad_email', ['field' => 'notify_email']); return; }
            }
        }

        $cfg = new Tiger_Model_Config();
        $g   = Tiger_Model_Config::SCOPE_GLOBAL;
        $cfg->set($g, '', 'tiger.backup.components', implode(',', $components ?: [Tiger_Backup::DATABASE]));
        $cfg->set($g, '', 'tiger.backup.disk', $disk);
        if ($maySecrets) {
            $cfg->set($g, '', 'tiger.backup.include_secrets', $this->_wantsSecrets($params) ? '1' : '0');
        }
        $cfg->set($g, '', 'tiger.backup.retention.max', (string) max(0, (int) ($params['retention_max'] ?? 7)));
        $cfg->set($g, '', 'tiger.backup.notify.enabled', !empty($params['notify_enabled']) ? '1' : '0');
        $cfg->set($g, '', 'tiger.backup.notify.email', $email);

        $this->_success([], 'backup.settings.saved');
    }

    // ----- helpers -----------------------------------------------------------

    /** Normalize + validate the components list (array or csv) against the known set. */
    protected function _components(array $params): array
    {
        $raw = $params['components'] ?? [];
        if (is_string($raw)) { $raw = explode(',', $raw); }
        $raw = array_map('trim', (array) $raw);
        return array_values(array_intersect(Tiger_Backup::COMPONENTS, $raw));
    }

    /** Validate a destination disk name against the configured destinations; null if invalid. */
    protected function _disk($name): ?string
    {
        $name = (string) $name;
        foreach (Tiger_Backup::disks() as $d) { if ($d['name'] === $name) { return $name; } }
        return null;
    }
}
