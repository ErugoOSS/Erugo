<?php

namespace App\Http\Controllers;

use App\Models\Share;
use Carbon\Carbon;
use App\Haikunator;
use App\Models\Setting;
use App\Models\User;
use App\Models\Download;
use App\Mail\shareDownloadedMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
use App\Services\PatternGenerator;
use App\Jobs\cleanSpecificShares;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ShareRecipient;
use App\Mail\shareCreatedMail;


class SharesController extends Controller
{
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

  // Check if the share is expired based on expires_at field 
  private function shareIsExpired($share): bool
  {
    return isset($share->expires_at) && $share->expires_at && now()->greaterThan($share->expires_at);
  }

  // Get the list of recipients for a share by share ID
  // Requires authentication and authorization to manage the share
  // Returns an empty list if the share is expired
  // Deletes recipient records if the share is expired
  // GET /api/shares/{id}/recipients
  public function getRecipients($id)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    $share = Share::find($id);
    if (!$share) {
      return response()->json(['status' => 'error', 'message' => 'Share not found'], 404);
    }

    if (!$this->canManageShare($share, $user)) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    // If expired, purge recipients and return empty
    if ($this->shareIsExpired($share)) {
      $share->recipients()->delete();
      return response()->json(['status' => 'success', 'data' => ['recipients' => []]]);
    }

    $recipients = $share->recipients()
      ->orderBy('created_at', 'asc')
      ->get(['email', 'last_emailed_at']);

