<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_AdminController — the Media Library admin settings screen (rendered in the admin shell).
 *
 * Thin: the settings action prefills Media_Form_Settings from the resolved config (org → global →
 * default) and renders; the save is an /api call to Media_Service_Settings. Built per ADMIN.md.
 */
class Media_AdminController extends Tiger_Controller_Admin_Action
{
    /** Admin layout comes from the base; keep the explicit init cascade. */
    public function init()
    {
        parent::init();
    }

    /** Media settings: filename obfuscation per file visibility. */
    public function settingsAction()
    {
        $orgId = $this->_orgId();
        $form  = new Media_Form_Settings();
        $form->populate([
            'obfuscate_public'  => Tiger_Model_Media::obfuscateEnabled(Tiger_Model_Media::VISIBILITY_PUBLIC,  $orgId) ? '1' : '0',
            'obfuscate_private' => Tiger_Model_Media::obfuscateEnabled(Tiger_Model_Media::VISIBILITY_PRIVATE, $orgId) ? '1' : '0',
        ]);

        $this->view->title = 'Media Settings — Tiger Admin';
        $this->view->form  = $form;

        // Storage-migration card: configured disks (media.disks.*), the current default, per-disk counts.
        $disks = [];
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $disksCfg = ($cfg && $cfg->get('media')) ? $cfg->get('media')->get('disks') : null;
        if ($disksCfg instanceof Zend_Config) { foreach ($disksCfg as $name => $c) { $disks[] = (string) $name; } }
        $counts = [];
        try {
            $db = (new Tiger_Model_Media())->getAdapter();
            foreach ($db->fetchAll($db->select()->from('media', ['disk', 'n' => new Zend_Db_Expr('COUNT(*)')])->group('disk')) as $r) {
                $counts[(string) $r['disk']] = (int) $r['n'];
            }
        } catch (Throwable $e) { /* fresh install / no media table yet */ }
        $this->view->disks       = $disks ?: ['local'];
        $this->view->defaultDisk = Tiger_Media_Storage::defaultDisk();
        $this->view->diskCounts  = $counts;

        // Storage tab: the configured cloud disk (media.disks.cloud.*), for prefill. Secrets are NEVER
        // echoed — only a boolean "a value is stored" so the field can show a "saved" placeholder.
        $cloud = ($disksCfg instanceof Zend_Config && $disksCfg->get(Media_Service_Settings::CLOUD_NAME) instanceof Zend_Config)
            ? $disksCfg->get(Media_Service_Settings::CLOUD_NAME)->toArray() : [];
        $this->view->storage = [
            'adapter'        => (string) ($cloud['adapter'] ?? 'none') ?: 'none',
            'bucket'         => (string) ($cloud['bucket'] ?? ''),
            'region'         => (string) ($cloud['region'] ?? 'us-east-1'),
            'endpoint'       => (string) ($cloud['endpoint'] ?? ''),
            'use_path_style' => (int) ($cloud['use_path_style'] ?? 0) === 1,
            'project_id'     => (string) ($cloud['project_id'] ?? ''),
            'key_file'       => (string) ($cloud['key_file'] ?? ''),
            'account'        => (string) ($cloud['account'] ?? ''),
            'container'      => (string) ($cloud['container'] ?? ''),
            'cdn'            => (string) ($cloud['cdn'] ?? ''),
            'has_key'        => !empty($cloud['key']),
            'has_secret'     => !empty($cloud['secret']),
        ];
        // Which cloud SDKs are installed — the Storage tab warns (not blocks) when one is missing.
        $this->view->sdk = [
            's3'    => class_exists('Aws\\S3\\S3Client'),
            'gcs'   => class_exists('Google\\Cloud\\Storage\\StorageClient'),
            'azure' => class_exists('MicrosoftAzure\\Storage\\Blob\\BlobRestProxy'),
        ];

        // Friendly disk labels for the migration dropdown — the exact service, not just "cloud".
        $adapterLabels = ['s3' => 'AWS S3', 'gcs' => 'Google Cloud Storage', 'azure' => 'Azure Blob Storage'];
        $labels = [];
        foreach ($this->view->disks as $d) {
            if ($d === 'local') {
                $labels[$d] = 'Local disk';
            } elseif ($d === Media_Service_Settings::CLOUD_NAME) {
                $labels[$d] = $adapterLabels[$this->view->storage['adapter']] ?? 'Cloud';
            } else {
                $labels[$d] = $d;
            }
        }
        $this->view->diskLabels = $labels;
    }

    /** The acting org id ('' when org-less / global). */
    protected function _orgId()
    {
        $idn = Zend_Auth::getInstance()->getIdentity();
        return ($idn && !empty($idn->org_id)) ? (string) $idn->org_id : '';
    }
}
