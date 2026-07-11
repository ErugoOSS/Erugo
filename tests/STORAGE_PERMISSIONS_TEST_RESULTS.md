# Storage Permissions Fix — Test Results

**Branch:** `fix/storage-permissions-chmod`
**Run date:** 2026-07-11 23:23:12 UTC
**Platform:** Linux x86_64 (Kali)

---

## Change Under Test

| File | Change |
|---|---|
| `docker/alpine/start-container:85` | Added `chmod -R u=rwX,g=rX,o= /var/www/html/storage` immediately after the existing `chown -R erugo:erugo /var/www/html/storage` |

**Root cause context:** Synology DSM shared folders with Windows ACL mode enabled create new directories with POSIX mode `000`. The prior code ran `chown` but never `chmod`, so the `erugo` user owned every storage directory but had zero permissions on them. Laravel's logging bootstrap could not traverse `storage/` and threw `Permission denied` on every startup artisan call.

---

## Test Script

**Script:** `tests/scripts/test_storage_permissions.sh`
**Runner:** `sh tests/scripts/test_storage_permissions.sh`

Simulates the Synology scenario by creating a temp directory tree and setting all entries to mode `000` (children first, parent last), then applying the fix and verifying the resulting permissions.

---

## Output

```
=================================================================
Storage Permissions Fix — Test Suite
Covers: docker/alpine/start-container (chmod -R u=rwX,g=rX,o=)
=================================================================

-- Pre-condition: simulate Synology ACL 000 permissions --
   (Children are chmoded first while parent is still traversable,
    then the parent is set to 000 last — matches Synology behaviour
    where all dirs land as 000 from creation.)
PASS: PRE: logs/ has mode 000 (0)
PASS: PRE: app/ has mode 000 (0)
PASS: PRE: framework/ has mode 000 (0)
PASS: PRE: templates/ has mode 000 (0)
PASS: PRE: storage/ has mode 000 (0)

-- Applying fix: chmod -R u=rwX,g=rX,o= --
chmod applied.

-- Post-condition: directories should be mode 750 (rwxr-x---) --
PASS: POST: storage/ has mode 750 (750)
PASS: POST: logs/ has mode 750 (750)
PASS: POST: app/ has mode 750 (750)
PASS: POST: framework/ has mode 750 (750)
PASS: POST: templates/ has mode 750 (750)

-- Post-condition: directories are now accessible --
PASS: storage/ is traversable after fix (traversable)
PASS: logs/ is traversable after fix (traversable)
PASS: app/ is traversable after fix (traversable)
PASS: storage/ is writable after fix (writable)
PASS: logs/ is writable after fix (writable)
PASS: storage/ is readable after fix (readable)

-- Post-condition: data files get 640 (rw-r-----) not 750 --
   (capital X in chmod only sets execute on directories)
PASS: POST: app.key has mode 640 not 750 (640)
PASS: POST: .setup-lock has mode 640 not 750 (640)
PASS: POST: laravel.log has mode 640 not 750 (640)
PASS: POST: database.sqlite has mode 640 (640)
PASS: app.key has no execute bit (execute bit correctly absent on data file)
PASS: jwt.secret has no execute bit (execute bit correctly absent on data file)
PASS: laravel.log has no execute bit (execute bit correctly absent on data file)

-- Runtime: files created after fix should get normal permissions --
   (Simulates erugo user writing log entries, uploads, etc.)
PASS: Runtime-created log file has mode 664 (not 000)
PASS: Can write to logs/ at runtime (writable)

=================================================================
Results: 25 passed, 0 failed
All tests passed.
```

---

## Summary

| Test group | Tests | Pass | Fail |
|---|---|---|---|
| Pre-condition: 000 permissions set correctly | 5 | 5 | 0 |
| Post-condition: directories are mode 750 | 5 | 5 | 0 |
| Post-condition: directories are accessible | 6 | 6 | 0 |
| Post-condition: data files are mode 640 (not 750) | 7 | 7 | 0 |
| Runtime file creation works after fix | 2 | 2 | 0 |
| **Total** | **25** | **25** | **0** |

The test confirms:
- The `chmod -R u=rwX,g=rX,o=` command restores access after 000-mode directories
- Capital `X` correctly applies execute only to directories, not data files — preventing log files, SQLite DBs, and keys from becoming executable
- Runtime file creation (new logs, uploads) works normally once the directories are accessible
