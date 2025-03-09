<?php

use Illuminate\Support\Facades\Route;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Mail\shareDownloadedMail;
use App\Jobs\sendEmail;
use App\Models\Share;
use App\Models\Theme;
use App\Http\Controllers\ExternalAuthController;

function getSettings()
{
    $settings = Setting::whereLike('group', 'ui%')->orWhere('key', 'default_language')->orWhere('key', 'show_language_selector')->get();
    $indexedSettings = [];
    foreach ($settings as $setting) {
        $indexedSettings[$setting->key] = $setting->value;
    }
    return $indexedSettings;
}

Route::get('/', function () {
    $indexedSettings = getSettings();

    //have we any users in the database?
    $userCount = User::count();
    $indexedSettings['setup_needed'] = $userCount > 0 ? 'false' : 'true';

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;

    $theme = Theme::where('active', true)->first();


    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});

Route::get('/reset-password/{token}', function ($token) {
    $indexedSettings = getSettings();

    //have we any users in the database?
    $userCount = User::count();
    $indexedSettings['setup_needed'] = $userCount > 0 ? 'false' : 'true';

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;
    $indexedSettings['token'] = $token;

    $theme = Theme::where('active', true)->first();


    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});

Route::get('/shares/{share}', function () {
    $indexedSettings = getSettings();

    //have we any users in the database?
    $userCount = User::count();
    $indexedSettings['setup_needed'] = $userCount > 0 ? 'false' : 'true';

    //grab the app url from env
    $appURL = env('APP_URL');
    $indexedSettings['api_url'] = $appURL;

    $theme = Theme::where('active', true)->first();

    return view('app', ['settings' => $indexedSettings, 'theme' => $theme]);
});


Route::get('/get-logo', function () {
    //grab the logo file data from settings
    $setting = Setting::where('key', 'logo')->first();
    $logo = Storage::disk('public')->get($setting->value);
    // return $setting;
    return response($logo)->header('Content-Type', 'image/png');
});

//auth provider login
Route::get('/auth/provider/{provider}/login', [ExternalAuthController::class, 'providerLogin'])
    ->name('social.provider.login');

//auth provider callback
Route::get('/auth/provider/{provider}/callback', [ExternalAuthController::class, 'providerCallback'])
    ->name('social.provider.callback');

//auth provider link account
Route::get('/auth/provider/{provider}/link', [ExternalAuthController::class, 'linkAccount'])
    ->name('social.provider.link');
