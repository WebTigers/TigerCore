<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media — the file store's metadata (see migration 0018 + MEDIA.md).
 *
 * The bytes live behind a Tiger_Media_Storage adapter (by `disk` + `storage_key`); this
 * model is the queryable metadata gateway: it classifies uploads by kind, owns the library
 * query, and resolves URLs (a public object's direct/CDN URL, else the ACL-checked streamer
 * route). Variants (thumbnails, previews) are a JSON map of derivative keys.
 *
 * @api
 */
class Tiger_Model_Media extends Tiger_Model_Table
{
    protected $_name    = 'media';
    protected $_primary = 'media_id';

    const KIND_IMAGE    = 'image';
    const KIND_DOCUMENT = 'document';
    const KIND_PDF      = 'pdf';
    const KIND_VIDEO    = 'video';
    const KIND_AUDIO    = 'audio';
    const KIND_ARCHIVE  = 'archive';
    const KIND_OTHER    = 'other';

    /** Storage sub-folder per kind (tenant path grouping) — see kindFolder(). */
    const KIND_FOLDERS = [
        self::KIND_IMAGE    => 'images',
        self::KIND_VIDEO    => 'videos',
        self::KIND_AUDIO    => 'audio',
        self::KIND_PDF      => 'docs',
        self::KIND_DOCUMENT => 'docs',
        self::KIND_ARCHIVE  => 'files',
        self::KIND_OTHER    => 'files',
    ];

    const VISIBILITY_PUBLIC  = 'public';
    const VISIBILITY_PRIVATE = 'private';

    // Filename obfuscation (config, org-scoped). '1' = random storage key; '0' = readable slug.
    // Default: private obfuscated, public readable (obfuscateDefault()).
    const CFG_OBFUSCATE = 'tiger.media.obfuscate.';   // + 'public' | 'private'

    const SCAN_SKIPPED   = 'skipped';
    const SCAN_PENDING   = 'pending';
    const SCAN_CLEAN     = 'clean';
    const SCAN_INFECTED  = 'infected';
    const SCAN_REJECTED  = 'rejected';
    const SCAN_IN_REVIEW = 'in_review';
    const SCAN_APPROVED  = 'approved';

    /**
     * The config scope for a tenant's media settings: per-org when the identity acts in an org,
     * else global (single-tenant / org-less install).
     *
     * @param  string $orgId the acting org id ('' = global)
     * @return array [scope, scopeId]
     */
    public static function settingScope($orgId)
    {
        return ($orgId !== '' && $orgId !== null)
            ? [Tiger_Model_Config::SCOPE_ORG, (string) $orgId]
            : [Tiger_Model_Config::SCOPE_GLOBAL, ''];
    }

