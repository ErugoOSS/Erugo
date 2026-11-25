<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Mesh\ManifestRepository;
use App\Contracts\Mesh\NodeRepository;
use App\Contracts\Mesh\ReplicationRepository;
use App\Contracts\Mesh\TombstoneRepository;
use App\Repositories\Mesh\EloquentManifestRepository;
use App\Repositories\Mesh\EloquentNodeRepository;
use App\Repositories\Mesh\EloquentReplicationRepository;
use App\Repositories\Mesh\EloquentTombstoneRepository;
use App\Services\SettingsService;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mesh metadata stays behind interfaces so SQLite can be swapped for a
        // shared database in a later phase without touching the data plane.
        $this->app->bind(ManifestRepository::class, EloquentManifestRepository::class);
        $this->app->bind(NodeRepository::class, EloquentNodeRepository::class);
        $this->app->bind(ReplicationRepository::class, EloquentReplicationRepository::class);
        $this->app->bind(TombstoneRepository::class, EloquentTombstoneRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use a view composer for email templates so settings are loaded fresh each time.
        // This is important for queue workers which are long-running processes -
        // View::share() would capture stale values from when the worker started.
        View::composer('emails.*', function ($view) {
            try {
                $settingsService = app(SettingsService::class);
                $settings = $settingsService->getGlobalViewData();
                $view->with('settings', $settings);
            } catch (\Exception $e) {
                // If settings can't be loaded, provide empty defaults
                $view->with('settings', []);
            }
        });

        View::prependLocation(storage_path('templates'));
    }
}
