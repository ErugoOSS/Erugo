<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\FileHelper;

class SharesControllerSecurityTest extends TestCase
{
    public function test_file_helper_sanitization_prevents_path_traversal()
    {
        $testCases = [
            '../../../etc/passwd',
            '..\\..\\windows\\system32\\config',
            '/absolute/path/file.txt',
            'normal_file.txt',
            'file with spaces.txt',
            ''
        ];

        foreach ($testCases as $input) {
            $result = FileHelper::sanitizeFilename($input);
            
            // Verify no path traversal sequences
            $this->assertStringNotContainsString('..', $result);
            $this->assertStringNotContainsString('//', $result);
            
            // Verify no absolute paths
            $this->assertStringStartsNotWith('/', $result);
            $this->assertStringStartsNotWith('\\', $result);
            
            // For empty input, should generate a safe filename
            if (empty($input)) {
                $this->assertStringStartsWith('file_', $result);
            }
            
            // Result should not be empty
            $this->assertNotEmpty($result);
        }
    }

    public function test_path_parameter_validation()
    {
        $validPaths = [
            'normal_file.txt',
            'subfolder/file.txt',
            'image.jpg'
        ];

        $invalidPaths = [
            '../../../etc/passwd',
            '/absolute/path',
            '..\\windows\\system32',
            'file/../other',
            ''
        ];

        foreach ($validPaths as $path) {
            $this->assertTrue(FileHelper::validatePathParameter($path), "Path should be valid: $path");
        }

        foreach ($invalidPaths as $path) {
            $this->assertFalse(FileHelper::validatePathParameter($path), "Path should be invalid: $path");
        }
    }

    public function test_dangerous_filename_detection()
    {
        $dangerousFilenames = [
            '../../../etc/passwd',
            '/absolute/path',
            "file\0null.txt",
            "file\x01control.txt"
        ];

        $safeFilenames = [
            'normal_file.txt',
            'image.jpg',
            'document.pdf'
        ];

        foreach ($dangerousFilenames as $filename) {
            $this->assertTrue(FileHelper::isDangerousFilename($filename), "Should detect as dangerous: $filename");
        }

        foreach ($safeFilenames as $filename) {
            $this->assertFalse(FileHelper::isDangerousFilename($filename), "Should detect as safe: $filename");
        }
    }
}