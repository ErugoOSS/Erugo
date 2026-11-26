<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\FileHelper;

class FileHelperTest extends TestCase
{
    /**
     * Test filename sanitization removes path traversal sequences.
     * Requirement 1.2: WHEN a filename contains path traversal sequences (../, ..\, etc.) 
     * THEN the system SHALL remove these sequences completely
     */
    public function test_sanitize_filename_removes_path_traversal_sequences()
    {
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('../test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('..\\test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('../../test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('..\\..\\test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('....//test.txt'));
    }

    /**
     * Test filename sanitization strips absolute path indicators.
     * Requirement 1.3: WHEN a filename contains absolute path indicators 
     * THEN the system SHALL strip leading path separators
     */
    public function test_sanitize_filename_strips_absolute_paths()
    {
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('/test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('\\test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('/path/to/test.txt'));
        $this->assertEquals('test.txt', FileHelper::sanitizeFilename('\\path\\to\\test.txt'));
    }

    /**
     * Test filename sanitization generates safe filename when empty.
     * Requirement 1.4: WHEN a filename is empty after sanitization 
     * THEN the system SHALL generate a unique safe filename
     */
    public function test_sanitize_filename_generates_safe_name_when_empty()
    {
        $result = FileHelper::sanitizeFilename('');
        $this->assertStringStartsWith('file_', $result);
        $this->assertStringEndsWith('.txt', $result);
        
        $result = FileHelper::sanitizeFilename('...');
        $this->assertStringStartsWith('file_', $result);
        
        $result = FileHelper::sanitizeFilename('///');
        $this->assertStringStartsWith('file_', $result);
    }

    /**
     * Test filename sanitization removes dangerous characters.
     */
    public function test_sanitize_filename_removes_dangerous_characters()
    {
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test<>file.txt'));
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test|file.txt'));
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test*file.txt'));
        $this->assertEquals('test_file.txt', FileHelper::sanitizeFilename('test?file.txt'));
    }

    /**
     * Test path parameter validation rejects traversal attempts.
     * Requirement 1.5: WHEN validating path parameters THEN the system SHALL reject 
     * any containing traversal attempts or absolute paths
     */
    public function test_validate_path_parameter_rejects_traversal()
    {
        $this->assertFalse(FileHelper::validatePathParameter('../test.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('..\\test.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('test/../file.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('test\\..\\file.txt'));
    }

    /**
     * Test path parameter validation rejects absolute paths.
     */
    public function test_validate_path_parameter_rejects_absolute_paths()
    {
        $this->assertFalse(FileHelper::validatePathParameter('/test.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('\\test.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('C:\\test.txt'));
        $this->assertFalse(FileHelper::validatePathParameter('/var/www/test.txt'));
    }

    /**
     * Test path parameter validation accepts safe paths.
     */
    public function test_validate_path_parameter_accepts_safe_paths()
    {
        $this->assertTrue(FileHelper::validatePathParameter('test.txt'));
        $this->assertTrue(FileHelper::validatePathParameter('image.jpg'));
        $this->assertTrue(FileHelper::validatePathParameter('background-image.png'));
        $this->assertTrue(FileHelper::validatePathParameter('folder/test.txt'));
    }

    /**
     * Test dangerous filename detection.
     */
    public function test_is_dangerous_filename_detection()
    {
        $this->assertTrue(FileHelper::isDangerousFilename('../test.txt'));
        $this->assertTrue(FileHelper::isDangerousFilename('/test.txt'));
        $this->assertTrue(FileHelper::isDangerousFilename("test\0.txt"));
        $this->assertFalse(FileHelper::isDangerousFilename('safe-file.txt'));
    }

    /**
     * Test reserved filename handling.
     */
    public function test_sanitize_filename_handles_reserved_names()
    {
        $this->assertStringStartsWith('file_', FileHelper::sanitizeFilename('CON.txt'));
        $this->assertStringStartsWith('file_', FileHelper::sanitizeFilename('PRN.txt'));
        $this->assertStringStartsWith('file_', FileHelper::sanitizeFilename('AUX.txt'));
    }

    /**
     * The stored extension must come from the detected media type, never from
     * the client filename: a media file named "*.php" would otherwise land in a
     * web-served directory with an executable extension.
     */
    public function test_force_media_extension_uses_detected_type()
    {
        $this->assertEquals('photo.jpg', FileHelper::forceMediaExtension('photo.jpg', 'image/jpeg'));
        $this->assertEquals('photo.jpg', FileHelper::forceMediaExtension('photo.jpeg', 'image/jpeg'));
        $this->assertEquals('clip.webm', FileHelper::forceMediaExtension('clip.webm', 'video/webm'));

        // executable extensions are replaced
        $this->assertEquals('payload.jpg', FileHelper::forceMediaExtension('payload.php', 'image/jpeg'));
        $this->assertEquals('payload_php.jpg', FileHelper::forceMediaExtension('payload.php.jpg', 'image/jpeg'));
        $this->assertEquals('my_photo.jpg', FileHelper::forceMediaExtension('my.photo.jpeg', 'image/jpeg'));
        $this->assertEquals('payload.png', FileHelper::forceMediaExtension('payload.phtml', 'image/png'));

        // names without an extension get one
        $this->assertEquals('photo.png', FileHelper::forceMediaExtension('photo', 'image/png'));

        // traversal in the client name is still stripped by the sanitiser
        $this->assertEquals('passwd.jpg', FileHelper::forceMediaExtension('../../etc/passwd', 'image/jpeg'));

        // unknown types are refused outright
        $this->assertNull(FileHelper::forceMediaExtension('photo.jpg', 'application/x-php'));
        $this->assertNull(FileHelper::forceMediaExtension('photo.svg', 'image/svg+xml'));
    }
}
