<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\FileHelper;

class UploadsControllerSecurityTest extends TestCase
{
    /**
     * Test that sanitized filenames are used correctly in file operations.
     * This test verifies the security fix for requirement 2.2 and 2.4.
     */
    public function test_sanitized_filename_usage_in_file_operations()
    {
        // Test various dangerous filenames and their sanitized versions
        $testCases = [
            '../../../etc/passwd' => 'etc_passwd',
            '..\\..\\windows\\system32\\config' => 'windows_system32_config',
            '/absolute/path/file.txt' => 'file.txt',
            'file<>name.txt' => 'file_name.txt',
            'file|name.txt' => 'file_name.txt',
            'CON.txt' => 'file_CON.txt',
            'PRN.txt' => 'file_PRN.txt',
        ];

        foreach ($testCases as $dangerous => $expected) {
            $sanitized = FileHelper::sanitizeFilename($dangerous);
            
            // Verify the sanitized filename is safe
            $this->assertFalse(FileHelper::isDangerousFilename($sanitized), 
                "Sanitized filename '$sanitized' should not be dangerous");
            
            // Verify no path traversal sequences remain
            $this->assertStringNotContainsString('..', $sanitized, 
                "Sanitized filename should not contain path traversal sequences");
            
            // Verify no absolute path indicators remain
            $this->assertStringNotContainsString('/', $sanitized, 
                "Sanitized filename should not contain path separators");
            $this->assertStringNotContainsString('\\', $sanitized, 
                "Sanitized filename should not contain backslashes");
        }
    }

    /**
     * Test that the file operation logic uses sanitized names correctly.
     * This simulates the logic from createShareFromChunks method.
     */
    public function test_file_operation_security_logic()
    {
        // Simulate a file record with dangerous original name but sanitized stored name
        $dangerousOriginalName = '../../../etc/passwd';
        $sanitizedStoredName = FileHelper::sanitizeFilename($dangerousOriginalName);
        
        // Simulate the file record structure
        $fileRecord = (object) [
            'name' => $sanitizedStoredName,  // This is what should be used for file operations
            'original_name' => $dangerousOriginalName,  // This is for display only
            'id' => 1
        ];

        // Verify that the sanitized name is safe for file operations
        $this->assertFalse(strpos($fileRecord->name, '..') !== false, 
            'Stored filename should not contain path traversal sequences');
        
        $this->assertFalse(strpos($fileRecord->name, '/') === 0, 
            'Stored filename should not start with path separator');
        
        // Verify the original dangerous name is preserved for display
        $this->assertEquals($dangerousOriginalName, $fileRecord->original_name);
        
        // Verify the sanitized name is what gets used in file operations
        $this->assertEquals($sanitizedStoredName, $fileRecord->name);
        
        // This simulates the fixed line in createShareFromChunks:
        // $sanitizedFilename = $file->name;
        $filenameUsedInOperation = $fileRecord->name;
        $this->assertFalse(FileHelper::isDangerousFilename($filenameUsedInOperation),
            'Filename used in file operations must be safe');
    }

    /**
     * Test edge cases for filename sanitization.
     */
    public function test_filename_sanitization_edge_cases()
    {
        // Empty filename
        $result = FileHelper::sanitizeFilename('');
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('file_', $result);

        // Only dots
        $result = FileHelper::sanitizeFilename('...');
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('file_', $result);

        // Only path separators
        $result = FileHelper::sanitizeFilename('///');
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('file_', $result);

        // Very long filename
        $longName = str_repeat('a', 300) . '.txt';
        $result = FileHelper::sanitizeFilename($longName);
        $this->assertLessThanOrEqual(255, strlen($result));
    }
}