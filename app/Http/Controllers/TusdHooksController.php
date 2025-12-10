<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\File;
use App\Models\UploadSession;
use App\Utils\FileHelper;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use App\Services\SettingsService;

class TusdHooksController extends Controller
{
    /**
     * Handle incoming tusd hook requests
     * tusd sends different hook types: pre-create, post-create, post-receive, post-finish, post-terminate
     * 
     * Security: This endpoint should only be called by the tusd process.
     * We validate the request comes from localhost or internal Docker network IPs.
     */
    public function handleHook(Request $request)
    {
        // Security: Verify request is from internal network (tusd process)
        $clientIp = $request->ip();
        $allowedNetworks = [
            '172.', // Docker bridge networks
            '10.',  // Private network
            '192.168.', // Private network
            '127.0.0.1', // Localhost IPv4
            '::1', // Localhost IPv6
        ];
        
        $isAllowed = false;
        foreach ($allowedNetworks as $network) {
            if (str_starts_with($clientIp, $network)) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            Log::warning('tusd hook rejected: unauthorized source IP', [
                'ip' => $clientIp
            ]);
            return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        }

        $payload = $request->all();
        // Hook type is in the payload's Type field, not in headers
        $hookName = $payload['Type'] ?? null;

        Log::debug('tusd hook received', [
            'hook' => $hookName
        ]);

        switch ($hookName) {
            case 'pre-create':
                return $this->preCreate($request, $payload);
            case 'post-create':
                return $this->postCreate($request, $payload);
            case 'post-finish':
                return $this->postFinish($request, $payload);
            case 'post-terminate':
                return $this->postTerminate($request, $payload);
            default:
                // Other hooks (post-receive) - just acknowledge
                return response()->json(['ok' => true]);
        }
    }

