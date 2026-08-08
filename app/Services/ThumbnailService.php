<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class ThumbnailService
{
    public const THUMB_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
    public const THUMB_HEIC_TYPES = ['image/heic', 'image/heif'];
    public const THUMB_HEIC_EXTENSIONS = ['heic', 'heif'];
    public const THUMB_VIDEO_TYPES = ['video/mp4', 'video/webm', 'video/quicktime'];
    public const THUMB_AUDIO_TYPES = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/x-m4b', 'audio/aac', 'audio/flac', 'audio/ogg'];
    // Audio extensions to check when MIME type is generic (application/octet-stream)
    public const THUMB_AUDIO_EXTENSIONS = ['mp3', 'm4a', 'm4b', 'aac', 'flac', 'ogg'];
    public const THUMB_EPUB_TYPES = ['application/epub+zip'];
    public const THUMB_EPUB_EXTENSIONS = ['epub'];
    public const THUMB_PDF_TYPES = ['application/pdf'];
    public const THUMB_PDF_EXTENSIONS = ['pdf'];
    public const THUMB_DNG_TYPES = ['image/x-adobe-dng', 'image/dng'];
    public const THUMB_DNG_EXTENSIONS = ['dng'];
    public const THUMB_PSD_TYPES = ['image/vnd.adobe.photoshop', 'image/x-photoshop', 'image/psd'];
    public const THUMB_PSD_EXTENSIONS = ['psd', 'psb'];

    // Thumbnail size presets (width in pixels)
    public const THUMB_SIZES = [
        'small' => 200,
        'medium' => 600,
        'large' => 1200,
    ];

    /**
     * Resolve a size name to a pixel width.
     * Returns the default (small) width for unrecognised values.
     */
    public function resolveWidth(string $size = 'small'): int
    {
        return self::THUMB_SIZES[$size] ?? self::THUMB_SIZES['small'];
    }

    /**
     * Build the cache filename for a given file ID and size.
     * "small" maps to "{id}.webp" for backward compatibility with existing caches.
     */
    public function thumbCacheFilename(int $fileId, string $size = 'small'): string
    {
        if ($size === 'small' || !isset(self::THUMB_SIZES[$size])) {
            return $fileId . '.webp';
        }

        return $fileId . '_' . $size . '.webp';
    }

    /**
     * Check if a file type supports thumbnail generation.
     */
    public function canGenerateThumbnail(?string $mimeType, ?string $filename = null): bool
    {
        if (!$mimeType) {
            return false;
        }

        if (in_array($mimeType, self::THUMB_IMAGE_TYPES) || in_array($mimeType, self::THUMB_VIDEO_TYPES) || in_array($mimeType, self::THUMB_HEIC_TYPES)) {
            return true;
        }

        if (in_array($mimeType, self::THUMB_AUDIO_TYPES)) {
            return true;
        }

        if (in_array($mimeType, self::THUMB_EPUB_TYPES)) {
            return true;
        }

        if (in_array($mimeType, self::THUMB_PDF_TYPES)) {
            return true;
        }

        if (in_array($mimeType, self::THUMB_DNG_TYPES)) {
            return true;
        }

        if (in_array($mimeType, self::THUMB_PSD_TYPES)) {
            return true;
        }

        // Check extension for files with generic MIME type
        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, self::THUMB_HEIC_EXTENSIONS) || in_array($ext, self::THUMB_AUDIO_EXTENSIONS) || in_array($ext, self::THUMB_EPUB_EXTENSIONS) || in_array($ext, self::THUMB_PDF_EXTENSIONS) || in_array($ext, self::THUMB_DNG_EXTENSIONS) || in_array($ext, self::THUMB_PSD_EXTENSIONS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a file is a HEIC/HEIF file (by MIME type or extension).
     */
    public function isHeicFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_HEIC_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_HEIC_EXTENSIONS);
        }

        return false;
    }

    /**
     * Check if a file is an audio file (by MIME type or extension).
     */
    public function isAudioFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_AUDIO_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_AUDIO_EXTENSIONS);
        }

        return false;
    }

    /**
     * Check if a file is an EPUB file (by MIME type or extension).
     */
    public function isEpubFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_EPUB_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_EPUB_EXTENSIONS);
        }

        return false;
    }

    /**
     * Check if a file is a PDF file (by MIME type or extension).
     */
    public function isPdfFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_PDF_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_PDF_EXTENSIONS);
        }

        return false;
    }

    /**
     * Check if a file is a DNG file (by MIME type or extension).
     */
    public function isDngFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_DNG_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_DNG_EXTENSIONS);
        }

        return false;
    }

    /**
     * Check if a file is a PSD file (by MIME type or extension).
     */
    public function isPsdFile(?string $mimeType, ?string $filename = null): bool
    {
        if (in_array($mimeType, self::THUMB_PSD_TYPES)) {
            return true;
        }

        if ($mimeType === 'application/octet-stream' && $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            return in_array($ext, self::THUMB_PSD_EXTENSIONS);
        }

        return false;
    }

    /**
     * Generate a thumbnail from a source file path.
     * Returns true if the thumbnail was successfully generated.
     *
     * @param int $width Target width in pixels (default 200 for backward compatibility)
     */
    public function generateThumbnailFromPath(string $sourcePath, string $thumbPath, string $mimeType, string $filename, int $width = 200): bool
    {
        if (!$this->canGenerateThumbnail($mimeType, $filename)) {
            return false;
        }

        $isHeic = $this->isHeicFile($mimeType, $filename);
        $isVideo = in_array($mimeType, self::THUMB_VIDEO_TYPES);
        $isAudio = $this->isAudioFile($mimeType, $filename);
        $isEpub = $this->isEpubFile($mimeType, $filename);
        $isPdf = $this->isPdfFile($mimeType, $filename);
        $isDng = $this->isDngFile($mimeType, $filename);
        $isPsd = $this->isPsdFile($mimeType, $filename);

        if ($isHeic) {
            $this->generateHeicThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isVideo) {
            $this->generateVideoThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isAudio) {
            $this->generateAudioThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isEpub) {
            $this->generateEpubThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isPdf) {
            $this->generatePdfThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isDng) {
            $this->generateDngThumbnail($sourcePath, $thumbPath, $width);
        } elseif ($isPsd) {
            $this->generatePsdThumbnail($sourcePath, $thumbPath, $width);
        } else {
            $this->generateImageThumbnail($sourcePath, $thumbPath, $width);
        }

        return file_exists($thumbPath);
    }

    /**
     * Return a file response with caching headers.
     */
    public function fileResponseWithCaching(string $path, string $contentType)
    {
        $lastModified = filemtime($path);
        $etag = md5_file($path);

        return response()->file($path, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"' . $etag . '"',
            'Last-Modified' => gmdate('D, d M Y H:i:s', $lastModified) . ' GMT',
        ]);
    }

    /**
     * Generate an image thumbnail.
     */
    public function generateImageThumbnail(string $sourcePath, string $outputPath, int $width = 200): void
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($sourcePath);
        $image->scale(width: $width);
        $encoded = $image->toWebp(80);
        file_put_contents($outputPath, $encoded);
    }

    /**
     * Generate a thumbnail from a HEIC/HEIF file using heif-convert.
     */
    public function generateHeicThumbnail(string $heicPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Try ffmpeg first
        $command = sprintf(
            'ffmpeg -i %s -vframes 1 -y %s 2>&1',
            escapeshellarg($heicPath),
            escapeshellarg($tempPath)
        );

        exec($command, $output, $returnCode);

        // If ffmpeg fails, try fallback methods
        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $tempPath = $outputPath . '.tmp.jpg';
            $extracted = false;

            // Try exiftool embedded preview tags
            foreach (['PreviewImage', 'JpgFromRaw', 'ThumbnailImage'] as $tag) {
                $command = sprintf(
                    'exiftool -b -%s %s 2>/dev/null',
                    $tag,
                    escapeshellarg($heicPath)
                );
                $previewData = shell_exec($command);

                if ($previewData && strlen($previewData) > 100) {
                    file_put_contents($tempPath, $previewData);
                    $extracted = true;
                    break;
                }
            }

            // Try ImageMagick as last resort
            if (!$extracted) {
                $tempPath = $outputPath . '.tmp.png';
                $command = sprintf(
                    'convert %s[0] -thumbnail %dx%d %s 2>&1',
                    escapeshellarg($heicPath),
                    $width,
                    $width,
                    escapeshellarg($tempPath)
                );
                exec($command, $convertOutput, $convertReturnCode);

                if ($convertReturnCode !== 0 || !file_exists($tempPath)) {
                    Log::warning('HEIC thumbnail generation failed (all methods)', [
                        'source' => $heicPath,
                        'convert_output' => implode("\n", $convertOutput ?? []),
                    ]);
                    return;
                }
            }
        }

        // Convert to webp and resize
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $image->scale(width: $width);
            $encoded = $image->toWebp(80);
            file_put_contents($outputPath, $encoded);
        } catch (\Exception $e) {
            Log::warning('HEIC thumbnail conversion to webp failed', [
                'source' => $heicPath,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Generate a video thumbnail using ffmpeg.
     */
    public function generateVideoThumbnail(string $videoPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Try to extract a frame at 1 second
        $command = sprintf(
            'ffmpeg -ss 00:00:01 -i %s -vframes 1 -vf "scale=%d:-1" -y %s 2>/dev/null',
            escapeshellarg($videoPath),
            $width,
            escapeshellarg($tempPath)
        );

        exec($command, $output, $returnCode);

        // If that fails, try first frame
        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $command = sprintf(
                'ffmpeg -i %s -vframes 1 -vf "scale=%d:-1" -y %s 2>/dev/null',
                escapeshellarg($videoPath),
                $width,
                escapeshellarg($tempPath)
            );
            exec($command, $output, $returnCode);
        }

        // Convert to webp
        if (file_exists($tempPath)) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $encoded = $image->toWebp(80);
            file_put_contents($outputPath, $encoded);
            unlink($tempPath);
        }
    }

    /**
     * Generate a thumbnail from audio file's embedded cover art using ffmpeg.
     */
    public function generateAudioThumbnail(string $audioPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Extract embedded cover art from audio file
        $command = sprintf(
            'ffmpeg -i %s -an -vcodec copy -y %s 2>/dev/null',
            escapeshellarg($audioPath),
            escapeshellarg($tempPath)
        );

        exec($command, $output, $returnCode);

        // If direct copy fails, try extracting as image
        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $command = sprintf(
                'ffmpeg -i %s -an -vf "scale=%d:-1" -y %s 2>/dev/null',
                escapeshellarg($audioPath),
                $width,
                escapeshellarg($tempPath)
            );
            exec($command, $output, $returnCode);
        }

        // Convert to webp and resize
        if (file_exists($tempPath)) {
            try {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($tempPath);
                $image->scale(width: $width);
                $encoded = $image->toWebp(80);
                file_put_contents($outputPath, $encoded);
            } catch (\Exception $e) {
                // Cover art extraction failed
            } finally {
                unlink($tempPath);
            }
        }
    }

    /**
     * Generate a thumbnail from EPUB cover image.
     */
    public function generateEpubThumbnail(string $epubPath, string $outputPath, int $width = 200): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($epubPath) !== true) {
            return;
        }

        $coverImage = null;

        // Common cover image locations/patterns in EPUBs
        $coverPatterns = [
            'cover.jpg', 'cover.jpeg', 'cover.png', 'cover.gif',
            'Cover.jpg', 'Cover.jpeg', 'Cover.png', 'Cover.gif',
            'OEBPS/cover.jpg', 'OEBPS/cover.jpeg', 'OEBPS/cover.png',
            'OEBPS/images/cover.jpg', 'OEBPS/images/cover.jpeg', 'OEBPS/images/cover.png',
            'OEBPS/Images/cover.jpg', 'OEBPS/Images/cover.jpeg', 'OEBPS/Images/cover.png',
            'images/cover.jpg', 'images/cover.jpeg', 'images/cover.png',
            'Images/cover.jpg', 'Images/cover.jpeg', 'Images/cover.png',
        ];

        // Try common cover locations first
        foreach ($coverPatterns as $pattern) {
            if ($zip->locateName($pattern) !== false) {
                $coverImage = $pattern;
                break;
            }
        }

        // If not found, search for any file with 'cover' in the name
        if (!$coverImage) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    if (stripos(basename($filename), 'cover') !== false) {
                        $coverImage = $filename;
                        break;
                    }
                }
            }
        }

        // If still not found, try parsing the OPF file to find the cover
        if (!$coverImage) {
            $coverImage = $this->findEpubCoverFromOpf($zip);
        }

        if (!$coverImage) {
            $zip->close();
            return;
        }

        // Extract cover to temp file
        $tempPath = $outputPath . '.tmp.' . pathinfo($coverImage, PATHINFO_EXTENSION);
        $coverData = $zip->getFromName($coverImage);
        $zip->close();

        if (!$coverData) {
            return;
        }

        file_put_contents($tempPath, $coverData);

        // Convert to webp thumbnail
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $image->scale(width: $width);
            $encoded = $image->toWebp(80);
            file_put_contents($outputPath, $encoded);
        } catch (\Exception $e) {
            // Cover extraction failed
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Find cover image path from EPUB's OPF metadata file.
     */
    public function findEpubCoverFromOpf(\ZipArchive $zip): ?string
    {
        // Find the OPF file (usually content.opf or package.opf)
        $opfPath = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (preg_match('/\.opf$/i', $filename)) {
                $opfPath = $filename;
                break;
            }
        }

        if (!$opfPath) {
            return null;
        }

        $opfContent = $zip->getFromName($opfPath);
        if (!$opfContent) {
            return null;
        }

        $opfDir = dirname($opfPath);
        if ($opfDir === '.') {
            $opfDir = '';
        } else {
            $opfDir .= '/';
        }

        // Try to find cover meta element: <meta name="cover" content="cover-image-id"/>
        if (preg_match('/<meta[^>]*name=["\']cover["\'][^>]*content=["\']([^"\']+)["\']/', $opfContent, $matches) ||
            preg_match('/<meta[^>]*content=["\']([^"\']+)["\'][^>]*name=["\']cover["\']/', $opfContent, $matches)) {
            $coverId = $matches[1];

            // Find the item with this id
            if (preg_match('/<item[^>]*id=["\']' . preg_quote($coverId, '/') . '["\'][^>]*href=["\']([^"\']+)["\']/', $opfContent, $hrefMatches) ||
                preg_match('/<item[^>]*href=["\']([^"\']+)["\'][^>]*id=["\']' . preg_quote($coverId, '/') . '["\']/', $opfContent, $hrefMatches)) {
                return $opfDir . $hrefMatches[1];
            }
        }

        // Try to find item with properties="cover-image"
        if (preg_match('/<item[^>]*properties=["\'][^"\']*cover-image[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/', $opfContent, $matches) ||
            preg_match('/<item[^>]*href=["\']([^"\']+)["\'][^>]*properties=["\'][^"\']*cover-image[^"\']*["\']/', $opfContent, $matches)) {
            return $opfDir . $matches[1];
        }

        return null;
    }

    /**
     * Generate a thumbnail from PDF first page.
     */
    public function generatePdfThumbnail(string $pdfPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Scale DPI proportionally so the rasterised page has enough pixels
        // for the requested width (72 DPI is the baseline for 200px thumbnails).
        $dpi = (int) max(72, round($width / 200 * 72));

        // Try pdftoppm first (from poppler-utils)
        // Use -singlefile to avoid -1 suffix, -r for resolution (lower = smaller/faster)
        $command = sprintf(
            'pdftoppm -png -singlefile -f 1 -l 1 -r %d %s %s 2>&1',
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($outputPath . '.tmp')
        );
        exec($command, $output, $returnCode);

        // -singlefile outputs without page number suffix
        $pdftoppmOutput = $outputPath . '.tmp.png';
        if ($returnCode === 0 && file_exists($pdftoppmOutput)) {
            $tempPath = $pdftoppmOutput;
        } else {
            // Try without -singlefile (older versions)
            $command = sprintf(
                'pdftoppm -png -f 1 -l 1 -r %d %s %s 2>&1',
                $dpi,
                escapeshellarg($pdfPath),
                escapeshellarg($outputPath . '.tmp')
            );
            exec($command, $output, $returnCode);

            // This version adds -1 suffix
            $pdftoppmOutput = $outputPath . '.tmp-1.png';
            if ($returnCode === 0 && file_exists($pdftoppmOutput)) {
                $tempPath = $pdftoppmOutput;
            }
        }

        // Convert to webp and resize
        if (file_exists($tempPath)) {
            try {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($tempPath);
                $image->scale(width: $width);
                $encoded = $image->toWebp(80);
                file_put_contents($outputPath, $encoded);
            } catch (\Exception $e) {
                // PDF thumbnail generation failed
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }
    }

    /**
     * Generate a thumbnail from PSD file using ImageMagick.
     */
    public function generatePsdThumbnail(string $psdPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Use ImageMagick to convert PSD to temporary PNG
        // [0] selects the composite/flattened image layer
        $convertCommand = sprintf(
            'convert %s[0] -thumbnail %dx%d %s 2>/dev/null',
            escapeshellarg($psdPath),
            $width,
            $width,
            escapeshellarg($tempPath)
        );
        shell_exec($convertCommand);

        // Convert to webp
        if (file_exists($tempPath) && filesize($tempPath) > 0) {
            try {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($tempPath);
                $encoded = $image->toWebp(80);
                file_put_contents($outputPath, $encoded);
            } catch (\Exception $e) {
                // PSD thumbnail conversion failed
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }
    }

    /**
     * Generate a thumbnail from DNG file using ImageMagick + exiftool for orientation.
     */
    public function generateDngThumbnail(string $dngPath, string $outputPath, int $width = 200): void
    {
        $tempPath = $outputPath . '.tmp.png';

        // Get orientation from exiftool (returns numeric value 1-8)
        $orientCommand = sprintf('exiftool -n -Orientation -s3 %s 2>/dev/null', escapeshellarg($dngPath));
        $orientation = trim(shell_exec($orientCommand) ?? '1');

        // Use ImageMagick to convert DNG to temporary PNG (no rotation applied)
        $convertCommand = sprintf(
            'convert %s[0] -thumbnail %dx%d %s 2>/dev/null',
            escapeshellarg($dngPath),
            $width,
            $width,
            escapeshellarg($tempPath)
        );
        shell_exec($convertCommand);

        // Convert to webp with correct orientation
        if (file_exists($tempPath) && filesize($tempPath) > 0) {
            try {
                $manager = new ImageManager(new Driver());
                $image = $manager->read($tempPath);

                // Apply rotation based on EXIF orientation value
                // 1 = normal, 2 = flip H, 3 = 180, 4 = flip V
                // 5 = flip H + 270, 6 = 90 CW, 7 = flip H + 90, 8 = 270 CW
                switch ($orientation) {
                    case '2':
                        $image->flip();
                        break;
                    case '3':
                        $image->rotate(180);
                        break;
                    case '4':
                        $image->flop();
                        break;
                    case '5':
                        $image->flip()->rotate(270);
                        break;
                    case '6':
                        $image->rotate(90);
                        break;
                    case '7':
                        $image->flip()->rotate(90);
                        break;
                    case '8':
                        $image->rotate(270);
                        break;
                    // case '1' or default: no rotation needed
                }

                $encoded = $image->toWebp(80);
                file_put_contents($outputPath, $encoded);
            } catch (\Exception $e) {
                // DNG thumbnail conversion failed
            } finally {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }
    }
}
