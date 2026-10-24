<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Secret;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;

/**
 * The TIGER-382 structural guard — plaintext can never ride `Tiger_Secret::resolve()` by mistake.
 *
 * `Tiger_Secret::resolve()` is deliberately forgiving: a value whose scheme it does not recognize is
 * returned UNCHANGED as plaintext (Secret.php — the `return $value;` passthrough). That one rule is what
 * makes adoption a one-liner at every `*_enc` call site, but it is a *convention*, not enforcement: a caller
 * that hard-codes a literal secret — `Tiger_Secret::resolve('super-secret-key')` — gets exactly that secret
 * back, silently, defeating the whole point of routing it through a provider (ssm/env/file/enc/…). This guard
 * makes the convention airtight.
 *
 * The rule: a **string-literal** argument to `Tiger_Secret::resolve()` MUST be a recognized `scheme:path`
 * reference (`ssm:` `env:` `file:` `enc:` `local:` `secretsmanager:` `chain:` `vault:` … — anything matching
 * `_parse()`'s `^[a-z][a-z0-9+_-]*:` scheme syntax). A literal that is NOT a scheme reference is a bare
 * plaintext value and is forbidden. A **variable or a concatenation** (`resolve($value)`,
 * `resolve($base . 'ses_key_id')`) is the runtime-config path — resolved from config where secrets belong —
 * and is never flagged; only a hard-coded non-scheme literal is.
 *
 * Detection is token-based (comments/docblocks are blanked), so a `resolve('secret')` written in a comment or
 * docblock never trips it — and the detector is mutation-tested below (a validator that only ever passes
 * proves nothing). Forward-looking: `resolve()` has no production caller in this repo yet (it's adopted at
 * `*_enc` sites as they migrate), so the guard protects the pattern before it spreads.
 */
final class SecretResolvePlaintextGuardTest extends UnitTestCase
{
    private static function repo(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Production source trees scanned — never `tests/` (its SecretTest legitimately passes plaintext literals). */
    private const ROOTS = ['library', 'core', 'modules'];

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

    /**
     * A bare-plaintext-literal handed to `Tiger_Secret::resolve()`, as applied to code-only source.
     * Matches only a SOLE string-literal argument (quote right after `(`, a closing `)`/`,` right after the
     * literal) — so a variable or a concatenation never matches. Returns the offending literals.
     */
    private static function offences(string $php): array
    {
        $code = self::codeOnly($php);
        $hits = [];
        // Tiger_Secret::resolve( '<literal>' ) | "<literal>"  — the whole argument is one string literal.
        if (preg_match_all('/Tiger_Secret::resolve\(\s*([\'"])(.*?)\1\s*[),]/s', $code, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $literal = $row[2];
                // A recognized scheme reference is allowed; a bare plaintext literal is not.
                if (!preg_match('/^[a-z][a-z0-9+_-]*:/', $literal)) {
                    $hits[] = $literal;
                }
            }
        }
        return $hits;
    }

    /** Every production .php file under the scanned roots. */
    private static function phpFiles(): array
    {
        $files = [];
        foreach (self::ROOTS as $root) {
            $dir = self::repo() . '/' . $root;
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        return $files;
    }

    #[Test]
    public function no_production_code_hands_a_plaintext_literal_to_tiger_secret_resolve(): void
    {
        $files = self::phpFiles();
        $this->assertNotEmpty($files, 'no production .php files found under ' . implode('/', self::ROOTS) . ' — the scan is wrong, not the rule');

        $offenders = [];
        foreach ($files as $file) {
            $hits = self::offences(file_get_contents($file));
            if ($hits) {
                $offenders[str_replace(self::repo() . '/', '', $file)] = array_values(array_unique($hits));
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "A plaintext literal is handed to Tiger_Secret::resolve() (TIGER-382) — it would be returned "
            . "verbatim as a secret. Use a provider reference (ssm:/… env:… enc:… file:…) read from config, "
            . "not a hard-coded value:\n" . json_encode($offenders, JSON_PRETTY_PRINT)
        );
    }

    #[Test]
    public function the_plaintext_detector_actually_catches_the_defect(): void
    {
        // Mutation guard: a bare plaintext literal handed to resolve() MUST be caught...
        $this->assertNotEmpty(self::offences("<?php \$v = Tiger_Secret::resolve('super-secret-key');"));
        $this->assertNotEmpty(self::offences('<?php Tiger_Secret::resolve("hunter2");'));
        // ...while a recognized scheme reference, a variable, a concatenation, and a comment do NOT trip it.
        $this->assertEmpty(self::offences("<?php Tiger_Secret::resolve('ssm:/tiger/prod/node/crypto_key');"), 'an ssm: reference is the correct form');
        $this->assertEmpty(self::offences("<?php Tiger_Secret::resolve('env:OPENSRS_API_KEY');"), 'an env: reference is the correct form');
        $this->assertEmpty(self::offences("<?php Tiger_Secret::resolve('enc:AbCdEf==');"), 'an enc: blob is the correct form');
        $this->assertEmpty(self::offences('<?php Tiger_Secret::resolve($value);'), 'a variable is the runtime-config path');
        $this->assertEmpty(self::offences("<?php Tiger_Secret::resolve(\$base . 'ses_key_id');"), 'a concatenation is not a bare literal');
        $this->assertEmpty(self::offences("<?php // Tiger_Secret::resolve('a-secret') in a comment\n\$x = 1;"), 'a mention in a comment is not a call');
    }
}
