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
        return $hits;
    }

    #[Test]
    public function no_module_service_decides_authorization_by_comparing_a_role_string(): void
    {
        $offenders = [];
        foreach (glob(self::repo() . '/modules/*/services/*.php') as $file) {
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
    public function the_role_compare_detector_actually_catches_the_defect(): void
    {
        // Mutation guard: the exact defect TIGER-335 removed must be caught...
        $this->assertNotEmpty(self::offences("<?php class X { function f(\$role){ return \$role === 'admin'; } }"));
        $this->assertNotEmpty(self::offences("<?php \$ok = \$acl->inheritsRole(\$r, 'admin');"));
        $this->assertNotEmpty(self::offences("<?php if (!\$this->_isAdmin()) { return; }"));
        $this->assertNotEmpty(self::offences("<?php \$this->_isAtLeastAdmin();"));
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
