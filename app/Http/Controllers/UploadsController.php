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
use App\Traits\ApiResponder;
use App\Traits\CheckCreateFolder;
use App\Traits\GenerateLongId;
use Illuminate\Support\Facades\Log;
class UploadsController extends Controller
{
  use ApiResponder;
  use CheckCreateFolder;
  use GenerateLongId;
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
      return $this->error('Validation failed', 422, $validator->errors());
    }

    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
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
    $tempDir = $user->id . '/' . $request->upload_id;
    $this->checkCreateFolder($tempDir, 'chunks');

    return $this->success(['session' => $session]);
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
      return $this->validationError(['errors' => $validator->errors()]);
    }

    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }

    // Find the upload session
    $session = UploadSession::where('upload_id', $request->upload_id)
      ->where('user_id', $user->id)
      ->first();

    if (!$session) {
      return $this->error('Upload session not found', 404);
    }

    // Get the chunk file
    $chunk = $request->file('chunk');
    $chunkIndex = $request->chunk_index;
    $chunkSize = $chunk->getSize();
    // Store the chunk file
    $chunkPath = $user->id . '/' . $request->upload_id . '/' . $chunkIndex;
    $finalPath = Storage::disk('chunks')->putFile($chunkPath, $chunk);


    // Record the chunk upload
    ChunkUpload::create([
      'upload_session_id' => $session->id,
      'chunk_index' => $chunkIndex,
      'chunk_size' => $chunkSize,
      'chunk_path' => $finalPath,
    ]);

    // Update the upload session
    $session->chunks_received += 1;
    if ($session->chunks_received == $session->total_chunks) {
      $session->status = 'complete';
    }
    $session->save();

    return $this->success([
      'chunk_index' => $chunkIndex,
      'received_chunks' => $session->chunks_received,
      'total_chunks' => $session->total_chunks,
      'is_complete' => ($session->chunks_received == $session->total_chunks)
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
      return $this->validationError(['errors' => $validator->errors()]);
    }

    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }

    // Find the upload session
    $session = UploadSession::where('upload_id', $request->upload_id)
      ->where('user_id', $user->id)
      ->first();

    if (!$session) {
      return $this->error('Upload session not found', 404);
    }

    // Check if all chunks are received
    if ($session->chunks_received != $session->total_chunks) {
      return $this->error('Not all chunks received', 400, [
          'received_chunks' => $session->chunks_received,
          'total_chunks' => $session->total_chunks
        ]
      );
    }

    // Path to the final assembled file
    $uuid = Str::uuid();
    $extension = pathinfo($request->filename, PATHINFO_EXTENSION);
    $finalFilePath = $user->id . '/' . $uuid . '.' . $extension;
    $this->checkCreateFolder($user->id . '/', 'chunks_assembled');
    $finalFilePath = storage_path('app/private/chunks_assembled/' . $finalFilePath);
    $finalFileHandle = fopen($finalFilePath, 'wb');

  
    // Get all chunks in proper order and concatenate them
    $chunks = ChunkUpload::where('upload_session_id', $session->id)
      ->orderBy('chunk_index', 'asc')
      ->get();

    foreach ($chunks as $chunk) {
      $chunkFilePath = $chunk->chunk_path;
      fwrite($finalFileHandle, Storage::disk('chunks')->get($chunkFilePath));
      Storage::disk('chunks')->delete($chunkFilePath);
    }

    $chunkDirectory = $user->id . '/' . $request->upload_id;
    Storage::disk('chunks')->deleteDirectory($chunkDirectory);

    fclose($finalFileHandle);

    // Create a file record in the database
    $file = File::create([
      'name' => $request->filename,
      'type' => $session->filetype ?? 'unknown',
      'size' => $session->filesize,
      'temp_path' => $user->id . '/' . $uuid . '.' . $extension
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

    return $this->success([
      'file' => $file
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
      return $this->validationError(['errors' => $validator->errors()]);
    }

    $maxExpiryTime = Setting::where('key', 'max_expiry_time')->first()->value;
    $expiryDate = Carbon::parse($request->expiry_date);

    if ($maxExpiryTime !== null) {
      $now = Carbon::now();

      if ($now->diffInDays($expiryDate) > $maxExpiryTime) {
        return $this->error('Expiry date is too long', 400, [
          'max_expiry_time' => $maxExpiryTime
        ]);
      }
    }

    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }

    // Generate a unique long ID for the share
    $longId = $this->generateLongId();

    // Create the share destination directory
    $completePath = $user->id . '/' . $longId;

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
        return $this->error('Password confirmation does not match', 400);
      }
    }

    // Create the share record
    $share = Share::create([
      'name' => $request->name,
      'description' => $request->description,
      'expires_at' => $expiryDate,
      'user_id' => $user->id,
      'path' => $completePath,
      'long_id' => $longId,
      'size' => $totalSize,
      'file_count' => $fileCount,
      'status' => 'pending',
      'password' => $password ? Hash::make($password) : null
    ]);

    // Associate files with the share and move from temp to share directory
    foreach ($files as $file) {
      // Move file from temp to share directory
      $sourcePath = storage_path('app/private/chunks_assembled/' . $file->temp_path);
      $originalPath = $request->filePaths[$file->id] ?? '';
      $originalPath = explode('/', $originalPath);
      $originalPath = implode('/', array_slice($originalPath, 0, -1));
      $destPath = $completePath . '/' . $originalPath;
      
      Storage::disk('shares_staging')->putFileAs($destPath, $sourcePath, $file->name);
      Storage::disk('chunks_assembled')->delete($file->temp_path);
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
      //return a success response - don't use our response helper here like we usually would, as we need to set the cookie
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

    return $this->success([
      'share' => $share
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
