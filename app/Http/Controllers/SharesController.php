<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Share;
use Carbon\Carbon;
use App\Haikunator;
use App\Models\Setting;
use App\Models\User;
use App\Models\Download;
use App\Mail\shareDownloadedMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
use App\Jobs\cleanSpecificShares;
use Illuminate\Support\Facades\Hash;

class SharesController extends Controller
{
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

        return response()->download($sharePath . '/' . $share->files[0]->name);
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

        return response()->download($filename, $share->name . '.zip');
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

    $shares = Share::where('user_id', $user->id)->orderBy('created_at', 'desc')->with('files');
    if ($showDeleted === 'false') {
      $shares = $shares->where('status', '!=', 'deleted');
    }
    $shares = $shares->get();
    return response()->json([
      'status' => 'success',
      'message' => 'My shares',
      'data' => [
        'shares' => $shares->map(function ($share) {
          return $this->formatSharePrivate($share);
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
    if ($share->user_id != $user->id) {
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
    if ($share->user_id != $user->id) {
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
    if ($share->user_id != $user->id) {
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

    $shares = Share::where('user_id', $user->id)->where('expires_at', '<', Carbon::now())->get();
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
    $prefix = $settingsService->get('share_url_prefix') ?? '';
    $prefix = trim($prefix);
    
    $baseId = match ($mode) {
      'random' => $this->generateRandomId($settingsService),
      'uuid' => $this->generateUuidId(),
      'shortcode' => $this->generateShortcodeId($settingsService),
      default => $this->generateHaikuId(),
    };

    if (!empty($prefix)) {
      return $prefix . '-' . $baseId;
    }

    return $baseId;
  }

  private function generateHaikuId(): string
  {
    return Haikunator::haikunate() . '-' . Haikunator::haikunate();
  }

  private function generateRandomId(SettingsService $settingsService): string
  {
    $length = (int) ($settingsService->get('share_url_random_length') ?? 16);
    $useLowercase = $settingsService->get('share_url_random_lowercase') === 'true';
    $useUppercase = $settingsService->get('share_url_random_uppercase') === 'true';
    $useNumbers = $settingsService->get('share_url_random_numbers') === 'true';
    $useSpecial = $settingsService->get('share_url_random_special') === 'true';

    $characters = '';
    if ($useLowercase) {
      $characters .= 'abcdefghijklmnopqrstuvwxyz';
    }
    if ($useUppercase) {
      $characters .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if ($useNumbers) {
      $characters .= '0123456789';
    }
    if ($useSpecial) {
      $characters .= '-_';
    }

    // Fallback to lowercase if no character sets selected
    if (empty($characters)) {
      $characters = 'abcdefghijklmnopqrstuvwxyz';
    }

    $result = '';
    $charactersLength = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
      $result .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $result;
  }

  private function generateShortcodeId(SettingsService $settingsService): string
  {
    $length = (int) ($settingsService->get('share_url_shortcode_length') ?? 6);
    // Use URL-safe characters, excluding ambiguous ones (0, O, l, 1, I)
    $characters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    
    $result = '';
    $charactersLength = strlen($characters);
    for ($i = 0; $i < $length; $i++) {
      $result .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $result;
  }

  private function generateUuidId(): string
  {
    return (string) \Illuminate\Support\Str::uuid();
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