    /**
     * Default obfuscation when nothing is stored: private files are obfuscated (random key, no
     * info leak), public files are readable (SEO / shareable).
     *
     * @param  string $visibility 'public' | 'private'
     * @return bool true = obfuscate, false = readable slug
     */
    public static function obfuscateDefault($visibility)
    {
        return $visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * Whether new uploads of this visibility get an obfuscated (random) storage key. Resolves the
     * org config, then the global config, then the built-in default.
     *
     * @param  string $visibility 'public' | 'private'
     * @param  string $orgId      the uploader's org id
     * @return bool
     */
    public static function obfuscateEnabled($visibility, $orgId)
    {
        $key = self::CFG_OBFUSCATE . ($visibility === self::VISIBILITY_PRIVATE ? 'private' : 'public');
        $cfg = new Tiger_Model_Config();
        list($scope, $sid) = self::settingScope($orgId);
        $val = $cfg->get($scope, $sid, $key);
        if ($val === null && $scope !== Tiger_Model_Config::SCOPE_GLOBAL) {
            $val = $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', $key);   // org falls back to global
        }
        if ($val === null) {
            return self::obfuscateDefault($visibility);
        }
        return $val === '1' || $val === 1 || $val === 'true';
    }

    /**
     * The storage-key basename (no folder, no extension) for an upload: a random hex when
     * obfuscated, else a slugified original filename + a short random suffix (readable + unique).
     *
     * @param  string $original  the original upload filename
     * @param  bool   $obfuscate obfuscate (random) or readable
     * @return string the basename
     */
    public static function storageBase($original, $obfuscate)
    {
        $rand = bin2hex(random_bytes(16));
        if ($obfuscate) {
            return $rand;
        }
        $slug = substr(self::slugify((string) pathinfo($original, PATHINFO_FILENAME)), 0, 80);
        return $slug !== '' ? ($slug . '-' . substr($rand, 0, 8)) : $rand;
    }

    /**
     * Slugify to a lowercase, filesystem/URL-safe token ('' if nothing survives).
     *
     * @param  string $s the input
     * @return string the slug
     */
    public static function slugify($s)
    {
        $s = strtolower((string) $s);
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) { $s = $t; }
        }
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim((string) $s, '-');
    }

    /**
     * Classify an upload by extension against the configured allowlists (`media.allow.*`).
     * Returns the kind and whether it's allowed at all. `pdf` is split out of documents so
     * it gets the pdf.js preview path.
     *
     * @param  string $extension the upload's file extension (with or without leading dot)
     * @return array{kind:string, allowed:bool}
     */
    public static function classify($extension)
    {
        $ext = strtolower(ltrim((string) $extension, '.'));
        if ($ext === 'pdf') {
            return ['kind' => self::KIND_PDF, 'allowed' => self::_extAllowed('document', 'pdf')];
        }
        $groups = [
            self::KIND_IMAGE   => 'image',
            self::KIND_VIDEO   => 'video',
            self::KIND_AUDIO   => 'audio',
            self::KIND_ARCHIVE => 'archive',
        ];
        foreach ($groups as $kind => $group) {
            if (self::_extAllowed($group, $ext)) {
                return ['kind' => $kind, 'allowed' => true];
            }
        }
        if (self::_extAllowed('document', $ext)) {
            return ['kind' => self::KIND_DOCUMENT, 'allowed' => true];
        }
        return ['kind' => self::KIND_OTHER, 'allowed' => false];
    }

    /**
     * The storage sub-folder for a kind — groups a tenant's files by type under its org path
     * (`<org>/images/…`, `/videos/…`, `/docs/…`, `/files/…`): pdf+document → docs, archive+other
     * → files. Used to build the storage key; the adapter adds the visibility root (public/ |
     * private/) on top.
     *
     * @param  string $kind a KIND_* constant
     * @return string the storage sub-folder name
     */
    public static function kindFolder($kind)
    {
        return self::KIND_FOLDERS[$kind] ?? 'files';
    }

    /** Is $ext in the `media.allow.<group>` list? */
    protected static function _extAllowed($group, $ext)
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $allow = ($cfg && $cfg->get('media') && $cfg->media->get('allow')) ? (string) $cfg->media->allow->get($group) : '';
        if ($allow === '') {
            return false;
        }
        return in_array(strtolower($ext), array_map('trim', explode(',', strtolower($allow))), true);
    }

    /**
     * A usable URL for a media item (or one of its variants): the adapter's direct/CDN URL
     * for public objects, else the ACL-checked streamer route. Accepts a row array.
     *
     * @param array       $media   a media row (array)
     * @param string|null $variant a variants key (e.g. 'thumbnail'); null/'original' = the file itself
     * @return string the resolved URL (direct/CDN or streamer route), or '' if none
     */
    public function url(array $media, $variant = null)
    {
        $visibility = (string) ($media['visibility'] ?? self::VISIBILITY_PUBLIC);
        $key        = (string) ($media['storage_key'] ?? '');

        if ($variant !== null && $variant !== 'original') {
            $variants = $this->variants($media);
            if (!empty($variants[$variant]['key'])) {
                $key = (string) $variants[$variant]['key'];
            } else {
                $variant = null;   // no such variant -> fall back to the original
            }
        }
        if ($key === '') {
            return '';
        }

        try {
            $direct = Tiger_Media_Storage::disk($media['disk'] ?? null)->url($key, $visibility);
        } catch (Throwable $e) {
            $direct = '';
        }
        if ($direct !== '') {
            return $direct;
        }
        // Private (or an adapter that can't mint a URL) -> stream through the ACL route
        // (Media_FileController::serveAction; zero-config module path).
        $route = '/media/file/serve/id/' . rawurlencode((string) ($media['media_id'] ?? ''));
        if ($variant !== null && $variant !== 'original') {
            $route .= '/v/' . rawurlencode($variant);
        }
        return $route;
    }

    /**
     * Decode the variants JSON to an array.
     *
     * @param  array $media a media row (array)
     * @return array the variants map (derivative key => descriptor)
     */
    public function variants(array $media)
    {
        $v = $media['variants'] ?? null;
        if (is_array($v)) {
            return $v;
        }
        return $v ? (array) json_decode((string) $v, true) : [];
    }

    /**
     * The best display URL for a thumbnail-sized preview (thumbnail variant, else original).
     *
     * @param  array $media a media row (array)
     * @return string the thumbnail (or original) URL
     */
    public function thumbUrl(array $media)
    {
        $variants = $this->variants($media);
        return isset($variants['thumbnail']) ? $this->url($media, 'thumbnail') : $this->url($media);
    }

    /**
     * DataTables source for the Media Library: kind filter, search across filename/title/
     * caption, sort, paginate. Query lives here; the service formats + ACL-gates.
     *
     * @param  array $opts query options (search, kind, limit, offset, orderCol, orderDir)
     * @return array{total:int,filtered:int,rows:array}
     */
    public function datatable(array $opts)
    {
        $db     = $this->getAdapter();
        $search = (string) ($opts['search'] ?? '');
        $kind   = (string) ($opts['kind'] ?? '');
        $limit  = max(1, (int) ($opts['limit'] ?? 24));
        $offset = max(0, (int) ($opts['offset'] ?? 0));

        $orderCols = [1 => 'filename', 2 => 'kind', 3 => 'file_size', 4 => 'created_at'];
        $col = (int) ($opts['orderCol'] ?? -1);
        $dir = (strtoupper((string) ($opts['orderDir'] ?? '')) === 'ASC') ? 'ASC' : 'DESC';
        $orderSql = isset($orderCols[$col]) ? ($orderCols[$col] . ' ' . $dir) : 'created_at DESC';

        $scope = function ($sel) use ($kind) {
            $sel->where('deleted = 0');
            if ($kind !== '') { $sel->where('kind = ?', $kind); }
        };
        $searchFn = function ($sel) use ($db, $search) {
            if ($search === '') { return; }
            $like  = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $parts = [];
            foreach (['filename', 'title', 'caption'] as $c) { $parts[] = $db->quoteInto("$c LIKE ?", $like); }
            $sel->where('(' . implode(' OR ', $parts) . ')');
        };

        $totalSel = $db->select()->from($this->_name, ['c' => new Zend_Db_Expr('COUNT(*)')]);
        $scope($totalSel);
        $total = (int) $db->fetchOne($totalSel);

        $filteredSel = $db->select()->from($this->_name, ['c' => new Zend_Db_Expr('COUNT(*)')]);
        $scope($filteredSel); $searchFn($filteredSel);
        $filtered = (int) $db->fetchOne($filteredSel);

        $rowsSel = $db->select()
            ->from($this->_name, ['media_id', 'disk', 'storage_key', 'visibility', 'kind', 'mime_type',
                'extension', 'file_size', 'width', 'height', 'filename', 'title', 'caption', 'alt_text',
                'variants', 'scan_status', 'created_at'])
            ->order(new Zend_Db_Expr($orderSql))
            ->limit($limit, $offset);
        $scope($rowsSel); $searchFn($rowsSel);

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $db->fetchAll($rowsSel)];
    }
}
