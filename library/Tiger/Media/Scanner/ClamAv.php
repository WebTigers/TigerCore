<?php
/**
 * Tiger_Media_Scanner_ClamAv — virus scan via ClamAV.
 *
 * Prefers the daemon client `clamdscan` (the DB stays resident in clamd — fast);
 * `--fdpass` hands clamd the open descriptor so it can read the PHP upload tmp file
 * regardless of its own user/permissions. Falls back to standalone `clamscan` (slow,
 * loads the signature DB each call) if the daemon client isn't present.
 *
 * ClamAV's resident signature DB needs ~1 GB RAM, so it won't run on the smallest
 * instances — enable `media.scan.clamav` only where clamd is available (see MEDIA.md).
 *
 * @api
 */
class Tiger_Media_Scanner_ClamAv implements Tiger_Media_Scanner_Interface
{
    public function scan(string $path, ?string $mime = null): array
    {
        $bin = $this->_binary();
        if ($bin === null) {
            return ['status' => 'error', 'reason' => 'clamav not installed', 'meta' => []];
        }

        $flags = ($bin === 'clamdscan') ? '--fdpass --no-summary' : '--no-summary';
        $cmd   = $bin . ' ' . $flags . ' ' . escapeshellarg($path) . ' 2>&1';
        $out   = [];
        $code  = 1;
        exec($cmd, $out, $code);
        $output = trim(implode("\n", $out));

        // clamscan/clamdscan exit codes: 0 = clean, 1 = infected, 2 = error.
        if ($code === 0) {
            return ['status' => 'clean', 'reason' => null, 'meta' => ['scanner' => $bin]];
        }
        if ($code === 1) {
            $sig = preg_match('/:\s*(.+?)\s+FOUND/', $output, $m) ? trim($m[1]) : 'malware';
            return ['status' => 'infected', 'reason' => $sig, 'meta' => ['scanner' => $bin, 'signature' => $sig]];
        }
        return ['status' => 'error', 'reason' => 'scan error', 'meta' => ['scanner' => $bin, 'output' => substr($output, 0, 300)]];
    }

    /** First available ClamAV client binary, or null. */
    protected function _binary(): ?string
    {
        foreach (['clamdscan', 'clamscan'] as $bin) {
            $which = [];
            exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $which, $code);
            if ($code === 0 && !empty($which)) {
                return $bin;
            }
        }
        return null;
    }
}
