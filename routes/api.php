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
use App\Services\SettingsService;
use App\Http\Controllers\ThemesController;
use App\Http\Controllers\AuthProvidersController;
use App\Http\Controllers\UploadsController;
use App\Http\Controllers\ReverseSharesController;
use App\Http\Controllers\EmailTemplatesController;
use App\Http\Controllers\TusdHooksController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\CloudConnectController;

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

        //force reset a user's password
        Route::post('/{id}/force-reset-password', [UsersController::class, 'forceResetPassword'])->name('users.forceResetPassword');
    });


    //manage settings [auth, admin]
    Route::group(['prefix' => 'settings', 'middleware' => ['auth', Admin::class]], function ($router) {
        //create or update a setting
        Route::put('/', [SettingsController::class, 'write'])->name('settings.write');
        Route::post('/logo', [SettingsController::class, 'writeLogo'])->name('settings.writeLogo');
        Route::delete('/logo', [SettingsController::class, 'deleteLogo'])->name('settings.deleteLogo');
        //favicon management
        Route::post('/favicon', [SettingsController::class, 'writeFavicon'])->name('settings.writeFavicon');
        Route::delete('/favicon', [SettingsController::class, 'deleteFavicon'])->name('settings.deleteFavicon');
        Route::get('/favicon/status', [SettingsController::class, 'hasFavicon'])->name('settings.hasFavicon');
        //list background images
        Route::get('/backgrounds', [BackgroundsController::class, 'list'])->name('backgrounds.list');
        //upload a background image
        Route::post('/backgrounds', [BackgroundsController::class, 'upload'])->name('backgrounds.upload');
        //delete a background image
        Route::delete('/backgrounds/{file}', [BackgroundsController::class, 'delete'])->name('backgrounds.delete');
    });

    //system stats [auth, admin]
    Route::group(['prefix' => 'stats', 'middleware' => ['auth', Admin::class]], function ($router) {
        Route::get('/', [StatsController::class, 'getStats'])->name('stats.get');
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

    //all shares [auth, admin]
    Route::group(['prefix' => 'shares', 'middleware' => ['auth', Admin::class]], function ($router) {
        //get all shares (admin only)
        Route::get('/all', [SharesController::class, 'allShares'])->name('shares.allShares');
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

    //cloud connect [auth, admin]
    Route::group(['prefix' => 'cloud-connect', 'middleware' => ['auth', Admin::class]], function ($router) {
        // Status and capabilities
        Route::get('/status', [CloudConnectController::class, 'status'])->name('cloud-connect.status');
        
        // Cloud account management
        Route::post('/register', [CloudConnectController::class, 'register'])->name('cloud-connect.register');
        Route::post('/login', [CloudConnectController::class, 'login'])->name('cloud-connect.login');
        Route::post('/logout', [CloudConnectController::class, 'logout'])->name('cloud-connect.logout');
        Route::post('/resend-verification', [CloudConnectController::class, 'resendVerification'])->name('cloud-connect.resendVerification');
        Route::post('/forgot-password', [CloudConnectController::class, 'forgotPassword'])->name('cloud-connect.forgotPassword');
        Route::post('/reset-password', [CloudConnectController::class, 'resetPassword'])->name('cloud-connect.resetPassword');
        
        // User profile and usage
        Route::get('/usage', [CloudConnectController::class, 'usage'])->name('cloud-connect.usage');
        Route::patch('/user', [CloudConnectController::class, 'updateUser'])->name('cloud-connect.updateUser');
        
        // Subscription management
        Route::get('/subscription', [CloudConnectController::class, 'subscription'])->name('cloud-connect.subscription');
        Route::get('/plans', [CloudConnectController::class, 'plans'])->name('cloud-connect.plans');
        Route::post('/checkout', [CloudConnectController::class, 'checkout'])->name('cloud-connect.checkout');
        Route::post('/billing/portal', [CloudConnectController::class, 'billingPortal'])->name('cloud-connect.billingPortal');
        
        // Instance management
        Route::get('/instances', [CloudConnectController::class, 'instances'])->name('cloud-connect.instances');
        Route::post('/instances', [CloudConnectController::class, 'createInstance'])->name('cloud-connect.createInstance');
        Route::get('/instances/{instanceId}', [CloudConnectController::class, 'getInstance'])->name('cloud-connect.getInstance');
        Route::patch('/instances/{instanceId}', [CloudConnectController::class, 'updateInstance'])->name('cloud-connect.updateInstance');
        Route::delete('/instances/{instanceId}', [CloudConnectController::class, 'deleteInstance'])->name('cloud-connect.deleteInstance');
        Route::post('/instances/{instanceId}/regenerate-token', [CloudConnectController::class, 'regenerateToken'])->name('cloud-connect.regenerateToken');
        Route::post('/instances/{instanceId}/link', [CloudConnectController::class, 'linkInstance'])->name('cloud-connect.linkInstance');
        Route::get('/subdomains/check', [CloudConnectController::class, 'checkSubdomain'])->name('cloud-connect.checkSubdomain');
        
        // Tunnel management
        Route::post('/connect', [CloudConnectController::class, 'connect'])->name('cloud-connect.connect');
        Route::post('/disconnect', [CloudConnectController::class, 'disconnect'])->name('cloud-connect.disconnect');
        Route::get('/tunnel-status', [CloudConnectController::class, 'tunnelStatus'])->name('cloud-connect.tunnelStatus');
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
    
    //download specific file from share [public] - filepath can include nested directories
    Route::get('/shares/{share}/download/file/{filepath}', [SharesController::class, 'downloadFile'])
        ->where('filepath', '.*')
        ->name('shares.downloadFile');

    //use background image [public]
    Route::get('/backgrounds', [BackgroundsController::class, 'list'])->name('backgrounds.list');
    Route::get('/backgrounds/{file}/thumb', [BackgroundsController::class, 'useThumb'])->name('backgrounds.useThumb');
    Route::get('/backgrounds/{file}', [BackgroundsController::class, 'use'])->name('backgrounds.use');

    //serve favicon [public]
    Route::get('/favicon', [SettingsController::class, 'getFavicon'])->name('settings.getFavicon');
});


// tusd webhook handler (called by tusd server, not authenticated via middleware)
Route::post('/tusd-hooks', [TusdHooksController::class, 'handleHook'])->name('tusd.hooks');

// Upload routes (now using tusd for actual uploads)
Route::group(['prefix' => 'uploads', 'middleware' => ['auth']], function ($router) {
    // Verify if an upload session is still valid (for tus resume functionality)
    Route::get('/verify/{uploadId}', [UploadsController::class, 'verifyUpload'])->name('uploads.verify');
    // Create a share from uploaded files (after tusd uploads complete)
    Route::post('/create-share-from-uploads', [UploadsController::class, 'createShareFromUploads'])->name('uploads.createShareFromUploads');
});
