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
use App\Http\Controllers\Api\App\AppLivesharesController;
use App\Http\Controllers\Api\App\AppSettingsController;
use App\Http\Controllers\Api\App\AppUsersAdminController;
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
    Route::get('/logo.png', 'AppBrandingController@logoPng')->name('app.branding.logoPng');
    Route::get('/favicon', 'AppBrandingController@favicon')->name('app.branding.favicon');
    Route::get('/favicon.png', 'AppBrandingController@faviconPng')->name('app.branding.faviconPng');
    Route::get('/favicon/status', 'AppBrandingController@faviconStatus')->name('app.branding.faviconStatus');
    Route::get('/backgrounds', 'AppBrandingController@backgrounds')->name('app.branding.backgrounds');
    Route::get('/backgrounds/{id}', 'AppBrandingController@background')->name('app.branding.background');
    Route::get('/backgrounds/{id}/thumb', 'AppBrandingController@backgroundThumb')->name('app.branding.backgroundThumb');
});

/*
|--------------------------------------------------------------------------
| Branding Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'branding', 'middleware' => ['auth', Admin::class]], function () {
    // Logo management
    Route::post('/logo', 'AppBrandingController@uploadLogo')->name('app.branding.uploadLogo');
    Route::delete('/logo', 'AppBrandingController@deleteLogo')->name('app.branding.deleteLogo');
    
    // Favicon management
    Route::post('/favicon', 'AppBrandingController@uploadFavicon')->name('app.branding.uploadFavicon');
    Route::delete('/favicon', 'AppBrandingController@deleteFavicon')->name('app.branding.deleteFavicon');
    
    // Background management
    Route::post('/backgrounds', 'AppBrandingController@uploadBackground')->name('app.branding.uploadBackground');
    Route::delete('/backgrounds/{id}', 'AppBrandingController@deleteBackground')->name('app.branding.deleteBackground');
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
| User Management Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'users', 'middleware' => ['auth', Admin::class]], function () {
    Route::get('/', 'AppUsersAdminController@index')->name('app.users.index');
    Route::post('/', 'AppUsersAdminController@create')->name('app.users.create');
    Route::get('/{id}', 'AppUsersAdminController@show')->name('app.users.show');
    Route::put('/{id}', 'AppUsersAdminController@update')->name('app.users.adminUpdate');
    Route::delete('/{id}', 'AppUsersAdminController@delete')->name('app.users.delete');
    Route::post('/{id}/force-reset-password', 'AppUsersAdminController@forceResetPassword')->name('app.users.forceResetPassword');
});

/*
|--------------------------------------------------------------------------
| Shares Routes
|--------------------------------------------------------------------------
*/
// Authenticated share routes (must be before public routes to avoid wildcard conflicts)
Route::group(['prefix' => 'shares', 'middleware' => ['auth']], function () {
    Route::get('/', 'AppSharesController@index')->name('app.shares.index');
    Route::get('/{shareId}/files/{fileId}/thumb', 'AppSharesController@fileThumbnail')
        ->name('app.shares.fileThumbnail');
    Route::post('/{id}/expire', 'AppSharesController@expire')->name('app.shares.expire');
    Route::post('/{id}/extend', 'AppSharesController@extend')->name('app.shares.extend');
    Route::post('/{id}/download-limit', 'AppSharesController@setDownloadLimit')->name('app.shares.setDownloadLimit');
    Route::post('/prune', 'AppSharesController@prune')->name('app.shares.prune');
});

// Public share routes (after authenticated routes - wildcard filePath would otherwise match thumbnail routes)
Route::get('/shares/{longId}', 'AppSharesController@read')
    ->name('app.shares.read');
Route::get('/shares/{longId}/download', 'AppSharesController@download')
    ->name('app.shares.download');
Route::get('/shares/{longId}/files/{filePath}', 'AppSharesController@downloadFile')
    ->where('filePath', '.*')
    ->name('app.shares.downloadFile');

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
| Liveshares Routes
|--------------------------------------------------------------------------
*/
// Admin routes
Route::group(['prefix' => 'liveshares/admin', 'middleware' => ['auth', Admin::class]], function () {
    Route::get('/all', 'AppLivesharesController@adminListAll')->name('app.liveshares.admin.all');
    Route::put('/{id}/limits', 'AppLivesharesController@adminSetLimits')->name('app.liveshares.admin.limits');
});

