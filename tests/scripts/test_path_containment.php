#!/usr/bin/env php
<?php
/**
 * Standalone test for the path containment security fix.
 * Tests the strpos() → separator-aware check change in:
 *   - app/Http/Controllers/UploadsController.php:294-298
 *   - app/Http/Controllers/TusdHooksController.php:441-444
 *
 * No vendor/composer install required. Run with: php tests/scripts/test_path_containment.php
 */

$pass = 0;
$fail = 0;
$failures = [];

function assert_blocked(string $name, string $resolvedPath, string $basePath): void {
    global $pass, $fail, $failures;
    $blocked = is_path_outside($resolvedPath, $basePath);
    if ($blocked) {
        echo "PASS: $name\n";
        $pass++;
    } else {
        echo "FAIL: $name (path was ALLOWED but should have been BLOCKED)\n";
        echo "      resolvedPath = $resolvedPath\n";
        echo "      basePath     = $basePath\n";
        $fail++;
        $failures[] = $name;
    }
}

function assert_allowed(string $name, string $resolvedPath, string $basePath): void {
    global $pass, $fail, $failures;
    $blocked = is_path_outside($resolvedPath, $basePath);
    if (!$blocked) {
        echo "PASS: $name\n";
        $pass++;
    } else {
        echo "FAIL: $name (path was BLOCKED but should have been ALLOWED)\n";
        echo "      resolvedPath = $resolvedPath\n";
        echo "      basePath     = $basePath\n";
        $fail++;
        $failures[] = $name;
    }
}

// -----------------------------------------------------------------------
// OLD (vulnerable) logic — reproduced here to prove the bug existed
// Returns true if path is OUTSIDE base (i.e., should be blocked)
// -----------------------------------------------------------------------
function is_path_outside_old(string $resolvedPath, string $basePath): bool {
    return strpos($resolvedPath, $basePath) !== 0;
}

// -----------------------------------------------------------------------
// NEW (fixed) logic — matches what was committed to the branch
// Returns true if path is OUTSIDE base
// -----------------------------------------------------------------------
function is_path_outside(string $resolvedPath, string $basePath): bool {
    return $resolvedPath !== $basePath &&
           strpos($resolvedPath, $basePath . DIRECTORY_SEPARATOR) !== 0;
}

echo "=================================================================\n";
echo "Path Containment Security Fix — Test Suite\n";
echo "Covers: UploadsController.php:298 and TusdHooksController.php:444\n";
echo "=================================================================\n\n";

$base = '/var/www/html/storage/app/shares/42/abc123longid';

// --- Correct behaviour: paths that SHOULD be allowed ---

echo "-- Paths that should be ALLOWED --\n";

assert_allowed(
    'Exact match on share root itself',
    $base,
    $base
);

assert_allowed(
    'Direct child directory',
    $base . '/subdir',
    $base
);

assert_allowed(
    'Deeply nested child',
    $base . '/level1/level2/level3',
    $base
);

assert_allowed(
    'File directly in share root',
    $base . '/file.txt',
    $base
);

assert_allowed(
    'File in nested subdir',
    $base . '/docs/report.pdf',
    $base
);

echo "\n-- Paths that should be BLOCKED --\n";

// --- Fixed behaviour: paths that SHOULD be blocked ---

assert_blocked(
    'Sibling path with same prefix (the fixed bug: abc123longid vs abc123longidEVIL)',
    '/var/www/html/storage/app/shares/42/abc123longidEVIL',
    $base
);

assert_blocked(
    'Sibling path sharing only partial prefix',
    '/var/www/html/storage/app/shares/42/abc123',
    $base
);

assert_blocked(
    'Completely different path',
    '/etc/passwd',
    $base
);

assert_blocked(
    'Parent directory (one level up)',
    '/var/www/html/storage/app/shares/42',
    $base
);

assert_blocked(
    'Parent directory (storage root)',
    '/var/www/html/storage',
    $base
);

assert_blocked(
    'Different user share with same longId prefix',
    '/var/www/html/storage/app/shares/99/abc123longid',
    $base
);

assert_blocked(
    'Web root escape attempt',
    '/var/www/html/public/shell.php',
    $base
);

echo "\n-- OLD vs NEW: demonstrating the fix ---\n";

$vuln_path = '/var/www/html/storage/app/shares/42/abc123longidEVIL';
$old_blocks = is_path_outside_old($vuln_path, $base);
$new_blocks = is_path_outside($vuln_path, $base);

if (!$old_blocks && $new_blocks) {
    echo "CONFIRMED: Old code ALLOWED '$vuln_path' with base '$base'\n";
    echo "CONFIRMED: New code BLOCKS  '$vuln_path' with base '$base'\n";
    echo "PASS: Regression demonstrates the fix is necessary\n";
    $pass++;
} else {
    echo "UNEXPECTED result comparing old vs new logic\n";
    $fail++;
    $failures[] = 'Old vs New comparison';
}

// --- Summary ---
echo "\n=================================================================\n";
echo "Results: $pass passed, $fail failed\n";
if ($fail > 0) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "All tests passed.\n";
exit(0);
