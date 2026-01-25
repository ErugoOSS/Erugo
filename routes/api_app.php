<?php

/**
 * App API Routes (v1)
 * 
 * Routes for native mobile and desktop applications.
 * Key differences from web API:
 * - Tokens returned in JSON body (not HTTP-only cookies)
 * - Longer refresh token TTL (30 days)
 * - External OAuth flow with custom URL scheme callbacks
 * - Consolidated config endpoint for app bootstrapping
 */

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AdminMiddleware as Admin;

use App\Http\Controllers\Api\App\AppAuthController;
use App\Http\Controllers\Api\App\AppConfigController;
use App\Http\Controllers\Api\App\AppBrandingController;
use App\Http\Controllers\Api\App\AppUserController;
use App\Http\Controllers\Api\App\AppSharesController;
use App\Http\Controllers\Api\App\AppUploadsController;
use App\Http\Controllers\Api\App\AppReverseSharesController;
use App\Http\Controllers\StatsController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'auth'], function () {
    // Public auth routes
    Route::post('login', 'AppAuthController@login')->name('app.auth.login');
    Route::post('refresh', 'AppAuthController@refresh')->name('app.auth.refresh');
    Route::post('logout', 'AppAuthController@logout')->name('app.auth.logout');
    Route::post('forgot-password', 'AppAuthController@forgotPassword')->name('app.auth.forgotPassword');
    Route::post('reset-password', 'AppAuthController@resetPassword')->name('app.auth.resetPassword');
    
    // Self-registration routes
    Route::post('register', 'AppAuthController@register')->name('app.auth.register');
    Route::post('verify-email', 'AppAuthController@verifyEmail')->name('app.auth.verifyEmail');
    Route::post('resend-verification', 'AppAuthController@resendVerification')->name('app.auth.resendVerification');
    Route::get('registration-settings', 'AppAuthController@registrationSettings')->name('app.auth.registrationSettings');
    
    // External auth routes (OAuth for native apps)
    Route::post('external/initiate', 'AppAuthController@externalInitiate')->name('app.auth.external.initiate');
    Route::post('external/complete', 'AppAuthController@externalComplete')->name('app.auth.external.complete');
    
    // Link external provider (requires authentication)
    Route::post('external/link', 'AppAuthController@externalLink')
        ->middleware('auth')
        ->name('app.auth.external.link');
});

/*
|--------------------------------------------------------------------------
| Configuration Routes (Public)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'config'], function () {
    Route::get('/', 'AppConfigController@getConfig')->name('app.config.get');
    Route::get('/theme', 'AppConfigController@getTheme')->name('app.config.theme');
});

/*
|--------------------------------------------------------------------------
| Branding Routes (Public)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'branding'], function () {
    Route::get('/logo', 'AppBrandingController@logo')->name('app.branding.logo');
    Route::get('/favicon', 'AppBrandingController@favicon')->name('app.branding.favicon');
    Route::get('/backgrounds', 'AppBrandingController@backgrounds')->name('app.branding.backgrounds');
    Route::get('/backgrounds/{id}', 'AppBrandingController@background')->name('app.branding.background');
    Route::get('/backgrounds/{id}/thumb', 'AppBrandingController@backgroundThumb')->name('app.branding.backgroundThumb');
});

/*
|--------------------------------------------------------------------------
| User Profile Routes (Authenticated)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'users/me', 'middleware' => ['auth']], function () {
    Route::get('/', 'AppUserController@me')->name('app.users.me');
    Route::put('/', 'AppUserController@update')->name('app.users.update');
    Route::post('/change-password', 'AppUserController@changePassword')->name('app.users.changePassword');
    Route::delete('/providers/{providerId}', 'AppUserController@unlinkProvider')->name('app.users.unlinkProvider');
});

/*
|--------------------------------------------------------------------------
| Shares Routes
|--------------------------------------------------------------------------
*/
// Public share routes
Route::get('/shares/{longId}', 'AppSharesController@read')
    ->name('app.shares.read');
Route::get('/shares/{longId}/download', 'AppSharesController@download')
    ->name('app.shares.download');
Route::get('/shares/{longId}/files/{filePath}', 'AppSharesController@downloadFile')
    ->where('filePath', '.*')
    ->name('app.shares.downloadFile');

// Authenticated share routes
Route::group(['prefix' => 'shares', 'middleware' => ['auth']], function () {
    Route::get('/', 'AppSharesController@index')->name('app.shares.index');
    Route::post('/{id}/expire', 'AppSharesController@expire')->name('app.shares.expire');
    Route::post('/{id}/extend', 'AppSharesController@extend')->name('app.shares.extend');
    Route::post('/{id}/download-limit', 'AppSharesController@setDownloadLimit')->name('app.shares.setDownloadLimit');
    Route::post('/prune', 'AppSharesController@prune')->name('app.shares.prune');
});

/*
|--------------------------------------------------------------------------
| Uploads Routes (Authenticated)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'uploads', 'middleware' => ['auth']], function () {
    Route::get('/verify/{uploadId}', 'AppUploadsController@verify')->name('app.uploads.verify');
    Route::post('/create-share', 'AppUploadsController@createShare')->name('app.uploads.createShare');
});

/*
|--------------------------------------------------------------------------
| Reverse Shares Routes
|--------------------------------------------------------------------------
*/
// Public route for accepting invite with token (guest flow)
Route::post('/reverse-shares/accept', 'AppReverseSharesController@accept')
    ->name('app.reverseShares.accept');

// Authenticated routes
Route::group(['prefix' => 'reverse-shares', 'middleware' => ['auth']], function () {
    Route::post('/invite', 'AppReverseSharesController@invite')->name('app.reverseShares.invite');
    Route::post('/accept-by-id', 'AppReverseSharesController@acceptById')->name('app.reverseShares.acceptById');
});

/*
|--------------------------------------------------------------------------
| Statistics Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'stats', 'middleware' => ['auth', Admin::class]], function () {
    Route::get('/', [StatsController::class, 'getStats'])->name('app.stats.get');
    Route::get('/system-info', [StatsController::class, 'getSystemInfo'])->name('app.stats.systemInfo');
});
