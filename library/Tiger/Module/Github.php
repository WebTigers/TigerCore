<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Module_Github — read GitHub repos over cURL. PUBLIC by default; PRIVATE when authorized.
 *
 * It pulls `module.json`/`theme.json` (the technical manifest) and `TIGER.md` (the vendor's human
 * description) as RAW files, resolves a pinned release ref, and downloads the release tarball. With no
 * credential resolver installed it is public-only, exactly as before — a private repo 404s on raw and
 * the installer treats it as "not installable". When an org-scoped resolver is installed via
 * {@see setAuthResolver()} (the registry wires it from authenticated sources), a request to a repo the
 * resolver yields a token for is sent with an `Authorization: Bearer` header, so a PRIVATE repo the
 * caller is authorized for becomes readable + installable from its authenticated tarball. Private
 * release-ZIP *assets* (vendored-bundle modules) are a documented follow-up — source/theme private
 * repos install from the tarball. A test transport ({@see setTransport()}) replaces the network.
 *
 * @api
 */
class Tiger_Module_Github
{
    const RAW = 'https://raw.githubusercontent.com';
    const API = 'https://api.github.com';
    const UA  = 'Tiger-Module-Installer';

    /** @var callable|null fn(string $org, string $repo): string — a bearer token for a repo, or '' (public) */
    protected static $authResolver = null;
    /** @var callable|null test seam: fn(string $url, array $headers, ?string $toFile): array{code:int,body:?string} */
    protected static $transport = null;

    /**
     * Install an org-scoped credential resolver. When set, {@see _http()} asks it for a bearer token for
     * the repo a request targets and, if one comes back non-empty, authenticates the request — so a
     * PRIVATE repo the caller is authorized for becomes readable/installable. Public repos (resolver
     * returns '') are fetched exactly as before. The registry wires this from authenticated sources;
     * null (the default) is public-only.
     */
    public static function setAuthResolver(?callable $resolver): void
    {
        self::$authResolver = $resolver;
    }

    /** Swap the HTTP transport — tests inject a fake to assert headers without the network. Null = real cURL. */
    public static function setTransport(?callable $transport): void
    {
        self::$transport = $transport;
    }

    /** The bearer token for a repo via the resolver (org-scoped), or '' when none/public. Never throws. */
    protected static function _tokenFor($org, $repo): string
    {
        if (!self::$authResolver) { return ''; }
        try { return (string) (self::$authResolver)((string) $org, (string) $repo); }
        catch (\Throwable $e) { return ''; }
    }

    /** Recognize the {org, repo} a GitHub URL targets (raw / api / archive / codeload), or null. */
    protected static function _repoFromUrl($url): ?array
    {
        $u = (string) $url;
        if (preg_match('~(?:raw\.githubusercontent\.com|codeload\.github\.com)/([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)~', $u, $m)
            || preg_match('~api\.github\.com/repos/([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)~', $u, $m)
            || preg_match('~github\.com/([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)/(?:archive|releases|tarball|zipball)~', $u, $m)) {
            return ['org' => $m[1], 'repo' => $m[2]];
        }
        return null;
    }

    /**
     * Parse a GitHub repo URL/slug → ['org','repo'], or null. Accepts …/org/repo(.git)(/…).
     *
     * @param  string $url a GitHub repo URL or an "org/repo" slug
     * @return array{org:string,repo:string}|null the parsed parts, or null if unrecognized
     */
    public static function parseRepo($url)
    {
        $url = trim((string) $url);
        if (preg_match('~github\.com[:/]+([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+?)(?:\.git)?(?:[/#?].*)?$~', $url, $m)
            || preg_match('~^([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)$~', $url, $m)) {
            return ['org' => $m[1], 'repo' => $m[2]];
        }
        return null;
    }

    /**
     * Fetch a raw file from a public repo at a ref (branch/tag/sha). Content string, or null.
     *
     * @param  string $org  the repo owner
     * @param  string $repo the repo name
     * @param  string $ref  the branch, tag, or sha
     * @param  string $path the file path within the repo
     * @return string|null the file contents, or null if unreachable
     */
    public static function fetchRaw($org, $repo, $ref, $path)
    {
        return self::_http(self::RAW . "/{$org}/{$repo}/{$ref}/" . ltrim((string) $path, '/'));
    }

    /**
     * The latest RELEASE tag (preferred — pinnable), else the default branch. Null if neither.
     *
     * @param  string $org  the repo owner
     * @param  string $repo the repo name
     * @return string|null the resolved ref, or null if neither is available
     */
    public static function latestRef($org, $repo)
    {
        $rel = self::_http(self::API . "/repos/{$org}/{$repo}/releases/latest", true);
        if ($rel) {
            $d = json_decode($rel, true);
            if (!empty($d['tag_name'])) { return $d['tag_name']; }
        }
        $meta = self::_http(self::API . "/repos/{$org}/{$repo}", true);
        if ($meta) {
            $d = json_decode($meta, true);
            if (!empty($d['default_branch'])) { return $d['default_branch']; }
        }
        return null;
    }

