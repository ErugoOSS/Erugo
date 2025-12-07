<?php

namespace App\Utils;

class FileHelper
{
    /**
     * Sanitize a filename to prevent path traversal attacks and ensure safe file operations.
     * 
     * This method implements comprehensive sanitization logic to:
     * - Remove path traversal sequences (../, ..\, etc.)
     * - Strip absolute path indicators
     * - Remove dangerous characters
     * - Generate safe filename if result is empty
     * 
     * @param string $filename The original filename to sanitize
     * @return string The sanitized filename safe for file operations
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Handle empty input
        if (empty(trim($filename))) {
            return self::generateSafeFilename();
        }

        // Remove path traversal sequences
        $sanitized = str_replace(['../', '..\\'], '', $filename);
        
        // Remove additional traversal patterns
        $sanitized = str_replace(['..//', '..\\\\', '....//'], '', $sanitized);
        
        // Strip absolute path indicators and get basename
        // Handle both Unix and Windows path separators
        $sanitized = preg_replace('/.*[\/\\\\]/', '', $sanitized);
        
        // Replace dangerous characters with single underscore
        // Allow alphanumeric, dots, hyphens, underscores, and spaces
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-\s]/', '_', $sanitized);
        
        // Replace multiple consecutive underscores with single underscore
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        
        // Remove multiple consecutive dots (potential traversal)
        $sanitized = preg_replace('/\.{2,}/', '.', $sanitized);
        
        // Remove leading/trailing dots, spaces, and underscores
        $sanitized = trim($sanitized, '. _');
        
        // Ensure filename doesn't start with dot (hidden files)
        if (strpos($sanitized, '.') === 0) {
            $sanitized = 'file' . $sanitized;
        }
        
        // Check for reserved names and dangerous patterns
        $reservedNames = ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'];
        $nameWithoutExt = pathinfo($sanitized, PATHINFO_FILENAME);
        
        if (in_array(strtoupper($nameWithoutExt), $reservedNames)) {
            $sanitized = 'file_' . $sanitized;
        }
        
        // If sanitization resulted in empty string or just extension, generate safe name
        if (empty($sanitized) || $sanitized === '.' || strlen(trim($sanitized, '.')) === 0) {
            return self::generateSafeFilename();
        }
        
        // Limit filename length (255 is typical filesystem limit)
        if (strlen($sanitized) > 255) {
            $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
            $name = pathinfo($sanitized, PATHINFO_FILENAME);
            $maxNameLength = 255 - strlen($extension) - 1; // -1 for the dot
            $sanitized = substr($name, 0, $maxNameLength) . '.' . $extension;
        }
        
        return $sanitized;
    }

    /**
     * Validate a path parameter to prevent path traversal attacks.
     * 
     * This method checks if a path parameter is safe for use in file operations by:
     * - Rejecting paths containing traversal sequences
     * - Rejecting absolute paths
     * - Allowing only safe characters
     * 
     * @param string $path The path parameter to validate
     * @return bool True if the path is safe, false otherwise
     */
    public static function validatePathParameter(string $path): bool
    {
        // Reject empty paths
        if (empty(trim($path))) {
            return false;
        }
        
        // Reject paths containing traversal sequences
        if (strpos($path, '..') !== false) {
            return false;
        }
        
        // Reject absolute paths (starting with / or \)
        if (strpos($path, '/') === 0 || strpos($path, '\\') === 0) {
            return false;
        }
        
        // Reject paths containing drive letters (Windows absolute paths)
        if (preg_match('/^[a-zA-Z]:/', $path)) {
            return false;
        }
        
        // Only allow alphanumeric characters, dots, hyphens, underscores, spaces, and forward slashes for subdirectories
        if (!preg_match('/^[a-zA-Z0-9._\-\/\s]+$/', $path)) {
            return false;
        }
        
        // Reject paths with multiple consecutive slashes
        if (strpos($path, '//') !== false) {
            return false;
        }
        
        // Reject paths starting or ending with dots
        if (strpos($path, '.') === 0 || substr($path, -1) === '.') {
            return false;
        }
        
        return true;
    }

    /**
     * Generate a unique safe filename when sanitization fails or input is invalid.
     * 
     * @param string $extension Optional file extension to append
     * @return string A unique safe filename
     */
    private static function generateSafeFilename(string $extension = 'txt'): string
    {
        return 'file_' . uniqid() . '.' . $extension;
    }

    /**
     * Extract and sanitize file extension from filename.
     * 
     * @param string $filename The filename to extract extension from
     * @return string The sanitized file extension (without dot)
     */
    public static function sanitizeFileExtension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Remove dangerous characters from extension
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
        
        // Limit extension length
        if (strlen($extension) > 10) {
            $extension = substr($extension, 0, 10);
        }
        
        return strtolower($extension);
    }

    /**
     * Check if a filename is potentially dangerous.
     * 
     * @param string $filename The filename to check
     * @return bool True if filename appears dangerous, false otherwise
     */
    public static function isDangerousFilename(string $filename): bool
    {
        // Check for path traversal sequences
        if (strpos($filename, '..') !== false) {
            return true;
        }
        
        // Check for absolute path indicators
        if (strpos($filename, '/') === 0 || strpos($filename, '\\') === 0) {
            return true;
        }
        
        // Check for null bytes
        if (strpos($filename, "\0") !== false) {
            return true;
        }
        
        // Check for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            return true;
        }
        
        return false;
    }
}