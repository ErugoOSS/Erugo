<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\Share;
use App\Models\File;
use App\Models\Download;
use App\Mail\shareDownloadedMail;
use App\Jobs\sendEmail;
use App\Jobs\cleanSpecificShares;
use App\Services\SettingsService;
use App\Services\ThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AppSharesController extends Controller
{
    private ThumbnailService $thumbnailService;

    public function __construct(ThumbnailService $thumbnailService)
    {
        $this->thumbnailService = $thumbnailService;
    }

    /**
     * Symfony's Content-Disposition filename cannot contain "/" or "\".
     * Also strip basic header-breaking characters.
     */
    private function sanitizeDownloadFilename(string $filename, string $fallback = 'download'): string
    {
        $sanitized = str_replace(['/', '\\'], '-', $filename);
        $sanitized = str_replace(["\r", "\n"], '', $sanitized);
        $sanitized = trim($sanitized);

        return $sanitized !== '' ? $sanitized : $fallback;
    }

    /**
     * List the authenticated user's shares with pagination
     * 
     * Supports filtering via `status` parameter (comma-separated):
     * - active: not expired, not deleted (default)
     * - expired: expired but not deleted
     * - deleted: deleted shares
     * - all: no filtering
     * 
     * Examples:
     * - ?status=active (default)
     * - ?status=active,expired (all non-deleted)
     * - ?status=expired (only expired)
     * - ?status=all (everything)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $statusParam = $request->input('status', 'active');
        $perPage = min((int) $request->input('per_page', 20), 100); // Max 100 per page

        // Parse comma-separated status values
        $statuses = array_map('trim', explode(',', strtolower($statusParam)));

        // Include shares the user owns OR shares created for them via reverse share invites
        $query = Share::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('invite', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
        })->orderBy('created_at', 'desc')->with(['files', 'invite']);

        // Apply status filtering (unless 'all' is specified)
        if (!in_array('all', $statuses)) {
            $query->where(function ($q) use ($statuses) {
                $now = Carbon::now();
                
                if (in_array('active', $statuses)) {
                    // Active: not expired and not deleted
                    $q->orWhere(function ($active) use ($now) {
                        $active->where('status', '!=', 'deleted')
                            ->where(function ($exp) use ($now) {
                                $exp->where('expires_at', '>=', $now)
                                    ->orWhereNull('expires_at');
                            });
                    });
                }
                
                if (in_array('expired', $statuses)) {
                    // Expired: past expiry date but not deleted
                    $q->orWhere(function ($expired) use ($now) {
                        $expired->where('status', '!=', 'deleted')
                            ->where('expires_at', '<', $now);
                    });
                }
                
                if (in_array('deleted', $statuses)) {
                    // Deleted: status is deleted
                    $q->orWhere('status', 'deleted');
                }
            });
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'shares' => $paginated->getCollection()->map(function ($share) use ($user) {
                    $formatted = $this->formatSharePrivate($share);
                    // Flag shares that were created for the user (reverse shares) vs by the user
                    $formatted['shared_with_me'] = $share->invite && $share->invite->user_id == $user->id && $share->user_id != $user->id;
                    return $formatted;
                }),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'total_pages' => $paginated->lastPage(),
                    'total_items' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                ],
            ]
        ]);
    }

    /**
     * Get public share details by long_id
     */
    public function read($longId): JsonResponse
    {
        $share = Share::where('long_id', $longId)->with(['files', 'user'])->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if ($share->expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_EXPIRED',
                'message' => 'Share expired'
            ], 410);
        }

        if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_DOWNLOAD_LIMIT_REACHED',
                'message' => 'Download limit reached'
            ], 410);
        }

        if (!$this->checkShareAccess($share)) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'share' => $this->formatSharePublic($share)
            ]
        ]);
    }

    /**
     * Download entire share (zip for multi-file, direct for single file)
     * 
     * For the App API, password is passed via query parameter.
     */
    public function download($longId, Request $request)
    {
        $share = Share::where('long_id', $longId)->with('files')->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if ($share->password) {
            $password = $request->input('password');
            if (!$password) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_PASSWORD_REQUIRED',
                    'message' => 'Password required'
                ], 401);
            }
            if (!Hash::check($password, $share->password)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_PASSWORD_INVALID',
                    'message' => 'Invalid password'
                ], 401);
            }
        }

        if ($share->expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_EXPIRED',
                'message' => 'Share expired'
            ], 410);
        }

        if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_DOWNLOAD_LIMIT_REACHED',
                'message' => 'Download limit reached'
            ], 410);
        }

        if (!$this->checkShareAccess($share)) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_ACCESS_DENIED',
                'message' => 'Access denied'
            ], 403);
        }

        $sharePath = storage_path('app/shares/' . $share->path);

        // Single file share - download directly
        if ($share->file_count == 1) {
            if (file_exists($sharePath . '/' . $share->files[0]->name)) {
                $this->createDownloadRecord($share);
                return response()->download(
                    $sharePath . '/' . $share->files[0]->name,
                    $this->sanitizeDownloadFilename($share->files[0]->display_name)
                );
            } else {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_FILE_NOT_FOUND',
                    'message' => 'File not found'
                ], 404);
            }
        }

        // Multi-file share - check status
        if ($share->status == 'pending') {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_PENDING',
                'message' => 'Share is being prepared for download'
            ], 202);
        }

        if ($share->status == 'ready') {
            $filename = $sharePath . '.zip';
            if (file_exists($filename)) {
                $this->createDownloadRecord($share);
                return response()->download(
                    $filename,
                    $this->sanitizeDownloadFilename($share->name ?? '', 'share') . '.zip'
                );
            } else {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_FILE_NOT_FOUND',
                    'message' => 'Archive not found'
                ], 404);
            }
        }

        return response()->json([
            'status' => 'error',
            'code' => 'SHARE_FAILED',
            'message' => 'Share preparation failed'
        ], 500);
    }

    /**
     * Download a specific file from a multi-file share
     */
    public function downloadFile($longId, $filePath, Request $request)
    {
        $share = Share::where('long_id', $longId)->with('files')->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if ($share->password) {
            $password = $request->input('password');
            if (!$password) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_PASSWORD_REQUIRED',
                    'message' => 'Password required'
                ], 401);
            }
            if (!Hash::check($password, $share->password)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SHARE_PASSWORD_INVALID',
                    'message' => 'Invalid password'
                ], 401);
            }
        }

        if ($share->expires_at < Carbon::now()) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_EXPIRED',
                'message' => 'Share expired'
            ], 410);
        }

        if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_DOWNLOAD_LIMIT_REACHED',
                'message' => 'Download limit reached'
            ], 410);
        }

        if (!$this->checkShareAccess($share)) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_ACCESS_DENIED',
                'message' => 'Access denied'
            ], 403);
        }

        // Decode the filepath
        $filePath = urldecode($filePath);

        // For single file shares
        if ($share->file_count == 1) {
            $file = $share->files[0];
            $expectedPath = $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name;

            if ($filePath === $expectedPath || $filePath === $file->display_name) {
                $sharePath = storage_path('app/shares/' . $share->path);
                $diskFilePath = $sharePath . '/' . $file->name;

                if (file_exists($diskFilePath)) {
                    $this->createDownloadRecord($share);
                    return response()->download(
                        $diskFilePath,
                        $this->sanitizeDownloadFilename($file->display_name)
                    );
                }
            }
            return response()->json([
                'status' => 'error',
                'code' => 'FILE_NOT_FOUND',
                'message' => 'File not found'
            ], 404);
        }

        // Multi-file share - extract from zip
        if ($share->status !== 'ready') {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_READY',
                'message' => 'Share is not ready'
            ], 400);
        }

        $sharePath = storage_path('app/shares/' . $share->path);
        $zipPath = $sharePath . '.zip';

        if (!file_exists($zipPath)) {
            return response()->json([
                'status' => 'error',
                'code' => 'ARCHIVE_NOT_FOUND',
                'message' => 'Archive not found'
            ], 404);
        }

        // Find the file in the share's file list
        $foundFile = null;
        foreach ($share->files as $file) {
            $expectedPath = $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name;
            if ($filePath === $expectedPath || $filePath === $file->display_name) {
                $foundFile = $file;
                break;
            }
        }

        if (!$foundFile) {
            return response()->json([
                'status' => 'error',
                'code' => 'FILE_NOT_FOUND',
                'message' => 'File not found in share'
            ], 404);
        }

        // Build the path as it exists in the zip
        $zipFilePath = $foundFile->full_path ? $foundFile->full_path . '/' . $foundFile->name : $foundFile->name;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return response()->json([
                'status' => 'error',
                'code' => 'ARCHIVE_ERROR',
                'message' => 'Failed to open archive'
            ], 500);
        }

        // Try to find the file in the zip
        $fileIndex = $zip->locateName($zipFilePath);
        if ($fileIndex === false) {
            $fileIndex = $zip->locateName($foundFile->name);
        }

        if ($fileIndex === false) {
            $zip->close();
            return response()->json([
                'status' => 'error',
                'code' => 'FILE_NOT_FOUND_IN_ARCHIVE',
                'message' => 'File not found in archive'
            ], 404);
        }

        $stat = $zip->statIndex($fileIndex);
        $fileSize = $stat['size'];

        $stream = $zip->getStream($zip->getNameIndex($fileIndex));
        if (!$stream) {
            $zip->close();
            return response()->json([
                'status' => 'error',
                'code' => 'STREAM_ERROR',
                'message' => 'Failed to read file from archive'
            ], 500);
        }

        $this->createDownloadRecord($share);

        $mimeType = $foundFile->type ?? 'application/octet-stream';

        return response()->stream(function () use ($stream, $zip) {
            while (!feof($stream)) {
                echo fread($stream, 65536);
                flush();
            }
            fclose($stream);
            $zip->close();
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'attachment; filename="' . $this->sanitizeDownloadFilename($foundFile->display_name) . '"',
            'Content-Length' => $fileSize,
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Expire a share immediately
     */
    public function expire($shareId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $share = Share::where('id', $shareId)->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if (!$this->canManageShare($share, $user)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'You do not have permission to manage this share'
            ], 403);
        }

        $share->expires_at = Carbon::now();
        $share->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Share expired',
            'data' => [
                'share' => $this->formatSharePrivate($share)
            ]
        ]);
    }

    /**
     * Extend a share's expiration
     */
    public function extend($shareId, Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $share = Share::where('id', $shareId)->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if (!$this->canManageShare($share, $user)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'You do not have permission to manage this share'
            ], 403);
        }

        // Optional days parameter, default to 7
        $days = $request->input('days', 7);
        
        $share->expires_at = Carbon::now()->addDays($days);
        $share->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Share extended',
            'data' => [
                'share' => $this->formatSharePrivate($share),
                'new_expires_at' => $share->expires_at,
            ]
        ]);
    }

    /**
     * Set download limit on a share
     */
    public function setDownloadLimit($shareId, Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $share = Share::where('id', $shareId)->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if (!$this->canManageShare($share, $user)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'You do not have permission to manage this share'
            ], 403);
        }

        $limit = $request->input('limit');

        if ($limit == -1 || $limit === null) {
            $share->download_limit = null;
        } else {
            $share->download_limit = $limit;
        }
        $share->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Download limit updated',
            'data' => [
                'share' => $this->formatSharePrivate($share)
            ]
        ]);
    }

    /**
     * Prune (delete) all expired shares for the current user
     */
    public function prune(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Include shares the user owns OR shares created for them via reverse share invites
        $shares = Share::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas('invite', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
        })->where('expires_at', '<', Carbon::now())->get();

        cleanSpecificShares::dispatch($shares->pluck('id')->toArray(), $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Expired shares scheduled for deletion',
            'data' => [
                'deleted_count' => $shares->count(),
                'shares' => $shares->map(function ($share) {
                    return $this->formatSharePrivate($share);
                }),
            ]
        ]);
    }

    /**
     * Check if a user can manage a share
     */
    private function canManageShare(Share $share, $user): bool
    {
        // Admins can manage any share
        if ($user->admin) {
            return true;
        }

        // User is the owner of the share
        if ($share->user_id == $user->id) {
            return true;
        }

        // User created the invite that resulted in this share
        if ($share->invite && $share->invite->user_id == $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current request has access to a non-public share
     */
    private function checkShareAccess(Share $share): bool
    {
        if (!$share->public) {
            // For App API, check Authorization header
            $user = Auth::user();

            if (!$user) {
                return false;
            }

            $allowedUser = $share->invite->user ?? null;
            if ($allowedUser && $allowedUser->id == $user->id) {
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Format share for public display
     */
    private function formatSharePublic(Share $share): array
    {
        return [
            'id' => $share->id,
            'long_id' => $share->long_id,
            'name' => $share->name,
            'description' => $share->description,
            'expires_at' => $share->expires_at,
            'download_limit' => $share->download_limit,
            'download_count' => $share->download_count,
            'size' => $share->size,
            'file_count' => $share->file_count,
            'status' => $share->status,
            'expired' => $share->expires_at ? Carbon::parse($share->expires_at)->isPast() : false,
            'deleted' => $share->status === 'deleted',
            'deletes_at' => $share->deletes_at,
            'files' => $share->files->map(function ($file) {
                return [
                    'id' => $file->id,
                    'name' => $file->display_name,
                    'size' => $file->size,
                    'type' => $file->type,
                    'full_path' => $file->full_path,
                    'created_at' => $file->created_at,
                    'updated_at' => $file->updated_at
                ];
            }),
            'user' => [
                'name' => $share->user ? $share->user->name : 'Guest User',
            ],
            'password_protected' => $share->password ? true : false
        ];
    }

    /**
     * Format share for private display (owner view)
     */
    private function formatSharePrivate(Share $share): array
    {
        return [
            'id' => $share->id,
            'long_id' => $share->long_id,
            'name' => $share->name,
            'description' => $share->description,
            'expires_at' => $share->expires_at,
            'download_limit' => $share->download_limit,
            'download_count' => $share->download_count,
            'size' => $share->size,
            'file_count' => $share->file_count,
            'status' => $share->status,
            'expired' => $share->expires_at ? Carbon::parse($share->expires_at)->isPast() : false,
            'deleted' => $share->status === 'deleted',
            'deletes_at' => $share->deletes_at,
            'public' => (bool) $share->public,
            'path' => $share->path,
            'created_at' => $share->created_at,
            'updated_at' => $share->updated_at,
            'password_protected' => $share->password ? true : false,
            'files' => $share->files->map(function ($file) use ($share) {
                $data = [
                    'id' => $file->id,
                    'name' => $file->display_name,
                    'size' => $file->size,
                    'type' => $file->type,
                    'full_path' => $file->full_path,
                    'created_at' => $file->created_at,
                    'updated_at' => $file->updated_at,
                ];
                if ($this->thumbnailService->canGenerateThumbnail($file->type, $file->name)) {
                    $data['thumbnail_url'] = '/api/app/v1/shares/' . $share->id . '/files/' . $file->id . '/thumb';
                }
                return $data;
            }),
        ];
    }

    /**
     * Create a download record and send notification email
     */
    private function createDownloadRecord(Share $share): Download
    {
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();
        $download = Download::create([
            'share_id' => $share->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
        $download->save();

        if ($share->download_count == 0) {
            $this->sendShareDownloadedEmail($share);
        }

        $share->download_count++;
        $share->save();

        return $download;
    }

    /**
     * Send email notification when share is first downloaded
     */
    private function sendShareDownloadedEmail(Share $share): void
    {
        $settingsService = new SettingsService();
        $sendEmail = $settingsService->get('emails_share_downloaded_enabled');
        if ($sendEmail == 'true' && $share->user) {
            sendEmail::dispatch($share->user->email, shareDownloadedMail::class, ['share' => $share]);
        }
    }

    /**
     * Get thumbnail for a file in a share
     */
    public function fileThumbnail($shareId, $fileId)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $share = Share::where('id', $shareId)->with('files')->first();

        if (!$share) {
            return response()->json([
                'status' => 'error',
                'code' => 'SHARE_NOT_FOUND',
                'message' => 'Share not found'
            ], 404);
        }

        if (!$this->canManageShare($share, $user)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_FORBIDDEN',
                'message' => 'You do not have permission to access this share'
            ], 403);
        }

        // Find the file
        $file = $share->files->firstWhere('id', $fileId);
        if (!$file) {
            return response()->json([
                'status' => 'error',
                'code' => 'FILE_NOT_FOUND',
                'message' => 'File not found'
            ], 404);
        }

        // Check if file type supports thumbnails
        if (!$this->thumbnailService->canGenerateThumbnail($file->type, $file->name)) {
            return response()->json([
                'status' => 'error',
                'code' => 'THUMBNAIL_NOT_SUPPORTED',
                'message' => 'Thumbnail not supported for this file type'
            ], 400);
        }

        // Check for cached thumbnail
        $thumbCacheDir = storage_path('app/shares/thumbs/' . $share->id);
        $thumbPath = $thumbCacheDir . '/' . $file->id . '.webp';

        if (file_exists($thumbPath)) {
            return $this->thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        // Ensure cache directory exists
        if (!is_dir($thumbCacheDir)) {
            mkdir($thumbCacheDir, 0755, true);
        }

        // Need to generate thumbnail - get source file
        $sharePath = storage_path('app/shares/' . $share->path);

        // Single file share - file is on disk directly
        if ($share->file_count == 1) {
            $sourcePath = $sharePath . '/' . $file->name;
            if (!file_exists($sourcePath)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'SOURCE_FILE_NOT_FOUND',
                    'message' => 'Source file not found'
                ], 404);
            }

            if ($this->thumbnailService->generateThumbnailFromPath($sourcePath, $thumbPath, $file->type, $file->name)) {
                return $this->thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
            }

            return response()->json([
                'status' => 'error',
                'code' => 'THUMBNAIL_GENERATION_FAILED',
                'message' => 'Failed to generate thumbnail'
            ], 500);
        }

        // Multi-file share - extract from zip
        $zipPath = $sharePath . '.zip';
        if (!file_exists($zipPath)) {
            return response()->json([
                'status' => 'error',
                'code' => 'ARCHIVE_NOT_FOUND',
                'message' => 'Archive not found'
            ], 404);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return response()->json([
                'status' => 'error',
                'code' => 'ARCHIVE_ERROR',
                'message' => 'Failed to open archive'
            ], 500);
        }

        // Build the path as it exists in the zip
        $zipFilePath = $file->full_path ? $file->full_path . '/' . $file->name : $file->name;

        // Try to find the file in the zip
        $fileIndex = $zip->locateName($zipFilePath);
        if ($fileIndex === false) {
            $fileIndex = $zip->locateName($file->name);
        }

        if ($fileIndex === false) {
            $zip->close();
            return response()->json([
                'status' => 'error',
                'code' => 'FILE_NOT_FOUND_IN_ARCHIVE',
                'message' => 'File not found in archive'
            ], 404);
        }

        // Extract to temp file for thumbnail generation
        $tempDir = sys_get_temp_dir();
        $tempFile = $tempDir . '/erugo_thumb_' . $share->id . '_' . $file->id . '_' . uniqid();

        // Get the file extension for proper handling
        $extension = pathinfo($file->name, PATHINFO_EXTENSION);
        if ($extension) {
            $tempFile .= '.' . $extension;
        }

        $stream = $zip->getStream($zip->getNameIndex($fileIndex));
        if (!$stream) {
            $zip->close();
            return response()->json([
                'status' => 'error',
                'code' => 'STREAM_ERROR',
                'message' => 'Failed to read file from archive'
            ], 500);
        }

        // Write to temp file
        $tempHandle = fopen($tempFile, 'wb');
        while (!feof($stream)) {
            fwrite($tempHandle, fread($stream, 65536));
        }
        fclose($tempHandle);
        fclose($stream);
        $zip->close();

        // Generate thumbnail from temp file
        try {
            $this->thumbnailService->generateThumbnailFromPath($tempFile, $thumbPath, $file->type, $file->name);
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        if (file_exists($thumbPath)) {
            return $this->thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        return response()->json([
            'status' => 'error',
            'code' => 'THUMBNAIL_GENERATION_FAILED',
            'message' => 'Failed to generate thumbnail'
        ], 500);
    }
}
