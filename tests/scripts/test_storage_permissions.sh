#!/bin/sh
# Test the chmod fix for storage directory permissions.
#
# Reproduces the Synology DSM Windows-ACL-mode scenario where new directories
# created by root inside a Docker container receive POSIX mode 000.
# Verifies that the fix (chmod -R u=rwX,g=rX,o= after chown) corrects this.
#
# Root is NOT required. The test runs as the current user.
#
# Run with: sh tests/scripts/test_storage_permissions.sh

PASS=0
FAIL=0
FAILURES=""

# -----------------------------------------------------------------------
# Helpers
# -----------------------------------------------------------------------

assert_mode() {
    TEST_NAME="$1"
    EXPECTED="$2"
    PATH_TO_CHECK="$3"
    ACTUAL=$(stat -c '%a' "$PATH_TO_CHECK" 2>/dev/null || stat -f '%Lp' "$PATH_TO_CHECK" 2>/dev/null)
    if [ "$EXPECTED" = "$ACTUAL" ]; then
        echo "PASS: $TEST_NAME ($ACTUAL)"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME"
        echo "  Path:     $PATH_TO_CHECK"
        echo "  Expected: $EXPECTED"
        echo "  Actual:   $ACTUAL"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

assert_writable() {
    TEST_NAME="$1"
    PATH_TO_CHECK="$2"
    if [ -w "$PATH_TO_CHECK" ]; then
        echo "PASS: $TEST_NAME (writable)"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME (not writable)"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

assert_readable() {
    TEST_NAME="$1"
    PATH_TO_CHECK="$2"
    if [ -r "$PATH_TO_CHECK" ]; then
        echo "PASS: $TEST_NAME (readable)"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME (not readable)"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

assert_traversable() {
    TEST_NAME="$1"
    PATH_TO_CHECK="$2"
    if [ -x "$PATH_TO_CHECK" ]; then
        echo "PASS: $TEST_NAME (traversable)"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME (not traversable — execute bit missing)"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

assert_file_not_executable() {
    TEST_NAME="$1"
    PATH_TO_CHECK="$2"
    if [ ! -x "$PATH_TO_CHECK" ]; then
        echo "PASS: $TEST_NAME (execute bit correctly absent on data file)"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME (execute bit incorrectly set on data file)"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

# -----------------------------------------------------------------------
# Set up temp directory simulating /var/www/html/storage layout
# -----------------------------------------------------------------------

TMPROOT=$(mktemp -d)
STORAGE="$TMPROOT/storage"

mkdir -p "$STORAGE"
mkdir -p "$STORAGE/logs"
mkdir -p "$STORAGE/app"
mkdir -p "$STORAGE/framework"
mkdir -p "$STORAGE/templates"

# Create some data files to verify execute bit is NOT set on them
touch "$STORAGE/.setup-lock"
printf 'base64:abc123==' > "$STORAGE/app.key"
printf 'secretjwt' > "$STORAGE/jwt.secret"
touch "$STORAGE/logs/laravel.log"
touch "$STORAGE/app/database.sqlite"

echo "================================================================="
echo "Storage Permissions Fix — Test Suite"
echo "Covers: docker/alpine/start-container (chmod -R u=rwX,g=rX,o=)"
echo "Temp storage root: $STORAGE"
echo "================================================================="

echo ""
echo "-- Pre-condition: simulate Synology ACL 000 permissions --"
echo "   (Children are chmoded first while parent is still traversable,"
echo "    then the parent is set to 000 last — matches Synology behaviour"
echo "    where all dirs land as 000 from creation.)"

# Simulate Synology ACL-created dirs landing with 000 POSIX mode.
# Children must be set BEFORE the parent, since once the parent is 000
# we can no longer traverse into it to chmod children.
chmod 000 "$STORAGE/logs"
chmod 000 "$STORAGE/app"
chmod 000 "$STORAGE/framework"
chmod 000 "$STORAGE/templates"
# Also simulate root-created files with 000
chmod 000 "$STORAGE/.setup-lock" 2>/dev/null || true
chmod 000 "$STORAGE/app.key" 2>/dev/null || true

# Assert children BEFORE sealing the parent
assert_mode "PRE: logs/ has mode 000"      "0" "$STORAGE/logs"
assert_mode "PRE: app/ has mode 000"       "0" "$STORAGE/app"
assert_mode "PRE: framework/ has mode 000" "0" "$STORAGE/framework"
assert_mode "PRE: templates/ has mode 000" "0" "$STORAGE/templates"

# Seal the parent last
chmod 000 "$STORAGE"
assert_mode "PRE: storage/ has mode 000"   "0" "$STORAGE"

echo ""
echo "-- Applying fix: chmod -R u=rwX,g=rX,o= --"

# This is the exact command added to start-container
chmod -R u=rwX,g=rX,o= "$STORAGE"
echo "chmod applied."

echo ""
echo "-- Post-condition: directories should be mode 750 (rwxr-x---) --"

assert_mode "POST: storage/ has mode 750"   "750" "$STORAGE"
assert_mode "POST: logs/ has mode 750"      "750" "$STORAGE/logs"
assert_mode "POST: app/ has mode 750"       "750" "$STORAGE/app"
assert_mode "POST: framework/ has mode 750" "750" "$STORAGE/framework"
assert_mode "POST: templates/ has mode 750" "750" "$STORAGE/templates"

echo ""
echo "-- Post-condition: directories are now accessible --"

assert_traversable "storage/ is traversable after fix" "$STORAGE"
assert_traversable "logs/ is traversable after fix"    "$STORAGE/logs"
assert_traversable "app/ is traversable after fix"     "$STORAGE/app"
assert_writable    "storage/ is writable after fix"    "$STORAGE"
assert_writable    "logs/ is writable after fix"       "$STORAGE/logs"
assert_readable    "storage/ is readable after fix"    "$STORAGE"

echo ""
echo "-- Post-condition: data files get 640 (rw-r-----) not 750 --"
echo "   (capital X in chmod only sets execute on directories)"

assert_mode "POST: app.key has mode 640 not 750"      "640" "$STORAGE/app.key"
assert_mode "POST: .setup-lock has mode 640 not 750"  "640" "$STORAGE/.setup-lock"
assert_mode "POST: laravel.log has mode 640 not 750"  "640" "$STORAGE/logs/laravel.log"
assert_mode "POST: database.sqlite has mode 640"       "640" "$STORAGE/app/database.sqlite"

assert_file_not_executable "app.key has no execute bit"      "$STORAGE/app.key"
assert_file_not_executable "jwt.secret has no execute bit"   "$STORAGE/jwt.secret"
assert_file_not_executable "laravel.log has no execute bit"  "$STORAGE/logs/laravel.log"

echo ""
echo "-- Runtime: files created after fix should get normal permissions --"
echo "   (Simulates erugo user writing log entries, uploads, etc.)"

touch "$STORAGE/logs/new-runtime.log"
printf 'upload content' > "$STORAGE/app/new-upload.bin"
RUNTIME_LOG_MODE=$(stat -c '%a' "$STORAGE/logs/new-runtime.log" 2>/dev/null || stat -f '%Lp' "$STORAGE/logs/new-runtime.log" 2>/dev/null)
RUNTIME_UPLOAD_MODE=$(stat -c '%a' "$STORAGE/app/new-upload.bin" 2>/dev/null || stat -f '%Lp' "$STORAGE/app/new-upload.bin" 2>/dev/null)

if [ "$RUNTIME_LOG_MODE" != "0" ] && [ "$RUNTIME_LOG_MODE" != "" ]; then
    echo "PASS: Runtime-created log file has mode $RUNTIME_LOG_MODE (not 000)"
    PASS=$((PASS+1))
else
    echo "FAIL: Runtime-created log file has mode 000 — directory permissions not fixed"
    FAIL=$((FAIL+1))
    FAILURES="$FAILURES\n  - Runtime file creation"
fi

assert_writable "Can write to logs/ at runtime" "$STORAGE/logs"

echo ""
echo "-- Clean up --"
chmod -R u=rwx "$TMPROOT" 2>/dev/null
rm -rf "$TMPROOT"
echo "Temp directory removed."

echo ""
echo "================================================================="
echo "Results: $PASS passed, $FAIL failed"
if [ "$FAIL" -gt 0 ]; then
    printf "FAILURES:%b\n" "$FAILURES"
    exit 1
fi
echo "All tests passed."
exit 0
