<?php

namespace Tests\Unit;

use App\Utils\PathGuard;
use PHPUnit\Framework\TestCase;

class PathGuardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/pathguard_' . uniqid();
        mkdir($this->root . '/shares/abc/sub', 0750, true);
        mkdir($this->root . '/shares/abcevil', 0750, true);
        file_put_contents($this->root . '/shares/abc/sub/inside.txt', 'ok');
        file_put_contents($this->root . '/shares/abcevil/outside.txt', 'no');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        parent::tearDown();
    }

    public function test_path_inside_base_is_accepted(): void
    {
        $base = $this->root . '/shares/abc';
        $this->assertTrue(PathGuard::isWithin($base . '/sub/inside.txt', $base));
        $this->assertTrue(PathGuard::isWithin($base . '/sub', $base));
        $this->assertTrue(PathGuard::isWithin($base, $base));
    }

    public function test_sibling_with_same_prefix_is_rejected(): void
    {
        $base = $this->root . '/shares/abc';
        $sibling = $this->root . '/shares/abcevil/outside.txt';

        // The old strpos($resolved, $base) === 0 check let this through.
        $this->assertSame(0, strpos(realpath($sibling), realpath($base)));
        $this->assertFalse(PathGuard::isWithin($sibling, $base));
        $this->assertFalse(PathGuard::isWithin($base . '/../abcevil/outside.txt', $base));
    }

    public function test_missing_paths_are_rejected(): void
    {
        $base = $this->root . '/shares/abc';
        $this->assertFalse(PathGuard::isWithin($base . '/nope.txt', $base));
        $this->assertFalse(PathGuard::isWithin($base . '/sub/inside.txt', $this->root . '/missing'));
    }

    public function test_single_share_file_honours_full_path(): void
    {
        $base = $this->root . '/shares/abc';

        $this->assertSame(
            realpath($base . '/sub/inside.txt'),
            PathGuard::singleShareFile($base, 'sub', 'inside.txt')
        );
        $this->assertNull(PathGuard::singleShareFile($base, null, 'inside.txt'));
        $this->assertNull(PathGuard::singleShareFile($base, '../abcevil', 'outside.txt'));
        $this->assertNull(PathGuard::singleShareFile($base, null, 'sub'));
    }
}
