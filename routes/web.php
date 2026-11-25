<?php

use Illuminate\Support\Facades\Route;
use App\Models\Setting;
use App\Models\User;
use App\Mail\shareDownloadedMail;
use App\Jobs\sendEmail;
use App\Models\Share;
use App\Models\Theme;
use App\Http\Controllers\ExternalAuthController;
use App\Http\Controllers\MeshNodeController;
use App\Services\SettingsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

// Data-plane routes for the storage mesh. Peers are private in phase 1 and the
// public gateway routes /n/<node>/* to them. The ping route exists to prove the
// token contract before chunk endpoints are added.
Route::prefix('n/{node}')->group(function () {
    Route::get('/health', [MeshNodeController::class, 'health'])
        ->where('node', '[A-Za-z0-9_-]+');

    Route::get('/ping', [MeshNodeController::class, 'ping'])
        ->middleware('mesh.token')
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->where('node', '[A-Za-z0-9_-]+');

    Route::put('/chunks/{intent}/{index}', [MeshNodeController::class, 'putChunk'])
        ->middleware('mesh.token')
        ->withoutMiddleware(ValidateCsrfToken::class)
        ->where('node', '[A-Za-z0-9_-]+')
        ->where('intent', '[a-f0-9]{32}')
        ->where('index', '[0-9]+');
});

// Feature tests boot the application more than once in a process, so the
// routes file can be included repeatedly. Guard the global helper.
if (!function_exists('getSettings')) {
function getSettings()
{
    
    $settingsService = new SettingsService();

    
    
    $settings = Setting::whereLike('group', 'ui%')
        ->orWhere('key', 'default_language')
        ->orWhere('key', 'show_language_selector')
        ->orWhere('key', 'allow_reverse_shares')
        ->orWhere('key', 'max_expiry_time')
        ->orWhere('key', 'default_expiry_time')
        ->get();

    $settings = $settings->map(function ($setting) use ($settingsService) {
        return [
            'key' => $setting->key,
            'value' => $settingsService->convertToCorrectType($setting->value)
        ];
    });

    $indexedSettings = [];
    foreach ($settings as $setting) {
        $indexedSettings[$setting['key']] = $setting['value'];
    }

    //have we any users in the database?
    $userCount = User::count();
    $indexedSettings['setup_needed'] = $userCount > 0 ? false : true;

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;

    // Add app version
    $indexedSettings['version'] = config('app.version');

    return $indexedSettings;
}
}

Route::get('/', function () {
    $indexedSettings = getSettings();

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;

    $theme = Theme::where('active', true)->first();


    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});

Route::get('/reset-password/{token}', function ($token) {
    $indexedSettings = getSettings();

    $indexedSettings['token'] = $token;

    $theme = Theme::where('active', true)->first();


    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});

Route::get('/shares/{share}', function ($shareId) {
    // Detect CLI tools (curl, wget, etc.) and serve file directly
    $userAgent = request()->userAgent() ?? '';
    $cliPatterns = [
        '/^curl\//i',
        '/^wget\//i',
        '/^libcurl/i',
        '/^Wget/i',
        '/^HTTPie\//i',
    ];
    
    $isCli = false;
    foreach ($cliPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            $isCli = true;
            break;
        }
    }
    
    if ($isCli) {
        // Serve file directly (curl uses Content-Disposition with -OJ flag)
        $controller = app(\App\Http\Controllers\SharesController::class);
        return $controller->download($shareId);
    }

    $indexedSettings = getSettings();

    $theme = Theme::where('active', true)->first();

    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});

// Direct download route with filename in URL (for wget compatibility - wget uses URL path for filename)
Route::get('/shares/{share}/{filename}', function ($shareId, $filename) {
    $controller = app(\App\Http\Controllers\SharesController::class);
    return $controller->download($shareId);
})->where('filename', '[^/]+'); // Only match single filename, not paths

// Download specific file from a multi-file share (supports nested paths)
Route::get('/shares/{share}/file/{filepath}', function ($shareId, $filepath) {
    $controller = app(\App\Http\Controllers\SharesController::class);
    return $controller->downloadFile($shareId, $filepath);
})->where('filepath', '.*');


//auth provider login
Route::get('/auth/provider/{provider}/login', [ExternalAuthController::class, 'providerLogin'])
    ->name('social.provider.login');

//auth provider callback
Route::get('/auth/provider/{provider}/callback', [ExternalAuthController::class, 'providerCallback'])
    ->name('social.provider.callback');

//auth provider link account
Route::get('/auth/provider/{provider}/link', [ExternalAuthController::class, 'linkAccount'])
    ->name('social.provider.link');
