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
use App\Jobs\CloudConnectHeartbeat;

//daily jobs
Schedule::job(cleanExpiredShares::class)->daily();
Schedule::job(sendExpiryWarningEmails::class)->daily();
Schedule::job(sendDeletionWarningEmails::class)->daily();
Schedule::job(maintainDb::class)->daily();
Schedule::job(pruneLogs::class)->daily();
Schedule::job(backUpDatabase::class)->daily();
//hourly jobs
Schedule::job(sendExpiredWarningEmails::class)->hourly();
//cloud connect heartbeat - every minute (run synchronously, not via queue)
Schedule::call(function () {
    app(\App\Jobs\CloudConnectHeartbeat::class)->handle(
        app(\App\Services\CloudConnectService::class),
        app(\App\Services\SettingsService::class)
    );
})->everyMinute();

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
