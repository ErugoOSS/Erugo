<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Share;
use App\Models\File;
use App\Models\UploadSession;
use App\Models\ChunkUpload;
use Carbon\Carbon;
use App\Jobs\CreateShareZip;
use App\Mail\shareCreatedMail;
use App\Jobs\sendEmail;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UploadsController extends Controller
{
  /**
   * Create an upload session for chunked file upload
   */
  public function createSession(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'upload_id' => ['required', 'string'],
      'filename' => ['required', 'string'],
      'filesize' => ['required', 'numeric'],
      'total_chunks' => ['required', 'numeric']
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

    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    // Create upload session
    $session = UploadSession::create([
      'upload_id' => $request->upload_id,
      'user_id' => $user->id,
      'filename' => $request->filename,
      'filesize' => $request->filesize,
      'filetype' => $request->filetype ?? 'unknown',
      'total_chunks' => $request->total_chunks,
      'chunks_received' => 0,
      'status' => 'pending'
    ]);

    // Create temp directory for chunks
    $tempDir = storage_path('app/chunks/' . $user->id . '/' . $request->upload_id);
    if (!file_exists($tempDir)) {
      mkdir($tempDir, 0777, true);
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Upload session created',
      'data' => [
        'session' => $session
      ]
    ]);
  }

  /**
   * Upload a chunk of a file
   */
  public function uploadChunk(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'chunk' => ['required', 'file'],
      'upload_id' => ['required', 'string'],
      'chunk_index' => ['required', 'numeric'],
      'total_chunks' => ['required', 'numeric']
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

    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    // Find the upload session
    $session = UploadSession::where('upload_id', $request->upload_id)
      ->where('user_id', $user->id)
      ->first();

    if (!$session) {
      return response()->json([
        'status' => 'error',
        'message' => 'Upload session not found'
      ], 404);
    }

    // Get the chunk file
    $chunk = $request->file('chunk');
    $chunkIndex = $request->chunk_index;

    // Store the chunk file
    $chunkPath = 'chunks/' . $user->id . '/' . $request->upload_id . '/' . $chunkIndex;
    move_uploaded_file($chunk->getPathname(), storage_path('app/' . $chunkPath));

    // Record the chunk upload
    ChunkUpload::create([
      'upload_session_id' => $session->id,
      'chunk_index' => $chunkIndex,
      'chunk_size' => $chunk->getSize(),
      'chunk_path' => $chunkPath,
    ]);

    // Update the upload session
    $session->chunks_received += 1;
    if ($session->chunks_received == $session->total_chunks) {
      $session->status = 'complete';
    }
    $session->save();

    return response()->json([
      'status' => 'success',
      'message' => 'Chunk uploaded',
      'data' => [
        'chunk_index' => $chunkIndex,
        'received_chunks' => $session->chunks_received,
        'total_chunks' => $session->total_chunks,
        'is_complete' => ($session->chunks_received == $session->total_chunks)
      ]
    ]);
  }

  /**
   * Finalize a chunked upload by assembling the chunks into a single file
   */
  public function finalizeUpload(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'upload_id' => ['required', 'string'],
      'filename' => ['required', 'string'],
      'name' => ['string', 'max:255'],
      'description' => ['max:500'],
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

    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    // Find the upload session
    $session = UploadSession::where('upload_id', $request->upload_id)
      ->where('user_id', $user->id)
      ->first();

    if (!$session) {
      return response()->json([
        'status' => 'error',
        'message' => 'Upload session not found'
      ], 404);
    }

    // Check if all chunks are received
    if ($session->chunks_received != $session->total_chunks) {
      return response()->json([
        'status' => 'error',
        'message' => 'Not all chunks received',
        'data' => [
          'received_chunks' => $session->chunks_received,
          'total_chunks' => $session->total_chunks
        ]
      ], 400);
    }

    // Create directory for the assembled file
    $tempAssembledFilePath = storage_path('app/temp/' . $user->id);
    if (!file_exists($tempAssembledFilePath)) {
      mkdir($tempAssembledFilePath, 0777, true);
    }

    // Path to the final assembled file
    $uuid = Str::uuid();
    $extension = pathinfo($request->filename, PATHINFO_EXTENSION);
    $finalFilePath = $tempAssembledFilePath . '/' . $uuid . '.' . $extension;
    $finalFileHandle = fopen($finalFilePath, 'wb');

    // Get all chunks in proper order and concatenate them
    $chunks = ChunkUpload::where('upload_session_id', $session->id)
      ->orderBy('chunk_index', 'asc')
      ->get();

    foreach ($chunks as $chunk) {
      $chunkFilePath = storage_path('app/' . $chunk->chunk_path);
      $chunkContent = file_get_contents($chunkFilePath);
      fwrite($finalFileHandle, $chunkContent);

      // Clean up the chunk file after it's been used
      unlink($chunkFilePath);
    }

    fclose($finalFileHandle);

    // Create a file record in the database
    $file = File::create([
      'name' => $request->filename,
      'type' => $session->filetype ?? 'unknown',
      'size' => $session->filesize,
      'temp_path' => 'temp/' . $user->id . '/' . $uuid . '.' . $extension
    ]);

    // If recipients are provided, process them
    $recipients = [];
    if ($request->has('recipients') && is_array($request->recipients)) {
      $recipients = $request->recipients;
    }

    // Update the session to reflect completion
    $session->status = 'processed';
    $session->file_id = $file->id;
    $session->save();

    //tidy up left over folders and records
    $chunks = ChunkUpload::where('upload_session_id', $session->id)->get();
    foreach ($chunks as $chunk) {
      $path = storage_path('app/' . $chunk->chunk_path);
      $path = explode('/', $path);
      unset($path[count($path) - 1]);
      $path = implode('/', $path);
      if (is_dir($path)) {
        rmdir($path);
      }
    }

    $session->chunks()->delete();
    $session->delete();

    return response()->json([
      'status' => 'success',
      'message' => 'Upload finalized',
      'data' => [
        'file' => $file
      ]
    ]);
  }

  /**
   * Create a share from uploaded chunks
   */
  public function createShareFromChunks(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'upload_id' => ['required', 'string'],
      'name' => ['string', 'max:255'],
      'description' => ['max:500'],
      'fileInfo' => ['required', 'array'],
      'fileInfo.*' => ['required', 'numeric', 'exists:files,id'],
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

    // Calculate total size of all files
    $totalSize = 0;
    $fileCount = count($request->files);
    $files = File::whereIn('id', $request->fileInfo)->get();
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

    // Associate files with the share and move from temp to share directory
    foreach ($files as $file) {
      // Move file from temp to share directory
      $sourcePath = storage_path('app/' . $file->temp_path);
      $originalPath = $request->filePaths[$file->id];
      $originalPath = explode('/', $originalPath);
      $originalPath = implode('/', array_slice($originalPath, 0, -1));
      $destPath = $completePath . '/' . $originalPath;

      if (!file_exists($destPath)) {
        mkdir($destPath, 0777, true);
      }
      
      rename($sourcePath, $destPath . '/' . $file->name);

      // Update file record
      $file->share_id = $share->id;
      $file->full_path = $originalPath;
      $file->temp_path = null;
      $file->save();
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