    /**
     * The GitHub API tarball endpoint for a ref — a 302 to a signed codeload URL (download() follows).
     *
     * We use the API endpoint (`api.github.com/repos/{org}/{repo}/tarball/{ref}`), NOT the web archive
     * path (`github.com/{org}/{repo}/archive/{ref}.tar.gz`): the web path 404s for a PRIVATE repo even
     * with a valid bearer token, whereas the API endpoint honours the token and 302s to a signed codeload
     * URL (which then needs no auth — curl drops the Authorization header on the cross-host redirect). The
     * API endpoint works for public repos too, so this one URL serves both the public directory and an
     * authenticated private/company source. (The licensed/authority path mints its own signed URL and does
     * not come through here.)
     *
     * @param  string $org  the repo owner
     * @param  string $repo the repo name
     * @param  string $ref  the branch, tag, or sha to archive
     * @return string the tarball download URL
     */
    public static function tarballUrl($org, $repo, $ref)
    {
        return self::API . "/repos/{$org}/{$repo}/tarball/" . rawurlencode((string) $ref);
    }

    /**
     * The download URL of a release's first `.zip` **asset** (an uploaded artifact), for a tag or the
     * latest release. A vendored-bundle module (e.g. an SDK provider, or a theme with licensed assets)
     * ships its `vendor/`/`assets/` inside a release ZIP asset — NOT the git source archive, which omits
     * gitignored build output. Callers prefer this over {@see tarballUrl} when an asset is present, and
     * fall back to the source tarball for source-only modules.
     *
     * @param  string  $org  the repo owner
     * @param  string  $repo the repo name
     * @param  ?string $ref  a release tag, or null for the latest release
     * @return string|null the asset's browser_download_url, or null when the release has no zip asset
     */
    public static function releaseAsset($org, $repo, $ref = null)
    {
        $url = $ref
            ? self::API . "/repos/{$org}/{$repo}/releases/tags/" . rawurlencode((string) $ref)
            : self::API . "/repos/{$org}/{$repo}/releases/latest";
        $body = self::_http($url, true);
        if (!$body) { return null; }
        $d = json_decode($body, true);
        foreach ($d['assets'] ?? [] as $a) {
            if (!empty($a['browser_download_url']) && preg_match('/\.zip$/i', (string) ($a['name'] ?? ''))) {
                return (string) $a['browser_download_url'];
            }
        }
        return null;
    }

    /**
     * Download a URL to a local file. Returns bool.
     *
     * @param  string $url      the URL to download
     * @param  string $destFile the local path to write to
     * @return bool true on success
     */
    public static function download($url, $destFile)
    {
        return (bool) self::_http($url, false, $destFile);
    }

    /**
     * GET any public URL (e.g. the Vendor Registry index). Body string, or null.
     *
     * @param  string $url the URL to fetch
     * @return string|null the response body, or null on failure
     */
    public static function get($url)
    {
        return self::_http($url);
    }

    /** HTTP GET via cURL (follows redirects, UA set; authenticates when a resolver yields a token for the
     *  target repo). Body string / true(to file) / null. A test transport, if set, replaces the network. */
    protected static function _http($url, $api = false, $toFile = null)
    {
        $headers = [];
        if ($api) { $headers[] = 'Accept: application/vnd.github+json'; }
        if ($r = self::_repoFromUrl($url)) {
            $token = self::_tokenFor($r['org'], $r['repo']);
            if ($token !== '') { $headers[] = 'Authorization: Bearer ' . $token; }
        }

        // Test seam: a fake transport captures the final headers (asserting auth) without the network.
        if (self::$transport) {
            $res  = (array) (self::$transport)((string) $url, $headers, $toFile);
            $code = (int) ($res['code'] ?? 0);
            if ($code < 200 || $code >= 300) { return null; }
            if ($toFile) { return @file_put_contents($toFile, (string) ($res['body'] ?? '')) !== false ? true : null; }
            return $res['body'] ?? null;
        }

        if (!function_exists('curl_init')) {
            $hdr  = $headers ? implode("\r\n", $headers) . "\r\n" : '';
            $ctx  = stream_context_create(['http' => ['user_agent' => self::UA, 'timeout' => 30, 'header' => $hdr]]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body === false) { return null; }
            if ($toFile) { return @file_put_contents($toFile, $body) !== false ? true : null; }
            return $body;
        }

        $ch = curl_init($url);
        $fh = null;
        $opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => self::UA,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($headers) { $opts[CURLOPT_HTTPHEADER] = $headers; }
        if ($toFile) {
            $fh = fopen($toFile, 'wb');
            $opts[CURLOPT_FILE] = $fh;
        } else {
            $opts[CURLOPT_RETURNTRANSFER] = true;
        }
        curl_setopt_array($ch, $opts);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // No curl_close(): on PHP 8+ the CurlHandle is freed by GC when $ch falls out of scope, and the
        // call is a deprecated no-op as of 8.5.
        if ($fh) { fclose($fh); }

        if ($code < 200 || $code >= 300) {
            if ($toFile) { @unlink($toFile); }
            return null;
        }
        return $toFile ? true : $res;
    }
}
