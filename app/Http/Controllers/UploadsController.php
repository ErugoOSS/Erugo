<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use App\Models\Share;
use App\Models\File;
use App\Models\UploadSession;
use App\Models\ReverseShareInvite;
use Carbon\Carbon;
use App\Jobs\CreateShareZip;
use App\Mail\shareCreatedMail;
use App\Jobs\sendEmail;
use App\Models\Setting;
use App\Services\ClamavScanner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UploadsController extends Controller
{
  /**
   * Verify if an upload session exists and is valid for resuming
   * This is used by the frontend to check if a previous tus upload can be resumed
   */
  public function verifyUpload(Request $request, string $uploadId)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    // Check if the upload session exists and belongs to this user
    $session = UploadSession::where('upload_id', $uploadId)
      ->where('user_id', $user->id)
      ->whereIn('status', ['pending', 'complete'])
      ->first();

    if (!$session) {
      return response()->json([
        'status' => 'error',
        'message' => 'Upload session not found'
      ], 404);
    }

    // Also verify the file still exists on disk
    $uploadPath = storage_path('app/uploads/' . $uploadId);
    if (!file_exists($uploadPath)) {
      // Clean up the orphaned session
      $session->delete();
      return response()->json([
        'status' => 'error',
        'message' => 'Upload file not found'
      ], 404);
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Upload session valid',
      'data' => [
        'upload_id' => $uploadId,
        'status' => $session->status,
        'filename' => $session->filename,
        'filesize' => $session->filesize
      ]
    ]);
  }

  /**
   * Create a share from tusd-uploaded files
   */
  public function createShareFromUploads(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'upload_id' => ['required', 'string'],
      'name' => ['string', 'max:255'],
      'description' => ['max:500'],
      'uploadIds' => ['required', 'array'],
      'uploadIds.*' => ['required', 'string'],
      'expiry_date' => ['required', 'date']
    ]);

    if ($validator->fails()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Validation failed',
        'data' => [
          'errors' => $validator->errors()
        ]
      ], 422);
    }

    $maxExpiryTime = Setting::where('key', 'max_expiry_time')->first()->value;
    $expiryDate = Carbon::parse($request->expiry_date);

    if ($maxExpiryTime !== null) {
      $now = Carbon::now();

      if ($now->diffInDays($expiryDate) > $maxExpiryTime) {
        return response()->json([
          'status' => 'error',
          'message' => 'Expiry date is too long',
          'data' => [
            'max_expiry_time' => $maxExpiryTime
          ]
        ], 400);
      }
    }

    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    // Generate a unique long ID for the share
    $longId = app('App\Http\Controllers\SharesController')->generateLongId();

    // Create the share destination directory
    $sharePath = $user->id . '/' . $longId;
    $completePath = storage_path('app/shares/' .  $sharePath);

    if (!file_exists($completePath)) {
      mkdir($completePath, 0777, true);
    }

    // Find files from upload sessions by tusd upload IDs
    // Use retry loop to handle race condition where post-finish hooks
    // may still be processing when this endpoint is called (especially with many small files)
    $expectedCount = count($request->uploadIds);
    $maxRetries = 5;
    $sessions = null;

    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
      $sessions = UploadSession::whereIn('upload_id', $request->uploadIds)
        ->where('user_id', $user->id)
        ->where('status', 'complete')
        ->get();

      if ($sessions->count() === $expectedCount) {
        // All sessions found and complete
        break;
      }

      if ($attempt < $maxRetries - 1) {
        // Wait with exponential backoff: 100ms, 200ms, 400ms, 800ms
        $delayMs = 100 * pow(2, $attempt);
        usleep($delayMs * 1000);

        Log::debug('Waiting for upload sessions to complete', [
          'attempt' => $attempt + 1,
          'found' => $sessions->count(),
          'expected' => $expectedCount,
          'delay_ms' => $delayMs
        ]);
      }
    }

    if ($sessions->count() !== $expectedCount) {
      Log::warning('Upload sessions not complete after retries', [
        'found' => $sessions->count(),
        'expected' => $expectedCount,
        'upload_ids' => $request->uploadIds
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'Some uploads were not found or not completed'
      ], 400);
    }

    // Get file records from sessions
    // Handle both regular uploads and bundle uploads
    $fileIds = [];
    $isBundleUpload = false;

    foreach ($sessions as $session) {
      if ($session->is_bundle && $session->bundle_file_ids) {
        // Bundle upload - get all file IDs from the bundle
        $bundleFileIds = $session->getBundleFileIdsArray();
        $fileIds = array_merge($fileIds, $bundleFileIds);
        $isBundleUpload = true;
      } elseif ($session->file_id) {
        // Regular upload
        $fileIds[] = $session->file_id;
      }
    }

    $fileIds = array_filter($fileIds);
    $files = File::whereIn('id', $fileIds)->get();

    if ($files->count() === 0) {
      return response()->json([
        'status' => 'error',
        'message' => 'No files found for the uploads'
      ], 400);
    }

    Log::info('createShareFromUploads: Processing files', [
      'file_count' => $files->count(),
      'is_bundle' => $isBundleUpload
    ]);

    // --- Antivirus scan with ClamAV (enabled when CLAMAV_URL is set) ---
    try {
      /** @var \App\Services\ClamavScanner $scanner */
      $scanner = app(ClamavScanner::class);

      foreach ($files as $file) {
        if (!$file->temp_path) {
          continue;
        }

        $clean = $scanner->scanPath($file->temp_path);
        if (!$clean) {
          // Clean up physical files and sessions before returning
          foreach ($files as $cleanupFile) {
            if ($cleanupFile->temp_path) {
              $abs = storage_path('app/' . $cleanupFile->temp_path);
              if (file_exists($abs)) {
                @unlink($abs);
              }
              // Clean up tusd .info file for non-bundle uploads
              $info = $abs . '.info';
              if (file_exists($info)) {
                @unlink($info);
              }
            }
          }

          // Clean up bundle extraction directories if present
          foreach ($sessions as $session) {
            if ($session->is_bundle) {
              $extractDir = storage_path('app/uploads/' . $session->upload_id . '_extracted');
              if (is_dir($extractDir)) {
                $this->recursiveDelete($extractDir);
              }
            }
            $session->delete();
          }

          // Delete DB file records for this failed share creation
          try {
            \App\Models\File::whereIn('id', $fileIds)->delete();
          } catch (\Throwable $dbEx) {
            Log::warning('Failed to delete file records after AV rejection', [
              'error' => $dbEx->getMessage(),
            ]);
          }

          return response()->json([
            'status' => 'error',
            'message' => 'Uploaded file failed antivirus scan and was rejected.',
          ], 422);
        }
      }
    } catch (\Throwable $e) {
      Log::error('ClamAV scan failed during share creation', [
        'error' => $e->getMessage(),
      ]);

      // Fail closed if AV is configured
      if (env('CLAMAV_URL')) {
        return response()->json([
          'status' => 'error',
          'message' => 'Antivirus scan failed; upload rejected.',
        ], 500);
      }
    }


    // Calculate total size of all files
    $totalSize = 0;
    $fileCount = $files->count();
    foreach ($files as $file) {
      $totalSize += $file->size;
    }

    $password = $request->password;
    $passwordConfirm = $request->password_confirm;

    if ($password) {
      if ($password !== $passwordConfirm) {
        return response()->json([
          'status' => 'error',
          'message' => 'Password confirmation does not match'
        ], 400);
      }
    }

    // Create the share record
    $share = Share::create([
      'name' => $request->name,
      'description' => $request->description,
      'expires_at' => $expiryDate,
      'user_id' => $user->id,
      'path' => $sharePath,
      'long_id' => $longId,
      'size' => $totalSize,
      'file_count' => $fileCount,
      'status' => 'pending',
      'password' => $password ? Hash::make($password) : null
    ]);

    // Create a mapping from upload_id to file for path lookup (for non-bundle uploads)
    $uploadIdToFile = [];
    foreach ($sessions as $session) {
      if (!$session->is_bundle && $session->file_id) {
        $uploadIdToFile[$session->upload_id] = $files->firstWhere('id', $session->file_id);
      }
    }

    // Associate files with the share and move from tusd uploads to share directory
    foreach ($files as $file) {
      // Move file from tusd uploads to share directory
      $sourcePath = storage_path('app/' . $file->temp_path);
      
      // Determine the original path based on whether this is a bundle file or not
      if ($isBundleUpload) {
        // For bundle files, the path is embedded in temp_path after '_extracted/'
        // e.g., 'uploads/abc123_extracted/folder/file.txt' -> 'folder/file.txt'
        $tempPathParts = explode('_extracted/', $file->temp_path);
        if (count($tempPathParts) > 1) {
          $bundleFilePath = $tempPathParts[1];
          $pathParts = explode('/', $bundleFilePath);
          array_pop($pathParts); // Remove filename
          $originalPath = implode('/', $pathParts);
        } else {
          $originalPath = '';
        }
      } else {
        // For regular uploads, use the filePaths from the request
        $uploadId = null;
        foreach ($uploadIdToFile as $uid => $f) {
          if ($f && $f->id === $file->id) {
            $uploadId = $uid;
            break;
          }
        }
        
        $originalPath = $request->filePaths[$uploadId] ?? '';
        $originalPath = explode('/', $originalPath);
        $originalPath = implode('/', array_slice($originalPath, 0, -1));
        
        // Sanitize path to prevent directory traversal attacks
        $originalPath = $this->sanitizePath($originalPath);
      }

      $destPath = $completePath . '/' . $originalPath;
      
      // Verify the resolved path is within the share directory
      // Create parent directories first so realpath can resolve
      if (!file_exists($destPath)) {
        mkdir($destPath, 0777, true);
      }
      $resolvedPath = realpath($destPath);
      $resolvedSharePath = realpath($completePath);
      
      if ($resolvedPath === false || $resolvedSharePath === false || 
          strpos($resolvedPath, $resolvedSharePath) !== 0) {
        Log::warning('Path traversal attempt detected', [
          'user_id' => $user->id,
          'original_path' => $request->filePaths[$uploadId] ?? '',
          'resolved_path' => $resolvedPath,
          'share_path' => $resolvedSharePath
        ]);
        
        // Clean up and skip this file
        continue;
      }
      
      // Use sanitized filename from database for file operations
      $sanitizedFilename = $file->name;
      $destFile = $destPath . '/' . $sanitizedFilename;
      
      // Move file to share directory
      // Use copy + unlink instead of rename to handle cross-filesystem moves
      if (file_exists($sourcePath)) {
        if (copy($sourcePath, $destFile)) {
          unlink($sourcePath);
        } else {
          // Fallback to rename if copy fails
          rename($sourcePath, $destFile);
        }
      }
      
      // Clean up tusd .info file (only for non-bundle files)
      if (!$isBundleUpload) {
        $infoPath = $sourcePath . '.info';
        if (file_exists($infoPath)) {
          unlink($infoPath);
        }
      }

      // Update file record
      $file->share_id = $share->id;
      $file->full_path = $originalPath;
      $file->temp_path = null;
      $file->save();
    }

    // Clean up upload sessions and bundle extraction directories
    foreach ($sessions as $session) {
      if ($session->is_bundle) {
        // Clean up the extraction directory
        $extractDir = storage_path('app/uploads/' . $session->upload_id . '_extracted');
        if (is_dir($extractDir)) {
          $this->recursiveDelete($extractDir);
        }
      }
      $session->delete();
    }

    // Dispatch job to create ZIP file
    CreateShareZip::dispatch($share);

    if ($user->is_guest) {
      // Guest user flow (unchanged)
      $invite = $user->invite;
      $share->public = false;
      $share->invite_id = $invite->id;
      $share->user_id = null;
      $share->save();

      if ($invite->user) {
        $this->sendShareCreatedEmail($share, $invite->user);
      } else {
        Log::error('Guest user has no invite user', ['user_id' => $user->id]);
      }

      $invite->guest_user_id = null;
      $invite->save();

      //log the user out
      Auth::logout();
      $user->delete();

      $cookie = cookie('refresh_token', '', 0, null, null, false, true);
      return response()->json([
        'status' => 'success',
        'message' => 'Share created',
      ])->withCookie($cookie);
    }

    // Check if this existing user has an active reverse share invite
    // (i.e., they accepted an invite by logging in and are now uploading)
    $activeInvite = ReverseShareInvite::where('guest_user_id', $user->id)
      ->where('recipient_email', $user->email)
      ->whereNotNull('used_at')
      ->whereNull('completed_at')
      ->first();

    if ($activeInvite) {
      // Existing user uploading via reverse share invite
      // Keep the share associated with their account but also notify the requester
      $share->invite_id = $activeInvite->id;
      $share->save();

      // Send notification to the requester
      if ($activeInvite->user) {
        $this->sendShareCreatedEmail($share, [
          'name' => $activeInvite->user->name,
          'email' => $activeInvite->user->email
        ]);
      }

      // Mark the invite as completed
      $activeInvite->completed_at = now();
      $activeInvite->guest_user_id = null; // Clear the link since upload is done
      $activeInvite->save();

      return response()->json([
        'status' => 'success',
        'message' => 'Share created',
        'data' => [
          'share' => $share
        ]
      ]);
    }

    // Process recipients if provided (normal share flow)
    if ($request->has('recipients') && is_array($request->recipients)) {
      foreach ($request->recipients as $recipient) {
        if (is_array($recipient) && isset($recipient['name']) && isset($recipient['email'])) {
          $this->sendShareCreatedEmail($share, $recipient);
        }
      }
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Share created',
      'data' => [
        'share' => $share
      ]
    ]);
  }

  /**
   * Send email notification that a share has been created
   */
  private function sendShareCreatedEmail(Share $share, $recipient)
  {
    $user = Auth::user();
    if ($recipient) {
      sendEmail::dispatch($recipient['email'], shareCreatedMail::class, [
        'user' => $user,
        'share' => $share,
        'recipient' => $recipient
      ]);
    }
  }

  /**
   * Sanitize a file path to prevent directory traversal attacks
   * Removes ../, ..\, and leading slashes
   */
  private function sanitizePath(string $path): string
  {
    // Normalize directory separators
    $path = str_replace('\\', '/', $path);
    
    // Remove any ../ or ..\ sequences (handles encoded variants too)
    $path = preg_replace('/\.\.[\\/\\\\]/', '', $path);
    
    // Also remove standalone .. 
    $path = preg_replace('/\.\./', '', $path);
    
    // Remove leading slashes to prevent absolute paths
    $path = ltrim($path, '/');
    
    // Remove any null bytes
    $path = str_replace("\0", '', $path);
    
    // Clean up any double slashes that may have resulted
    $path = preg_replace('/\/+/', '/', $path);
    
    return $path;
  }

  /**
   * Recursively delete a directory and its contents
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
