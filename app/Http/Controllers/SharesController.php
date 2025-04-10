<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Share;
use App\Models\File;
use App\Jobs\CreateShareZip;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\User;
use App\Models\Download;
use App\Mail\shareDownloadedMail;
use App\Mail\shareCreatedMail;
use App\Jobs\sendEmail;
use App\Services\SettingsService;
use App\Jobs\cleanSpecificShares;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponder;
use App\Traits\TotalFileSize;
use App\Traits\CheckCreateFolder;
use App\Traits\GenerateLongId;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class SharesController extends Controller
{
  use ApiResponder;
  use TotalFileSize;
  use CheckCreateFolder;
  use GenerateLongId;
  public function create(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'name' => ['string', 'max:255'],
      'description' => ['max:500'],
      'expires_at' => ['date'],
      'files' => ['required', 'array'],
      'expiry_date' => ['required', 'date']
    ]);

    if ($validator->fails()) {
      return $this->validationError($validator->errors());
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

    $longId = $this->generateLongId();
    $files = $request->file('files');

    $totalFileSize = $this->getTotalFileSize($files);

    $password = $request->password;
    $passwordConfirm = $request->password_confirm;

    $password = $request->password;
    $passwordConfirm = $request->password_confirm;

    if ($password) {
      if ($password !== $passwordConfirm) {
        return $this->error('Password confirmation does not match', 400);
      }
    }

    $sharePath = $user->id . '/' . $longId;
  

    $share = Share::create([
      'name' => $request->name,
      'description' => $request->description,
      'expires_at' => $expiryDate,
      'user_id' => $user->id,
      'path' => $sharePath,
      'long_id' => $longId,
      'size' => $totalFileSize,
      'file_count' => count($files),
      'password' => $password ? Hash::make($password) : null
    ]);

    $this->processFiles($share, $files, $sharePath, $request);

    $share->status = 'pending';
    $share->save();

    //dispatch the job to create the zip file
    CreateShareZip::dispatch($share);

    if ($user->is_guest) {
      return $this->handleGuestShare($share, $user);
    }

    // Process recipients if provided
    if ($request->has('recipients')) {
      $this->processRecipients($share, $request->recipients);
    }

    return $this->success([
      'share' => $share
    ]);
  }

  private function processRecipients(Share $share, $recipients)
  {

    foreach ($recipients as $recipient) {
      if (is_array($recipient) && isset($recipient['name']) && isset($recipient['email'])) {
        $this->sendShareCreatedEmail($share, $recipient);
      } else if (is_string($recipient)) {
        $recipientData = json_decode($recipient, true);
        if ($recipientData && isset($recipientData['name']) && isset($recipientData['email'])) {
          $this->sendShareCreatedEmail($share, $recipientData);
        }
      }
    }
  }

  private function handleGuestShare(Share $share, $user)
  {
    $invite = $user->invite;

    $share->public = false;
    $share->invite_id = $invite->id;
    $share->user_id = null;
    $share->save();

    if ($invite->user) {
      $this->sendShareCreatedEmail($share, $invite->user);
    }

    $invite->guest_user_id = null;
    $invite->save();

    //log the user out
    Auth::logout();
    $user->delete();

    $cookie = cookie('refresh_token', '', 0, null, null, false, true);
    //don't use our response helper here like we usually would, as we need to set the cookie
    return response()->json([
      'status' => 'success',
      'message' => 'Share created',
      'data' => [
        'share' => $share
      ]
    ])->withCookie($cookie);
  }

  private function sendShareCreatedEmail(Share $share, $recipient)
  {
    $user = Auth::user();
    if ($recipient) {
      sendEmail::dispatch($recipient['email'], shareCreatedMail::class, ['user' => $user, 'share' => $share, 'recipient' => $recipient]);
    }
  }

  public function read($shareId)
  {
    $share = Share::where('long_id', $shareId)->with(['files', 'user'])->first();
    if (!$share) {
      return $this->error('Share not found', 404);
    }

    if ($share->expires_at < Carbon::now()) {
      return $this->error('Share expired', 410);
    }

    if ($share->download_limit != null && $share->download_count >= $share->download_limit) {
      return $this->error('Download limit reached', 410);
    }

    if (!$this->checkShareAccess($share)) {
      return $this->error('Share not found', 404);
    }

    return $this->success(['share' => $this->formatSharePublic($share)]);
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
          'name' => $file->name,
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

    $sharePath = $share->path;


    //otherise let's check the status: pending, ready, or failed
    if ($share->status == 'pending' || $share->status == 'moving') {
      return view('shares.pending', [
        'share' => $share,
        'settings' => $this->getSettings()
      ]);
    }

    //if the share is ready, download the zip file
    if ($share->status == 'ready') {
      $filename = $sharePath . '/' . $share->long_id . '.zip';
      //does the file exist?
      if ($share->shareDisk->exists($filename)) {
        $this->createDownloadRecord($share);
        return redirect()->to($share->shareDisk->temporaryUrl($filename, now()->addMinutes(5)));
      } else {
        Log::error('Share ready but file does not exist', ['share_id' => $share->id]);
        return view('shares.failed', [
          'share' => $share,
          'settings' => $this->getSettings()
        ]);
      }
    }

    //if the share is failed, show the failed view
    if ($share->status == 'failed') {
      Log::error('Share failed', ['share_id' => $share->id]);
      return view('shares.failed', [
        'share' => $share,
        'settings' => $this->getSettings()
      ]);
    }

    //if we got here we have no idea what to do so let's show the failed view
    Log::error('Share status unknown', ['share_id' => $share->id]);
    return view('shares.failed', [
      'share' => $share,
      'settings' => $this->getSettings()
    ]);
  }

  public function myShares(Request $request)
  {
    $user = Auth::user();

    if (!$user) {
      return $this->unauthorised();
    }

    $showDeleted = $request->input('show_deleted', false);

    $shares = Share::where('user_id', $user->id)->orderBy('created_at', 'desc')->with('files');
    if ($showDeleted === 'false') {
      $shares = $shares->where('status', '!=', 'deleted');
    }
    $shares = $shares->get();
    return $this->success([
      'shares' => $shares->map(function ($share) {
        return $this->formatSharePrivate($share);
      })
    ]);
  }

  public function expire($shareId)
  {
    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return $this->error('Share not found', 404);
    }
    if ($share->user_id != $user->id) {
      return $this->unauthorised();
    }
    $share->expires_at = Carbon::now();
    $share->save();

    return $this->success([
      'share' => $share
    ]);
  }

  public function extend($shareId)
  {

    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return $this->error('Share not found', 404);
    }
    if ($share->user_id != $user->id) {
      return $this->unauthorised();
    }
    $share->expires_at = $share->expires_at->addDays(7);
    $share->save();
    return $this->success([
      'share' => $share
    ]);
  }

  public function setDownloadLimit($shareId, Request $request)
  {
    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }
    $share = Share::where('id', $shareId)->first();
    if (!$share) {
      return $this->error('Share not found', 404);
    }
    if ($share->user_id != $user->id) {
      return $this->unauthorised();
    }
    if ($request->amount == -1) {
      $share->download_limit = null;
    } else {
      $share->download_limit = $request->amount;
    }
    $share->save();
    return $this->success([
      'share' => $share
    ]);
  }

  public function pruneExpiredShares()
  {
    $user = Auth::user();
    if (!$user) {
      return $this->unauthorised();
    }

    $shares = Share::where('user_id', $user->id)->where('expires_at', '<', Carbon::now())->get();
    cleanSpecificShares::dispatch($shares->pluck('id')->toArray(), $user->id);

    return $this->success([
      'shares' => $shares
    ]);
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

  /**
   * @param Share $share
   * @param \Illuminate\Http\UploadedFile[] $files
   * @param string $completePath
   * @param Request $request
   * @return void
   */
  private function processFiles(Share $share, array $files, string $completePath, Request $request)
  {
    foreach ($files as $index => $file) {
      $db_file = File::create([
        'share_id' => $share->id,
        'name' => $file->getClientOriginalName(),
        'type' => $file->getMimeType(),
        'size' => $file->getSize()
      ]);

      $originalPath = $request->file_paths[$index];
      $originalPath = explode('/', $originalPath);
      $originalPath = implode('/', array_slice($originalPath, 0, -1));
      Storage::disk('shares_staging')->putFileAs($completePath . '/' . $originalPath, $file, $file->getClientOriginalName());
      unlink($file->getPathname());
      $db_file->full_path = $originalPath;
      $db_file->save();
    }
  }
}
