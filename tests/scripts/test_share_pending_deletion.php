<?php
/**
 * Test harness for F4: User-initiated share pending deletion.
 *
 * Tests the business logic of the pending_deletion state machine:
 * - requestDeletion: valid transitions, auth, double-request guard
 * - undoDeletion: valid revert, auth, non-pending guard
 * - Email suppression: pending_deletion shares excluded from warning queries
 * - Access control: read/download blocked for pending_deletion shares
 *
 * Does not require a running Laravel app or composer vendor/.
 * Run with: php tests/scripts/test_share_pending_deletion.php
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
// Simulate share state objects (mirrors Share model attributes)
// ---------------------------------------------------------------------------

function makeShare(array $attrs = []): object {
    return (object) array_merge([
        'id' => 1,
        'status' => 'ready',
        'expires_at' => (new DateTime('+30 days'))->format('Y-m-d H:i:s'),
        'deletion_requested_at' => null,
        'deletion_requested_by' => null,
    ], $attrs);
}

function makeUser(bool $admin = false): object {
    return (object)['id' => 1, 'admin' => $admin];
}

// ---------------------------------------------------------------------------
// Mirror: Share::getPendingDeletionAttribute()
// ---------------------------------------------------------------------------
function isPendingDeletion(object $share): bool {
    return $share->status === 'pending_deletion';
}

// ---------------------------------------------------------------------------
// Mirror: SharesController::canManageShare()
// ---------------------------------------------------------------------------
function canManageShare(object $share, object $user): bool {
    if ($user->admin) return true;
    if (isset($share->user_id) && $share->user_id === $user->id) return true;
    return false;
}

// ---------------------------------------------------------------------------
// Mirror: SharesController::requestDeletion() business logic
// ---------------------------------------------------------------------------
function requestDeletion(object $share, object $user): array {
    if (!canManageShare($share, $user)) {
        return ['status' => 'error', 'code' => 401, 'message' => 'Unauthorized'];
    }
    if (in_array($share->status, ['pending_deletion', 'deleted'])) {
        return ['status' => 'error', 'code' => 422, 'message' => 'Already pending deletion or deleted'];
    }
    $share->status = 'pending_deletion';
    $share->deletion_requested_at = (new DateTime())->format('Y-m-d H:i:s');
    $share->deletion_requested_by = $user->admin ? 'admin' : 'user';
    return ['status' => 'success', 'code' => 200, 'share' => $share];
}

// ---------------------------------------------------------------------------
// Mirror: SharesController::undoDeletion() business logic
// ---------------------------------------------------------------------------
function undoDeletion(object $share, object $user): array {
    if (!canManageShare($share, $user)) {
        return ['status' => 'error', 'code' => 401, 'message' => 'Unauthorized'];
    }
    if ($share->status !== 'pending_deletion') {
        return ['status' => 'error', 'code' => 422, 'message' => 'Share is not pending deletion'];
    }
    $share->status = 'ready';
    $share->deletion_requested_at = null;
    $share->deletion_requested_by = null;
    return ['status' => 'success', 'code' => 200, 'share' => $share];
}

// ---------------------------------------------------------------------------
// Mirror: read() / download() access guard
// ---------------------------------------------------------------------------
function isAccessible(object $share): bool {
    return !in_array($share->status, ['pending_deletion', 'deleted']);
}

// ---------------------------------------------------------------------------
// Mirror: email job query filter (checks status exclusion)
// ---------------------------------------------------------------------------
function shouldReceiveExpiryWarning(object $share): bool {
    // Mirrors: whereNotIn('status', ['pending_deletion', 'deleted'])
    return !in_array($share->status, ['pending_deletion', 'deleted']);
}

// ===========================================================================
// TEST CASES
// ===========================================================================

echo "=================================================================\n";
echo "F4: Share Pending Deletion — Test Suite\n";
echo "=================================================================\n\n";

// ---------------------------------------------------------------------------
echo "-- State machine: requestDeletion() --\n\n";
// ---------------------------------------------------------------------------

$owner = makeUser(false);
$admin = makeUser(true);
$other = (object)['id' => 99, 'admin' => false];

$share = makeShare(['user_id' => 1, 'status' => 'ready']);
$result = requestDeletion($share, $owner);
assert_eq('Owner can request deletion of own share — status OK', 'success', $result['status']);
assert_eq('Status set to pending_deletion', 'pending_deletion', $share->status);
assert_eq('deletion_requested_by = user', 'user', $share->deletion_requested_by);
assert_true('deletion_requested_at is set', $share->deletion_requested_at !== null);
assert_true('share is now pending deletion', isPendingDeletion($share));

$share2 = makeShare(['user_id' => 1, 'status' => 'ready']);
$result2 = requestDeletion($share2, $admin);
assert_eq('Admin can request deletion of any share', 'success', $result2['status']);
assert_eq('deletion_requested_by = admin when admin requests', 'admin', $share2->deletion_requested_by);

$share3 = makeShare(['user_id' => 1, 'status' => 'ready']);
$result3 = requestDeletion($share3, $other);
assert_eq('Non-owner cannot request deletion', 'error', $result3['status']);
assert_eq('Non-owner gets 401', 401, $result3['code']);
assert_eq('Share status unchanged after unauthorized request', 'ready', $share3->status);

$shareAlready = makeShare(['user_id' => 1, 'status' => 'pending_deletion']);
$result4 = requestDeletion($shareAlready, $owner);
assert_eq('Cannot double-request deletion', 'error', $result4['status']);
assert_eq('Double-request returns 422', 422, $result4['code']);

$shareDeleted = makeShare(['user_id' => 1, 'status' => 'deleted']);
$result5 = requestDeletion($shareDeleted, $owner);
assert_eq('Cannot request deletion of already-deleted share', 'error', $result5['status']);
assert_eq('Already-deleted returns 422', 422, $result5['code']);

echo "\n-- State machine: undoDeletion() --\n\n";

$sharePending = makeShare(['user_id' => 1, 'status' => 'pending_deletion',
    'deletion_requested_at' => (new DateTime())->format('Y-m-d H:i:s'),
    'deletion_requested_by' => 'user']);
$undoResult = undoDeletion($sharePending, $owner);
assert_eq('Owner can undo deletion of own share', 'success', $undoResult['status']);
assert_eq('Status restored to ready', 'ready', $sharePending->status);
assert_eq('deletion_requested_at cleared', null, $sharePending->deletion_requested_at);
assert_eq('deletion_requested_by cleared', null, $sharePending->deletion_requested_by);
assert_false('Share is no longer pending deletion', isPendingDeletion($sharePending));

$sharePending2 = makeShare(['user_id' => 1, 'status' => 'pending_deletion',
    'deletion_requested_at' => (new DateTime())->format('Y-m-d H:i:s'),
    'deletion_requested_by' => 'user']);
$undoAdmin = undoDeletion($sharePending2, $admin);
assert_eq('Admin can undo deletion on any share', 'success', $undoAdmin['status']);

$shareReady = makeShare(['user_id' => 1, 'status' => 'ready']);
$undoBad = undoDeletion($shareReady, $owner);
assert_eq('Cannot undo deletion of non-pending share', 'error', $undoBad['status']);
assert_eq('Non-pending undo returns 422', 422, $undoBad['code']);

$shareReady2 = makeShare(['user_id' => 1, 'status' => 'ready']);
$undoOther = undoDeletion($shareReady2, $other);
assert_eq('Non-owner cannot undo deletion', 'error', $undoOther['status']);
assert_eq('Non-owner undo returns 401', 401, $undoOther['code']);

echo "\n-- Access control: read() / download() --\n\n";

$activeShare = makeShare(['status' => 'ready']);
assert_true('Active share is accessible', isAccessible($activeShare));

$pendingShare = makeShare(['status' => 'pending_deletion']);
assert_false('Pending deletion share is not accessible', isAccessible($pendingShare));

$deletedShare = makeShare(['status' => 'deleted']);
assert_false('Deleted share is not accessible', isAccessible($deletedShare));

$expiredShare = makeShare(['status' => 'ready', 'expires_at' => (new DateTime('-1 hour'))->format('Y-m-d H:i:s')]);
assert_true('Expired-but-ready share passes the pending_deletion gate (expiry checked separately)', isAccessible($expiredShare));

echo "\n-- Email suppression: warning emails skip pending_deletion --\n\n";

$readyShare    = makeShare(['status' => 'ready']);
$pendingShare2 = makeShare(['status' => 'pending_deletion']);
$deletedShare2 = makeShare(['status' => 'deleted']);

assert_true('Ready share receives expiry warning', shouldReceiveExpiryWarning($readyShare));
assert_false('Pending deletion share suppressed from expiry warning', shouldReceiveExpiryWarning($pendingShare2));
assert_false('Deleted share suppressed from expiry warning', shouldReceiveExpiryWarning($deletedShare2));

echo "\n-- Round-trip: request then undo then re-request --\n\n";

$shareRT = makeShare(['user_id' => 1, 'status' => 'ready']);
$r1 = requestDeletion($shareRT, $owner);
assert_eq('Round-trip step 1: request deletion succeeds', 'success', $r1['status']);
$u1 = undoDeletion($shareRT, $owner);
assert_eq('Round-trip step 2: undo succeeds', 'success', $u1['status']);
assert_eq('Round-trip step 2: status back to ready', 'ready', $shareRT->status);
$r2 = requestDeletion($shareRT, $owner);
assert_eq('Round-trip step 3: can request deletion again after undo', 'success', $r2['status']);
assert_eq('Round-trip step 3: status is pending_deletion again', 'pending_deletion', $shareRT->status);

// ---------------------------------------------------------------------------
// Negative tests
// ---------------------------------------------------------------------------
echo "\n-- Negative tests --\n\n";

$shareNull = makeShare(['user_id' => 1, 'status' => 'pending']);
$negResult = requestDeletion($shareNull, $owner);
assert_eq('Pending (zip building) share can be requested for deletion', 'success', $negResult['status']);

$shareFailed = makeShare(['user_id' => 1, 'status' => 'failed']);
$negResult2 = requestDeletion($shareFailed, $owner);
assert_eq('Failed share can be requested for deletion', 'success', $negResult2['status']);

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
