<?php
/**
 * Test harness for F5: Admin immediate share deletion.
 *
 * Tests:
 * - deleteImmediately: admin-only, typed confirmation, valid target states
 * - cleanExpiredShares job: processes pending_deletion alongside expired shares
 * - Eligibility: which share states can be immediately deleted
 *
 * Does not require a running Laravel app or composer vendor/.
 * Run with: php tests/scripts/test_admin_share_deletion.php
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

function assert_true(string $name, bool $value): void {
    if ($value) pass($name);
    else fail($name, "expected true");
}

function assert_false(string $name, bool $value): void {
    if (!$value) pass($name);
    else fail($name, "expected false");
}

// ---------------------------------------------------------------------------
// Simulate share state objects
// ---------------------------------------------------------------------------

function makeShare(array $attrs = []): object {
    return (object) array_merge([
        'id'                    => 1,
        'status'                => 'ready',
        'expires_at'            => (new DateTime('+30 days'))->format('Y-m-d H:i:s'),
        'deletion_requested_at' => null,
        'deletion_requested_by' => null,
        'cleaned'               => false,   // set to true by simulateCleanFiles()
        'deleted_flag'          => false,
    ], $attrs);
}

function makeAdmin(): object   { return (object)['id' => 1, 'admin' => true]; }
function makeNonAdmin(): object { return (object)['id' => 2, 'admin' => false]; }

// ---------------------------------------------------------------------------
// Mirror: deleteImmediately() business logic
// ---------------------------------------------------------------------------
function deleteImmediately(object $share, object $user, ?string $confirmation): array {
    if (!$user->admin) {
        return ['status' => 'error', 'code' => 401, 'message' => 'Unauthorized'];
    }

    if (strtolower(trim($confirmation ?? '')) !== 'delete') {
        return ['status' => 'error', 'code' => 422, 'message' => 'Confirmation required'];
    }

    if ($share->status === 'deleted') {
        return ['status' => 'error', 'code' => 422, 'message' => 'Share is already deleted'];
    }

    $isExpired = $share->expires_at !== null && $share->expires_at < (new DateTime())->format('Y-m-d H:i:s');
    $actionableStatuses = ['pending_deletion', 'deleted'];

    if (!in_array($share->status, $actionableStatuses) && !$isExpired) {
        return ['status' => 'error', 'code' => 422, 'message' => 'Share must be pending deletion or expired'];
    }

    // Simulate cleanFiles()
    $share->cleaned = true;
    $share->status  = 'deleted';

    return ['status' => 'success', 'code' => 200, 'share' => $share];
}

// ---------------------------------------------------------------------------
// Mirror: canDeleteImmediately() Vue helper
// ---------------------------------------------------------------------------
function canDeleteImmediately(object $share): bool {
    $isPending = $share->status === 'pending_deletion';
    $isExpired = $share->expires_at !== null && $share->expires_at < (new DateTime())->format('Y-m-d H:i:s');
    $isDeleted = $share->status === 'deleted';
    return $isPending || ($isExpired && !$isDeleted);
}

// ---------------------------------------------------------------------------
// Mirror: cleanExpiredShares job — which shares get processed
// ---------------------------------------------------------------------------
function shouldCleanupJobProcess(object $share, int $cleanFilesAfterDays = 30): bool {
    // Pending deletion: always processed
    if ($share->status === 'pending_deletion') return true;

    // Already deleted: skip
    if ($share->status === 'deleted') return false;

    // Expired past cleanup window
    if ($share->expires_at !== null) {
        $cleanAfter = (new DateTime($share->expires_at));
        $cleanAfter->modify("+{$cleanFilesAfterDays} days");
        return $cleanAfter < new DateTime();
    }

    return false;
}

// ===========================================================================
// TEST CASES
// ===========================================================================

echo "=================================================================\n";
echo "F5: Admin Immediate Share Deletion — Test Suite\n";
echo "=================================================================\n\n";

$admin    = makeAdmin();
$nonAdmin = makeNonAdmin();

// ---------------------------------------------------------------------------
echo "-- Authorization: admin-only --\n\n";
// ---------------------------------------------------------------------------

$share = makeShare(['status' => 'pending_deletion']);
$r = deleteImmediately($share, $nonAdmin, 'delete');
assert_eq('Non-admin cannot call deleteImmediately', 'error', $r['status']);
assert_eq('Non-admin gets 401', 401, $r['code']);
assert_false('Non-admin attempt does not clean files', $share->cleaned);

$share2 = makeShare(['status' => 'pending_deletion']);
$r2 = deleteImmediately($share2, $admin, 'delete');
assert_eq('Admin can call deleteImmediately', 'success', $r2['status']);
assert_true('Admin attempt cleans files', $share2->cleaned);
assert_eq('Share status set to deleted', 'deleted', $share2->status);

// ---------------------------------------------------------------------------
echo "\n-- Confirmation phrase --\n\n";
// ---------------------------------------------------------------------------

$share3 = makeShare(['status' => 'pending_deletion']);
assert_eq('No confirmation → error', 'error', deleteImmediately($share3, $admin, null)['status']);
assert_eq('Empty string → error', 'error', deleteImmediately($share3, $admin, '')['status']);
assert_eq('Wrong word → error', 'error', deleteImmediately($share3, $admin, 'yes')['status']);
assert_eq('Partial match → error', 'error', deleteImmediately($share3, $admin, 'delet')['status']);

$share4 = makeShare(['status' => 'pending_deletion']);
assert_eq('"delete" (lowercase) → success', 'success', deleteImmediately($share4, $admin, 'delete')['status']);

$share5 = makeShare(['status' => 'pending_deletion']);
assert_eq('"DELETE" (uppercase) → success (case-insensitive)', 'success', deleteImmediately($share5, $admin, 'DELETE')['status']);

$share6 = makeShare(['status' => 'pending_deletion']);
assert_eq('"  delete  " (whitespace) → success (trimmed)', 'success', deleteImmediately($share6, $admin, '  delete  ')['status']);

// ---------------------------------------------------------------------------
echo "\n-- Eligible share states --\n\n";
// ---------------------------------------------------------------------------

$pendingShare = makeShare(['status' => 'pending_deletion']);
assert_eq('pending_deletion → can delete immediately', 'success', deleteImmediately($pendingShare, $admin, 'delete')['status']);

$expiredShare = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('-1 hour'))->format('Y-m-d H:i:s')]);
assert_eq('expired ready share → can delete immediately', 'success', deleteImmediately($expiredShare, $admin, 'delete')['status']);

$activeShare = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('+30 days'))->format('Y-m-d H:i:s')]);
$rActive = deleteImmediately($activeShare, $admin, 'delete');
assert_eq('active (non-expired) share → cannot delete immediately', 'error', $rActive['status']);
assert_eq('active share → 422', 422, $rActive['code']);

$alreadyDeleted = makeShare(['status' => 'deleted']);
$rDel = deleteImmediately($alreadyDeleted, $admin, 'delete');
assert_eq('already-deleted share → error', 'error', $rDel['status']);
assert_eq('already-deleted → 422', 422, $rDel['code']);

// ---------------------------------------------------------------------------
echo "\n-- canDeleteImmediately() Vue helper --\n\n";
// ---------------------------------------------------------------------------

assert_true('pending_deletion → button shown', canDeleteImmediately(makeShare(['status' => 'pending_deletion'])));
assert_true('expired ready → button shown', canDeleteImmediately(makeShare([
    'status' => 'ready',
    'expires_at' => (new DateTime('-1 hour'))->format('Y-m-d H:i:s')
])));
assert_false('active share → button hidden', canDeleteImmediately(makeShare([
    'status' => 'ready',
    'expires_at' => (new DateTime('+30 days'))->format('Y-m-d H:i:s')
])));
assert_false('already deleted → button hidden', canDeleteImmediately(makeShare(['status' => 'deleted'])));

// ---------------------------------------------------------------------------
echo "\n-- cleanExpiredShares job: pending_deletion included --\n\n";
// ---------------------------------------------------------------------------

$pendingShare2 = makeShare(['status' => 'pending_deletion', 'expires_at' => (new DateTime('+30 days'))->format('Y-m-d H:i:s')]);
assert_true('Job processes pending_deletion share even if not yet expired', shouldCleanupJobProcess($pendingShare2));

$expiredOld = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('-35 days'))->format('Y-m-d H:i:s')]);
assert_true('Job processes share expired past cleanup window (30d)', shouldCleanupJobProcess($expiredOld));

$expiredRecent = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('-5 days'))->format('Y-m-d H:i:s')]);
assert_false('Job skips share expired only recently (within 30d window)', shouldCleanupJobProcess($expiredRecent));

$deletedShare2 = makeShare(['status' => 'deleted', 'expires_at' => (new DateTime('-60 days'))->format('Y-m-d H:i:s')]);
assert_false('Job skips already-deleted shares', shouldCleanupJobProcess($deletedShare2));

$activeShare2 = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('+10 days'))->format('Y-m-d H:i:s')]);
assert_false('Job skips active shares', shouldCleanupJobProcess($activeShare2));

// ---------------------------------------------------------------------------
echo "\n-- Idempotency: double-click guard --\n\n";
// ---------------------------------------------------------------------------

$share7 = makeShare(['status' => 'pending_deletion']);
$r7a = deleteImmediately($share7, $admin, 'delete');
assert_eq('First delete succeeds', 'success', $r7a['status']);
$r7b = deleteImmediately($share7, $admin, 'delete');
assert_eq('Second delete on same share fails (already deleted)', 'error', $r7b['status']);

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
