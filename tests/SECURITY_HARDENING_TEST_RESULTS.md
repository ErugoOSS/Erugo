# Security Hardening — Test Results

**Branch:** `fix/security-hardening`
**Run date:** 2026-07-11 23:23:12 UTC
**PHP version:** PHP 8.4.23 (cli)
**Platform:** Linux (Kali) x86_64

---

## Changes Under Test

| File | Change |
|---|---|
| `app/Http/Controllers/UploadsController.php:298` | `strpos()` → separator-aware path containment check |
| `app/Http/Controllers/TusdHooksController.php:444` | Same fix for bundle extraction directory check |
| `docker/alpine/start-container:125` | `sed` → `awk` for `.env` value quoting (escapes `"` in values) |

---

## Test 1 — Path Containment Fix

**Script:** `tests/scripts/test_path_containment.php`
**Runner:** `php tests/scripts/test_path_containment.php`

**PHPUnit class** (requires `composer install`): `tests/Unit/PathContainmentSecurityTest.php`

### Output

```
=================================================================
Path Containment Security Fix — Test Suite
Covers: UploadsController.php:298 and TusdHooksController.php:444
=================================================================

-- Paths that should be ALLOWED --
PASS: Exact match on share root itself
PASS: Direct child directory
PASS: Deeply nested child
PASS: File directly in share root
PASS: File in nested subdir

-- Paths that should be BLOCKED --
PASS: Sibling path with same prefix (the fixed bug: abc123longid vs abc123longidEVIL)
PASS: Sibling path sharing only partial prefix
PASS: Completely different path
PASS: Parent directory (one level up)
PASS: Parent directory (storage root)
PASS: Different user share with same longId prefix
PASS: Web root escape attempt

-- OLD vs NEW: demonstrating the fix ---
CONFIRMED: Old code ALLOWED '/var/www/html/storage/app/shares/42/abc123longidEVIL' with base '/var/www/html/storage/app/shares/42/abc123longid'
CONFIRMED: New code BLOCKS  '/var/www/html/storage/app/shares/42/abc123longidEVIL' with base '/var/www/html/storage/app/shares/42/abc123longid'
PASS: Regression demonstrates the fix is necessary

=================================================================
Results: 13 passed, 0 failed
All tests passed.
```

**Result: 13/13 PASS**

---

## Test 2 — .env Value Quoting Fix

**Script:** `tests/scripts/test_env_quoting.sh`
**Runner:** `sh tests/scripts/test_env_quoting.sh`

### Output

```
=================================================================
ENV Quoting Fix — Test Suite
Covers: docker/alpine/start-container (awk .env generation)
=================================================================

-- Normal values --
PASS: simple value
PASS: empty value
PASS: value with spaces

-- Values with equals signs (base64 keys, connection strings) --
PASS: value with trailing =
PASS: value with multiple =
PASS: value with = in query string

-- Values with double-quote characters (the security fix) --
PASS: value with single double-quote
PASS: value with quoted word
PASS: value that is double-quotes only

-- OLD sed command (demonstrating the bug) --
CONFIRMED: Old sed produces malformed output: APP_NAME="My"App"
CONFIRMED: New awk escapes correctly

=================================================================
Results: 10 passed, 0 failed
All tests passed.
```

**Result: 10/10 PASS**

---

## Automated Security Scan

**Tool:** semgrep 1.156.0
**Configs:** `--config=auto`, `--config=p/php`, `--config=p/command-injection`, `--config=p/security-audit`
**Findings:** 0

---

## Summary

| Test suite | Tests | Pass | Fail |
|---|---|---|---|
| Path containment (standalone PHP) | 13 | 13 | 0 |
| .env value quoting (shell) | 10 | 10 | 0 |
| semgrep automated scan | n/a | 0 findings | — |
| **Total** | **23** | **23** | **0** |

All tests pass. The regression test explicitly confirms the old `strpos()` code was vulnerable to the prefix-bypass and the new code blocks it.
