<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AutoTaggingService
{
    /**
     * MIME types that map to the 'document' media type tag.
     */
    private const DOCUMENT_TYPES = [
        'application/pdf',
        'application/msword',
        'application/rtf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
    ];

    /**
     * MIME prefixes that map to the 'document' media type tag.
     */
    private const DOCUMENT_PREFIXES = [
        'text/',
        'application/vnd.openxmlformats-officedocument.',
        'application/vnd.ms-',
    ];

    /**
     * MIME types/substrings that map to the 'archive' media type tag.
     */
    private const ARCHIVE_SUBSTRINGS = [
        'zip',
        'tar',
        'rar',
        '7z',
        'gzip',
        'bzip',
        'compress',
        'x-xz',
    ];

    /**
     * MIME types that map to the 'ebook' media type tag.
     */
    private const EBOOK_TYPES = [
        'application/epub+zip',
        'application/x-mobipocket-ebook',
        'application/vnd.amazon.ebook',
    ];

    /**
     * Image MIME types that support dimension detection via getimagesize().
     */
    private const STANDARD_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'image/bmp',
        'image/tiff',
    ];

    /**
     * HEIC/HEIF MIME types that need exiftool for dimension detection.
     */
    private const HEIC_TYPES = ['image/heic', 'image/heif'];

    /**
     * HEIC extensions for fallback detection.
     */
    private const HEIC_EXTENSIONS = ['heic', 'heif'];

    /**
     * DNG MIME types that need exiftool for dimension detection.
     */
    private const DNG_TYPES = ['image/x-adobe-dng', 'image/dng'];

    /**
     * DNG extensions for fallback detection.
     */
    private const DNG_EXTENSIONS = ['dng'];

    /**
     * Get all auto-tags for a file based on its metadata.
     *
     * @return string[] Array of tag name strings
     */
    public function getAutoTags(string $filePath, string $mimeType, string $originalName): array
    {
        $tags = [];

        $mediaType = $this->getMediaTypeTag($mimeType);
        if ($mediaType) {
            $tags[] = $mediaType;
        }

        $orientation = $this->getOrientationTag($filePath, $mimeType, $originalName);
        if ($orientation) {
            $tags[] = $orientation;
        }

        $extension = $this->getExtensionTag($originalName);
        if ($extension) {
            $tags[] = $extension;
        }

        return $tags;
    }

    /**
     * Determine the media type tag from a MIME type.
     */
    public function getMediaTypeTag(string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        // Check ebook types before document (epub is application/*)
        foreach (self::EBOOK_TYPES as $ebookType) {
            if ($mimeType === $ebookType) {
                return 'ebook';
            }
        }

        // Check archive by substring match
        foreach (self::ARCHIVE_SUBSTRINGS as $substring) {
            if (str_contains($mimeType, $substring)) {
                return 'archive';
            }
        }

        // Check exact document types
        if (in_array($mimeType, self::DOCUMENT_TYPES)) {
            return 'document';
        }

        // Check document prefixes
        foreach (self::DOCUMENT_PREFIXES as $prefix) {
            if (str_starts_with($mimeType, $prefix)) {
                return 'document';
            }
        }

        return 'other';
    }

    /**
     * Determine the orientation tag from image dimensions.
     * Returns 'portrait', 'landscape', 'square', or null if not an image / detection fails.
     */
    public function getOrientationTag(string $filePath, string $mimeType, string $originalName): ?string
    {
        $mimeType = strtolower($mimeType);

        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $dimensions = $this->getImageDimensions($filePath, $mimeType, $originalName);
        if (!$dimensions) {
            return null;
        }

        [$width, $height] = $dimensions;

        if ($width === $height) {
            return 'square';
        }

        return $width > $height ? 'landscape' : 'portrait';
    }

    /**
     * Get the file extension tag from the original filename.
     * Returns the lowercase extension or null if none.
     */
    public function getExtensionTag(string $originalName): ?string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return $ext !== '' ? $ext : null;
    }

    /**
     * Get image dimensions [width, height] using the appropriate method for the file type.
     * Returns null if dimensions cannot be determined.
     *
     * @return int[]|null
     */
    private function getImageDimensions(string $filePath, string $mimeType, string $originalName): ?array
    {
        // Standard image types: use getimagesize()
        if (in_array($mimeType, self::STANDARD_IMAGE_TYPES)) {
            return $this->getDimensionsViaGetImageSize($filePath);
        }

        // HEIC/HEIF: use exiftool
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($mimeType, self::HEIC_TYPES) || ($mimeType === 'application/octet-stream' && in_array($ext, self::HEIC_EXTENSIONS))) {
            return $this->getDimensionsViaExiftool($filePath);
        }

        // DNG: use exiftool
        if (in_array($mimeType, self::DNG_TYPES) || ($mimeType === 'application/octet-stream' && in_array($ext, self::DNG_EXTENSIONS))) {
            return $this->getDimensionsViaExiftool($filePath);
        }

        // Fallback: try getimagesize() for any other image/* type
        return $this->getDimensionsViaGetImageSize($filePath);
    }

    /**
     * Get image dimensions using PHP's getimagesize().
     *
     * @return int[]|null
     */
    private function getDimensionsViaGetImageSize(string $filePath): ?array
    {
        try {
            $info = @getimagesize($filePath);
            if ($info && $info[0] > 0 && $info[1] > 0) {
                return [$info[0], $info[1]];
            }
        } catch (\Exception $e) {
            Log::debug('getimagesize failed for auto-tagging', ['path' => $filePath, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Get image dimensions using exiftool (for HEIC, DNG, etc.).
     *
     * @return int[]|null
     */
    private function getDimensionsViaExiftool(string $filePath): ?array
    {
        try {
            $command = sprintf(
                'exiftool -s3 -ImageWidth -ImageHeight %s 2>/dev/null',
                escapeshellarg($filePath)
            );

            $output = [];
            exec($command, $output, $returnCode);

            if ($returnCode === 0 && count($output) >= 2) {
                $width = (int) trim($output[0]);
                $height = (int) trim($output[1]);
                if ($width > 0 && $height > 0) {
                    return [$width, $height];
                }
            }
        } catch (\Exception $e) {
            Log::debug('exiftool dimension detection failed for auto-tagging', ['path' => $filePath, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
