# Download full_path Fix — Test Results

**Branch:** `fix/download-full-path`
**Run date:** 2026-07-11 UTC
**Platform:** Linux x86_64 (Kali)

---

## Change Under Test

| File | Change |
|---|---|
| `app/Http/Controllers/SharesController.php:195-201` | `download()`: extract file to local var; prepend `full_path . '/'` when non-null before `name` in both `file_exists()` check and `response()->download()` call |
| `app/Http/Controllers/SharesController.php:295` | `downloadFile()`: prepend `full_path . '/'` when non-null before `name` in filesystem path |

**Root cause context:** Single-file shares with files in subdirectories (i.e. `full_path` is non-null in the DB) silently failed to download. `download()` redirected back to the share page; `downloadFile()` returned `{"error":"File not found"}`. Both methods constructed the filesystem path as `$sharePath . '/' . $file->name`, omitting the `full_path` subdirectory segment. The `downloadFile()` URL-matching logic on line 291 correctly included `full_path` in the expected URL path (so the route matched), but the subsequent filesystem lookup did not — causing the file_exists() check to always fail for subdirectory files.

---

## Test Script

**Script:** `tests/scripts/test_download_full_path.php`
**Runner:** `php tests/scripts/test_download_full_path.php`

Tests the old and new path construction logic across four scenarios:
1. File in nested subdirectory with sanitized filename (exact reproduction of the bug report)
2. File at share root with `full_path = null` (regression: no change in behaviour)
3. File in single-level subdirectory
4. Edge case: `full_path` is empty string (treated as falsy — no spurious slash)

---

## Output

```
=================================================================
Download full_path Fix — Test Suite
Covers: SharesController::download() and SharesController::downloadFile()
=================================================================

-- Scenario 1: File in subdirectory with sanitized filename --
   (Reproduces: Preclassic Maya video uploaded into subdirectory)

PASS: OLD download(): omits full_path (path is wrong)
PASS: NEW download(): includes full_path (path is correct)
PASS: OLD path missing subdirectory segment (confirms the bug)
PASS: NEW path contains full subdirectory chain
PASS: expectedPath includes full_path + display_name (URL matching, unchanged)
PASS: NEW downloadFile(): filesystem path includes full_path
PASS: OLD downloadFile(): filesystem path missing full_path (confirms the bug)

-- Scenario 2: File at share root (full_path is null) — regression check --

PASS: download(): null full_path — path unchanged (no regression)
PASS: downloadFile(): null full_path — path unchanged (no regression)
PASS: expectedPath with null full_path = display_name only

-- Scenario 3: File in single-level subdirectory --

PASS: NEW download(): single-level full_path
PASS: expectedPath: single-level

-- Scenario 4: full_path is empty string (edge case) --

PASS: Empty full_path treated as falsy — no spurious leading slash

=================================================================
Results: 13 passed, 0 failed
All tests passed.
```

---

## Summary

| Test group | Tests | Pass | Fail |
|---|---|---|---|
| Subdirectory file: old path wrong, new path correct | 4 | 4 | 0 |
| URL matching (expectedPath) correct in both old/new | 1 | 1 | 0 |
| downloadFile() old vs new filesystem path | 2 | 2 | 0 |
| Regression: null full_path unchanged | 3 | 3 | 0 |
| Single-level subdirectory | 2 | 2 | 0 |
| Edge case: empty string full_path | 1 | 1 | 0 |
| **Total** | **13** | **13** | **0** |

The tests confirm:
- The old code omits `full_path` from the filesystem path, causing `file_exists()` to return false for any file not at the share root
- The fix correctly inserts `$file->full_path . '/'` when `full_path` is non-null
- Files at the share root (null or empty `full_path`) are unaffected — no regression