    /**
     * Pre-create hook: Validate JWT, check user permissions, and enforce max upload size
     * Note: Upload ID is not available yet at this stage
     */
    protected function preCreate(Request $request, array $payload)
    {
        try {
            // Extract authorization header from the upload request metadata
            $authHeader = $payload['Event']['HTTPRequest']['Header']['Authorization'][0] ?? null;

            if (!$authHeader) {
                Log::warning('tusd pre-create: No authorization header');
                return response()->json([
                    'ok' => false,
                    'message' => 'Unauthorized: No authorization header'
                ], 401);
            }

            // Extract token from "Bearer <token>"
            $token = str_replace('Bearer ', '', $authHeader);

            // Validate the JWT token
            $user = JWTAuth::setToken($token)->authenticate();

            if (!$user) {
                Log::warning('tusd pre-create: Invalid token');
                return response()->json([
                    'ok' => false,
                    'message' => 'Unauthorized: Invalid token'
                ], 401);
            }

            $fileSize = $payload['Event']['Upload']['Size'] ?? 0;
            $settingsService = app(SettingsService::class);
            $maxUploadSize = $settingsService->getMaxUploadSize();

            if ($maxUploadSize) {
                // Check individual file size
                if ($fileSize > $maxUploadSize) {
                    $maxSizeFormatted = $this->formatBytes($maxUploadSize);
                    Log::warning('tusd pre-create: File exceeds max upload size', [
                        'user_id' => $user->id,
                        'file_size' => $fileSize,
                        'max_size' => $maxUploadSize
                    ]);
                    return response()->json([
                        'ok' => false,
                        'message' => "File size exceeds maximum allowed size of {$maxSizeFormatted}"
                    ], 413);
                }

                // Check cumulative size of all pending uploads for this user
                // This prevents malicious users from bypassing frontend validation
                // by uploading multiple files that together exceed the limit
                $pendingUploadsSize = UploadSession::where('user_id', $user->id)
                    ->whereIn('status', ['pending', 'complete'])
                    ->sum('filesize');

                $totalSizeAfterUpload = $pendingUploadsSize + $fileSize;

                if ($totalSizeAfterUpload > $maxUploadSize) {
                    $maxSizeFormatted = $this->formatBytes($maxUploadSize);
                    $currentSizeFormatted = $this->formatBytes($pendingUploadsSize);
                    
                    Log::warning('tusd pre-create: Cumulative upload size exceeds max', [
                        'user_id' => $user->id,
                        'file_size' => $fileSize,
                        'pending_size' => $pendingUploadsSize,
                        'total_would_be' => $totalSizeAfterUpload,
                        'max_size' => $maxUploadSize
                    ]);

                    // Clean up all pending uploads for this user since they're trying to exceed the limit
                    $this->cleanupPendingUploads($user->id);

                    return response()->json([
                        'ok' => false,
                        'message' => "Total upload size would exceed maximum allowed size of {$maxSizeFormatted}. Current pending uploads: {$currentSizeFormatted}. All pending uploads have been cancelled."
                    ], 413);
                }
            }

            Log::info('tusd pre-create: Upload authorized', [
                'user_id' => $user->id,
                'file_size' => $fileSize
            ]);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('tusd pre-create error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized: ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Clean up all pending uploads for a user (used when they exceed the limit)
     */
    private function cleanupPendingUploads(int $userId): void
    {
        $sessions = UploadSession::where('user_id', $userId)
            ->whereIn('status', ['pending', 'complete'])
            ->get();

        foreach ($sessions as $session) {
            // Delete the uploaded file from disk
            $uploadPath = storage_path('app/uploads/' . $session->upload_id);
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            // Also delete the .info file that tusd creates
            $infoPath = $uploadPath . '.info';
            if (file_exists($infoPath)) {
                unlink($infoPath);
            }

            // Delete associated File record if exists
            if ($session->file_id) {
                $file = File::find($session->file_id);
                if ($file) {
                    $file->delete();
                }
            }

            $session->delete();
        }

        Log::info('tusd: Cleaned up pending uploads for user exceeding limit', [
            'user_id' => $userId,
            'sessions_deleted' => $sessions->count()
        ]);
    }

    /**
     * Post-create hook: Create upload session after tusd has assigned an ID
     */
    protected function postCreate(Request $request, array $payload)
    {
        try {
            // Extract authorization header to get user
            $authHeader = $payload['Event']['HTTPRequest']['Header']['Authorization'][0] ?? null;
            $token = str_replace('Bearer ', '', $authHeader);
            $user = JWTAuth::setToken($token)->authenticate();

            // Get metadata from the upload
            $metadata = $payload['Event']['Upload']['MetaData'] ?? [];
            $filename = $metadata['filename'] ?? 'unknown';
            $filesize = $payload['Event']['Upload']['Size'] ?? 0;
            $filetype = $metadata['filetype'] ?? 'application/octet-stream';
            $uploadId = $payload['Event']['Upload']['ID'] ?? null;
            
            // Security: Validate upload ID is a safe hex string
            if (!$uploadId || !preg_match('/^[a-f0-9]+$/i', $uploadId)) {
                Log::warning('tusd post-create: Invalid upload ID format', [
                    'upload_id' => $uploadId
                ]);
                return response()->json(['ok' => true]);
            }

            // Create an upload session to track this upload
            $session = UploadSession::create([
                'upload_id' => $uploadId,
                'user_id' => $user->id,
                'filename' => $filename,
                'filesize' => $filesize,
                'filetype' => $filetype,
                'total_chunks' => 1, // tusd handles chunking internally
                'chunks_received' => 0,
                'status' => 'pending'
            ]);

            Log::info('tusd post-create: Upload session created', [
                'user_id' => $user->id,
                'upload_id' => $uploadId,
                'filename' => $filename
            ]);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('tusd post-create error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['ok' => true]); // Don't fail the upload
        }
    }

    /**
     * Post-finish hook: Create File record in database after upload completes
     */
    protected function postFinish(Request $request, array $payload)
    {
        try {
            $uploadId = $payload['Event']['Upload']['ID'] ?? null;
            
            // Security: Validate upload ID is a safe hex string (tusd generates 32-char hex IDs)
            if (!$uploadId || !preg_match('/^[a-f0-9]+$/i', $uploadId)) {
                Log::warning('tusd post-finish: Invalid upload ID format', [
                    'upload_id' => $uploadId
                ]);
                return response()->json(['ok' => true]);
            }
            
            $metadata = $payload['Event']['Upload']['MetaData'] ?? [];
            $filename = $metadata['filename'] ?? 'unknown';
            $filesize = $payload['Event']['Upload']['Size'] ?? 0;
            $filetype = $metadata['filetype'] ?? 'application/octet-stream';
            $storagePath = $payload['Event']['Upload']['Storage']['Path'] ?? null;

            // Find the upload session
            $session = UploadSession::where('upload_id', $uploadId)->first();

            if (!$session) {
                Log::warning('tusd post-finish: Upload session not found', [
                    'upload_id' => $uploadId
                ]);
                return response()->json(['ok' => true]);
            }

            // Sanitize filename for storage
            $sanitizedFilename = FileHelper::sanitizeFilename($filename);

            // Create file record
            $file = File::create([
                'name' => $sanitizedFilename,
                'original_name' => $filename,
                'type' => $filetype,
                'size' => $filesize,
                'temp_path' => 'uploads/' . $uploadId // Path relative to storage/app
            ]);

            // Update session
            $session->status = 'complete';
            $session->chunks_received = 1;
            $session->file_id = $file->id;
            $session->save();

            Log::info('tusd post-finish: File record created', [
                'file_id' => $file->id,
                'upload_id' => $uploadId,
                'filename' => $filename
            ]);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('tusd post-finish error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['ok' => true]); // Don't fail the upload
        }
    }

    /**
     * Post-terminate hook: Clean up when upload is cancelled
     */
    protected function postTerminate(Request $request, array $payload)
    {
        try {
            $uploadId = $payload['Event']['Upload']['ID'] ?? null;
            
            // Security: Validate upload ID is a safe hex string
            if (!$uploadId || !preg_match('/^[a-f0-9]+$/i', $uploadId)) {
                Log::warning('tusd post-terminate: Invalid upload ID format', [
                    'upload_id' => $uploadId
                ]);
                return response()->json(['ok' => true]);
            }

            // Find and delete the upload session
            $session = UploadSession::where('upload_id', $uploadId)->first();

            if ($session) {
                // If a file was created, delete it
                if ($session->file_id) {
                    $file = File::find($session->file_id);
                    if ($file) {
                        $file->delete();
                    }
                }
                $session->delete();

                Log::info('tusd post-terminate: Upload session cleaned up', [
                    'upload_id' => $uploadId
                ]);
            }

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('tusd post-terminate error', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['ok' => true]);
        }
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) { // 1 GB
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) { // 1 MB
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) { // 1 KB
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}

