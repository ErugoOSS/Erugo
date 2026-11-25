<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\cleanExpiredShares;
use App\Jobs\maintainDb;
use App\Jobs\sendExpiryWarningEmails;
use App\Jobs\sendExpiredWarningEmails;
use App\Jobs\sendDeletionWarningEmails;
use App\Jobs\pruneLogs;
use App\Jobs\updateLegacySharePaths;
use App\Jobs\backUpDatabase;
use App\Services\SettingsService;
use App\Contracts\Mesh\TombstoneRepository;
use App\Models\MeshUploadIntent;
use App\Services\MeshEnrollmentService;
use App\Services\MeshIdentityService;
use Illuminate\Support\Facades\Http;

//daily jobs
Schedule::job(cleanExpiredShares::class)->daily();
Schedule::job(sendExpiryWarningEmails::class)->daily();
Schedule::job(sendDeletionWarningEmails::class)->daily();
Schedule::job(maintainDb::class)->daily();
Schedule::job(pruneLogs::class)->daily();
Schedule::job(backUpDatabase::class)->daily();
//hourly jobs
Schedule::job(sendExpiredWarningEmails::class)->hourly();
//manually run jobs
Artisan::command('clean-expired-shares', function () {
    cleanExpiredShares::dispatch();
})->purpose('Clean expired shares');

Artisan::command('send-expiry-warning', function () {
    sendExpiryWarningEmails::dispatch();
})->purpose('Send expiry warning emails');

Artisan::command('send-expired-warning', function () {
    sendExpiredWarningEmails::dispatch();
})->purpose('Send expired warning emails');

Artisan::command('send-deletion-warning', function () {
    sendDeletionWarningEmails::dispatch();
})->purpose('Send deletion warning emails');

Artisan::command('maintain-db', function () {
    maintainDb::dispatch();
})->purpose('Maintain the database');

Artisan::command('prune-logs', function () {
    pruneLogs::dispatch();
})->purpose('Prune logs');

Artisan::command('update-legacy-share-paths', function () {
    updateLegacySharePaths::dispatch();
})->purpose('Update legacy share paths');

Artisan::command('back-up-database', function () {
    backUpDatabase::dispatch();
})->purpose('Back up the database');

Artisan::command('mesh:peer-init {node} {url}', function (
    string $node,
    string $url,
    MeshIdentityService $identity,
    MeshEnrollmentService $enrollment
) {
    $coordinator = rtrim((string) config('mesh.coordinator_url'), '/');

    try {
        $response = Http::timeout(10)->get($coordinator . '/api/mesh/public-key');
    } catch (\Throwable $e) {
        $this->error('Could not reach the coordinator public-key endpoint: ' . $e->getMessage());
        return 1;
    }

    if (!$response->successful()) {
        $this->error('Coordinator public-key endpoint returned HTTP ' . $response->status());
        return 1;
    }

    $publicKey = $response->json('data.public_key');
    if (!is_string($publicKey)) {
        $this->error('Coordinator returned an invalid public key.');
        return 1;
    }

    try {
        $identity->rememberCoordinatorPublicKey($publicKey);
        $blob = $enrollment->createBlob($node, $url);
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        return 1;
    }

    $this->info('Paste this blob into Settings -> Mesh on the coordinator:');
    $this->line($blob);
    $this->comment('The blob expires soon and can be used once. Private keys never leave this host.');

    return 0;
})->purpose('Generate a peer enrollment blob using the coordinator public key');

Artisan::command('mesh:gc', function (TombstoneRepository $tombstones) {
    $stale = MeshUploadIntent::where('status', 'pending')
        ->where('expires_at', '<', now())
        ->get();

    foreach ($stale as $intent) {
        $intent->status = 'expired';
        $intent->save();
        $tombstones->enqueue($intent->object_id, 1, now());
    }

    $this->info('Expired ' . $stale->count() . ' mesh upload intents; tombstones queued.');
})->purpose('Expire stale mesh upload intents and queue peer cleanup');

Artisan::command('clear-settings-cache', function () {
    app(SettingsService::class)->clearCache();
    $this->info('Settings cache cleared.');
})->purpose('Clear the settings cache');
