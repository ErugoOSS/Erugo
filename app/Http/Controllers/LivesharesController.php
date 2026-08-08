<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Models\Liveshare;
use App\Models\LiveshareMember;
use App\Models\LiveshareFile;
use App\Models\LiveshareTag;
use App\Models\LiveshareInvite;
use App\Models\UploadSession;
use App\Models\File;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\ThumbnailService;
use App\Services\AutoTaggingService;
use App\Haikunator;
use App\Services\PatternGenerator;
use App\Utils\FileHelper;
use App\Mail\liveshareInviteMail;
use App\Jobs\sendEmail;
use Illuminate\Support\Str;

class LivesharesController extends Controller
{
    // -------------------------------------------------------
    // Liveshare CRUD
    // -------------------------------------------------------

    /**
     * List liveshares the authenticated user owns or is a member of.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $owned = Liveshare::where('user_id', $user->id)->get();

        $memberOf = Liveshare::whereHas('members', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->get();

        // Merge and deduplicate
        $liveshares = $owned->merge($memberOf)->unique('id')->values();

        // Attach the user's role to each liveshare
        $liveshares->each(function ($liveshare) use ($user) {
            $liveshare->setAttribute('my_role', $liveshare->getUserRole($user));
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'liveshares' => $liveshares
            ]
        ]);
    }

    /**
     * Create a new liveshare.
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        if ($user->is_guest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guest users cannot create liveshares'
            ], 403);
        }

        if ($user->isRestricted()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Restricted users cannot create liveshares'
            ], 403);
        }

        $settingsService = app(SettingsService::class);
        $enabled = $settingsService->get('liveshares_enabled') ?? true;
        if (!$enabled) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshares are disabled'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $longId = $this->generateLongId();
        $storagePath = (string) $user->id . '/liveshares/' . $longId;

        $liveshare = Liveshare::create([
            'user_id' => $user->id,
            'name' => $request->name,
            'description' => $request->description,
            'long_id' => $longId,
            'path' => $storagePath,
        ]);

        $liveshare->setAttribute('my_role', 'owner');

        return response()->json([
            'status' => 'success',
            'message' => 'Liveshare created',
            'data' => [
                'liveshare' => $liveshare
            ]
        ], 201);
    }

    /**
     * Get liveshare details including members and files.
     */
    public function show(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        // System admins can view any liveshare
        if (!$user->admin && !$liveshare->hasAccess($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $liveshare->load(['members.user', 'files.uploader', 'files.tags', 'owner', 'tags']);
        $liveshare->setAttribute('my_role', $liveshare->getUserRole($user));

        // Append thumbnail_url to files that support it
        $thumbnailService = app(ThumbnailService::class);
        $liveshare->files->each(function ($file) use ($liveshare, $thumbnailService) {
            if ($thumbnailService->canGenerateThumbnail($file->type, $file->name)) {
                $file->setAttribute('thumbnail_url', '/api/liveshares/' . $liveshare->long_id . '/files/' . $file->id . '/thumb');
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'liveshare' => $liveshare
            ]
        ]);
    }

    /**
     * Update liveshare name and/or description.
     */
    public function update(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this liveshare'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        if ($request->has('name')) {
            $liveshare->name = $request->name;
        }
        if ($request->has('description')) {
            $liveshare->description = $request->description;
        }

        $liveshare->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Liveshare updated',
            'data' => [
                'liveshare' => $liveshare
            ]
        ]);
    }

    /**
     * Delete a liveshare and all its files.
     */
    public function destroy(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        // Only the owner or a system admin can delete
        if (!$liveshare->isOwner($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only the owner can delete this liveshare'
            ], 403);
        }

        $liveshare->deleteWithFiles();

        return response()->json([
            'status' => 'success',
            'message' => 'Liveshare deleted'
        ]);
    }

    // -------------------------------------------------------
    // Member management
    // -------------------------------------------------------

    /**
     * List members of a liveshare.
     */
    public function listMembers(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $members = $liveshare->members()->with('user')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'members' => $members,
                'owner' => $liveshare->owner
            ]
        ]);
    }

    /**
     * Add a member to a liveshare.
     */
    public function addMember(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add members'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:manager,collaborator,viewer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $targetUser = User::where('email', $request->email)->first();

        if (!$targetUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found with that email address'
            ], 404);
        }

        if ($targetUser->is_guest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guest users cannot be added to liveshares'
            ], 400);
        }

        if ($liveshare->isOwner($targetUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The owner is already part of this liveshare'
            ], 400);
        }

        // Check if already a member
        $existing = LiveshareMember::where('liveshare_id', $liveshare->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'This user is already a member of this liveshare'
            ], 400);
        }

        $member = LiveshareMember::create([
            'liveshare_id' => $liveshare->id,
            'user_id' => $targetUser->id,
            'role' => $request->role,
        ]);

        $member->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Member added',
            'data' => [
                'member' => $member
            ]
        ], 201);
    }

    /**
     * Update a member's role.
     */
    public function updateMember(Request $request, string $longId, int $memberId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update members'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:manager,collaborator,viewer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $member = LiveshareMember::where('id', $memberId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found'
            ], 404);
        }

        $member->role = $request->role;
        $member->save();
        $member->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Member role updated',
            'data' => [
                'member' => $member
            ]
        ]);
    }

    /**
     * Remove a member from a liveshare.
     */
    public function removeMember(Request $request, string $longId, int $memberId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to remove members'
            ], 403);
        }

        $member = LiveshareMember::where('id', $memberId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found'
            ], 404);
        }

        $member->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Member removed'
        ]);
    }

    // -------------------------------------------------------
    // File management
    // -------------------------------------------------------

    /**
     * List files in a liveshare.
     * Supports optional query parameters for search and filtering:
     *   ?search=keyword  -- case-insensitive search on original_name
     *   ?tags=1,2,3      -- filter by custom tag IDs (OR -- file must have at least one)
     *   ?type=image      -- filter by media type auto-tag
     * When both ?tags and ?type are present, they are combined with AND:
     *   the file must match the type AND have at least one of the custom tags.
     */
    public function listFiles(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $query = $liveshare->files()->with(['uploader', 'tags']);

        // Text search on filename
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where('original_name', 'LIKE', '%' . $search . '%');
        }

        // Filter by media type auto-tag (shortcut)
        if ($request->has('type') && $request->type !== '') {
            $typeName = $request->type;
            $query->whereHas('tags', function ($q) use ($liveshare, $typeName) {
                $q->where('liveshare_id', $liveshare->id)
                  ->where('type', 'auto')
                  ->where('name', $typeName);
            });
        }

        // Filter by custom tag IDs (OR -- file must have at least one of the specified tags)
        if ($request->has('tags') && $request->tags !== '') {
            $tagIds = array_map('intval', array_filter(explode(',', $request->tags), fn($v) => is_numeric($v)));
            if (!empty($tagIds)) {
                $query->whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('liveshare_tags.id', $tagIds);
                });
            }
        }

        $files = $query->get();

        // Append thumbnail_url to files that support it
        $thumbnailService = app(ThumbnailService::class);
        $files->each(function ($file) use ($liveshare, $thumbnailService) {
            if ($thumbnailService->canGenerateThumbnail($file->type, $file->name)) {
                $file->setAttribute('thumbnail_url', '/api/liveshares/' . $liveshare->long_id . '/files/' . $file->id . '/thumb');
            }
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'files' => $files
            ]
        ]);
    }

    /**
     * Add files to a liveshare from completed TUS uploads.
     */
    public function addFiles(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canAddFiles($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add files'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'uploadIds' => ['required', 'array'],
            'uploadIds.*' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // Check limits
        $limitError = $this->checkLimits($liveshare, $user, $request->uploadIds);
        if ($limitError) {
            return $limitError;
        }

        // Find completed upload sessions
        $expectedCount = count($request->uploadIds);
        $maxRetries = 5;
        $sessions = null;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            $sessions = UploadSession::whereIn('upload_id', $request->uploadIds)
                ->where('user_id', $user->id)
                ->where('status', 'complete')
                ->get();

            if ($sessions->count() === $expectedCount) {
                break;
            }

            if ($attempt < $maxRetries - 1) {
                $delayMs = 100 * pow(2, $attempt);
                usleep($delayMs * 1000);
            }
        }

        if ($sessions->count() !== $expectedCount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Some uploads were not found or not completed'
            ], 400);
        }

        // Collect file IDs from sessions
        $fileIds = [];
        foreach ($sessions as $session) {
            if ($session->is_bundle && $session->bundle_file_ids) {
                $bundleFileIds = $session->getBundleFileIdsArray();
                $fileIds = array_merge($fileIds, $bundleFileIds);
            } elseif ($session->file_id) {
                $fileIds[] = $session->file_id;
            }
        }

        $fileIds = array_filter($fileIds);
        $uploadedFiles = File::whereIn('id', $fileIds)->get();

        if ($uploadedFiles->count() === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No files found for the uploads'
            ], 400);
        }

        // Create the liveshare storage directory
        $completePath = storage_path('app/liveshares/' . $liveshare->id);
        if (!file_exists($completePath)) {
            mkdir($completePath, 0777, true);
        }

        $createdFiles = [];

        foreach ($uploadedFiles as $file) {
            $sourcePath = storage_path('app/' . $file->temp_path);
            $sanitizedFilename = FileHelper::sanitizeFilename($file->original_name ?? $file->name);

            // Handle name collisions by appending a counter
            $destFile = $completePath . '/' . $sanitizedFilename;
            if (file_exists($destFile)) {
                $ext = pathinfo($sanitizedFilename, PATHINFO_EXTENSION);
                $name = pathinfo($sanitizedFilename, PATHINFO_FILENAME);
                $counter = 1;
                while (file_exists($destFile)) {
                    $sanitizedFilename = $name . '_' . $counter . ($ext ? '.' . $ext : '');
                    $destFile = $completePath . '/' . $sanitizedFilename;
                    $counter++;
                }
            }

            // Move file from temp to liveshare storage
            if (file_exists($sourcePath)) {
                if (copy($sourcePath, $destFile)) {
                    unlink($sourcePath);
                } else {
                    rename($sourcePath, $destFile);
                }
            }

            // Clean up tusd .info file
            $infoPath = $sourcePath . '.info';
            if (file_exists($infoPath)) {
                unlink($infoPath);
            }

            // Create liveshare file record
            $liveshareFile = LiveshareFile::create([
                'liveshare_id' => $liveshare->id,
                'uploaded_by' => $user->id,
                'name' => $sanitizedFilename,
                'original_name' => $file->original_name ?? $file->name,
                'size' => $file->size,
                'type' => $file->type,
                'full_path' => $sanitizedFilename,
            ]);

            // Auto-tag the file
            $autoTaggingService = app(AutoTaggingService::class);
            $autoTagNames = $autoTaggingService->getAutoTags($destFile, $file->type, $file->original_name ?? $file->name);

            if (!empty($autoTagNames)) {
                $autoTagIds = [];
                foreach ($autoTagNames as $tagName) {
                    $tag = LiveshareTag::firstOrCreate(
                        ['liveshare_id' => $liveshare->id, 'name' => $tagName],
                        ['type' => 'auto', 'created_by' => null]
                    );
                    $autoTagIds[] = $tag->id;
                }
                $liveshareFile->tags()->syncWithoutDetaching($autoTagIds);
            }

            $liveshareFile->load(['uploader', 'tags']);
            $createdFiles[] = $liveshareFile;
        }

        // Clean up upload sessions and bundle extraction directories
        foreach ($sessions as $session) {
            if ($session->is_bundle) {
                $extractDir = storage_path('app/uploads/' . $session->upload_id . '_extracted');
                if (is_dir($extractDir)) {
                    $this->recursiveDelete($extractDir);
                }
            }
            $session->delete();
        }

        // Recalculate stats
        $liveshare->recalculateStats();

        return response()->json([
            'status' => 'success',
            'message' => count($createdFiles) . ' file(s) added',
            'data' => [
                'files' => $createdFiles
            ]
        ], 201);
    }

    /**
     * Remove a file from a liveshare.
     */
    public function removeFile(Request $request, string $longId, int $fileId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canRemoveFiles($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to remove files'
            ], 403);
        }

        $file = LiveshareFile::where('id', $fileId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        // Delete from disk (validate path stays within liveshare directory)
        $basePath = storage_path('app/liveshares/' . $liveshare->id);
        $filePath = $basePath . '/' . $file->full_path;
        $realBase = realpath($basePath);
        $realFile = realpath($filePath);

        if ($realBase && $realFile && str_starts_with($realFile, $realBase . '/') && file_exists($realFile)) {
            unlink($realFile);
        }

        $file->delete();
        $liveshare->recalculateStats();

        return response()->json([
            'status' => 'success',
            'message' => 'File removed'
        ]);
    }

    /**
     * Download a single file from a liveshare.
     * Supports authentication via Authorization header or ?token= query parameter
     * (for direct browser downloads).
     */
    public function downloadFile(Request $request, string $longId, int $fileId)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $file = LiveshareFile::where('id', $fileId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        // Validate path stays within liveshare directory (defense in depth)
        $basePath = storage_path('app/liveshares/' . $liveshare->id);
        $filePath = $basePath . '/' . $file->full_path;
        $realBase = realpath($basePath);
        $realFile = realpath($filePath);

        if (!$realBase || !$realFile || !str_starts_with($realFile, $realBase . '/')) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found on disk'
            ], 404);
        }

        return response()->download($realFile, $file->original_name, [
            'Content-Type' => $file->type,
        ]);
    }

    /**
     * Get a thumbnail for a liveshare file.
     * Supports authentication via Authorization header or ?token= query parameter
     * (for <img> src usage).
     *
     * Optional query parameter: ?size=small|medium|large (defaults to small)
     */
    public function fileThumbnail(Request $request, string $longId, int $fileId)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $file = LiveshareFile::where('id', $fileId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        $thumbnailService = app(ThumbnailService::class);

        // Check if file type supports thumbnails
        if (!$thumbnailService->canGenerateThumbnail($file->type, $file->name)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thumbnail not supported for this file type'
            ], 400);
        }

        // Resolve requested size (defaults to small for backward compatibility)
        $size = $request->query('size', 'small');
        $width = $thumbnailService->resolveWidth($size);

        // Check for cached thumbnail
        $thumbCacheDir = storage_path('app/liveshares/thumbs/' . $liveshare->id);
        $thumbPath = $thumbCacheDir . '/' . $thumbnailService->thumbCacheFilename($file->id, $size);

        if (file_exists($thumbPath)) {
            return $thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        // Resolve the source file
        $basePath = storage_path('app/liveshares/' . $liveshare->id);
        $sourcePath = $basePath . '/' . $file->full_path;
        $realBase = realpath($basePath);
        $realSource = realpath($sourcePath);

        if (!$realBase || !$realSource || !str_starts_with($realSource, $realBase . '/')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Source file not found on disk'
            ], 404);
        }

        // Ensure cache directory exists
        if (!is_dir($thumbCacheDir)) {
            mkdir($thumbCacheDir, 0755, true);
        }

        // Generate the thumbnail
        if ($thumbnailService->generateThumbnailFromPath($realSource, $thumbPath, $file->type, $file->name, $width)) {
            return $thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to generate thumbnail'
        ], 500);
    }

    /**
     * Download multiple files as a zip archive.
     * Supports authentication via Authorization header or ?token= query parameter.
     *
     * Accepts a JSON body with EITHER:
     *   { "fileIds": [1, 2, 3] }                           -- specific files
     *   { "search": "keyword", "tags": "1,2", "type": "image" }  -- filter params (same as listFiles)
     *
     * If fileIds is provided, filter params are ignored.
     */
    public function downloadFiles(Request $request, string $longId)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        // Resolve which files to include
        if ($request->has('fileIds') && is_array($request->fileIds) && count($request->fileIds) > 0) {
            // Explicit file IDs
            $files = LiveshareFile::whereIn('id', $request->fileIds)
                ->where('liveshare_id', $liveshare->id)
                ->get();
        } else {
            // Use filter params (same logic as listFiles)
            $query = $liveshare->files();

            if ($request->has('search') && $request->search !== '') {
                $query->where('original_name', 'LIKE', '%' . $request->search . '%');
            }

            if ($request->has('type') && $request->type !== '') {
                $typeName = $request->type;
                $query->whereHas('tags', function ($q) use ($liveshare, $typeName) {
                    $q->where('liveshare_id', $liveshare->id)
                      ->where('type', 'auto')
                      ->where('name', $typeName);
                });
            }

            if ($request->has('tags') && $request->tags !== '') {
                $tagIds = array_map('intval', array_filter(explode(',', $request->tags), fn($v) => is_numeric($v)));
                if (!empty($tagIds)) {
                    $query->whereHas('tags', function ($q) use ($tagIds) {
                        $q->whereIn('liveshare_tags.id', $tagIds);
                    });
                }
            }

            $files = $query->get();
        }

        if ($files->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No files to download'
            ], 400);
        }

        // Single file -- skip the zip and download directly
        if ($files->count() === 1) {
            $file = $files->first();
            $basePath = storage_path('app/liveshares/' . $liveshare->id);
            $filePath = $basePath . '/' . $file->full_path;
            $realBase = realpath($basePath);
            $realFile = realpath($filePath);

            if (!$realBase || !$realFile || !str_starts_with($realFile, $realBase . '/')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found on disk'
                ], 404);
            }

            return response()->download($realFile, $file->original_name, [
                'Content-Type' => $file->type,
            ]);
        }

        // Multiple files -- create a temporary zip
        $tempDir = storage_path('app/liveshares/tmp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFilename = $liveshare->long_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.zip';
        $zipPath = $tempDir . '/' . $zipFilename;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create zip archive'
            ], 500);
        }

        $basePath = storage_path('app/liveshares/' . $liveshare->id);
        $realBase = realpath($basePath);
        $usedNames = [];
        $addedCount = 0;

        foreach ($files as $file) {
            $filePath = $basePath . '/' . $file->full_path;
            $realFile = realpath($filePath);

            if (!$realBase || !$realFile || !str_starts_with($realFile, $realBase . '/') || !file_exists($realFile)) {
                continue;
            }

            // Deduplicate names within the zip
            $entryName = $file->original_name ?? $file->name;
            if (isset($usedNames[$entryName])) {
                $ext = pathinfo($entryName, PATHINFO_EXTENSION);
                $nameWithout = pathinfo($entryName, PATHINFO_FILENAME);
                $counter = $usedNames[$entryName] + 1;
                $usedNames[$entryName] = $counter;
                $entryName = $nameWithout . '_' . $counter . ($ext ? '.' . $ext : '');
            } else {
                $usedNames[$entryName] = 1;
            }

            $zip->addFile($realFile, $entryName);
            $addedCount++;
        }

        $zip->close();

        if ($addedCount === 0) {
            @unlink($zipPath);
            return response()->json([
                'status' => 'error',
                'message' => 'No files could be added to the archive'
            ], 400);
        }

        $downloadName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $liveshare->name) . '.zip';

        return response()->download($zipPath, $downloadName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    // -------------------------------------------------------
    // Tag management
    // -------------------------------------------------------

    /**
     * List all tags for a liveshare (with file counts).
     */
    public function listTags(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $tags = $liveshare->tags()
            ->withCount('files')
            ->orderBy('type') // auto first, then custom
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tags' => $tags
            ]
        ]);
    }

    /**
     * Create a custom tag for a liveshare.
     */
    public function createTag(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to create tags'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // Check for duplicate name
        $existing = $liveshare->tags()->where('name', $request->name)->first();
        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'A tag with this name already exists'
            ], 400);
        }

        $tag = LiveshareTag::create([
            'liveshare_id' => $liveshare->id,
            'name' => $request->name,
            'type' => 'custom',
            'color' => $request->color,
            'created_by' => $user->id,
        ]);

        $tag->loadCount('files');

        return response()->json([
            'status' => 'success',
            'message' => 'Tag created',
            'data' => [
                'tag' => $tag
            ]
        ], 201);
    }

    /**
     * Update a custom tag (rename and/or recolor).
     */
    public function updateTag(Request $request, string $longId, int $tagId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update tags'
            ], 403);
        }

        $tag = LiveshareTag::where('id', $tagId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$tag) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tag not found'
            ], 404);
        }

        if ($tag->isAuto()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Auto-generated tags cannot be modified'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        if ($request->has('name') && $request->name !== $tag->name) {
            // Check for duplicate name
            $existing = $liveshare->tags()
                ->where('name', $request->name)
                ->where('id', '!=', $tag->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A tag with this name already exists'
                ], 400);
            }

            $tag->name = $request->name;
        }

        if ($request->has('color')) {
            $tag->color = $request->color;
        }

        $tag->save();
        $tag->loadCount('files');

        return response()->json([
            'status' => 'success',
            'message' => 'Tag updated',
            'data' => [
                'tag' => $tag
            ]
        ]);
    }

    /**
     * Delete a custom tag.
     */
    public function deleteTag(Request $request, string $longId, int $tagId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete tags'
            ], 403);
        }

        $tag = LiveshareTag::where('id', $tagId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$tag) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tag not found'
            ], 404);
        }

        if ($tag->isAuto()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Auto-generated tags cannot be deleted'
            ], 400);
        }

        $tag->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tag deleted'
        ]);
    }

    // -------------------------------------------------------
    // File tagging
    // -------------------------------------------------------

    /**
     * Add tags to a file.
     */
    public function addFileTags(Request $request, string $longId, int $fileId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $file = LiveshareFile::where('id', $fileId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        // Permission: owner/manager can tag any file, collaborator only own files
        if (!$liveshare->canManage($user) && !$user->admin) {
            if (!$liveshare->canAddFiles($user) || $file->uploaded_by !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to tag this file'
                ], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'tagIds' => ['required', 'array'],
            'tagIds.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // Verify all tags belong to this liveshare
        $tags = LiveshareTag::whereIn('id', $request->tagIds)
            ->where('liveshare_id', $liveshare->id)
            ->get();

        if ($tags->count() !== count($request->tagIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more tags were not found in this liveshare'
            ], 400);
        }

        // Attach tags (syncWithoutDetaching avoids duplicates)
        $file->tags()->syncWithoutDetaching($request->tagIds);
        $file->load('tags');

        return response()->json([
            'status' => 'success',
            'message' => 'Tags added',
            'data' => [
                'tags' => $file->tags
            ]
        ]);
    }

    /**
     * Remove a tag from a file.
     */
    public function removeFileTag(Request $request, string $longId, int $fileId, int $tagId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $file = LiveshareFile::where('id', $fileId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File not found'
            ], 404);
        }

        $tag = LiveshareTag::where('id', $tagId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$tag) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tag not found'
            ], 404);
        }

        if ($tag->isAuto()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Auto-generated tags cannot be removed from files'
            ], 400);
        }

        // Permission: owner/manager can untag any file, collaborator only own files
        if (!$liveshare->canManage($user) && !$user->admin) {
            if (!$liveshare->canAddFiles($user) || $file->uploaded_by !== $user->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to untag this file'
                ], 403);
            }
        }

        $file->tags()->detach($tagId);
        $file->load('tags');

        return response()->json([
            'status' => 'success',
            'message' => 'Tag removed',
            'data' => [
                'tags' => $file->tags
            ]
        ]);
    }

    /**
     * Bulk add tags to multiple files.
     */
    public function bulkAddFileTags(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fileIds' => ['required', 'array'],
            'fileIds.*' => ['required', 'integer'],
            'tagIds' => ['required', 'array'],
            'tagIds.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // Verify all tags belong to this liveshare
        $tags = LiveshareTag::whereIn('id', $request->tagIds)
            ->where('liveshare_id', $liveshare->id)
            ->get();

        if ($tags->count() !== count($request->tagIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more tags were not found in this liveshare'
            ], 400);
        }

        $files = LiveshareFile::whereIn('id', $request->fileIds)
            ->where('liveshare_id', $liveshare->id)
            ->get();

        $isManager = $liveshare->canManage($user) || $user->admin;
        $taggedCount = 0;

        foreach ($files as $file) {
            // Skip files the user cannot tag
            if (!$isManager && $file->uploaded_by !== $user->id) {
                continue;
            }

            $file->tags()->syncWithoutDetaching($request->tagIds);
            $taggedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => $taggedCount . ' file(s) tagged',
            'data' => [
                'tagged_count' => $taggedCount
            ]
        ]);
    }

    /**
     * Bulk remove tags from multiple files.
     */
    public function bulkRemoveFileTags(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->hasAccess($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this liveshare'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'fileIds' => ['required', 'array'],
            'fileIds.*' => ['required', 'integer'],
            'tagIds' => ['required', 'array'],
            'tagIds.*' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        // Only allow removing custom tags
        $customTagIds = LiveshareTag::whereIn('id', $request->tagIds)
            ->where('liveshare_id', $liveshare->id)
            ->where('type', 'custom')
            ->pluck('id')
            ->toArray();

        if (empty($customTagIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No removable custom tags found'
            ], 400);
        }

        $files = LiveshareFile::whereIn('id', $request->fileIds)
            ->where('liveshare_id', $liveshare->id)
            ->get();

        $isManager = $liveshare->canManage($user) || $user->admin;
        $untaggedCount = 0;

        foreach ($files as $file) {
            // Skip files the user cannot untag
            if (!$isManager && $file->uploaded_by !== $user->id) {
                continue;
            }

            $file->tags()->detach($customTagIds);
            $untaggedCount++;
        }

        return response()->json([
            'status' => 'success',
            'message' => $untaggedCount . ' file(s) untagged',
            'data' => [
                'untagged_count' => $untaggedCount
            ]
        ]);
    }

    // -------------------------------------------------------
    // Invite management
    // -------------------------------------------------------

    /**
     * Create an email invite for a liveshare.
     */
    public function createEmailInvite(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to create invites'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'role' => ['required', 'string', 'in:manager,collaborator,viewer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $token = Str::random(64);

        $invite = LiveshareInvite::create([
            'liveshare_id' => $liveshare->id,
            'created_by' => $user->id,
            'type' => 'email',
            'email' => $request->email,
            'token' => $token,
            'role' => $request->role,
            'max_uses' => 1,
        ]);

        $invite->load('creator', 'liveshare');

        // Dispatch invitation email
        $settingsService = app(SettingsService::class);
        $appUrl = $settingsService->get('application_url') ?? config('app.url');
        $inviteUrl = $appUrl . '/liveshares/invite/' . $token;

        sendEmail::dispatch(
            $request->email,
            liveshareInviteMail::class,
            [
                'invite' => $invite,
                'inviter' => $user,
                'inviteUrl' => $inviteUrl,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Email invite sent',
            'data' => [
                'invite' => $invite
            ]
        ], 201);
    }

    /**
     * Create a link invite for a liveshare.
     */
    public function createLinkInvite(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to create invites'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'string', 'in:manager,collaborator,viewer'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        $token = Str::random(64);

        $invite = LiveshareInvite::create([
            'liveshare_id' => $liveshare->id,
            'created_by' => $user->id,
            'type' => 'link',
            'token' => $token,
            'role' => $request->role,
            'max_uses' => $request->max_uses,
            'expires_at' => $request->expires_at,
        ]);

        $settingsService = app(SettingsService::class);
        $appUrl = $settingsService->get('application_url') ?? config('app.url');
        $inviteUrl = $appUrl . '/liveshares/invite/' . $token;

        $invite->load('creator');

        return response()->json([
            'status' => 'success',
            'message' => 'Link invite created',
            'data' => [
                'invite' => $invite,
                'invite_url' => $inviteUrl
            ]
        ], 201);
    }

    /**
     * List invites for a liveshare.
     */
    public function listInvites(Request $request, string $longId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view invites'
            ], 403);
        }

        $invites = LiveshareInvite::where('liveshare_id', $liveshare->id)
            ->with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        // Generate invite URLs for link invites
        $settingsService = app(SettingsService::class);
        $appUrl = $settingsService->get('application_url') ?? config('app.url');

        $invites->each(function ($invite) use ($appUrl) {
            $invite->setAttribute('invite_url', $appUrl . '/liveshares/invite/' . $invite->token);
            $invite->setAttribute('can_be_used', $invite->canBeUsed());
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'invites' => $invites
            ]
        ]);
    }

    /**
     * Revoke (delete) an invite.
     */
    public function revokeInvite(Request $request, string $longId, int $inviteId)
    {
        $user = Auth::user();
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        if (!$liveshare->canManage($user) && !$user->admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to revoke invites'
            ], 403);
        }

        $invite = LiveshareInvite::where('id', $inviteId)
            ->where('liveshare_id', $liveshare->id)
            ->first();

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite not found'
            ], 404);
        }

        $invite->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Invite revoked'
        ]);
    }

    /**
     * Get invite info (public endpoint -- for the acceptance page).
     */
    public function getInviteInfo(Request $request, string $token)
    {
        $invite = LiveshareInvite::where('token', $token)
            ->with(['liveshare', 'creator'])
            ->first();

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite not found'
            ], 404);
        }

        if (!$invite->canBeUsed()) {
            return response()->json([
                'status' => 'error',
                'message' => $invite->isExpired() ? 'This invite has expired' : 'This invite has been used up'
            ], 410);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'liveshare_name' => $invite->liveshare->name,
                'liveshare_long_id' => $invite->liveshare->long_id,
                'inviter_name' => $invite->creator->first_name,
                'role' => $invite->role,
                'type' => $invite->type,
            ]
        ]);
    }

    /**
     * Accept an invite (authenticated user).
     */
    public function acceptInvite(Request $request, string $token)
    {
        $user = Auth::user();

        $invite = LiveshareInvite::where('token', $token)
            ->with('liveshare')
            ->first();

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite not found'
            ], 404);
        }

        if (!$invite->canBeUsed()) {
            return response()->json([
                'status' => 'error',
                'message' => $invite->isExpired() ? 'This invite has expired' : 'This invite has been used up'
            ], 410);
        }

        // For email invites, verify the email matches
        if ($invite->type === 'email' && !$invite->isValidForEmail($user->email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This invite was sent to a different email address'
            ], 403);
        }

        $liveshare = $invite->liveshare;

        // Check if user is already the owner
        if ($liveshare->isOwner($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are already the owner of this liveshare'
            ], 400);
        }

        // Check if already a member
        $existing = LiveshareMember::where('liveshare_id', $liveshare->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'success',
                'message' => 'You are already a member of this liveshare',
                'data' => [
                    'liveshare_long_id' => $liveshare->long_id,
                    'already_member' => true,
                ]
            ]);
        }

        // Add user as a member
        LiveshareMember::create([
            'liveshare_id' => $liveshare->id,
            'user_id' => $user->id,
            'role' => $invite->role,
        ]);

        $invite->recordUse();

        return response()->json([
            'status' => 'success',
            'message' => 'You have joined the liveshare',
            'data' => [
                'liveshare_long_id' => $liveshare->long_id,
            ]
        ]);
    }

    // -------------------------------------------------------
    // Admin endpoints
    // -------------------------------------------------------

    /**
     * List all liveshares (system admin only).
     */
    public function adminListAll(Request $request)
    {
        $liveshares = Liveshare::with('owner')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'liveshares' => $liveshares
            ]
        ]);
    }

    /**
     * Set per-liveshare limit overrides (system admin only).
     */
    public function adminSetLimits(Request $request, int $id)
    {
        $liveshare = Liveshare::find($id);

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'max_size_override' => ['nullable', 'integer', 'min:0'],
            'max_files_per_user_override' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => ['errors' => $validator->errors()]
            ], 422);
        }

        if ($request->has('max_size_override')) {
            $liveshare->max_size_override = $request->max_size_override;
        }
        if ($request->has('max_files_per_user_override')) {
            $liveshare->max_files_per_user_override = $request->max_files_per_user_override;
        }

        $liveshare->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Limits updated',
            'data' => [
                'liveshare' => $liveshare
            ]
        ]);
    }

    // -------------------------------------------------------
    // Avatar
    // -------------------------------------------------------

    /**
     * Return a random image thumbnail from the liveshare to use as an avatar.
     * Auth: logged-in user with access, OR a valid invite token via ?invite_token=.
     */
    public function avatar(Request $request, string $longId)
    {
        $liveshare = Liveshare::where('long_id', $longId)->first();

        if (!$liveshare) {
            return response()->json([
                'status' => 'error',
                'message' => 'Liveshare not found'
            ], 404);
        }

        // Check access: logged-in user with access, OR valid invite token
        $hasAccess = false;

        $user = $this->resolveUser($request);
        if ($user && ($liveshare->hasAccess($user) || $user->admin)) {
            $hasAccess = true;
        }

        if (!$hasAccess && $request->has('invite_token')) {
            $invite = LiveshareInvite::where('token', $request->query('invite_token'))
                ->where('liveshare_id', $liveshare->id)
                ->first();

            if ($invite && $invite->canBeUsed()) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Find image files that support thumbnails (only true images)
        $thumbnailService = app(ThumbnailService::class);
        $imageFiles = $liveshare->files()
            ->whereIn('type', ThumbnailService::THUMB_IMAGE_TYPES)
            ->get();

        if ($imageFiles->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No images available'
            ], 404);
        }

        // Pick a random image file
        $file = $imageFiles->random();

        // Check for cached avatar thumbnail (separate cache from regular thumbs)
        $thumbCacheDir = storage_path('app/liveshares/thumbs/' . $liveshare->id);
        $thumbPath = $thumbCacheDir . '/' . $file->id . '.webp';

        if (file_exists($thumbPath)) {
            return $thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        // Resolve the source file
        $basePath = storage_path('app/liveshares/' . $liveshare->id);
        $sourcePath = $basePath . '/' . $file->full_path;
        $realBase = realpath($basePath);
        $realSource = realpath($sourcePath);

        if (!$realBase || !$realSource || !str_starts_with($realSource, $realBase . '/')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Source file not found on disk'
            ], 404);
        }

        // Ensure cache directory exists
        if (!is_dir($thumbCacheDir)) {
            mkdir($thumbCacheDir, 0755, true);
        }

        // Generate the thumbnail
        if ($thumbnailService->generateThumbnailFromPath($realSource, $thumbPath, $file->type, $file->name)) {
            return $thumbnailService->fileResponseWithCaching($thumbPath, 'image/webp');
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to generate thumbnail'
        ], 500);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Check if adding files would exceed liveshare limits.
     */
    private function checkLimits(Liveshare $liveshare, User $user, array $uploadIds): ?\Illuminate\Http\JsonResponse
    {
        $settingsService = app(SettingsService::class);

        // Calculate max size in bytes
        $maxSize = $liveshare->max_size_override;
        if ($maxSize === null) {
            $maxSizeSetting = $settingsService->get('liveshares_max_size') ?? 5;
            $maxSizeUnit = $settingsService->get('liveshares_max_size_unit') ?? 'GB';
            $multiplier = match (strtoupper($maxSizeUnit)) {
                'KB' => 1024,
                'MB' => 1024 * 1024,
                'GB' => 1024 * 1024 * 1024,
                'TB' => 1024 * 1024 * 1024 * 1024,
                default => 1024 * 1024 * 1024,
            };
            $maxSize = (int) $maxSizeSetting * $multiplier;
        }

        // Calculate max files per user
        $maxFilesPerUser = $liveshare->max_files_per_user_override;
        if ($maxFilesPerUser === null) {
            $maxFilesPerUser = (int) ($settingsService->get('liveshares_max_files_per_user') ?? 100);
        }

        // Check total size
        $sessions = UploadSession::whereIn('upload_id', $uploadIds)
            ->where('user_id', $user->id)
            ->where('status', 'complete')
            ->get();

        $newSize = $sessions->sum('filesize');
        if ($liveshare->size + $newSize > $maxSize) {
            return response()->json([
                'status' => 'error',
                'message' => 'Adding these files would exceed the liveshare size limit'
            ], 400);
        }

        // Check files per user
        $userFileCount = LiveshareFile::where('liveshare_id', $liveshare->id)
            ->where('uploaded_by', $user->id)
            ->count();

        $newFileCount = 0;
        foreach ($sessions as $session) {
            if ($session->is_bundle && $session->bundle_file_ids) {
                $newFileCount += count($session->getBundleFileIdsArray());
            } else {
                $newFileCount++;
            }
        }

        if ($userFileCount + $newFileCount > $maxFilesPerUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Adding these files would exceed the per-user file limit for this liveshare'
            ], 400);
        }

        return null;
    }

    /**
     * Generate a unique long_id for the liveshare URL.
     */
    private function generateLongId(): string
    {
        $settingsService = app(SettingsService::class);
        $mode = $settingsService->get('share_url_mode') ?? 'haiku';

        $maxAttempts = 10;
        $attempts = 0;

        $id = $this->generateIdByMode($mode, $settingsService);
        while (Liveshare::where('long_id', $id)->exists() && $attempts < $maxAttempts) {
            $id = $this->generateIdByMode($mode, $settingsService);
            $attempts++;
        }

        if ($attempts >= $maxAttempts) {
            throw new \Exception('Unable to generate unique long_id after ' . $maxAttempts . ' attempts');
        }

        return $id;
    }

    private function generateIdByMode(string $mode, SettingsService $settingsService): string
    {
        return match ($mode) {
            'pattern' => $this->generatePatternId($settingsService),
            default => $this->generateHaikuId(),
        };
    }

    private function generateHaikuId(): string
    {
        return Haikunator::haikunate() . '-' . Haikunator::haikunate();
    }

    private function generatePatternId(SettingsService $settingsService): string
    {
        $pattern = $settingsService->get('share_url_pattern') ?? '******';
        $generator = new PatternGenerator();

        $error = $generator->validate($pattern);
        if ($error !== null) {
            Log::warning("Invalid share URL pattern configured: {$error}. Using default.");
            $pattern = '******';
        }

        return $generator->generate($pattern);
    }

    /**
     * Resolve the authenticated user from either the standard auth guard
     * or a ?token= query parameter (used for direct browser downloads).
     */
    private function resolveUser(Request $request): ?User
    {
        $user = Auth::user();
        if ($user) {
            return $user;
        }

        if ($request->has('token')) {
            try {
                Auth::setToken($request->query('token'));
                return Auth::authenticate();
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Recursively delete a directory and its contents.
     */
    private function recursiveDelete(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
