# F4: Share Pending Deletion — Test Results

**Branch:** `feature/share-pending-deletion`
**Run date:** 2026-07-11 UTC
**Platform:** Linux x86_64 (Kali)

---

## Changes Under Test

| File | Change |
|---|---|
| `database/migrations/2026_07_11_200000_add_pending_deletion_to_shares_table.php` | Adds `deletion_requested_at` (timestamp, nullable) and `deletion_requested_by` (string 10, nullable) to shares |
| `app/Models/Share.php` | Adds `pending_deletion` to `$appends`/`$fillable`/`$casts`; adds `getPendingDeletionAttribute()` |
| `app/Http/Controllers/SharesController.php` | Blocks `pending_deletion` in `read()` and `download()`; adds `requestDeletion()` and `undoDeletion()` |
| `app/Jobs/sendDeletionWarningEmails.php` | Excludes `pending_deletion` shares from deletion warning query |
| `app/Jobs/sendExpiryWarningEmails.php` | Excludes `pending_deletion` shares from expiry warning query |
| `app/Jobs/sendExpiredWarningEmails.php` | Excludes `pending_deletion` shares from expired warning query |
| `routes/api.php` | Adds `POST /{id}/request-deletion` and `POST /{id}/undo-deletion` routes (auth middleware) |
| `resources/js/api.js` | Adds `requestShareDeletion()` and `undoShareDeletion()` |
| `resources/js/components/settings/myShares.vue` | Splits shares into activeShares/pendingDeletionShares computed; adds "Request deletion" button; adds pending deletion section (conditional on non-empty) with undo button |
| `resources/js/components/settings/allShares.vue` | Adds pending_deletion badge with requester label; adds request/undo deletion buttons for admin |

---

## Test Script

**Script:** `tests/scripts/test_share_pending_deletion.php`
**Runner:** `php tests/scripts/test_share_pending_deletion.php`

Mirrors `requestDeletion()`, `undoDeletion()`, access guard (`isAccessible()`), and email suppression logic as standalone PHP functions. No Laravel runtime or composer vendor required.

---

## Output

```
=================================================================
F4: Share Pending Deletion — Test Suite
=================================================================

-- State machine: requestDeletion() --

PASS: Owner can request deletion of own share — status OK
PASS: Status set to pending_deletion
PASS: deletion_requested_by = user
PASS: deletion_requested_at is set
PASS: share is now pending deletion
PASS: Admin can request deletion of any share
PASS: deletion_requested_by = admin when admin requests
PASS: Non-owner cannot request deletion
PASS: Non-owner gets 401
PASS: Share status unchanged after unauthorized request
PASS: Cannot double-request deletion
PASS: Double-request returns 422
PASS: Cannot request deletion of already-deleted share
PASS: Already-deleted returns 422

-- State machine: undoDeletion() --

PASS: Owner can undo deletion of own share
PASS: Status restored to ready
PASS: deletion_requested_at cleared
PASS: deletion_requested_by cleared
PASS: Share is no longer pending deletion
PASS: Admin can undo deletion on any share
PASS: Cannot undo deletion of non-pending share
PASS: Non-pending undo returns 422
PASS: Non-owner cannot undo deletion
PASS: Non-owner undo returns 401

-- Access control: read() / download() --

PASS: Active share is accessible
PASS: Pending deletion share is not accessible
PASS: Deleted share is not accessible
PASS: Expired-but-ready share passes the pending_deletion gate (expiry checked separately)

-- Email suppression: warning emails skip pending_deletion --

PASS: Ready share receives expiry warning
PASS: Pending deletion share suppressed from expiry warning
PASS: Deleted share suppressed from expiry warning

-- Round-trip: request then undo then re-request --

PASS: Round-trip step 1: request deletion succeeds
PASS: Round-trip step 2: undo succeeds
PASS: Round-trip step 2: status back to ready
PASS: Round-trip step 3: can request deletion again after undo
PASS: Round-trip step 3: status is pending_deletion again

-- Negative tests --

PASS: Pending (zip building) share can be requested for deletion
PASS: Failed share can be requested for deletion

=================================================================
Results: 38 passed, 0 failed
All tests passed.
```

---

## Summary

| Test group | Tests | Pass | Fail |
|---|---|---|---|
| requestDeletion() state machine | 14 | 14 | 0 |
| undoDeletion() state machine | 10 | 10 | 0 |
| Access control (read/download gate) | 4 | 4 | 0 |
| Email suppression | 3 | 3 | 0 |
| Round-trip (request → undo → re-request) | 5 | 5 | 0 |
| Negative tests (non-ready statuses) | 2 | 2 | 0 |
| **Total** | **38** | **38** | **0** |
