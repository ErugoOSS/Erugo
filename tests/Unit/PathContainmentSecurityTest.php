<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the path containment security fix applied in fix/security-hardening.
 *
 * The original strpos() check in UploadsController.php and TusdHooksController.php:
 *
 *     strpos($resolvedPath, $resolvedSharePath) !== 0
 *
 * allowed paths that share a string prefix with the base path but are NOT
 * inside it. For example, with base = /share/abc:
 *
 *     strpos('/share/abcdef', '/share/abc') === 0  → TRUE → path incorrectly ALLOWED
 *
 * The fix requires a DIRECTORY_SEPARATOR boundary:
 *
 *     ($resolvedPath !== $resolvedSharePath &&
 *      strpos($resolvedPath, $resolvedSharePath . DIRECTORY_SEPARATOR) !== 0)
 *
 * This test suite verifies both the old vulnerability and the new behaviour.
 *
 * @see app/Http/Controllers/UploadsController.php:294–308
 * @see app/Http/Controllers/TusdHooksController.php:439–451
 */
class PathContainmentSecurityTest extends TestCase
{
    private string $base = '/var/www/html/storage/app/shares/42/abc123longid';

    // -----------------------------------------------------------------------
    // Helpers mirroring the exact logic in both controllers
    // -----------------------------------------------------------------------

    /**
     * The OLD (vulnerable) path containment check.
     * Returns true if the path is OUTSIDE the base (should be blocked).
     */
    private function isOutside_OLD(string $resolvedPath, string $basePath): bool
    {
        return strpos($resolvedPath, $basePath) !== 0;
    }

    /**
     * The NEW (fixed) path containment check.
     * Returns true if the path is OUTSIDE the base (should be blocked).
     */
    private function isOutside_NEW(string $resolvedPath, string $basePath): bool
    {
        return $resolvedPath !== $basePath &&
               strpos($resolvedPath, $basePath . DIRECTORY_SEPARATOR) !== 0;
    }

    // -----------------------------------------------------------------------
    // Tests: paths that SHOULD be allowed (isOutside returns false)
    // -----------------------------------------------------------------------

    public function test_exact_share_root_is_allowed(): void
    {
        $this->assertFalse($this->isOutside_NEW($this->base, $this->base));
    }

    public function test_direct_child_directory_is_allowed(): void
    {
        $path = $this->base . '/subdir';
        $this->assertFalse($this->isOutside_NEW($path, $this->base));
    }

    public function test_deeply_nested_child_is_allowed(): void
    {
        $path = $this->base . '/level1/level2/level3';
        $this->assertFalse($this->isOutside_NEW($path, $this->base));
    }

    public function test_file_in_share_root_is_allowed(): void
    {
        $path = $this->base . '/document.pdf';
        $this->assertFalse($this->isOutside_NEW($path, $this->base));
    }

    public function test_file_in_nested_subdir_is_allowed(): void
    {
        $path = $this->base . '/docs/archive/report.pdf';
        $this->assertFalse($this->isOutside_NEW($path, $this->base));
    }

    // -----------------------------------------------------------------------
    // Tests: paths that SHOULD be blocked (isOutside returns true)
    // -----------------------------------------------------------------------

    public function test_sibling_path_with_common_prefix_is_blocked(): void
    {
        // This is the core vulnerability the fix addresses.
        $path = '/var/www/html/storage/app/shares/42/abc123longidEVIL';
        $this->assertTrue($this->isOutside_NEW($path, $this->base));
    }

    public function test_partial_prefix_sibling_is_blocked(): void
    {
        $path = '/var/www/html/storage/app/shares/42/abc123';
        $this->assertTrue($this->isOutside_NEW($path, $this->base));
    }

    public function test_completely_different_path_is_blocked(): void
    {
        $this->assertTrue($this->isOutside_NEW('/etc/passwd', $this->base));
    }

    public function test_parent_directory_is_blocked(): void
    {
        $parent = '/var/www/html/storage/app/shares/42';
        $this->assertTrue($this->isOutside_NEW($parent, $this->base));
    }

    public function test_storage_root_is_blocked(): void
    {
        $this->assertTrue($this->isOutside_NEW('/var/www/html/storage', $this->base));
    }

    public function test_different_user_share_is_blocked(): void
    {
        $path = '/var/www/html/storage/app/shares/99/abc123longid';
        $this->assertTrue($this->isOutside_NEW($path, $this->base));
    }

    public function test_web_root_escape_is_blocked(): void
    {
        $this->assertTrue($this->isOutside_NEW('/var/www/html/public/shell.php', $this->base));
    }

    // -----------------------------------------------------------------------
    // Regression test: prove old code had the bug, new code fixes it
    // -----------------------------------------------------------------------

    /**
     * Demonstrate that the old strpos check incorrectly ALLOWED a sibling path.
     * This test documents the vulnerability that was fixed.
     */
    public function test_old_code_was_vulnerable_to_prefix_bypass(): void
    {
        $sibling = '/var/www/html/storage/app/shares/42/abc123longidEVIL';

        // Old check: this path has the base as a PREFIX → strpos returns 0 → NOT blocked (bug!)
        $this->assertFalse(
            $this->isOutside_OLD($sibling, $this->base),
            'Old code incorrectly allowed a sibling path — this documents the fixed vulnerability'
        );
    }

    /**
     * Confirm that the new check correctly BLOCKS the same sibling path.
     */
    public function test_new_code_blocks_prefix_bypass(): void
    {
        $sibling = '/var/www/html/storage/app/shares/42/abc123longidEVIL';

        $this->assertTrue(
            $this->isOutside_NEW($sibling, $this->base),
            'New code must block a path that is a string-prefix of the base but not a child directory'
        );
    }

    /**
     * Confirm new code still allows a valid child after the fix.
     */
    public function test_new_code_still_allows_valid_children_after_fix(): void
    {
        $validChild = $this->base . '/user-files/photo.jpg';
        $this->assertFalse($this->isOutside_NEW($validChild, $this->base));
    }
}
