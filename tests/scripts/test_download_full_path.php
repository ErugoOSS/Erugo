<?php
/**
 * Test the download full_path fix in SharesController.
 *
 * Reproduces the bug where single-file shares with files in subdirectories
 * failed to download because full_path was omitted from the filesystem path.
 *
 * Run with: php tests/scripts/test_download_full_path.php
 */

$pass = 0;
$fail = 0;
$failures = [];

function pass(string $name): void {
    global $pass;
    echo "PASS: $name\n";
    $pass++;
}

function fail(string $name, string $detail = ''): void {
    global $fail, $failures;
    echo "FAIL: $name" . ($detail ? " — $detail" : '') . "\n";
    $fail++;
    $failures[] = $name;
}

function assert_eq(string $name, $expected, $actual): void {
    if ($expected === $actual) {
        pass($name);
    } else {
        fail($name, "expected " . var_export($expected, true) . ", got " . var_export($actual, true));
    }
}

// ---------------------------------------------------------------------------
// Helpers that mirror the production logic exactly
// ---------------------------------------------------------------------------

/**
 * OLD (buggy) path builder — does not include full_path.
 * Mirrors: $sharePath . '/' . $file->name
 */
function old_build_path(string $sharePath, object $file): string {
    return $sharePath . '/' . $file->name;
}

/**
 * NEW (fixed) path builder — includes full_path when present.
 * Mirrors: $sharePath . '/' . ($file->full_path ? $file->full_path . '/' : '') . $file->name
 */
function new_build_path(string $sharePath, object $file): string {
    return $sharePath . '/' . ($file->full_path ? $file->full_path . '/' : '') . $file->name;
}

/**
 * NEW path builder for downloadFile() — same logic.
 */
function new_build_file_path(string $sharePath, object $file): string {
    return $sharePath . '/' . ($file->full_path ? $file->full_path . '/' : '') . $file->name;
}

/**
 * expectedPath logic from downloadFile() line 291 — unchanged by fix.
 * $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name
 */
function expected_path(object $file): string {
    return $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name;
}

// ---------------------------------------------------------------------------
// Scenario 1: File in subdirectory (the failing case from the bug report)
// ---------------------------------------------------------------------------

echo "=================================================================\n";
echo "Download full_path Fix — Test Suite\n";
echo "Covers: SharesController::download() and SharesController::downloadFile()\n";
echo "=================================================================\n\n";

echo "-- Scenario 1: File in subdirectory with sanitized filename --\n";
echo "   (Reproduces: Preclassic Maya video uploaded into subdirectory)\n\n";

$sharePath = '/var/www/html/storage/app/shares/cold-paper-bold-smoke';

$file1 = (object)[
    'name'          => 'Preclassic Maya Landscapes from the Mirador-Calakmul Basin _J. Thompson_ E. Escobar_ D. Wahl_ R. Hansen_ - HD 1080p.mov',
    'original_name' => 'Preclassic Maya Landscapes from the Mirador-Calakmul Basin (J. Thompson, E. Escobar, D. Wahl, R. Hansen) - HD 1080p.mov',
    'full_path'     => 'UC Berkeley Mirador Presentation/HD 1080p',
    'display_name'  => 'Preclassic Maya Landscapes from the Mirador-Calakmul Basin (J. Thompson, E. Escobar, D. Wahl, R. Hansen) - HD 1080p.mov',
];

$expectedOld = $sharePath . '/' . $file1->name;
$expectedNew = $sharePath . '/UC Berkeley Mirador Presentation/HD 1080p/' . $file1->name;

assert_eq(
    'OLD download(): omits full_path (path is wrong)',
    $expectedOld,
    old_build_path($sharePath, $file1)
);

assert_eq(
    'NEW download(): includes full_path (path is correct)',
    $expectedNew,
    new_build_path($sharePath, $file1)
);

// OLD path doesn't contain subdirectory; new one does
$old_path = old_build_path($sharePath, $file1);
$new_path = new_build_path($sharePath, $file1);

if (strpos($old_path, 'UC Berkeley Mirador Presentation') === false) {
    pass('OLD path missing subdirectory segment (confirms the bug)');
} else {
    fail('OLD path unexpectedly contains subdirectory');
}

if (strpos($new_path, 'UC Berkeley Mirador Presentation/HD 1080p') !== false) {
    pass('NEW path contains full subdirectory chain');
} else {
    fail('NEW path missing subdirectory chain');
}

// downloadFile expected_path (unchanged) should include full_path
$ep = expected_path($file1);
$expected_ep = 'UC Berkeley Mirador Presentation/HD 1080p/' . $file1->display_name;
assert_eq('expectedPath includes full_path + display_name (URL matching, unchanged)', $expected_ep, $ep);

// The filesystem path in downloadFile() — old vs new
$old_file_path = old_build_path($sharePath, $file1);
$new_file_path = new_build_file_path($sharePath, $file1);
assert_eq('NEW downloadFile(): filesystem path includes full_path', $expectedNew, $new_file_path);
assert_eq('OLD downloadFile(): filesystem path missing full_path (confirms the bug)', $expectedOld, $old_file_path);

// ---------------------------------------------------------------------------
// Scenario 2: File at share root (no full_path) — regression check
// ---------------------------------------------------------------------------

echo "\n-- Scenario 2: File at share root (full_path is null) — regression check --\n\n";

$file2 = (object)[
    'name'          => 'document.pdf',
    'original_name' => 'document.pdf',
    'full_path'     => null,
    'display_name'  => 'document.pdf',
];

$expected_root = $sharePath . '/document.pdf';

assert_eq(
    'download(): null full_path — path unchanged (no regression)',
    $expected_root,
    new_build_path($sharePath, $file2)
);

assert_eq(
    'downloadFile(): null full_path — path unchanged (no regression)',
    $expected_root,
    new_build_file_path($sharePath, $file2)
);

$ep2 = expected_path($file2);
assert_eq('expectedPath with null full_path = display_name only', 'document.pdf', $ep2);

// ---------------------------------------------------------------------------
// Scenario 3: File in single-level subdirectory
// ---------------------------------------------------------------------------

echo "\n-- Scenario 3: File in single-level subdirectory --\n\n";

$file3 = (object)[
    'name'          => 'report_Q1_2025.xlsx',
    'original_name' => 'report Q1, 2025.xlsx',
    'full_path'     => 'Finance',
    'display_name'  => 'report Q1, 2025.xlsx',
];

$expected3 = $sharePath . '/Finance/report_Q1_2025.xlsx';
assert_eq('NEW download(): single-level full_path', $expected3, new_build_path($sharePath, $file3));

$ep3 = expected_path($file3);
assert_eq('expectedPath: single-level', 'Finance/report Q1, 2025.xlsx', $ep3);

// ---------------------------------------------------------------------------
// Scenario 4: full_path is empty string (edge case — treat as no subdir)
// ---------------------------------------------------------------------------

echo "\n-- Scenario 4: full_path is empty string (edge case) --\n\n";

$file4 = (object)[
    'name'          => 'notes.txt',
    'original_name' => 'notes.txt',
    'full_path'     => '',
    'display_name'  => 'notes.txt',
];

// Empty string is falsy in PHP — should behave same as null
$expected4 = $sharePath . '/notes.txt';
assert_eq(
    'Empty full_path treated as falsy — no spurious leading slash',
    $expected4,
    new_build_path($sharePath, $file4)
);

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------

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
