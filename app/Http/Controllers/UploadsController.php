<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

use App\Models\Share;
use App\Models\File;
use App\Models\UploadSession;
use Carbon\Carbon;
use App\Jobs\CreateShareZip;
use App\Mail\shareCreatedMail;
use App\Jobs\sendEmail;
use App\Models\Setting;
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
    $sessions = UploadSession::whereIn('upload_id', $request->uploadIds)
      ->where('user_id', $user->id)
      ->where('status', 'complete')
      ->get();

    if ($sessions->count() !== count($request->uploadIds)) {
      return response()->json([
        'status' => 'error',
        'message' => 'Some uploads were not found or not completed'
      ], 400);
    }

    // Get file records from sessions
    $fileIds = $sessions->pluck('file_id')->filter()->toArray();
    $files = File::whereIn('id', $fileIds)->get();

    if ($files->count() === 0) {
      return response()->json([
        'status' => 'error',
        'message' => 'No files found for the uploads'
      ], 400);
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

    // Create a mapping from upload_id to file for path lookup
    $uploadIdToFile = [];
    foreach ($sessions as $session) {
      if ($session->file_id) {
        $uploadIdToFile[$session->upload_id] = $files->firstWhere('id', $session->file_id);
      }
    }

    // Associate files with the share and move from tusd uploads to share directory
    foreach ($files as $file) {
      // Move file from tusd uploads to share directory
      $sourcePath = storage_path('app/' . $file->temp_path);
      
      // Find the upload_id for this file to get the original path
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
      $destPath = $completePath . '/' . $originalPath;

      if (!file_exists($destPath)) {
        mkdir($destPath, 0777, true);
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
      
      // Clean up tusd .info file
      $infoPath = $sourcePath . '.info';
      if (file_exists($infoPath)) {
        unlink($infoPath);
      }

      // Update file record
      $file->share_id = $share->id;
      $file->full_path = $originalPath;
      $file->temp_path = null;
      $file->save();
    }

    // Clean up upload sessions
    foreach ($sessions as $session) {
      $session->delete();
    }

    // Dispatch job to create ZIP file
    CreateShareZip::dispatch($share);

    if ($user->is_guest) {
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

    // Process recipients if provided
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
}