    return response()->json([
      'status' => 'success',
      'data' => [
        'recipients' => $recipients,
      ],
    ]);
  }
  // Update the list of recipients for a share by share ID 
  // Requires authentication and authorization to manage the share
  // Replaces the existing recipient list with the provided list
  // Validates email format and ensures uniqueness
  public function updateRecipients(Request $request, $id)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    $share = Share::find($id);
    if (!$share) {
      return response()->json(['status' => 'error', 'message' => 'Share not found'], 404);
    }

    if (!$this->canManageShare($share, $user)) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    if ($this->shareIsExpired($share)) {
      $share->recipients()->delete();
      return response()->json(['status' => 'error', 'message' => 'Share has expired'], 422);
    }

    $validated = $request->validate([
      'recipients' => ['required', 'array'],
      'recipients.*' => ['required', 'email:rfc,dns'],
    ]);

    $emails = array_values(array_unique(array_map('strtolower', $validated['recipients'])));

    // Replace list (data minimisation: keep only current desired recipients)
    $share->recipients()->whereNotIn('email', $emails)->delete();

    foreach ($emails as $email) {
      ShareRecipient::updateOrCreate(
        ['share_id' => $share->id, 'email' => $email],
        []
      );
    }

    return response()->json(['status' => 'success', 'message' => 'Recipients updated']);
  }

  // Resend share created emails to all recipients of a share by share ID
  // Requires authentication and authorization to manage the share
  // Uses existing email job dispatching mechanism 
  // Updates last_emailed_at timestamp for each recipient 
  // Returns count of emails resent
  public function resendRecipientEmails($id)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    $share = Share::find($id);
    if (!$share) {
      return response()->json(['status' => 'error', 'message' => 'Share not found'], 404);
    }

    if (!$this->canManageShare($share, $user)) {
      return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }

    if ($this->shareIsExpired($share)) {
      $share->recipients()->delete();
      return response()->json(['status' => 'error', 'message' => 'Share has expired'], 422);
    }

    $recipients = $share->recipients()->get();
    if ($recipients->isEmpty()) {
      return response()->json(['status' => 'error', 'message' => 'No recipients found'], 422);
    }

    foreach ($recipients as $r) {
      // Use existing queued email job used in repo
      sendEmail::dispatch($r->email, shareCreatedMail::class, [
        'user' => $user,
        'share' => $share,
        'recipient' => ['name' => $r->email, 'email' => $r->email],
      ]);

      $r->last_emailed_at = now();
      $r->save();
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Recipient emails resent',
      'data' => ['recipient_count' => $recipients->count()],
    ]);
  }


  public function read($shareId)
  {
    $share = Share::where('long_id', $shareId)->with(['files', 'user'])->first();
    if (!$share) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share not found'
      ], 404);
    }

    if ($share->expires_at < Carbon::now()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share expired'
      ], 410);
    }

    if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
      return response()->json([
        'status' => 'error',
        'message' => 'Download limit reached'
      ], 410);
    }

    if (!$this->checkShareAccess($share)) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share not found'
      ], 404);
    }

    return response()->json([
      'status' => 'success',
      'message' => 'Share found',
      'data' => [

        'share' => $this->formatSharePublic($share)

      ]
    ]);
  }

  private function formatSharePublic(Share $share)
  {
    return [
      'id' => $share->id,
      'name' => $share->name,
      'description' => $share->description,
      'expires_at' => $share->expires_at,
      'download_limit' => $share->download_limit,
      'download_count' => $share->download_count,
      'size' => $share->size,
      'file_count' => $share->file_count,
      'files' => $share->files->map(function ($file) {
        return [
          'id' => $file->id,
          'name' => $file->display_name, // Show original filename to users
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

  private function formatSharePrivate(Share $share)
  {
    $share->password_protected = $share->password ? true : false;
    return $share;
  }

  /**
   * Check if a user can manage a share.
   * A user can manage a share if they are an admin, the owner, or if they created the invite for this share.
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

  private function checkShareAccess(Share $share)
  {
    if (!$share->public) {
      //get token from cookie
      $refreshToken = request()->cookie('refresh_token');

      if (!$refreshToken) {
        return false;
      }
      $user = Auth::setToken($refreshToken)->user();


      if (!$user) {
        return false;
      }
      $allowedUser = $share->invite->user;
      if ($user && $allowedUser && $allowedUser->id == $user->id) {
        return true;
      } else {
        return false;
      }
    }
    return true;
  }

  public function download($shareId)
  {

    $share = Share::where('long_id', $shareId)->with('files')->first();
    if (!$share) {
      return redirect()->to('/shares/' . $shareId);
    }

    if ($share->password) {
      $password = request()->input('password');
      if (!$password) {
        return redirect()->to('/shares/' . $shareId . '?error=password_required');
      }
      if (!Hash::check($password, $share->password)) {
        return redirect()->to('/shares/' . $shareId . '?error=invalid_password');
      }
    }

    if ($share->expires_at < Carbon::now()) {
      return redirect()->to('/shares/' . $shareId);
    }

    if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
      return redirect()->to('/shares/' . $shareId);
    }

    if (!$this->checkShareAccess($share)) {
      return redirect()->to('/shares/' . $shareId);
    }

    $sharePath = storage_path('app/shares/' . $share->path);

    //if there is only one file, download it directly
    if ($share->file_count == 1) {
      if (file_exists($sharePath . '/' . $share->files[0]->name)) {

        $this->createDownloadRecord($share);

        return response()->download(
          $sharePath . '/' . $share->files[0]->name,
          $this->sanitizeDownloadFilename($share->files[0]->display_name)
        );
      } else {
        return redirect()->to('/shares/' . $shareId);
      }
    }

    //otherise let's check the status: pending, ready, or failed
    if ($share->status == 'pending') {
      return view('shares.pending', [
        'share' => $share,
        'settings' => $this->getSettings()
      ]);
    }

    //if the share is ready, download the zip file
    if ($share->status == 'ready') {
      $filename = $sharePath . '.zip';
      \Log::info('looking for: ' . $filename);
      //does the file exist?
      if (file_exists($filename)) {
        $this->createDownloadRecord($share);

        return response()->download(
          $filename,
          $this->sanitizeDownloadFilename($share->name ?? '', 'share') . '.zip'
        );
      } else {
        //something went wrong, show the failed view
        return view('shares.failed', [
          'share' => $share,
          'settings' => $this->getSettings()
        ]);
      }
    }

    //if the share is failed, show the failed view
    if ($share->status == 'failed') {
      return view('shares.failed', [
        'share' => $share,
        'settings' => $this->getSettings()
      ]);
    }

    //if we got here we have no idea what to do so let's show the failed view
    return view('shares.failed', [
      'share' => $share,
      'settings' => $this->getSettings()
    ]);
  }

  /**
   * Download a specific file from a multi-file share
   * The filepath can include nested directories (e.g., "folder/subfolder/file.txt")
   */
  public function downloadFile($shareId, $filepath)
  {
    $share = Share::where('long_id', $shareId)->with('files')->first();
    if (!$share) {
      return response()->json(['error' => 'Share not found'], 404);
    }

    if ($share->password) {
      $password = request()->input('password');
      if (!$password) {
        return response()->json(['error' => 'Password required'], 401);
      }
      if (!Hash::check($password, $share->password)) {
        return response()->json(['error' => 'Invalid password'], 401);
      }
    }

    if ($share->expires_at < Carbon::now()) {
      return response()->json(['error' => 'Share expired'], 410);
    }

    if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
      return response()->json(['error' => 'Download limit reached'], 410);
    }

    if (!$this->checkShareAccess($share)) {
      return response()->json(['error' => 'Access denied'], 403);
    }

    // Decode the filepath (it may be URL encoded)
    $filepath = urldecode($filepath);

    // For single file shares, check if the requested file matches
    if ($share->file_count == 1) {
      $file = $share->files[0];
      $expectedPath = $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name;
      
      if ($filepath === $expectedPath || $filepath === $file->display_name) {
        $sharePath = storage_path('app/shares/' . $share->path);
        $filePath = $sharePath . '/' . $file->name;
        
        if (file_exists($filePath)) {
          $this->createDownloadRecord($share);
          return response()->download(
            $filePath,
            $this->sanitizeDownloadFilename($file->display_name)
          );
        }
      }
      return response()->json(['error' => 'File not found'], 404);
    }

    // For multi-file shares, we need to extract from the zip
    if ($share->status !== 'ready') {
      return response()->json(['error' => 'Share is not ready'], 400);
    }

    $sharePath = storage_path('app/shares/' . $share->path);
    $zipPath = $sharePath . '.zip';

    if (!file_exists($zipPath)) {
      return response()->json(['error' => 'Archive not found'], 404);
    }

    // Find the file in the share's file list to validate it exists
    $foundFile = null;
    foreach ($share->files as $file) {
      $expectedPath = $file->full_path ? $file->full_path . '/' . $file->display_name : $file->display_name;
      if ($filepath === $expectedPath || $filepath === $file->display_name) {
        $foundFile = $file;
        break;
      }
    }

    if (!$foundFile) {
      return response()->json(['error' => 'File not found in share'], 404);
    }

    // Build the path as it exists in the zip (using sanitized name)
    $zipFilePath = $foundFile->full_path ? $foundFile->full_path . '/' . $foundFile->name : $foundFile->name;

    // Open the zip and stream the file
    $zip = new \ZipArchive();
    if ($zip->open($zipPath) !== true) {
      return response()->json(['error' => 'Failed to open archive'], 500);
    }

    // Try to find the file in the zip
    $fileIndex = $zip->locateName($zipFilePath);
    if ($fileIndex === false) {
      // Try without the path prefix (zip might have different structure)
      $fileIndex = $zip->locateName($foundFile->name);
    }

    if ($fileIndex === false) {
      $zip->close();
      return response()->json(['error' => 'File not found in archive'], 404);
    }

    // Get file stats
    $stat = $zip->statIndex($fileIndex);
    $fileSize = $stat['size'];

    // Get the stream
    $stream = $zip->getStream($zip->getNameIndex($fileIndex));
    if (!$stream) {
      $zip->close();
      return response()->json(['error' => 'Failed to read file from archive'], 500);
    }

    $this->createDownloadRecord($share);

    // Determine content type
    $mimeType = $foundFile->type ?? 'application/octet-stream';

    // Stream the response with larger buffer for better throughput
    // 64KB buffer size optimizes for tunnel/proxy scenarios
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
      'X-Accel-Buffering' => 'no', // Disable proxy buffering for streaming
    ]);
  }

  public function myShares(Request $request)
  {
    $user = Auth::user();

    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    $showDeleted = $request->input('show_deleted', false);

    // Include shares the user owns OR shares created for them via reverse share invites
    $shares = Share::where(function ($query) use ($user) {
      $query->where('user_id', $user->id)
        ->orWhereHas('invite', function ($q) use ($user) {
          $q->where('user_id', $user->id);
        });
    })->orderBy('created_at', 'desc')->with(['files', 'invite']);
    if ($showDeleted === 'false') {
      $shares = $shares->where('status', '!=', 'deleted');
    }
    $shares = $shares->get();
    return response()->json([
      'status' => 'success',
      'message' => 'My shares',
      'data' => [
        'shares' => $shares->map(function ($share) use ($user) {
          $formatted = $this->formatSharePrivate($share);
          // Flag shares that were created for the user (reverse shares) vs by the user
          $formatted->shared_with_me = $share->invite && $share->invite->user_id == $user->id && $share->user_id != $user->id;
          return $formatted;
        })
      ]
    ]);
  }

  public function allShares(Request $request)
  {
    $user = Auth::user();

    if (!$user || !$user->admin) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }

    $showDeleted = $request->input('show_deleted', false);
    $userId = $request->input('user_id', null);

    $shares = Share::orderBy('created_at', 'desc')->with(['files', 'user', 'invite.user']);
    
    if ($showDeleted === 'false') {
      $shares = $shares->where('status', '!=', 'deleted');
    }
    
    if ($userId) {
      $shares = $shares->where('user_id', $userId);
    }
    
    $shares = $shares->get();
    
    return response()->json([
      'status' => 'success',
      'message' => 'All shares',
      'data' => [
        'shares' => $shares->map(function ($share) {
          $formatted = $this->formatSharePrivate($share);
          // For reverse shares, show the invite creator's info; otherwise show the share owner
          if ($share->invite && $share->invite->user) {
            $formatted->user_name = $share->invite->user->name;
            $formatted->user_email = $share->invite->user->email;
          } else {
            $formatted->user_name = $share->user ? $share->user->name : 'Unknown User';
            $formatted->user_email = $share->user ? $share->user->email : '';
          }
          return $formatted;
        })
      ]
    ]);
  }

  public function expire($shareId)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share not found'
      ], 404);
    }
    if (!$this->canManageShare($share, $user)) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    $share->expires_at = Carbon::now();
    $share->save();

    return response()->json([
      'status' => 'success',
      'message' => 'Share expired',
      'data' => [
        'share' => $share
      ]
    ]);
  }

  public function extend($shareId)
  {

    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share not found'
      ], 404);
    }
    if (!$this->canManageShare($share, $user)) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    $share->expires_at = Carbon::now()->addDays(7);
    $share->save();
    return response()->json([
      'status' => 'success',
      'message' => 'Share extended',
      'data' => [
        'share' => $share
      ]
    ]);
  }

  public function setDownloadLimit($shareId, Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return response()->json([
        'status' => 'error',
        'message' => 'Share not found'
      ], 404);
    }
    if (!$this->canManageShare($share, $user)) {
      return response()->json([
        'status' => 'error',
        'message' => 'Unauthorized'
      ], 401);
    }
    if ($request->amount == -1) {
      $share->download_limit = null;
    } else {
      $share->download_limit = $request->amount;
    }
    $share->save();
    return response()->json([
      'status' => 'success',
      'message' => 'Download limit set',
      'data' => [
        'share' => $share
      ]
    ]);
  }

  public function pruneExpiredShares()
  {
    $user = Auth::user();
    if (!$user) {
      return response()->json([
        'status' => 'error',
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
        'shares' => $shares
      ]
    ]);
  }


  public function generateLongId()
  {
    $settingsService = new SettingsService();
    $mode = $settingsService->get('share_url_mode') ?? 'haiku';

    $maxAttempts = 10;
    $attempts = 0;

    $id = $this->generateIdByMode($mode, $settingsService);
    while (Share::where('long_id', $id)->exists() && $attempts < $maxAttempts) {
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
      // Fallback to a safe default pattern if the configured one is invalid
      \Log::warning("Invalid share URL pattern configured: {$error}. Using default.");
      $pattern = '******';
    }
    
    return $generator->generate($pattern);
  }

  private function getSettings()
  {
    $settings = Setting::whereLike('group', 'ui%')->get();
    $indexedSettings = [];
    foreach ($settings as $setting) {
      $indexedSettings[$setting->key] = $setting->value;
    }

    //have we any users in the database?
    $userCount = User::count();
    $indexedSettings['setup_needed'] = $userCount > 0 ? 'false' : 'true';

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;

    return $indexedSettings;
  }

  private function createDownloadRecord(Share $share)
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

  private function sendShareDownloadedEmail(Share $share)
  {
    $settingsService = new SettingsService();
    $sendEmail = $settingsService->get('emails_share_downloaded_enabled');
    if ($sendEmail == 'true' && $share->user) {
      sendEmail::dispatch($share->user->email, shareDownloadedMail::class, ['share' => $share]);
    }
  }
}
