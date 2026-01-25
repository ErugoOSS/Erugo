<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use App\Utils\FileHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class AppBrandingController extends Controller
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    /**
     * Get the logo image with proper cache headers
     */
    public function logo()
    {
        // Check for custom logo in storage
        if (Storage::disk('public')->exists('images/logo.png')) {
            $path = Storage::disk('public')->path('images/logo.png');
            return $this->fileResponseWithCaching($path, 'image/png');
        }

        // Fall back to default logo
        $defaultPath = Storage::disk('public')->path('images/_default-logo.png');
        if (file_exists($defaultPath)) {
            return $this->fileResponseWithCaching($defaultPath, 'image/png');
        }

        abort(404, 'Logo not found');
    }

    /**
     * Get the favicon with proper cache headers
     */
    public function favicon()
    {
        // Check for custom favicon (PNG first, then SVG)
        if (Storage::disk('public')->exists('favicon.png')) {
            $path = Storage::disk('public')->path('favicon.png');
            return $this->fileResponseWithCaching($path, 'image/png');
        }
        
        if (Storage::disk('public')->exists('favicon.svg')) {
            $path = Storage::disk('public')->path('favicon.svg');
            return $this->fileResponseWithCaching($path, 'image/svg+xml');
        }
        
        // Fall back to default icon.svg
        $defaultPath = public_path('icon.svg');
        if (file_exists($defaultPath)) {
            return $this->fileResponseWithCaching($defaultPath, 'image/svg+xml');
        }
        
        abort(404, 'Favicon not found');
    }

    /**
     * List all available backgrounds with URLs
     */
    public function backgrounds(): JsonResponse
    {
        $settings = app(SettingsService::class);
        $files = Storage::disk('backgrounds')->files('');

        // Filter valid backgrounds (images and videos)
        $files = array_filter($files, function ($file) {
            return $this->isValidBackground($file);
        });

        $backgrounds = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $encodedFilename = rawurlencode($filename);
            $isVideo = $this->isVideo($filename);
            
            $backgrounds[] = [
                'id' => $encodedFilename,
                'filename' => $filename,
                'type' => $isVideo ? 'video' : 'image',
                'url' => url('/api/app/v1/branding/backgrounds/' . $encodedFilename),
                'thumbnail_url' => url('/api/app/v1/branding/backgrounds/' . $encodedFilename . '/thumb'),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'backgrounds' => array_values($backgrounds),
                'slideshow_speed' => (int) ($settings->get('background_slideshow_speed') ?? 180),
                'use_custom_backgrounds' => (bool) $settings->get('use_my_backgrounds'),
            ]
        ]);
    }

    /**
     * Get a specific background file
     */
    public function background(string $id)
    {
        $file = rawurldecode($id);
        
        // Validate path parameter to prevent path traversal attacks
        if (!FileHelper::validatePathParameter($file)) {
            abort(400, 'Invalid filename');
        }
        
        $safeFile = basename($file);
        $fullPath = Storage::disk('backgrounds')->path($safeFile);
        
        if (!file_exists($fullPath)) {
            abort(404, 'Background not found');
        }

        // For videos, use streaming with range support
        if ($this->isVideo($safeFile)) {
            return $this->streamVideo($fullPath, $safeFile);
        }

        // For images, use caching and scaling
        $cachedPath = Storage::disk('backgrounds')->path('cache/' . $safeFile);
        if (file_exists($cachedPath)) {
            return $this->fileResponseWithCaching($cachedPath, $this->getMimeType($safeFile));
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);
        $image->scale(width: 2000);
        $encoded = $image->toJpeg(95);

        Storage::disk('backgrounds')->put('cache/' . $safeFile, $encoded);

        return response($encoded, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Get a thumbnail for a specific background
     */
    public function backgroundThumb(string $id)
    {
        $file = rawurldecode($id);
        
        // Validate path parameter
        if (!FileHelper::validatePathParameter($file)) {
            abort(400, 'Invalid filename');
        }
        
        $safeFile = basename($file);
        
        // Thumbnails are always .webp
        $thumbFilename = pathinfo($safeFile, PATHINFO_FILENAME) . '.webp';
        $cachedPath = Storage::disk('backgrounds')->path('cache/thumbs/' . $thumbFilename);
        
        if (file_exists($cachedPath)) {
            return $this->fileResponseWithCaching($cachedPath, 'image/webp');
        }

        $fullPath = Storage::disk('backgrounds')->path($safeFile);
        if (!file_exists($fullPath)) {
            abort(404, 'Background not found');
        }

        // For videos, generate thumbnail from first frame
        if ($this->isVideo($safeFile)) {
            $this->generateVideoThumbnail($fullPath, $cachedPath);
            if (file_exists($cachedPath)) {
                return $this->fileResponseWithCaching($cachedPath, 'image/webp');
            }
            abort(500, 'Failed to generate video thumbnail');
        }

        // For images, scale down
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);
        $image->scale(width: 100);
        $encoded = $image->toWebp(80);

        Storage::disk('backgrounds')->put('cache/thumbs/' . $thumbFilename, $encoded);

        return response($encoded, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Return a file response with caching headers
     */
    private function fileResponseWithCaching(string $path, string $contentType)
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
     * Stream video with range support
     */
    private function streamVideo(string $fullPath, string $filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeType = $extension === 'webm' ? 'video/webm' : 'video/mp4';
        $fileSize = filesize($fullPath);
        
        $headers = [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ];

        $range = request()->header('Range');
        
        if ($range) {
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
            $start = intval($matches[1]);
            $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
            
            if ($start > $end || $start >= $fileSize) {
                return response('', 416)->header('Content-Range', "bytes */$fileSize");
            }
            
            $length = $end - $start + 1;
            
            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            $headers['Content-Length'] = $length;
            $headers['X-Accel-Buffering'] = 'no';
            
            return response()->stream(function () use ($fullPath, $start, $length) {
                $stream = fopen($fullPath, 'rb');
                fseek($stream, $start);
                $remaining = $length;
                $bufferSize = 65536;
                
                while ($remaining > 0 && !feof($stream)) {
                    $readSize = min($bufferSize, $remaining);
                    echo fread($stream, $readSize);
                    $remaining -= $readSize;
                    flush();
                }
                
                fclose($stream);
            }, 206, $headers);
        }
        
        $headers['Content-Length'] = $fileSize;
        $headers['X-Accel-Buffering'] = 'no';
        
        return response()->stream(function () use ($fullPath) {
            $stream = fopen($fullPath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 65536);
                flush();
            }
            fclose($stream);
        }, 200, $headers);
    }

    /**
     * Generate a thumbnail from video using ffmpeg
     */
    private function generateVideoThumbnail(string $videoPath, string $outputPath): void
    {
        $cacheDir = dirname($outputPath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $tempPath = $outputPath . '.tmp.png';
        
        $command = sprintf(
            'ffmpeg -ss 00:00:01 -i %s -vframes 1 -vf "scale=100:-1" -y %s 2>/dev/null',
            escapeshellarg($videoPath),
            escapeshellarg($tempPath)
        );
        
        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $command = sprintf(
                'ffmpeg -i %s -vframes 1 -vf "scale=100:-1" -y %s 2>/dev/null',
                escapeshellarg($videoPath),
                escapeshellarg($tempPath)
            );
            exec($command, $output, $returnCode);
        }

        if (file_exists($tempPath)) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $encoded = $image->toWebp(80);
            file_put_contents($outputPath, $encoded);
            unlink($tempPath);
        }
    }

    /**
     * Check if file is a video
     */
    private function isVideo(string $file): bool
    {
        return in_array(
            strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            self::VIDEO_EXTENSIONS
        );
    }

    /**
     * Check if file is an image
     */
    private function isImage(string $file): bool
    {
        return in_array(
            strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            self::IMAGE_EXTENSIONS
        );
    }

    /**
     * Check if file is a valid background (image or video)
     */
    private function isValidBackground(string $file): bool
    {
        return $this->isImage($file) || $this->isVideo($file);
    }

    /**
     * Get MIME type for a file
     */
    private function getMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            default => 'application/octet-stream',
        };
    }
}
