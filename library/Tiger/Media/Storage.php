<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Media_Storage — resolves a configured storage disk to its adapter.
 *
 * Disks are declared in config (`media.disks.<name>.adapter` + settings) and referenced by
 * a media row's `disk`. `Tiger_Media_Storage::disk()` returns the (memoized) adapter for a
 * disk, defaulting to `media.default_disk`. Adding a backend is a new
 * Tiger_Media_Storage_Interface class + a case here.
 *
 * @api
 */
class Tiger_Media_Storage
{
    /** @var Tiger_Media_Storage_Interface[] memoized per disk name */
    protected static $_disks = [];

    /**
     * The adapter for a disk (default disk when null).
     *
     * @param  ?string $name the disk name, or null for the default disk
     * @return Tiger_Media_Storage_Interface the memoized adapter for the disk
     */
    public static function disk($name = null)
    {
        $name = $name ?: self::defaultDisk();
        if (!isset(self::$_disks[$name])) {
            self::$_disks[$name] = self::_build($name);
        }
        return self::$_disks[$name];
    }

    /**
     * The configured default disk name (`local` if unset).
     *
     * @return string the default disk name
     */
    public static function defaultDisk()
    {
        $media = self::_config();
        $d = $media ? (string) $media->get('default_disk') : '';
        return $d !== '' ? $d : 'local';
    }

    /**
     * Reset the memo (tests / after a config change).
     *
     * @return void
     */
    public static function reset()
    {
        self::$_disks = [];
    }

    /**
     * Build an adapter from an explicit settings array (bypassing the config disk lookup).
     *
     * Lets a caller instantiate an adapter from settings that aren't saved as a disk yet — e.g. a
     * "test connection" probe on the admin Storage screen — using the same adapter switch as the
     * config-driven {@see disk()} path. The array must carry an `adapter` key
     * (`filesystem`|`local`|`s3`|`gcs`|`azure`) plus that adapter's settings.
     *
     * @param  array $settings the adapter settings (incl. `adapter`)
     * @return Tiger_Media_Storage_Interface the adapter
     * @throws RuntimeException on an unknown/empty adapter
     */
    public static function make(array $settings)
    {
        $adapter = strtolower((string) ($settings['adapter'] ?? ''));

        switch ($adapter) {
            case 'filesystem':
            case 'local':
                return new Tiger_Media_Storage_Filesystem($settings);
            case 's3':
                return new Tiger_Media_Storage_S3($settings);
            case 'gcs':
                return new Tiger_Media_Storage_Gcs($settings);
            case 'azure':
                return new Tiger_Media_Storage_Azure($settings);
            default:
                throw new RuntimeException("Tiger_Media_Storage: unknown adapter '{$adapter}'.");
        }
    }

    protected static function _build($name)
    {
        $media = self::_config();
        $disks = $media ? $media->get('disks') : null;
        $conf  = $disks ? $disks->get($name) : null;
        if (!$conf) {
            throw new RuntimeException("Tiger_Media_Storage: no config for disk '{$name}' (media.disks.{$name}.*)");
        }
        try {
            return self::make($conf->toArray());
        } catch (RuntimeException $e) {
            // Re-throw with the disk name for a clearer message than make()'s adapter-only one.
            throw new RuntimeException("Tiger_Media_Storage: cannot build disk '{$name}' — " . $e->getMessage());
        }
    }

    /** The `media` config node, or null. */
    protected static function _config()
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        return $cfg ? $cfg->get('media') : null;
    }
}
