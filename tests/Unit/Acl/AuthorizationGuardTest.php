<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Acl;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;

/**
 * The TIGER-335 structural guards — the "the F5 class cannot silently return" rails.
 *
 * 1. No module `/api` service decides authorization by comparing a ROLE STRING: no `_isAdmin()`,
 *    `_isAtLeastAdmin()`, `isAtLeastAdmin()`, `inheritsRole()`, or a `=== 'admin'`-style role-literal
 *    comparison (the removed comparers + the ARCHITECTURE §8 anti-pattern). Detection is token-based,
 *    so a role name mentioned in a COMMENT or docblock never trips it — and the detector is itself
 *    mutation-tested below (a validator that only ever passes proves nothing).
 * 2. No `acl.ini` grants `guest` a BLANKET (privilege-less) rule on a `*_Service_*` resource — the
 *    exact shape that let TIGER-114/F5 reach an admin-only method anonymously.
 */
final class AuthorizationGuardTest extends UnitTestCase
{
    private const ROLES = ['guest', 'user', 'manager', 'supermanager', 'admin', 'superadmin', 'developer'];

    private static function repo(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Source with comments/docblocks blanked (line numbers preserved), so only CODE tokens are scanned. */
    private static function codeOnly(string $php): string
    {
        $out = '';
        foreach (token_get_all($php) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($t[1], "\n"));
                    continue;
                }
                $out .= $t[1];
            } else {
                $out .= $t;
            }
        }
        return $out;
    }

    /** The role-string-authorization smells, as applied to code-only source. Returns matched snippets. */
    private static function offences(string $php): array
    {
        $code  = self::codeOnly($php);
        $roles = implode('|', self::ROLES);
        $hits  = [];
        $patterns = [
            '/\b_isAdmin\s*\(/',
            '/\b_isAtLeastAdmin\s*\(/',
            '/\bisAtLeastAdmin\s*\(/',
            '/->\s*inheritsRole\s*\(/',
            '/(===|!==|==|!=)\s*[\'"](' . $roles . ')[\'"]/',
            '/[\'"](' . $roles . ')[\'"]\s*(===|!==|==|!=)/',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $code, $m)) {
                $hits = array_merge($hits, $m[0]);
            }
        }
        return array_merge($hits, self::roleListOffences($php));
    }

    /**
     * The role-LIST smells (TIGER-511), as applied to code-only source. Narrower than offences() — no
     * single `=== 'user'` compare, because outside a service 'user'/'guest' are also chat-message roles
     * and auth-state words — so it is safe to run over the whole library:
     *   - an `in_array(<anything>, [... 'admin' ...])` membership test, whatever the first argument looks
     *     like — `$role`, or the inline `(string) ($identity->role ?? '')` shape that slipped past the
     *     original `\$\w*role\w*` pattern;
     *   - an array literal made ONLY of role names (two or more) — the `const ADMIN_HOME_ROLES = [...]` /
     *     `private static $_privileged = [...]` shape, which is a role compare the moment anything
     *     `in_array()`s against it, however far away that happens.
     *
     * @param  string $php PHP source
     * @return string[]    matched snippets
     */
    private static function roleListOffences(string $php): array
    {
        $code  = self::codeOnly($php);
        $roles = implode('|', self::ROLES);
        $role  = '[\'"](?:' . $roles . ')[\'"]';
        $hits  = [];
        $patterns = [
            // in_array(<expr without a statement break>, [ … 'admin' … ]
            '/in_array\s*\([^;]*?,\s*\[[^\]]*' . $role . '/i',
            // [ 'admin', 'superadmin', … ] — every element a role name
            '/\[\s*' . $role . '\s*(?:,\s*' . $role . '\s*)+,?\s*\]/',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $code, $m)) {
                $hits = array_merge($hits, $m[0]);
            }
        }
        return $hits;
    }

    #[Test]
    public function no_module_service_decides_authorization_by_comparing_a_role_string(): void
    {
        $offenders = [];
        // Services AND front-controller plugins — a plugin's role list is the same defect (TIGER-511).
        $files = array_merge(
            glob(self::repo() . '/modules/*/services/*.php') ?: [],
            glob(self::repo() . '/modules/*/plugins/*.php') ?: []
        );
        foreach ($files as $file) {
            $hits = self::offences(file_get_contents($file));
            if ($hits) {
                $offenders[str_replace(self::repo() . '/', '', $file)] = array_values(array_unique($hits));
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "A role-string authorization comparison reappeared in an /api service (TIGER-335). "
            . "Use \$this->_isAllowed(\$privilege) / \$this->_roleIsAllowed(\$role, \$privilege):\n"
            . json_encode($offenders, JSON_PRETTY_PRINT)
        );
    }

    #[Test]
    public function no_framework_code_gates_on_a_list_of_role_names(): void
    {
        // The whole framework — library/, core/ and every module file (controllers, views, Bootstraps
        // included), not just /api services: a role list in a session writer or a login redirect is the
        // same defect as one in a service (TIGER-511). Outside a service the narrower list-only detector
        // runs, so a chat message's 'user' role or a guest check never trips it.
        $offenders = [];
        foreach (['library', 'core', 'modules'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::repo() . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                $path = (string) $file;
                if (substr($path, -4) !== '.php' && substr($path, -6) !== '.phtml') { continue; }
                if (strpos($path, '/vendor/') !== false || strpos($path, '/languages/') !== false) { continue; }
                $hits = self::roleListOffences(file_get_contents($path));
                if ($hits) {
                    $offenders[str_replace(self::repo() . '/', '', $path)] = array_values(array_unique($hits));
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "A list of role names is deciding access (TIGER-511). Name the capability as an ACL resource in "
            . "acl.ini and ask Tiger_Acl_Acl::roleIsAllowed()/identityIsAllowed() (or _isAllowed() in a service):\n"
            . json_encode($offenders, JSON_PRETTY_PRINT)
        );
    }

    #[Test]
    public function the_role_list_detector_catches_every_shape_and_spares_data(): void
    {
        // Mutation guard for the list detector: the exact shapes TIGER-511 removed must be caught...
        $this->assertNotEmpty(self::roleListOffences("<?php class A { const ADMIN_HOME_ROLES = ['admin', 'superadmin', 'developer']; }"), 'a role-list constant');
        $this->assertNotEmpty(self::roleListOffences("<?php class S { private static \$_privileged = ['admin', 'superadmin']; }"), 'a role-list property');
        $this->assertNotEmpty(self::roleListOffences("<?php \$ok = in_array((string) (\$identity->role ?? ''), ['manager', 'admin'], true);"), 'the inline cast shape');
        $this->assertNotEmpty(self::roleListOffences("<?php \$ok = in_array(\$x->role, ['admin'], true);"), 'a single-element list in in_array');
        $this->assertNotEmpty(self::roleListOffences("<?php if (in_array(\$r, [\n 'superadmin',\n 'developer',\n])) {}"), 'a multi-line list');
        // ...while a role assigned as DATA, a single role literal, a mixed list and a comment do NOT.
        $this->assertEmpty(self::roleListOffences("<?php \$u->insert(['role' => 'admin']);"));
        $this->assertEmpty(self::roleListOffences("<?php \$m = ['role' => 'user', 'content' => \$t];"));
        $this->assertEmpty(self::roleListOffences("<?php \$x = ['admin', 'orders', 'reports'];"), 'not every element is a role');
        $this->assertEmpty(self::roleListOffences("<?php // never write ['admin', 'superadmin'] here\n\$x = 1;"));
        $this->assertEmpty(self::roleListOffences("<?php \$ok = in_array(\$k, ['draft', 'published'], true);"));
    }

    #[Test]
    public function the_role_compare_detector_actually_catches_the_defect(): void
    {
        // Mutation guard: the exact defect TIGER-335 removed must be caught...
        $this->assertNotEmpty(self::offences("<?php class X { function f(\$role){ return \$role === 'admin'; } }"));
        $this->assertNotEmpty(self::offences("<?php \$ok = \$acl->inheritsRole(\$r, 'admin');"));
        $this->assertNotEmpty(self::offences("<?php if (!\$this->_isAdmin()) { return; }"));
        $this->assertNotEmpty(self::offences("<?php \$this->_isAtLeastAdmin();"));
        $this->assertNotEmpty(self::offences("<?php return in_array(\$role, ['manager', 'admin'], true);"), 'a role LIST is a role compare');
        // ...while the clean primitive + a role name in a comment/string-key do NOT trip it.
        $this->assertEmpty(self::offences("<?php if (!\$this->_isAllowed('save')) { return; }"));
        $this->assertEmpty(self::offences("<?php // guard: never compare \$role === 'admin' here\n\$x = 1;"));
        $this->assertEmpty(self::offences("<?php \$user->insert(['role' => 'admin']);"), 'assigning a role as DATA is not a compare');
    }

    #[Test]
    public function no_acl_ini_grants_guest_a_blanket_service_resource(): void
    {
        $offenders = [];
        $inis = array_merge(
            glob(self::repo() . '/core/configs/acl.ini') ?: [],
            glob(self::repo() . '/modules/*/configs/acl.ini') ?: []
        );
        foreach ($inis as $ini) {
            foreach (self::guestServiceRules($ini) as $rule) {
                if ($rule['privilege'] === null) {
                    $offenders[] = str_replace(self::repo() . '/', '', $ini) . " → {$rule['resource']} (rule '{$rule['key']}')";
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "A blanket (privilege-less) guest grant on an /api service survives — the F5 shape (TIGER-335). "
            . "Enumerate the guest-reachable methods as privilege-scoped rules:\n" . implode("\n", $offenders)
        );
    }

    /** Parse an acl.ini's rules; return the ones granting `guest` on a *_Service_* resource. */
    private static function guestServiceRules(string $ini): array
    {
        $txt = file_get_contents($ini);
        $rules = [];
        foreach (['role', 'resource', 'privilege', 'permission'] as $field) {
            if (preg_match_all('/acl\.rules\.(\w+)\.' . $field . '\s*=\s*"?([^"\n;]+)"?/', $txt, $m, PREG_SET_ORDER)) {
                foreach ($m as $row) {
                    $rules[$row[1]][$field] = trim($row[2]);
                }
            }
        }
        $out = [];
        foreach ($rules as $key => $r) {
            $role = $r['role'] ?? '';
            $res  = $r['resource'] ?? '';
            $perm = $r['permission'] ?? 'allow';
            if ($role === 'guest' && $perm === 'allow' && strpos($res, '_Service_') !== false) {
                $out[] = ['key' => $key, 'resource' => $res, 'privilege' => $r['privilege'] ?? null];
            }
        }
        return $out;
    }
}
