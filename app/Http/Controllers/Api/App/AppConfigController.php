<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\AuthProvider;
use App\Models\Theme;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AppConfigController extends Controller
{
    /**
     * Get the fully qualified class name for an auth provider
     */
    private function getProviderClass(string $class): string
    {
        return "App\\AuthProviders\\" . $class . "AuthProvider";
    }

    /**
     * Check if a provider class exists
     */
    private function classExists(string $class): bool
    {
        $classWithoutNamespace = str_replace('App\\AuthProviders\\', '', $class);
        $path = app_path('AuthProviders/' . $classWithoutNamespace . '.php');
        return file_exists($path) && class_exists($class);
    }

    /**
     * Get consolidated configuration for app bootstrapping
     */
    public function getConfig(): JsonResponse
    {
        $settings = app(SettingsService::class);
        
        // Get enabled auth providers with icons from provider classes
        $authProviders = AuthProvider::where('enabled', true)
            ->get()
            ->map(function ($provider) {
                $class = $this->getProviderClass($provider->provider_class);
                if (!$this->classExists($class)) {
                    return null;
                }
                return [
                    'id' => $provider->id,
                    'uuid' => $provider->uuid,
                    'name' => $provider->name,
                    'type' => $provider->provider_class,
                    'icon' => $class::getIcon(),
                    'allow_registration' => (bool) $provider->allow_registration,
                ];
            })
            ->filter() // Remove nulls from providers with missing classes
            ->values(); // Re-index array

        return response()->json([
            'status' => 'success',
            'data' => [
                'instance' => [
                    'name' => $settings->get('application_name') ?? config('app.name'),
                    'url' => $settings->get('application_url') ?? config('app.url'),
                    'version' => config('app.version', '1.0.0'),
                ],
                'features' => [
                    'self_registration_enabled' => (bool) $settings->get('self_registration_enabled'),
                    'reverse_shares_enabled' => (bool) $settings->get('allow_reverse_shares'),
                    'external_auth_enabled' => $authProviders->isNotEmpty(),
                ],
                'limits' => [
                    'max_share_size_bytes' => $settings->getMaxUploadSize(),
                    'max_share_size_formatted' => $this->formatBytes($settings->getMaxUploadSize()),
                    'max_expiry_days' => $settings->get('max_expiry_time'),
                    'default_expiry_days' => $settings->get('default_expiry_time'),
                ],
                'auth_providers' => $authProviders,
                'branding' => [
                    'has_custom_logo' => Storage::disk('public')->exists('images/logo.png'),
                    'has_custom_favicon' => Storage::disk('public')->exists('favicon.png') || Storage::disk('public')->exists('favicon.svg'),
                    'has_custom_backgrounds' => (bool) $settings->get('use_my_backgrounds'),
                    'background_slideshow_speed' => (int) ($settings->get('background_slideshow_speed') ?? 180),
                ],
            ]
        ]);
    }

    /**
     * Get active theme in a native-friendly format
     */
    public function getTheme(): JsonResponse
    {
        $theme = Theme::where('active', true)->first();
        
        if (!$theme) {
            return response()->json([
                'status' => 'error',
                'code' => 'THEME_NOT_FOUND',
                'message' => 'No active theme found'
            ], 404);
        }

        // Convert theme to native-friendly format (default to empty array if null)
        // Cast to array since JSON may return stdClass
        $themeData = (array) ($theme->theme ?? []);
        
        // Map CSS variable format to native-friendly keys
        $nativeTheme = $this->convertThemeToNativeFormat($themeData);

        return response()->json([
            'status' => 'success',
            'data' => [
                'name' => $theme->name,
                'category' => $theme->category,
                'theme' => $nativeTheme,
                'raw' => $themeData, // Include raw format for apps that prefer CSS variables
            ]
        ]);
    }

    /**
     * Convert CSS variable theme format to native-friendly format
     */
    private function convertThemeToNativeFormat(array $themeData): array
    {
        $native = [];
        
        foreach ($themeData as $key => $value) {
            // Convert --color-primary to colorPrimary format
            $nativeKey = $this->cssVarToNativeKey($key);
            $native[$nativeKey] = $value;
        }

        return $native;
    }

    /**
     * Convert CSS variable name to native key format
     * e.g., --color-primary -> colorPrimary
     */
    private function cssVarToNativeKey(string $cssVar): string
    {
        // Remove leading dashes
        $key = ltrim($cssVar, '-');
        
        // Convert kebab-case to camelCase
        $key = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $key))));
        
        return $key;
    }

    /**
     * Format bytes into a human-readable string
     */
    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        // Remove trailing zeros and unnecessary decimal point
        $formatted = rtrim(rtrim(number_format($value, 2), '0'), '.');

        return $formatted . ' ' . $units[$i];
    }
}
