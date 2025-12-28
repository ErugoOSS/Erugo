<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Validator;
use App\Utils\FileHelper;
use App\Models\Setting;

class BackgroundsController extends Controller
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm'];

    private function isVideo(string $file): bool
    {
        return in_array(
            strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            self::VIDEO_EXTENSIONS
        );
    }

    private function isImage(string $file): bool
    {
        return in_array(
            strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            self::IMAGE_EXTENSIONS
        );
    }

    private function isValidBackground(string $file): bool
    {
        return $this->isImage($file) || $this->isVideo($file);
    }

    public function list()
    {
        //find all the files in the public/backgrounds folder
        $files = Storage::disk('backgrounds')->files('');

        //keep only the files that are images or videos
        $files = array_filter($files, function ($file) {
            return $this->isValidBackground($file);
        });

        $files = array_map(function ($file) {
            return rawurlencode(basename($file));
        }, $files);

        $files = array_values($files);

        return response()->json([
            'status' => 'success',
            'message' => 'Background files listed successfully',
            'data' => [
                'files' => $files,
            ]
        ]);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'background_image' => 'required|file|mimes:jpg,jpeg,png,gif,webp,mp4,webm',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background file upload failed',
                'data' => [
                    'errors' => $validator->errors(),
                ]
            ], 422);
        }

        try {
            $file = $request->file('background_image');
            $fileName = $file->getClientOriginalName();
            $safeFilename = FileHelper::sanitizeFilename($fileName);
            $file->storeAs('', $safeFilename, 'backgrounds');

            return response()->json([
                'status' => 'success',
                'message' => 'Background file uploaded successfully',
                'data' => [
                    'file' => $safeFilename,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background file upload failed',
            ], 500);
        }
    }

    public function delete($file)
    {
        // Validate path parameter to prevent path traversal attacks
        if (!FileHelper::validatePathParameter($file)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid filename',
            ], 400);
        }
        
        // Use basename as additional protection
        $safeFile = basename($file);
        
        //check if the file exists
        if (!Storage::disk('backgrounds')->exists($safeFile)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image not found',
            ], 404);
        }
        //delete the file
        try {
            //delete the file itself
            Storage::disk('backgrounds')->delete($safeFile);
            //delete the cached file (for images)
            Storage::disk('backgrounds')->delete('cache/' . $safeFile);
            //delete the cached thumbs (now always .webp)
            $thumbFilename = pathinfo($safeFile, PATHINFO_FILENAME) . '.webp';
            Storage::disk('backgrounds')->delete('cache/thumbs/' . $thumbFilename);

            // Check if there are any remaining background files
            $remainingFiles = array_filter(
                Storage::disk('backgrounds')->files(''),
                fn($file) => $this->isValidBackground($file)
            );

            // If no backgrounds remain, automatically disable use_my_backgrounds
            if (empty($remainingFiles)) {
                Setting::where('key', 'use_my_backgrounds')->update(['value' => false]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Background file deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Background image deletion failed',
            ], 500);
        }
    }

    public function use($file)
    {
        // Validate path parameter to prevent path traversal attacks
        if (!FileHelper::validatePathParameter($file)) {
            abort(400, 'Invalid filename');
        }
        
        // Use basename as additional protection
        $safeFile = basename($file);

        $fullPath = Storage::disk('backgrounds')->path($safeFile);
        //check the file exists
        if (!file_exists($fullPath)) {
            abort(404);
        }

        // For videos, use streamDownload with range support for efficient delivery
        if ($this->isVideo($safeFile)) {
            return $this->streamVideo($fullPath, $safeFile);
        }

        // For images, use caching and scaling
        $cachedPath = Storage::disk('backgrounds')->path('cache/' . $safeFile);
        if (file_exists($cachedPath)) {
            return response()->file($cachedPath);
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);

        $image->scale(width: 2000);
        $encoded = $image->toJpeg(95);

        //save the encoded image to the public/backgrounds/cache folder
        Storage::disk('backgrounds')->put('cache/' . $safeFile, $encoded);

        return response($encoded)->header('Content-Type', 'image/webp');
    }

    public function useThumb($file)
    {
        // Validate path parameter to prevent path traversal attacks
        if (!FileHelper::validatePathParameter($file)) {
            abort(400, 'Invalid filename');
        }
        
        // Use basename as additional protection
        $safeFile = basename($file);

        // For thumbnails, we always use .webp extension for the cached file
        $thumbFilename = pathinfo($safeFile, PATHINFO_FILENAME) . '.webp';
        $cachedPath = Storage::disk('backgrounds')->path('cache/thumbs/' . $thumbFilename);
        
        // Check if we have a cached version
        if (file_exists($cachedPath)) {
            return response()->file($cachedPath, ['Content-Type' => 'image/webp']);
        }

        $fullPath = Storage::disk('backgrounds')->path($safeFile);
        if (!file_exists($fullPath)) {
            abort(404);
        }

        // For videos, extract first frame using ffmpeg
        if ($this->isVideo($safeFile)) {
            $this->generateVideoThumbnail($fullPath, $cachedPath);
            if (file_exists($cachedPath)) {
                return response()->file($cachedPath, ['Content-Type' => 'image/webp']);
            }
            abort(500, 'Failed to generate video thumbnail');
        }

        // For images, use Intervention Image
        $manager = new ImageManager(new Driver());
        $image = $manager->read($fullPath);
        $image->scale(width: 100);
        $encoded = $image->toWebp(80);

        //save the encoded image to the public/backgrounds/cache folder
        Storage::disk('backgrounds')->put('cache/thumbs/' . $thumbFilename, $encoded);

        return response($encoded)->header('Content-Type', 'image/webp');
    }

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

        // Check for range request
        $range = request()->header('Range');
        
        if ($range) {
            // Parse the range header
            preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
            $start = intval($matches[1]);
            $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
            
            // Ensure valid range
            if ($start > $end || $start >= $fileSize) {
                return response('', 416)->header('Content-Range', "bytes */$fileSize");
            }
            
            $length = $end - $start + 1;
            
            $headers['Content-Range'] = "bytes $start-$end/$fileSize";
            $headers['Content-Length'] = $length;
            
            // Use larger buffer (64KB) for better throughput through tunnels/proxies
            $headers['X-Accel-Buffering'] = 'no';
            
            return response()->stream(function () use ($fullPath, $start, $length) {
                $stream = fopen($fullPath, 'rb');
                fseek($stream, $start);
                $remaining = $length;
                $bufferSize = 65536; // 64KB buffer for better throughput
                
                while ($remaining > 0 && !feof($stream)) {
                    $readSize = min($bufferSize, $remaining);
                    echo fread($stream, $readSize);
                    $remaining -= $readSize;
                    flush();
                }
                
                fclose($stream);
            }, 206, $headers);
        }
        
        // No range request - serve entire file
        $headers['Content-Length'] = $fileSize;
        $headers['X-Accel-Buffering'] = 'no'; // Disable proxy buffering for streaming
        
        return response()->stream(function () use ($fullPath) {
            $stream = fopen($fullPath, 'rb');
            while (!feof($stream)) {
                echo fread($stream, 65536); // 64KB buffer for better throughput
                flush();
            }
            fclose($stream);
        }, 200, $headers);
    }

    private function generateVideoThumbnail(string $videoPath, string $outputPath): void
    {
        // Ensure the cache directory exists
        $cacheDir = dirname($outputPath);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        // Use ffmpeg to extract first frame and create thumbnail
        // -ss 00:00:01 = seek to 1 second (to avoid black frames)
        // -vframes 1 = extract only 1 frame
        // -vf scale=100:-1 = scale to 100px width, maintain aspect ratio
        $tempPath = $outputPath . '.tmp.png';
        
        $command = sprintf(
            'ffmpeg -ss 00:00:01 -i %s -vframes 1 -vf "scale=100:-1" -y %s 2>/dev/null',
            escapeshellarg($videoPath),
            escapeshellarg($tempPath)
        );
        
        exec($command, $output, $returnCode);

        // If seeking to 1 second failed (video too short), try from start
        if ($returnCode !== 0 || !file_exists($tempPath)) {
            $command = sprintf(
                'ffmpeg -i %s -vframes 1 -vf "scale=100:-1" -y %s 2>/dev/null',
                escapeshellarg($videoPath),
                escapeshellarg($tempPath)
            );
            exec($command, $output, $returnCode);
        }

        // Convert to webp using Intervention Image
        if (file_exists($tempPath)) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($tempPath);
            $encoded = $image->toWebp(80);
            file_put_contents($outputPath, $encoded);
            unlink($tempPath);
        }
    }
}
