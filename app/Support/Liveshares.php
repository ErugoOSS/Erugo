<?php

namespace App\Support;

use App\Services\SettingsService;

class Liveshares
{
    /**
     * Whether the live shares feature is switched on for this install
     * (the ERUGO_LIVESHARES env flag).
     */
    public static function available(): bool
    {
        return (bool) config('erugo.liveshares');
    }

    /**
     * Whether users can use live shares right now. Always false when the env
     * flag is off, whatever the liveshares_enabled setting says. A missing
     * setting counts as on.
     */
    public static function enabled(): bool
    {
        if (!self::available()) {
            return false;
        }

        return (bool) (app(SettingsService::class)->get('liveshares_enabled') ?? true);
    }
}
