<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SettingsService;
use Illuminate\Support\Facades\View;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthRateLimiters();

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

    /**
     * Named rate limiters shared by web and app auth routes.
     */
    private function configureAuthRateLimiters(): void
    {
        RateLimiter::for('auth-login', function (Request $request) {
            $email = $this->normalizedEmail($request);

            return [
                Limit::perMinute(10)->by('ip:'.$request->ip()),
                Limit::perMinute(10)->by('email:'.$email),
            ];
        });

        RateLimiter::for('auth-forgot-password', function (Request $request) {
            $email = $this->normalizedEmail($request);

            return [
                Limit::perMinutes(5, 5)->by('ip:'.$request->ip()),
                Limit::perMinutes(5, 5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('auth-reset-password', function (Request $request) {
            $email = $this->normalizedEmail($request);

            return [
                Limit::perMinutes(5, 5)->by('ip:'.$request->ip()),
                Limit::perMinutes(5, 5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(10)->by('ip:'.$request->ip());
        });

        RateLimiter::for('auth-verify-email', function (Request $request) {
            $email = $this->normalizedEmail($request);

            return [
                Limit::perMinutes(15, 10)->by('ip:'.$request->ip()),
                Limit::perMinutes(15, 5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('auth-resend-verification', function (Request $request) {
            $email = $this->normalizedEmail($request);

            return [
                Limit::perMinutes(5, 3)->by('ip:'.$request->ip()),
                Limit::perMinutes(5, 3)->by('email:'.$email),
            ];
        });

        RateLimiter::for('auth-external', function (Request $request) {
            return Limit::perMinute(10)->by('ip:'.$request->ip());
        });

        RateLimiter::for('auth-setup', function (Request $request) {
            return Limit::perMinute(5)->by('ip:'.$request->ip());
        });
    }

    private function normalizedEmail(Request $request): string
    {
        return strtolower(trim((string) $request->input('email')));
    }
}
