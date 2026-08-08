<?php

namespace App\Services;

use App\Models\Setting;

class SettingsRepository
{
    public function __construct(
        private SettingsService $settingsService
    ) {}

    /**
     * Get all settings for a group, properly typed.
     */
    public function getGroup(string $group): array
    {
        $settings = Setting::where('group', $group)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->settingsService->convertToCorrectType($setting->value);
        }
        
        return $result;
    }

    /**
     * Update multiple settings in a group.
     * Only updates keys that are provided - partial updates supported.
     */
    public function updateGroup(string $group, array $data): void
    {
        foreach ($data as $key => $value) {
            $setting = Setting::where('key', $key)->where('group', $group)->first();
            
            if ($setting) {
                $setting->previous_value = $setting->value;
                $setting->value = $this->convertToString($value);
                $setting->save();
            }
        }
        
        $this->settingsService->clearCache();
    }

    /**
     * Convert a typed value to string for database storage.
     */
    private function convertToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return implode(',', $value);
        }
        if ($value === null) {
            return '';
        }
        return (string) $value;
    }
}
