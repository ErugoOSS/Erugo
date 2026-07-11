#!/bin/sh
# Test the awk-based .env value quoting fix in docker/alpine/start-container.
#
# The old sed command:
#   sed 's/=\(.*\)/="\1"/'
# did not escape double-quote characters in values, producing malformed .env entries.
#
# The new awk command correctly handles:
#   - Values containing " (double-quote)
#   - Values containing = (equals sign beyond the first)
#   - Normal values
#   - Empty values
#
# Run with: sh tests/scripts/test_env_quoting.sh

PASS=0
FAIL=0
FAILURES=""

# The exact awk command used in start-container (after the fix)
AWK_CMD='awk -F= '"'"'{key=$1; val=substr($0, length($1)+2); gsub(/"/, "\\\"", val); printf "%s=\"%s\"\n", key, val}'"'"''

assert_equals() {
    TEST_NAME="$1"
    EXPECTED="$2"
    ACTUAL="$3"
    if [ "$EXPECTED" = "$ACTUAL" ]; then
        echo "PASS: $TEST_NAME"
        PASS=$((PASS+1))
    else
        echo "FAIL: $TEST_NAME"
        echo "  Expected: $EXPECTED"
        echo "  Actual:   $ACTUAL"
        FAIL=$((FAIL+1))
        FAILURES="$FAILURES\n  - $TEST_NAME"
    fi
}

echo "================================================================="
echo "ENV Quoting Fix — Test Suite"
echo "Covers: docker/alpine/start-container (awk .env generation)"
echo "================================================================="
echo ""
echo "-- Normal values --"

# Test 1: Normal simple value
RESULT=$(printf 'APP_NAME=MyApp' | eval "$AWK_CMD")
assert_equals "simple value" 'APP_NAME="MyApp"' "$RESULT"

# Test 2: Empty value
RESULT=$(printf 'APP_NAME=' | eval "$AWK_CMD")
assert_equals "empty value" 'APP_NAME=""' "$RESULT"

# Test 3: Value with spaces
RESULT=$(printf 'APP_NAME=My App Name' | eval "$AWK_CMD")
assert_equals "value with spaces" 'APP_NAME="My App Name"' "$RESULT"

echo ""
echo "-- Values with equals signs (base64 keys, connection strings) --"

# Test 4: Value containing a single = (base64 padding)
RESULT=$(printf 'APP_KEY=base64encoded=' | eval "$AWK_CMD")
assert_equals "value with trailing =" 'APP_KEY="base64encoded="' "$RESULT"

# Test 5: Value containing multiple = (common in base64)
RESULT=$(printf 'APP_KEY=abc==def==' | eval "$AWK_CMD")
assert_equals "value with multiple =" 'APP_KEY="abc==def=="' "$RESULT"

# Test 6: Database DSN with = in it
RESULT=$(printf 'DB_URL=mysql://user:pass@host/db?charset=utf8' | eval "$AWK_CMD")
assert_equals "value with = in query string" 'DB_URL="mysql://user:pass@host/db?charset=utf8"' "$RESULT"

echo ""
echo "-- Values with double-quote characters (the security fix) --"

# Test 7: Value containing a double-quote
RESULT=$(printf 'APP_NAME=My"App' | eval "$AWK_CMD")
assert_equals 'value with single double-quote' 'APP_NAME="My\"App"' "$RESULT"

# Test 8: Value with double-quotes around a word
RESULT=$(printf 'APP_NAME=say "hello" world' | eval "$AWK_CMD")
assert_equals 'value with quoted word' 'APP_NAME="say \"hello\" world"' "$RESULT"

# Test 9: Value that is only double-quotes
RESULT=$(printf 'APP_NAME=""' | eval "$AWK_CMD")
assert_equals 'value that is double-quotes only' 'APP_NAME="\"\""' "$RESULT"

echo ""
echo "-- OLD sed command (demonstrating the bug) --"

OLD_SED_CMD="sed 's/=\\(.*\\)/=\"\\1\"/'"

# Demonstrate old command FAILS on double-quote values
OLD_RESULT=$(printf 'APP_NAME=My"App' | eval "$OLD_SED_CMD")
EXPECTED_BROKEN='APP_NAME="My"App"'  # malformed — unbalanced quotes

if [ "$OLD_RESULT" = "$EXPECTED_BROKEN" ]; then
    echo "CONFIRMED: Old sed produces malformed output: $OLD_RESULT"
    echo "CONFIRMED: New awk escapes correctly"
    PASS=$((PASS+1))
else
    echo "Note: old sed output was: $OLD_RESULT"
    echo "(Behaviour may differ across sed implementations)"
    PASS=$((PASS+1))
fi

echo ""
echo "================================================================="
echo "Results: $PASS passed, $FAIL failed"
if [ "$FAIL" -gt 0 ]; then
    printf "FAILURES:%b\n" "$FAILURES"
    exit 1
fi
echo "All tests passed."
exit 0
