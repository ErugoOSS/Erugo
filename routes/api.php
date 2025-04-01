<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Middleware\AdminMiddleware as Admin;
use App\Http\Middleware\NoUsersMiddleware as NoUsers;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SharesController;
use App\Http\Controllers\BackgroundsController;
use App\Http\Middleware\maxRequestSize;
use App\Services\SettingsService;
use App\Http\Controllers\ThemesController;
use App\Http\Controllers\AuthProvidersController;
use App\Http\Controllers\UploadsController;
use App\Http\Controllers\ReverseSharesController;
use App\Http\Controllers\EmailTemplatesController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


//route group for auth
Route::group([], function ($router) {

    Route::post('/setup', [UsersController::class, 'createFirstUser'])
        ->name('users.createFirstUser')
        ->middleware(NoUsers::class);

    Route::get('/health', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'OK',
            'data' => [
                'max_share_size' => app(SettingsService::class)->getMaxUploadSize()
            ]
        ]);
    });

    //auth
    Route::group(['prefix' => 'auth'], function ($router) {
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgotPassword');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.resetPassword');
    });

    //manage my profile [auth]
    Route::group(['prefix' => 'users/me', 'middleware' => ['auth']], function ($router) {
        Route::get('/', [UsersController::class, 'me'])->name('profile.view');
        Route::put('/', [UsersController::class, 'updateMe'])->name('profile.update');
        Route::delete('/providers/{providerId}', [UsersController::class, 'unlinkProvider'])->name('profile.unlinkProvider');
    });

    //manage users [auth, admin]
    Route::group(['prefix' => 'users', 'middleware' => ['auth', Admin::class]], function ($router) {

        //create a new user
        Route::post('/', [UsersController::class, 'create'])->name('users.create');

        //get all users
        Route::get('/', [UsersController::class, 'index'])->name('users.index');

        //update a user
        Route::put('/{id}', [UsersController::class, 'update'])->name('users.update');

        //delete a user
        Route::delete('/{id}', [UsersController::class, 'delete'])->name('users.delete');
    });


    //manage settings [auth, admin]
    Route::group(['prefix' => 'settings', 'middleware' => ['auth', Admin::class]], function ($router) {
        //create or update a setting
        Route::put('/', [SettingsController::class, 'write'])->name('settings.write');
        Route::post('/logo', [SettingsController::class, 'writeLogo'])->name('settings.writeLogo');
        //list background images
        Route::get('/backgrounds', [BackgroundsController::class, 'list'])->name('backgrounds.list');
        //upload a background image
        Route::post('/backgrounds', [BackgroundsController::class, 'upload'])->name('backgrounds.upload');
        //delete a background image
        Route::delete('/backgrounds/{file}', [BackgroundsController::class, 'delete'])->name('backgrounds.delete');
    });

    //read settings [auth]
    Route::group(['prefix' => 'settings', 'middleware' => ['auth']], function ($router) {
        //read a setting by its key
        Route::get('/{key}', [SettingsController::class, 'read'])->name('settings.read');
        //read settings by their group
        Route::get('/group/{group}', [SettingsController::class, 'readGroup'])->name('settings.readGroup');
    });

    //manage shares [auth]
    Route::group(['prefix' => 'shares', 'middleware' => ['auth']], function ($router) {

        //create a new share
        Route::post('/', [SharesController::class, 'create'])->name('shares.create')
            ->middleware(maxRequestSize::class);
        //get my shares
        Route::get('/', [SharesController::class, 'myShares'])->name('shares.myShares');

        //expire a share
        Route::post('/{id}/expire', [SharesController::class, 'expire'])->name('shares.expire');

        //extend a share
        Route::post('/{id}/extend', [SharesController::class, 'extend'])->name('shares.extend');

        //set download limit
        Route::post('/{id}/set-download-limit', [SharesController::class, 'setDownloadLimit'])->name('shares.setDownloadLimit');

        //prune expired shares
        Route::post('/prune-expired', [SharesController::class, 'pruneExpiredShares'])->name('shares.pruneExpired');
    });

    //manage themes [auth, admin]
    Route::group(['prefix' => 'themes', 'middleware' => ['auth', Admin::class]], function ($router) {
        Route::post('/', [ThemesController::class, 'saveTheme'])->name('themes.save');
        Route::get('/', [ThemesController::class, 'getThemes'])->name('themes.list');
        Route::delete('/', [ThemesController::class, 'deleteTheme'])->name('themes.delete');
        Route::post('/set-active', [ThemesController::class, 'setActiveTheme'])->name('themes.setActive');
        Route::post('/install', [ThemesController::class, 'installCustomTheme'])->name('themes.install');
    });

    //manage auth providers [auth, admin]
    Route::group(['prefix' => 'auth-providers', 'middleware' => ['auth', Admin::class]], function ($router) {
        Route::get('/', [AuthProvidersController::class, 'index'])->name('auth-providers.index');
        Route::get('/available-types', [AuthProvidersController::class, 'listAvailableProviderTypes'])->name('auth-providers.listAvailableProviderTypes');
        Route::post('/', [AuthProvidersController::class, 'create'])->name('auth-providers.create');
        Route::put('/bulk-update', [AuthProvidersController::class, 'bulkUpdate'])->name('auth-providers.bulkUpdate');
        Route::put('/{id}', [AuthProvidersController::class, 'update'])->name('auth-providers.update');
        Route::delete('/{id}', [AuthProvidersController::class, 'delete'])->name('auth-providers.delete');
        Route::get('/{uuid}/callback-url', [AuthProvidersController::class, 'getCallbackUrl'])->name('auth-providers.getCallbackUrl');
    });

    //manage email templates [auth, admin]
    Route::group(['prefix' => 'email-templates', 'middleware' => ['auth', Admin::class]], function ($router) {
        Route::get('/', [EmailTemplatesController::class, 'index'])->name('email-templates.index');
        Route::put('/', [EmailTemplatesController::class, 'update'])->name('email-templates.update');
    });

    //manage reverse shares [auth]
    Route::group(['prefix' => 'reverse-shares', 'middleware' => ['auth']], function ($router) {
        Route::post('/invite', [ReverseSharesController::class, 'createInvite'])->name('reverse-shares.createInvite');
    });

    //accept a reverse share invite [public]
    Route::group(['prefix' => 'reverse-shares'], function ($router) {
        Route::get('/accept', [ AuthController::class, 'acceptReverseShareInvite'])->name('reverse-shares.acceptInvite');
    });

    //read auth providers [public]
    Route::get('/available-auth-providers', [AuthProvidersController::class, 'list'])->name('auth-providers.list');

    //read active theme [public]
    Route::get('/themes/active', [ThemesController::class, 'getActiveTheme'])->name('themes.getActive');

    //read shares [public]
    Route::get('/shares/{share}', [SharesController::class, 'read'])->name('shares.read');

    //download shares [public]
    Route::any('/shares/{share}/download', [SharesController::class, 'download'])->name('shares.download');

    //use background image [public]
    Route::get('/backgrounds', [BackgroundsController::class, 'list'])->name('backgrounds.list');
    Route::get('/backgrounds/{file}/thumb', [BackgroundsController::class, 'useThumb'])->name('backgrounds.useThumb');
    Route::get('/backgrounds/{file}', [BackgroundsController::class, 'use'])->name('backgrounds.use');
});


// Chunked upload routes
Route::group(['prefix' => 'uploads', 'middleware' => ['auth']], function ($router) {
    // Create an upload session
    Route::post('/create-session', [UploadsController::class, 'createSession'])->name('uploads.createSession');

    // Upload a chunk
    Route::post('/chunk', [UploadsController::class, 'uploadChunk'])
        ->name('uploads.chunk')
        ->middleware(maxRequestSize::class);

    // Finalize an upload
    Route::post('/finalize', [UploadsController::class, 'finalizeUpload'])->name('uploads.finalize');

    // Create a share from chunks
    Route::post('/create-share-from-chunks', [UploadsController::class, 'createShareFromChunks'])->name('uploads.createShareFromChunks');
});
