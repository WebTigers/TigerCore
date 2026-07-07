<?php
/**
 * Media_Service_Media — the /api service for the Media Library (upload / list / update /
 * delete). Thin + ACL-gated (admin+); the storage and metadata live in the engine
 * (Tiger_Media_Storage, Tiger_Model_Media). See MEDIA.md for the upload pipeline.
 *
 * Uploads are multipart POSTs to /api (module=media, service=media, method=upload) — one
 * file per request so the client can show per-file progress; the file rides in $_FILES.
 * Scan hooks (ClamAV / AI) are P4 and config-gated; here uploads are stored + recorded.
 */
class Media_Service_Media extends Tiger_Service_Service
{
    /** Receive one uploaded file: validate -> store -> record. Returns the media row. */
    public function upload(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        if (!$file || !is_uploaded_file((string) $file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->_error('media.error.upload'); return;
        }

        $original = (string) $file['name'];
        $tmp      = (string) $file['tmp_name'];
        $size     = (int) $file['size'];
        $ext      = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

        $max = $this->_cfgInt('max_upload', 52428800);
        if ($size > $max) { $this->_error('media.error.too_large'); return; }

        $class = Tiger_Model_Media::classify($ext);
        if (!$class['allowed']) { $this->_error('media.error.type'); return; }

        // TODO(P4): virus scan (media.scan.clamav) + AI image review (media.scan.image) here,
        // before store; reject with a message on infected / over-threshold.

        $mime = $this->_mime($tmp, $ext);
        $visibility = (($params['visibility'] ?? 'public') === Tiger_Model_Media::VISIBILITY_PRIVATE)
            ? Tiger_Model_Media::VISIBILITY_PRIVATE : Tiger_Model_Media::VISIBILITY_PUBLIC;

        // Opaque, collision-free storage key sharded by month: 2026/07/<random>.<ext>
        $key  = date('Y/m') . '/' . bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
        $disk = Tiger_Media_Storage::defaultDisk();

        try {
            Tiger_Media_Storage::disk($disk)->put($key, $tmp, $visibility, $mime);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general'); return;
        }

        $dims = ($class['kind'] === Tiger_Model_Media::KIND_IMAGE) ? @getimagesize($tmp) : false;

        $model = new Tiger_Model_Media();
        try {
            $id = $model->insert([
                'org_id'      => $this->_orgId(),
                'locale'      => '',
                'disk'        => $disk,
                'storage_key' => $key,
                'visibility'  => $visibility,
                'kind'        => $class['kind'],
                'mime_type'   => $mime,
                'extension'   => $ext,
                'file_size'   => $size,
                'checksum'    => @hash_file('sha256', $tmp) ?: null,
                'width'       => $dims ? (int) $dims[0] : null,
                'height'      => $dims ? (int) $dims[1] : null,
                'filename'    => $original,
                'title'       => (string) pathinfo($original, PATHINFO_FILENAME),
                'scan_status' => Tiger_Model_Media::SCAN_SKIPPED,
            ]);
        } catch (Throwable $e) {
            Tiger_Media_Storage::disk($disk)->delete($key, $visibility);   // don't orphan the bytes
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general'); return;
        }

        // TODO(P2): generate image variants (Tiger_Media_Image) and write the variants JSON.

        $this->_success(['media' => $this->_present($model->findById($id))], 'media.uploaded');
    }

    /** DataTables source for the Library grid (thumbnails + metadata). */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt   = $this->_dtParams($params);
        $kinds = [Tiger_Model_Media::KIND_IMAGE, Tiger_Model_Media::KIND_DOCUMENT, Tiger_Model_Media::KIND_PDF,
                  Tiger_Model_Media::KIND_VIDEO, Tiger_Model_Media::KIND_AUDIO, Tiger_Model_Media::KIND_ARCHIVE, Tiger_Model_Media::KIND_OTHER];
        $data = (new Tiger_Model_Media())->datatable([
            'search'   => $dt['search'],
            'kind'     => in_array(($params['kind'] ?? ''), $kinds, true) ? (string) $params['kind'] : '',
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);

        $canDelete = $this->_isAdmin(static::class, 'delete');
        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = $this->_present($r) + ['can_delete' => $canDelete];
        }
        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /** Edit editorial fields (title / caption / alt / visibility). */
    public function update(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id = (string) ($params['media_id'] ?? '');
        $model = new Tiger_Model_Media();
        if ($id === '' || !$model->findById($id)) { $this->_error('core.api.error.general'); return; }

        $data = [];
        foreach (['title', 'caption', 'alt_text'] as $f) {
            if (array_key_exists($f, $params)) { $data[$f] = trim((string) $params[$f]); }
        }
        if (isset($params['visibility'])) {
            $data['visibility'] = ($params['visibility'] === Tiger_Model_Media::VISIBILITY_PRIVATE)
                ? Tiger_Model_Media::VISIBILITY_PRIVATE : Tiger_Model_Media::VISIBILITY_PUBLIC;
        }
        if (!$data) { $this->_error('core.api.error.general'); return; }

        try {
            $model->update($data, $model->getAdapter()->quoteInto('media_id = ?', $id));
            $this->_success(['media' => $this->_present($model->findById($id))], 'media.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** Soft-delete the row AND remove the stored bytes (+ variants). */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id  = (string) ($params['media_id'] ?? '');
        $model = new Tiger_Model_Media();
        $row = $id !== '' ? $model->findById($id) : null;
        if (!$row) { $this->_error('core.api.error.general'); return; }
        $media = $row->toArray();

        try {
            $adapter = Tiger_Media_Storage::disk($media['disk']);
            $adapter->delete($media['storage_key'], $media['visibility']);
            foreach ($model->variants($media) as $v) {
                if (!empty($v['key'])) { $adapter->delete($v['key'], $media['visibility']); }
            }
        } catch (Throwable $e) {
            // bytes may already be gone — proceed to soft-delete the row regardless
        }
        $model->softDelete($model->getAdapter()->quoteInto('media_id = ?', $id));
        $this->_success(['media_id' => $id], 'media.deleted');
    }

    /** Shape a media row for the client (adds URLs; hides storage internals). */
    protected function _present($row)
    {
        $m = is_array($row) ? $row : $row->toArray();
        $model = new Tiger_Model_Media();
        return [
            'media_id'   => $m['media_id'],
            'kind'       => $m['kind'],
            'mime_type'  => $m['mime_type'],
            'extension'  => $m['extension'],
            'file_size'  => (int) $m['file_size'],
            'filename'   => $m['filename'],
            'title'      => $m['title'],
            'caption'    => $m['caption'] ?? null,
            'alt_text'   => $m['alt_text'] ?? null,
            'visibility' => $m['visibility'],
            'width'      => isset($m['width']) ? (int) $m['width'] : null,
            'height'     => isset($m['height']) ? (int) $m['height'] : null,
            'scan_status'=> $m['scan_status'] ?? null,
            'url'        => $model->url($m),
            'thumb'      => $model->thumbUrl($m),
        ];
    }

    /** The uploading admin's org scope ('' when org-less / global). */
    protected function _orgId()
    {
        $idn = Zend_Auth::getInstance()->getIdentity();
        return ($idn && !empty($idn->org_id)) ? (string) $idn->org_id : '';
    }

    /** Best-effort MIME from the file (finfo), falling back to the extension map. */
    protected function _mime($tmp, $ext)
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $m  = $fi ? finfo_file($fi, $tmp) : false;
            if ($fi) { finfo_close($fi); }
            if ($m) { return (string) $m; }
        }
        return 'application/octet-stream';
    }

    protected function _cfgInt($key, $default)
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $media = $cfg ? $cfg->get('media') : null;
        return ($media && $media->get($key) !== null) ? (int) $media->get($key) : $default;
    }
}