// Authenticated routes
Route::group(['prefix' => 'liveshares', 'middleware' => ['auth']], function () {
    // CRUD
    Route::get('/', 'AppLivesharesController@index')->name('app.liveshares.index');
    Route::post('/', 'AppLivesharesController@create')->name('app.liveshares.create');
    Route::get('/{longId}', 'AppLivesharesController@show')->name('app.liveshares.show');
    Route::put('/{longId}', 'AppLivesharesController@update')->name('app.liveshares.update');
    Route::delete('/{longId}', 'AppLivesharesController@destroy')->name('app.liveshares.destroy');

    // Members
    Route::get('/{longId}/members', 'AppLivesharesController@listMembers')->name('app.liveshares.members.list');
    Route::post('/{longId}/members', 'AppLivesharesController@addMember')->name('app.liveshares.members.add');
    Route::put('/{longId}/members/{memberId}', 'AppLivesharesController@updateMember')->name('app.liveshares.members.update');
    Route::delete('/{longId}/members/{memberId}', 'AppLivesharesController@removeMember')->name('app.liveshares.members.remove');

    // Files
    Route::get('/{longId}/files', 'AppLivesharesController@listFiles')->name('app.liveshares.files.list');
    Route::post('/{longId}/files', 'AppLivesharesController@addFiles')->name('app.liveshares.files.add');
    Route::delete('/{longId}/files/{fileId}', 'AppLivesharesController@removeFile')->name('app.liveshares.files.remove');
    Route::get('/{longId}/files/{fileId}/download', 'AppLivesharesController@downloadFile')->name('app.liveshares.files.download');
    Route::post('/{longId}/files/download', 'AppLivesharesController@downloadFiles')->name('app.liveshares.files.downloadMultiple');
    Route::get('/{longId}/files/{fileId}/thumb', 'AppLivesharesController@fileThumbnail')->name('app.liveshares.files.thumb');

    // Tags
    Route::get('/{longId}/tags', 'AppLivesharesController@listTags')->name('app.liveshares.tags.list');
    Route::post('/{longId}/tags', 'AppLivesharesController@createTag')->name('app.liveshares.tags.create');
    Route::put('/{longId}/tags/{tagId}', 'AppLivesharesController@updateTag')->name('app.liveshares.tags.update');
    Route::delete('/{longId}/tags/{tagId}', 'AppLivesharesController@deleteTag')->name('app.liveshares.tags.delete');

    // File tags
    Route::post('/{longId}/files/{fileId}/tags', 'AppLivesharesController@addFileTags')->name('app.liveshares.fileTags.add');
    Route::delete('/{longId}/files/{fileId}/tags/{tagId}', 'AppLivesharesController@removeFileTag')->name('app.liveshares.fileTags.remove');
    Route::post('/{longId}/files/bulk-tag', 'AppLivesharesController@bulkAddFileTags')->name('app.liveshares.fileTags.bulkAdd');
    Route::post('/{longId}/files/bulk-untag', 'AppLivesharesController@bulkRemoveFileTags')->name('app.liveshares.fileTags.bulkRemove');

    // Invites
    Route::post('/{longId}/invites/email', 'AppLivesharesController@createEmailInvite')->name('app.liveshares.invites.email');
    Route::post('/{longId}/invites/link', 'AppLivesharesController@createLinkInvite')->name('app.liveshares.invites.link');
    Route::get('/{longId}/invites', 'AppLivesharesController@listInvites')->name('app.liveshares.invites.list');
    Route::delete('/{longId}/invites/{inviteId}', 'AppLivesharesController@revokeInvite')->name('app.liveshares.invites.revoke');

    // Accept invite (authenticated user)
    Route::post('/invite/{token}/accept', 'AppLivesharesController@acceptInvite')->name('app.liveshares.invites.accept');
});

// Public liveshare routes (no auth required)
Route::get('/liveshares/invite/{token}', 'AppLivesharesController@getInviteInfo')
    ->name('app.liveshares.invites.info');
Route::post('/liveshares/invite/{token}/register', 'AppAuthController@registerViaInvite')
    ->name('app.liveshares.invites.register');
Route::get('/liveshares/{longId}/avatar', 'AppLivesharesController@avatar')
    ->name('app.liveshares.avatar');

/*
|--------------------------------------------------------------------------
| Statistics Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'stats', 'middleware' => ['auth', Admin::class]], function () {
    Route::get('/', [StatsController::class, 'getStats'])->name('app.stats.get');
    Route::get('/system-info', [StatsController::class, 'getSystemInfo'])->name('app.stats.systemInfo');
});

/*
|--------------------------------------------------------------------------
| Settings Routes (Admin Only)
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'settings', 'middleware' => ['auth', Admin::class]], function () {
    // General settings
    Route::get('/general', 'AppSettingsController@getGeneral')->name('app.settings.general.get');
    Route::put('/general', 'AppSettingsController@updateGeneral')->name('app.settings.general.update');
    
    // Shares settings
    Route::get('/shares', 'AppSettingsController@getShares')->name('app.settings.shares.get');
    Route::put('/shares', 'AppSettingsController@updateShares')->name('app.settings.shares.update');
    
    // Branding settings
    Route::get('/branding', 'AppSettingsController@getBranding')->name('app.settings.branding.get');
    Route::put('/branding', 'AppSettingsController@updateBranding')->name('app.settings.branding.update');
    
    // SMTP settings
    Route::get('/smtp', 'AppSettingsController@getSmtp')->name('app.settings.smtp.get');
    Route::put('/smtp', 'AppSettingsController@updateSmtp')->name('app.settings.smtp.update');
    
    // Licence settings
    Route::get('/licence', 'AppSettingsController@getLicence')->name('app.settings.licence.get');
    Route::put('/licence', 'AppSettingsController@updateLicence')->name('app.settings.licence.update');
});
