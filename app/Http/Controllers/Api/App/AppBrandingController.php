<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SettingsService;
use App\Utils\FileHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
     * Get the logo as PNG (for iOS compatibility)
     * Logo is already stored as PNG, so this is straightforward
     */
    public function logoPng()
    {
        return $this->logo();
    }

    /**
     * Get the favicon as PNG (for iOS compatibility)
     * Converts SVG to PNG if needed, with caching
     */
    public function faviconPng()
    {
        // Check for custom favicon PNG - serve directly
        if (Storage::disk('public')->exists('favicon.png')) {
            $path = Storage::disk('public')->path('favicon.png');
            return $this->fileResponseWithCaching($path, 'image/png');
        }

        // Check if we have a cached PNG conversion
        $cachedPath = Storage::disk('public')->path('cache/favicon.png');
        if (file_exists($cachedPath)) {
            return $this->fileResponseWithCaching($cachedPath, 'image/png');
        }

        // Determine source SVG
        $svgPath = null;
        if (Storage::disk('public')->exists('favicon.svg')) {
            $svgPath = Storage::disk('public')->path('favicon.svg');
        } else {
            $defaultPath = public_path('icon.svg');
            if (file_exists($defaultPath)) {
                $svgPath = $defaultPath;
            }
        }

        if (!$svgPath) {
            abort(404, 'Favicon not found');
        }

        // Convert SVG to PNG using ImageMagick
        $pngData = $this->convertSvgToPng($svgPath);
        if (!$pngData) {
            abort(500, 'Failed to convert favicon to PNG');
        }

        // Cache the converted PNG
        $cacheDir = Storage::disk('public')->path('cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        file_put_contents($cachedPath, $pngData);

        return response($pngData, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Convert SVG to PNG using ImageMagick
     */
    private function convertSvgToPng(string $svgPath, int $size = 256): ?string
    {
        $tempOutput = sys_get_temp_dir() . '/favicon_' . uniqid() . '.png';

        // Use ImageMagick convert command
        // -background none: transparent background
        // -density: higher density for better quality SVG rendering
        // -resize: output size
        $command = sprintf(
            'convert -background none -density 300 %s -resize %dx%d %s 2>&1',
            escapeshellarg($svgPath),
            $size,
            $size,
            escapeshellarg($tempOutput)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempOutput)) {
            Log::error('SVG to PNG conversion failed', [
                'command' => $command,
                'output' => $output,
                'returnCode' => $returnCode,
            ]);
            return null;
        }

        $pngData = file_get_contents($tempOutput);
        unlink($tempOutput);

        return $pngData;
    }

    /**
     * Clear the cached favicon PNG
     */
    private function clearFaviconPngCache(): void
    {
        $cachedPath = Storage::disk('public')->path('cache/favicon.png');
        if (file_exists($cachedPath)) {
            unlink($cachedPath);
        }
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

    /*
    |--------------------------------------------------------------------------
    | Logo Management
    |--------------------------------------------------------------------------
    */

    /**
     * Upload a new logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:png,svg|max:2048',
        ]);

        if ($validator->fails()) {
            $failedRules = $validator->failed();
            $errorCode = 'validation_failed';
            
            if (isset($failedRules['logo']['Max'])) {
                $errorCode = 'file_too_large';
            } elseif (isset($failedRules['logo']['Mimes'])) {
                $errorCode = 'invalid_file_type';
            } elseif (isset($failedRules['logo']['Image'])) {
                $errorCode = 'invalid_image';
            } elseif (isset($failedRules['logo']['Required'])) {
                $errorCode = 'file_required';
            }

            return response()->json([
                'status' => 'error',
                'error_code' => $errorCode,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $logo = $request->file('logo');
        
        try {
            $stored = Storage::disk('public')->put('images/logo.png', file_get_contents($logo));
            
            if (!$stored) {
                Log::error('Failed to store logo file');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to save logo file',
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logo updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Logo upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset logo to default
     */
    public function deleteLogo(): JsonResponse
    {
        try {
            if (!Storage::disk('public')->exists('images/_default-logo.png')) {
                Log::error('Default logo not found in storage');
                return response()->json([
                    'status' => 'error',
                    'message' => 'Default logo not found',
                ], 404);
            }
            
            $defaultLogo = Storage::disk('public')->get('images/_default-logo.png');
            Storage::disk('public')->put('images/logo.png', $defaultLogo);

            $logoSetting = Setting::where('key', 'logo')->where('group', 'ui.logo')->first();
            if ($logoSetting) {
                $logoSetting->previous_value = $logoSetting->value;
                $logoSetting->value = 'erugo-logo.png';
                $logoSetting->save();
                
                app(SettingsService::class)->clearCache();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Logo reset to default successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Logo reset error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Favicon Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get favicon status
     */
    public function faviconStatus(): JsonResponse
    {
        $hasCustomFavicon = Storage::disk('public')->exists('favicon.png') || 
                           Storage::disk('public')->exists('favicon.svg');
        
        $filename = null;
        if (Storage::disk('public')->exists('favicon.png')) {
            $filename = 'favicon.png';
        } elseif (Storage::disk('public')->exists('favicon.svg')) {
            $filename = 'favicon.svg';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_custom_favicon' => $hasCustomFavicon,
                'filename' => $filename,
            ]
        ]);
    }

    /**
     * Upload a new favicon
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'favicon' => 'required|file|mimes:png,svg|max:1024',
        ]);

        if ($validator->fails()) {
            $failedRules = $validator->failed();
            $errorCode = 'validation_failed';
            
            if (isset($failedRules['favicon']['Max'])) {
                $errorCode = 'file_too_large';
            } elseif (isset($failedRules['favicon']['Mimes'])) {
                $errorCode = 'invalid_file_type';
            } elseif (isset($failedRules['favicon']['Required'])) {
                $errorCode = 'file_required';
            } elseif (isset($failedRules['favicon']['File'])) {
                $errorCode = 'invalid_file';
            }

            return response()->json([
                'status' => 'error',
                'error_code' => $errorCode,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $favicon = $request->file('favicon');
        $extension = FileHelper::sanitizeFileExtension($favicon->getClientOriginalName());
        
        // Ensure extension is one of the allowed types (extra safety after validation)
        if (!in_array($extension, ['png', 'svg'])) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'invalid_file_type',
                'message' => 'Invalid file extension',
            ], 422);
        }
        
        $filename = 'favicon.' . $extension;
        
        // Delete any existing favicon files first
        if (Storage::disk('public')->exists('favicon.png')) {
            Storage::disk('public')->delete('favicon.png');
        }
        if (Storage::disk('public')->exists('favicon.svg')) {
            Storage::disk('public')->delete('favicon.svg');
        }
        
        // Clear the cached PNG conversion
        $this->clearFaviconPngCache();
        
        Storage::disk('public')->put($filename, file_get_contents($favicon));

        return response()->json([
            'status' => 'success',
            'message' => 'Favicon updated successfully',
            'data' => [
                'filename' => $filename,
            ]
        ]);
    }

    /**
     * Delete custom favicon (resets to default)
     */
    public function deleteFavicon(): JsonResponse
    {
        $deleted = false;
        
        if (Storage::disk('public')->exists('favicon.png')) {
            Storage::disk('public')->delete('favicon.png');
            $deleted = true;
        }
        if (Storage::disk('public')->exists('favicon.svg')) {
            Storage::disk('public')->delete('favicon.svg');
            $deleted = true;
        }
        
        // Clear the cached PNG conversion so it regenerates from default
        $this->clearFaviconPngCache();

        return response()->json([
            'status' => 'success',
            'message' => $deleted ? 'Favicon deleted successfully' : 'No custom favicon to delete',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Background Management
    |--------------------------------------------------------------------------
    */

    /**
     * Upload a new background
     */
    public function uploadBackground(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'background' => 'required|file|mimes:jpg,jpeg,png,gif,webp,mp4,webm',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background file upload failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('background');
            $fileName = $file->getClientOriginalName();
            $safeFilename = FileHelper::sanitizeFilename($fileName);
            $file->storeAs('', $safeFilename, 'backgrounds');

            $encodedFilename = rawurlencode($safeFilename);
            $isVideo = $this->isVideo($safeFilename);

            return response()->json([
                'status' => 'success',
                'message' => 'Background uploaded successfully',
                'data' => [
                    'id' => $encodedFilename,
                    'filename' => $safeFilename,
                    'type' => $isVideo ? 'video' : 'image',
                    'url' => url('/api/app/v1/branding/backgrounds/' . $encodedFilename),
                    'thumbnail_url' => url('/api/app/v1/branding/backgrounds/' . $encodedFilename . '/thumb'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Background upload error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Background file upload failed',
            ], 500);
        }
    }

    /**
     * Delete a background
     */
    public function deleteBackground(string $id): JsonResponse
    {
        $file = rawurldecode($id);
        
        if (!FileHelper::validatePathParameter($file)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filename',
            ], 400);
        }
        
        $safeFile = basename($file);
        
        if (!Storage::disk('backgrounds')->exists($safeFile)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background not found',
            ], 404);
        }

        try {
            // Delete the file itself
            Storage::disk('backgrounds')->delete($safeFile);
            // Delete the cached file (for images)
            Storage::disk('backgrounds')->delete('cache/' . $safeFile);
            // Delete the cached thumbs (now always .webp)
            $thumbFilename = pathinfo($safeFile, PATHINFO_FILENAME) . '.webp';
            Storage::disk('backgrounds')->delete('cache/thumbs/' . $thumbFilename);

            // Check if there are any remaining background files
            $remainingFiles = array_filter(
                Storage::disk('backgrounds')->files(''),
                fn($file) => $this->isValidBackground($file)
            );

            // If no backgrounds remain, automatically disable use_my_backgrounds
            if (empty($remainingFiles)) {
                Setting::where('key', 'use_my_backgrounds')->update(['value' => 'false']);
                app(SettingsService::class)->clearCache();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Background deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Background delete error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Background deletion failed',
            ], 500);
        }
    }
}
